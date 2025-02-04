<?php
/**
 * Plugin Name: MembershipWorks Events to Posts
 * Plugin URI: https://wpplugins.symphony-ts.com/plugins/membershipworks-events-to-posts
 * Description: Automatically creates and updates WordPress posts from MembershipWorks events. Visit <a href="https://wpplugins.symphony-ts.com">wpplugins.symphony-ts.com</a> to register and receive plugin updates.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Symphony Technology Solutions
 * Author URI: https://symphony-ts.com
 * License: Custom License - No commercial use without permission
 * License URI: https://raw.githubusercontent.com/symphony-ts/membershipworks-events-to-posts/main/LICENSE.txt
 * Text Domain: mw-events-to-posts
 * Domain Path: /languages
 * Update URI: https://wpplugins.symphony-ts.com/api/v1/updates/check/membershipworks-events-to-posts
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MWEP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MWEP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Activation hook
function mwep_activate() {
    global $wpdb;

    // Create events table
    $table_name = $wpdb->prefix . 'mwep_events';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        event_id varchar(32) NOT NULL,
        post_id bigint(20) NOT NULL,
        event_data longtext NOT NULL,
        post_status varchar(20) DEFAULT 'active',
        last_updated datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY event_id (event_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Create plugin user if it doesn't exist
    $user_login = 'membershipworks-events';
    $user = get_user_by('login', $user_login);
    
    if (!$user) {
        $user_id = wp_insert_user(array(
            'user_login' => $user_login,
            'user_pass' => wp_generate_password(),
            'user_email' => 'noreply@' . parse_url(get_site_url(), PHP_URL_HOST),
            'display_name' => 'MembershipWorks Events',
            'role' => 'author',
            'user_registered' => current_time('mysql')
        ));

        if (!is_wp_error($user_id)) {
            update_option('mwep_plugin_user_id', $user_id);
        }
    } else {
        update_option('mwep_plugin_user_id', $user->ID);
    }

    // Set default options if they don't exist
    if (!get_option('mwep_settings')) {
        update_option('mwep_settings', array(
            'api_key' => '',
            'org' => '',
            'run_interval' => 'daily',
            'post_tags' => '',
            'update_existing_posts' => true,
            'events_base_url' => get_site_url() . '/events/#!event/'
        ));
    }

    // Schedule the CRON event if it's not already scheduled
    if (!wp_next_scheduled('mwep_sync_events')) {
        wp_schedule_event(time(), 'hourly', 'mwep_sync_events');
    }
}
register_activation_hook(__FILE__, 'mwep_activate');

// Clean up on uninstall
function mwep_uninstall() {
    // Only remove plugin user if specifically requested
    if (get_option('mwep_remove_user_on_uninstall', false)) {
        $user_id = get_option('mwep_plugin_user_id');
        if ($user_id) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            wp_delete_user($user_id);
        }
    }
    
    delete_option('mwep_plugin_user_id');
    delete_option('mwep_settings');
    delete_option('mwep_remove_user_on_uninstall');
}
register_uninstall_hook(__FILE__, 'mwep_uninstall');

// Deactivation hook
function mwep_deactivate_plugin() {
    // Remove the scheduled CRON event
    wp_clear_scheduled_hook('mwep_sync_events');
}
register_deactivation_hook(__FILE__, 'mwep_deactivate_plugin');

// Register the admin menu
function mwep_register_menu() {
    add_menu_page(
        'MembershipWorks Events',
        'MW Events',
        'manage_options',
        'mw-events-to-posts',
        'mwep_render_app',
        'dashicons-calendar-alt',
        20
    );
}
add_action('admin_menu', 'mwep_register_menu');

// Render the React app
function mwep_render_app() {
    echo '<div id="mwep-root"></div>';
}

// Enqueue admin scripts
function mwep_enqueue_scripts($hook) {
    if ('toplevel_page_mw-events-to-posts' !== $hook) {
        return;
    }

    $asset_file = include(MWEP_PLUGIN_PATH . 'build/index.asset.php');

    wp_enqueue_script(
        'mwep-admin',
        MWEP_PLUGIN_URL . 'build/index.js',
        $asset_file['dependencies'],
        $asset_file['version'],
        true
    );

    wp_enqueue_style(
        'mwep-admin',
        MWEP_PLUGIN_URL . 'build/index.css',
        array(),
        $asset_file['version']
    );

    // Add settings to JavaScript
    wp_localize_script('mwep-admin', 'mwepSettings', array(
        'apiNonce' => wp_create_nonce('wp_rest'),
        'apiUrl' => rest_url('mwep/v1/'),
    ));
}
add_action('admin_enqueue_scripts', 'mwep_enqueue_scripts');

// Register REST API endpoints
function mwep_register_rest_routes() {
    register_rest_route('mwep/v1', '/settings', array(
        'methods' => 'GET',
        'callback' => 'mwep_get_settings',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));

    register_rest_route('mwep/v1', '/settings', array(
        'methods' => 'POST',
        'callback' => 'mwep_update_settings',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));

    register_rest_route('mwep/v1', '/sync', array(
        'methods' => 'POST',
        'callback' => 'mwep_manual_sync',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
}
add_action('rest_api_init', 'mwep_register_rest_routes');

// Settings handlers
function mwep_get_settings() {
    $settings = get_option('mwep_settings', array(
        'api_key' => '',
        'org' => '',
        'run_interval' => 'hourly',
        'post_tags' => 'membershipworks,homepage',
        'post_format' => 'standard'
    ));
    return rest_ensure_response($settings);
}

function mwep_update_settings($request) {
    $settings = $request->get_params();
    update_option('mwep_settings', $settings);
    return rest_ensure_response(array('success' => true));
}

// Manual sync handler
function mwep_manual_sync() {
    do_action('mwep_sync_events');
    return rest_ensure_response(array('success' => true));
}

// Include the events sync functionality
require_once MWEP_PLUGIN_PATH . 'includes/class-mwep-sync.php';
