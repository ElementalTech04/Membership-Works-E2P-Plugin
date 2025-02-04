<?php
class Test_MWEP_Sync extends WP_UnitTestCase {
    private $mwep_sync;
    private $test_event_data;

    public function setUp(): void {
        parent::setUp();
        
        // Create test settings
        update_option('mwep_settings', array(
            'api_key' => 'test_key',
            'org' => 'test_org',
            'post_tags' => 'test,events',
            'update_existing_posts' => true,
            'events_base_url' => 'https://example.com/events/#!event/'
        ));

        // Initialize sync class
        $this->mwep_sync = new MWEP_Sync();

        // Set up test event data
        $this->test_event_data = array(
            'eid' => 'test123',
            'ttl' => 'Test Event',
            'str' => '2025-02-13 18:00:00',
            'end' => '2025-02-13 20:00:00',
            'loc' => '123 Main St, Test City',
            'dtl' => 'Test event description',
            'url' => '2025/2/13/test-event',
            'lgo' => array(
                's' => 'https://example.com/image-small.jpg',
                'l' => 'https://example.com/image-large.jpg'
            )
        );
    }

    public function tearDown(): void {
        parent::tearDown();
        delete_option('mwep_settings');
    }

    public function test_generate_post_excerpt() {
        $reflection = new ReflectionClass('MWEP_Sync');
        $method = $reflection->getMethod('generate_post_excerpt');
        $method->setAccessible(true);

        $excerpt = $method->invoke($this->mwep_sync, $this->test_event_data);
        
        // Check if excerpt contains date and location
        $this->assertStringContainsString('February 13, 2025', $excerpt);
        $this->assertStringContainsString('6:00 PM', $excerpt);
        $this->assertStringContainsString('123 Main St, Test City', $excerpt);
        $this->assertStringContainsString('|', $excerpt);
    }

    public function test_create_event_post() {
        $reflection = new ReflectionClass('MWEP_Sync');
        $method = $reflection->getMethod('create_event_post');
        $method->setAccessible(true);

        // Create test user
        $user_id = $this->factory->user->create(array('role' => 'author'));
        update_option('mwep_plugin_user_id', $user_id);

        // Create post
        $post_id = $method->invoke($this->mwep_sync, $this->test_event_data);
        
        // Assert post was created
        $this->assertNotFalse($post_id);
        
        // Get created post
        $post = get_post($post_id);
        
        // Verify post data
        $this->assertEquals('Test Event', $post->post_title);
        $this->assertStringContainsString('Test event description', $post->post_content);
        $this->assertStringContainsString('February 13, 2025', $post->post_excerpt);
        $this->assertEquals('publish', $post->post_status);
        
        // Verify registration link
        $this->assertStringContainsString(
            'https://example.com/events/#!event/2025/2/13/test-event',
            $post->post_content
        );

        // Clean up
        wp_delete_post($post_id, true);
        delete_option('mwep_plugin_user_id');
    }

    public function test_update_event_post() {
        $reflection = new ReflectionClass('MWEP_Sync');
        $create_method = $reflection->getMethod('create_event_post');
        $update_method = $reflection->getMethod('update_event_post');
        $create_method->setAccessible(true);
        $update_method->setAccessible(true);

        // Create initial post
        $post_id = $create_method->invoke($this->mwep_sync, $this->test_event_data);

        // Modify event data
        $updated_event_data = $this->test_event_data;
        $updated_event_data['loc'] = 'New Location';
        $updated_event_data['dtl'] = 'Updated description';

        // Update post
        $update_method->invoke($this->mwep_sync, $post_id, $updated_event_data, null);

        // Get updated post
        $post = get_post($post_id);

        // Verify updates
        $this->assertStringContainsString('New Location', $post->post_excerpt);
        $this->assertStringContainsString('Updated description', $post->post_content);

        // Clean up
        wp_delete_post($post_id, true);
    }

    public function test_invalid_event_data() {
        $reflection = new ReflectionClass('MWEP_Sync');
        $method = $reflection->getMethod('create_event_post');
        $method->setAccessible(true);

        // Test with missing required fields
        $invalid_event_data = array(
            'eid' => 'test123',
            // Missing title and other required fields
        );

        $post_id = $method->invoke($this->mwep_sync, $invalid_event_data);
        $this->assertFalse($post_id);
    }
}
