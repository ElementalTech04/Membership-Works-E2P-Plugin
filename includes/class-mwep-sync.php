<?php
class MWEP_Sync {
    private $api_key;
    private $org;
    private $settings;

    public function __construct() {
        add_action('mwep_sync_events', array($this, 'sync_events'));
        add_action('mwep_cleanup_old_posts', array($this, 'cleanup_old_posts'));
        $this->settings = get_option('mwep_settings');
        $this->api_key = $this->settings['api_key'];
        $this->org = $this->settings['org'];
    }

    public function sync_events() {
        try {
            // Validate settings
            if (empty($this->settings)) {
                throw new Exception('Plugin settings not found');
            }

            if (empty($this->api_key) || empty($this->org)) {
                throw new Exception('API key or organization not set');
            }

            // Fetch events
            $events = $this->get_events();
            if (empty($events)) {
                error_log('MembershipWorks Events-to-Posts: No events found or error fetching events');
                return;
            }

            // Sort events by start date
            usort($events, function($a, $b) {
                $date_a = strtotime($a['str'] ?? '');
                $date_b = strtotime($b['str'] ?? '');
                
                // Handle invalid dates
                if ($date_a === false || $date_b === false) {
                    error_log('MembershipWorks Events-to-Posts: Invalid date format in event data');
                    return 0;
                }
                
                return $date_a - $date_b;
            });

            $processed_count = 0;
            $error_count = 0;

            // Process each event
            foreach ($events as $event) {
                try {
                    if ($this->process_event($event)) {
                        $processed_count++;
                    } else {
                        $error_count++;
                    }
                } catch (Exception $e) {
                    error_log('MembershipWorks Events-to-Posts: Error processing event: ' . $e->getMessage());
                    $error_count++;
                }
            }

            // Log summary
            error_log(sprintf(
                'MembershipWorks Events-to-Posts: Sync completed. Processed: %d, Errors: %d',
                $processed_count,
                $error_count
            ));

            // Clean up old posts after processing new events
            $this->cleanup_old_posts();

        } catch (Exception $e) {
            error_log('MembershipWorks Events-to-Posts: Sync failed: ' . $e->getMessage());
            return false;
        }
    }

    public function cleanup_old_posts() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mwep_events';
        
        // Get all events that have ended
        $current_time = current_time('timestamp');
        $results = $wpdb->get_results(
            "SELECT * FROM {$table_name} WHERE post_id IS NOT NULL"
        );

        foreach ($results as $result) {
            $event_data = json_decode($result->event_data, true);
            if (!empty($event_data['edp']) && $event_data['edp'] < $current_time) {
                // Event has ended, update post status to draft
                wp_update_post(array(
                    'ID' => $result->post_id,
                    'post_status' => 'draft'
                ));
                
                // Keep the record but mark post as deleted
                $wpdb->update(
                    $table_name,
                    array('post_status' => 'deleted'),
                    array('event_id' => $result->event_id)
                );
            }
        }
    }

    private function process_event($event) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mwep_events';

        // Get detailed event info
        $event_details = $this->get_event_details($event['url']);
        if (!$event_details) {
            return;
        }

        // Check if event already exists
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE event_id = %s", $event['eid'])
        );

        $event_data = array_merge($event, $event_details);
        
        if ($existing) {
            // Check if we should update existing posts
            if (!empty($this->settings['update_existing_posts'])) {
                $this->update_event_post($existing->post_id, $event_data, $existing);
            }
            
            // Always update the event data in our table
            $wpdb->update(
                $table_name,
                array(
                    'event_data' => wp_json_encode($event_data),
                    'last_updated' => current_time('mysql')
                ),
                array('event_id' => $event['eid'])
            );
        } else {
            // Create new event post
            $post_id = $this->create_event_post($event_data);
            if ($post_id) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'event_id' => $event['eid'],
                        'post_id' => $post_id,
                        'event_data' => wp_json_encode($event_data),
                        'last_updated' => current_time('mysql'),
                        'post_status' => 'active'
                    )
                );
            }
        }
    }

    private function validate_event_data($event_data) {
        $required_fields = ['eid', 'ttl', 'str'];
        foreach ($required_fields as $field) {
            if (empty($event_data[$field])) {
                error_log(sprintf(
                    'MembershipWorks Events-to-Posts: Missing required field "%s" in event data',
                    $field
                ));
                return false;
            }
        }

        // Validate date format
        if (!strtotime($event_data['str'])) {
            error_log(sprintf(
                'MembershipWorks Events-to-Posts: Invalid date format in event data: %s',
                $event_data['str']
            ));
            return false;
        }

        return true;
    }

    private function generate_post_excerpt($event_data) {
        if (!$this->validate_event_data($event_data)) {
            return '';
        }

        $excerpt_parts = [];

        // Add date information
        $start_date = strtotime($event_data['str']);
        $date_format = get_option('date_format') . ' ' . get_option('time_format');
        $excerpt_parts[] = date_i18n($date_format, $start_date);

        // Add location if available
        if (!empty($event_data['loc'])) {
            $excerpt_parts[] = wp_kses(
                $event_data['loc'],
                array() // No HTML allowed in location
            );
        }

        return implode(' | ', array_filter($excerpt_parts));
    }

    private function create_event_post($event_data) {
        if (!$this->validate_event_data($event_data)) {
            return false;
        }

        try {
            // Get plugin user ID, fallback to admin if not found
            $plugin_user_id = get_option('mwep_plugin_user_id');
            if (!$plugin_user_id) {
                // Fallback to first admin user
                $admins = get_users(['role' => 'administrator', 'number' => 1]);
                if (empty($admins)) {
                    throw new Exception('No valid author found for creating posts');
                }
                $plugin_user_id = $admins[0]->ID;
            }
            
            // Convert event start date to WordPress post date format
            $event_date = strtotime($event_data['str']);
            $post_date = date('Y-m-d H:i:s', $event_date);
            $post_date_gmt = gmdate('Y-m-d H:i:s', $event_date);
            
            // Generate content and excerpt
            $content = $this->format_event_content($event_data);
            $excerpt = $this->generate_post_excerpt($event_data);
            
            $post_data = array(
                'post_title' => wp_strip_all_tags($event_data['ttl']),
                'post_content' => $content,
                'post_excerpt' => $excerpt,
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_author' => $plugin_user_id,
                'post_date' => $post_date,
                'post_date_gmt' => $post_date_gmt,
                'post_modified' => $post_date,
                'post_modified_gmt' => $post_date_gmt
            );

        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            // Set featured image if available
            if (!empty($event_data['lgo']['s'])) {
                $this->set_featured_image($post_id, $event_data['lgo']['s']);
            }

            // Set tags
            $tags = array_merge(
                explode(',', $this->settings['post_tags']),
                array(sanitize_title($event_data['ttl']))
            );
            wp_set_post_tags($post_id, $tags);
        }

        return $post_id;
    }

    private function update_event_post($post_id, $event_data, $existing) {
        if (!$this->validate_event_data($event_data)) {
            return false;
        }

        try {
            // Verify post exists and is the correct type
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'post') {
                throw new Exception('Invalid post ID or post type');
            }

            $content = $this->format_event_content($event_data);
            $excerpt = $this->generate_post_excerpt($event_data);
            
            $post_data = array(
                'ID' => $post_id,
                'post_content' => $content,
                'post_excerpt' => $excerpt,
            );

        wp_update_post($post_data);

        // Update featured image if changed
        if (!empty($event_data['lgo']['s'])) {
            $this->set_featured_image($post_id, $event_data['lgo']['s']);
        }

        // Update tags
        $tags = array_merge(
            explode(',', $this->settings['post_tags']),
            array(sanitize_title($event_data['ttl']))
        );
        wp_set_post_tags($post_id, $tags);
    }

    private function format_event_content($event_data) {
        $content = '';
        
        // Add small event image at the top if available
        if (!empty($event_data['lgo']['s'])) {
            $content .= sprintf(
                '<div class="event-image"><img src="%s" alt="%s" class="event-thumbnail" /></div>',
                esc_url($event_data['lgo']['s']),
                esc_attr($event_data['ttl'])
            );
        }

        // Add event date/time
        $start_date = date('F j, Y g:ia', $event_data['sdp']);
        $end_date = date('F j, Y g:ia', $event_data['edp']);
        $content .= "<p><strong>Date:</strong> {$start_date} - {$end_date}</p>";

        // Add location if available
        if (!empty($event_data['adr'])) {
            $addr = $event_data['adr'];
            $location = sprintf(
                "%s, %s, %s %s",
                $addr['ad1'],
                $addr['cit'],
                $addr['sta'],
                $addr['zip']
            );
            $content .= "<p><strong>Location:</strong> {$location}</p>";
        }

        // Add event description
        if (!empty($event_data['dtl'])) {
            $content .= "<div class='event-description'>{$event_data['dtl']}</div>";
        }

        // Add registration link
        if (!empty($event_data['url']) && !empty($this->settings['events_base_url'])) {
            $events_base_url = $this->settings['events_base_url'];
            
            // Validate URL format
            if (filter_var($events_base_url, FILTER_VALIDATE_URL)) {
                // Ensure URL ends with /events/#!event/
                if (!preg_match('/\/events\/#!event\/?$/', $events_base_url)) {
                    $events_base_url = rtrim($events_base_url, '/') . '/events/#!event/';
                }
                
                $registration_url = $events_base_url . $event_data['url'];
                $content .= sprintf(
                    '<p><a href="%s" class="button" target="_blank">%s</a></p>',
                    esc_url($registration_url),
                    esc_html__('Register for Event', 'mw-events-to-posts')
                );
            } else {
                error_log('MembershipWorks Events-to-Posts: Invalid events base URL format - ' . $events_base_url);
            }
        }

        return $content;
    }

    private function get_events() {
        $response = wp_remote_get('https://api.membershipworks.com/v2/events', array(
            'headers' => array(
                'X-API-Key' => $this->api_key,
                'X-Org' => $this->org
            )
        ));

        if (is_wp_error($response)) {
            error_log('MembershipWorks Events-to-Posts: Error fetching events - ' . $response->get_error_message());
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['evt'])) {
            return array();
        }

        return $data['evt'];
    }

    private function get_event_details($url) {
        $response = wp_remote_get("https://api.membershipworks.com/v2/event?url={$url}", array(
            'headers' => array(
                'X-API-Key' => $this->api_key,
                'X-Org' => $this->org
            )
        ));

        if (is_wp_error($response)) {
            error_log('MembershipWorks Events-to-Posts: Error fetching event details - ' . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    private function set_featured_image($post_id, $image_url) {
        if (empty($image_url)) {
            error_log('MembershipWorks Events-to-Posts: Empty image URL for post ' . $post_id);
            return false;
        }

        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Validate image URL
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            error_log('MembershipWorks Events-to-Posts: Invalid image URL - ' . $image_url);
            return false;
        }

        // Delete existing featured image
        $existing_thumbnail_id = get_post_thumbnail_id($post_id);
        if ($existing_thumbnail_id) {
            wp_delete_attachment($existing_thumbnail_id, true);
        }

        // Download and set new featured image
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            error_log('MembershipWorks Events-to-Posts: Failed to download image - ' . $tmp->get_error_message());
            return false;
        }

        // Prepare file array
        $file_array = array(
            'name' => 'event-image-' . $post_id . '-' . time() . '.jpg',
            'tmp_name' => $tmp
        );

        // Handle the file sideload
        $id = media_handle_sideload($file_array, $post_id, 'Event image for post ' . $post_id);
        if (is_wp_error($id)) {
            error_log('MembershipWorks Events-to-Posts: Failed to handle image sideload - ' . $id->get_error_message());
            @unlink($file_array['tmp_name']);
            return false;
        }

        // Set as featured image
        $result = set_post_thumbnail($post_id, $id);
        if (!$result) {
            error_log('MembershipWorks Events-to-Posts: Failed to set featured image for post ' . $post_id);
            return false;
        }

        return true;
    }
}

// Initialize the sync class
new MWEP_Sync();
