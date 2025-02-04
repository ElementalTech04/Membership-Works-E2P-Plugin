<?php
class Test_MWEP_Sync extends WP_UnitTestCase {
    private $sync;
    private $test_event_data;

    public function setUp(): void {
        parent::setUp();
        
        // Create test event data
        $this->test_event_data = array(
            'eid' => 'test123',
            'ttl' => 'Test Event',
            'dtl' => '<p>Test event description</p>',
            'sdp' => time(),
            'edp' => time() + 3600,
            'adr' => array(
                'ad1' => '123 Test St',
                'cit' => 'Test City',
                'sta' => 'TS',
                'zip' => '12345'
            ),
            'url' => '2025/1/1/test-event',
            'lgo' => array(
                'l' => 'https://example.com/test.jpg'
            )
        );

        // Mock settings
        update_option('mwep_settings', array(
            'api_key' => 'test-key',
            'org' => 'test-org',
            'post_tags' => 'test,tags'
        ));

        $this->sync = new MWEP_Sync();
    }

    public function tearDown(): void {
        parent::tearDown();
        delete_option('mwep_settings');
    }

    public function test_process_event_creates_post() {
        // Process the test event
        $method = new ReflectionMethod('MWEP_Sync', 'process_event');
        $method->setAccessible(true);
        $method->invoke($this->sync, $this->test_event_data);

        // Check if post was created
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'title' => $this->test_event_data['ttl']
        ));

        $this->assertEquals(1, count($posts));
        $this->assertEquals('Test Event', $posts[0]->post_title);

        // Check if tags were added
        $tags = wp_get_post_tags($posts[0]->ID);
        $this->assertGreaterThan(0, count($tags));
    }

    public function test_process_event_updates_existing_post() {
        // First create a post
        $method = new ReflectionMethod('MWEP_Sync', 'process_event');
        $method->setAccessible(true);
        $method->invoke($this->sync, $this->test_event_data);

        // Modify event data
        $this->test_event_data['ttl'] = 'Updated Test Event';
        
        // Process the updated event
        $method->invoke($this->sync, $this->test_event_data);

        // Check if post was updated
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'title' => 'Updated Test Event'
        ));

        $this->assertEquals(1, count($posts));
        $this->assertEquals('Updated Test Event', $posts[0]->post_title);
    }

    public function test_format_event_content() {
        $method = new ReflectionMethod('MWEP_Sync', 'format_event_content');
        $method->setAccessible(true);
        
        $content = $method->invoke($this->sync, $this->test_event_data);

        // Check if content includes event details
        $this->assertStringContainsString('Test event description', $content);
        $this->assertStringContainsString('123 Test St', $content);
        $this->assertStringContainsString('Test City', $content);
    }

    public function test_get_events_handles_api_error() {
        $method = new ReflectionMethod('MWEP_Sync', 'get_events');
        $method->setAccessible(true);
        
        // Mock WP_Error response
        add_filter('pre_http_request', function() {
            return new WP_Error('error', 'Test error');
        });

        $events = $method->invoke($this->sync);
        $this->assertEmpty($events);

        remove_all_filters('pre_http_request');
    }
}
