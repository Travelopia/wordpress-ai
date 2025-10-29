<?php
/**
 * Media functions test suite.
 *
 * Tests the Travelopia_WordPress_AI plugin's integration with WordPress media library,
 * focusing on AI-powered alt text generation workflow and security.
 *
 * @package travelopia-wp-ai
 */

namespace Travelopia_WordPress_AI\Tests;

use WP_Post;
use WP_UnitTestCase;
use WP_REST_Request;

use function Travelopia_WordPress_AI\Alt_Text\admin_enqueue_scripts;
use function Travelopia_WordPress_AI\Alt_Text\bootstrap;
use function Travelopia_WordPress_AI\Alt_Text\get_alt_text_action_url;
use function Travelopia_WordPress_AI\Alt_Text\get_attachment_editor_data;
use function Travelopia_WordPress_AI\Alt_Text\handle_rest_alt_text_update;
use function Travelopia_WordPress_AI\Alt_Text\media_row_actions;

/**
 * Class Test_Media.
 *
 * Validates that the alt text generation feature properly integrates with
 * WordPress media screens, REST API, and maintains security throughout.
 */
class Test_Media extends WP_UnitTestCase {

	/**
	 * Test attachment post ID.
	 *
	 * @var int
	 */
	private $attachment_id;

	/**
	 * Test attachment post object.
	 *
	 * @var WP_Post
	 */
	private $attachment;

	/**
	 * Setup test environment.
	 *
	 * Creates a fresh test image attachment before each test to ensure isolation.
	 * This prevents tests from affecting each other and provides consistent state.
	 *
	 * @return void
	 */
	public function set_up(): void {
		// Call parent set up.
		parent::set_up();

		// Create a test image attachment using WordPress's bundled test image.
		// This ensures we have a real image with proper MIME type for testing.
		$this->attachment_id = $this->factory()->attachment->create_upload_object(
			__DIR__ . '/../../../vendor/wp-phpunit/wp-phpunit/data/images/test-image.jpg'
		);

		// Store the attachment post object for tests to use.
		$this->attachment = get_post( $this->attachment_id );
	}

	/**
	 * Tear down test environment.
	 *
	 * Deletes test attachment and cleans up global state to prevent
	 * database pollution and ensure tests remain independent.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		// Delete the test attachment completely (force delete, skip trash).
		if ( $this->attachment_id ) {
			wp_delete_attachment( $this->attachment_id, true );
		}

		// Call parent tear down to clean up WordPress state.
		parent::tear_down();
	}

	/**
	 * Test: get_attachment_editor_data() returns null when not on attachment screen.
	 *
	 * WHY: Prevents unnecessary processing and script loading on non-attachment screens.
	 * BREAKS IF: Function tries to access attachment data on wrong screens, causing errors.
	 *
	 * @covers Travelopia_WordPress_AI\get_attachment_editor_data()
	 *
	 * @return void
	 */
	public function test_get_attachment_editor_data_returns_null_when_not_on_attachment_screen(): void {
		// Without setting current screen, this simulates being on any non-attachment page.
		$result = get_attachment_editor_data();

		// CRITICAL: Must return null to prevent enqueuing scripts on wrong pages.
		$this->assertNull( $result );
	}

	/**
	 * Test: get_attachment_editor_data() returns null when no post ID in URL.
	 *
	 * WHY: Prevents errors when user navigates to attachment screen without specific post.
	 * BREAKS IF: Function tries to process null/0 post ID, causing fatal errors.
	 *
	 * @covers Travelopia_WordPress_AI\get_attachment_editor_data()
	 *
	 * @return void
	 */
	public function test_get_attachment_editor_data_returns_null_when_no_post_id(): void {
		// Simulate being on attachment screen but without post ID in URL.
		set_current_screen( 'attachment' );

		// Call function to get editor data.
		$result = get_attachment_editor_data();

		// CRITICAL: Must handle missing post ID gracefully.
		$this->assertNull( $result );

		// Cleanup global state.
		set_current_screen( 'front' );
	}

	/**
	 * Test: get_attachment_editor_data() returns null for non-image attachments.
	 *
	 * WHY: Alt text generation only works for images, not PDFs, videos, etc.
	 * BREAKS IF: Plugin tries to generate alt text for non-images, wasting API calls.
	 *
	 * @covers Travelopia_WordPress_AI\get_attachment_editor_data()
	 *
	 * @return void
	 */
	public function test_get_attachment_editor_data_returns_null_for_non_image(): void {
		// Create a PDF to test MIME type filtering.
		$pdf_id = $this->factory()->attachment->create_object(
			[
				'file'           => 'test.pdf',
				'post_mime_type' => 'application/pdf',
				'post_type'      => 'attachment',
			]
		);

		// Simulate editing a PDF attachment.
		set_current_screen( 'attachment' );
		$_GET['post'] = $pdf_id;

		// Call function to get editor data for non-image.
		$result = get_attachment_editor_data();

		// CRITICAL: Must filter out non-images to prevent inappropriate API usage.
		$this->assertNull( $result );

		// Cleanup.
		unset( $_GET['post'] );
		set_current_screen( 'front' );
		wp_delete_attachment( $pdf_id, true );
	}

	/**
	 * Test: get_attachment_editor_data() returns complete data structure for valid image.
	 *
	 * WHY: The editor UI depends on this data structure to display alt text and controls.
	 * BREAKS IF: Missing keys cause JavaScript errors, UI doesn't render properly.
	 *
	 * @covers Travelopia_WordPress_AI\get_attachment_editor_data()
	 *
	 * @return void
	 */
	public function test_get_attachment_editor_data_returns_valid_data(): void {
		// Setup: Add alt text to simulate existing data.
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', 'Test alt text' );

		// Simulate editing an image attachment.
		set_current_screen( 'attachment' );
		$_GET['post'] = $this->attachment_id;

		// Set the nonce.
		$_GET['tp_nonce'] = wp_create_nonce( 'generate_alt_text_' . $this->attachment_id );

		// Call function to get editor data.
		$result = get_attachment_editor_data();

		// CRITICAL: Data structure must match what JavaScript expects.
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'post', $result );
		$this->assertArrayHasKey( 'alt_text', $result );
		$this->assertArrayHasKey( 'mode', $result );

		// Validate data types and values.
		$this->assertInstanceOf( WP_Post::class, $result['post'] );
		$this->assertEquals( $this->attachment_id, $result['post']->ID );
		$this->assertEquals( 'Test alt text', $result['alt_text'] );
		$this->assertEquals( 'default', $result['mode'] );

		// Cleanup.
		unset( $_GET['post'] );
		set_current_screen( 'front' );
	}

	/**
	 * Test: get_attachment_editor_data() sets mode to 'regenerate' with proper flags.
	 *
	 * WHY: Different UI is shown for regeneration (confirmation, different messaging).
	 * BREAKS IF: Mode detection fails, wrong UI shown, confusing user experience.
	 *
	 * @covers Travelopia_WordPress_AI\get_attachment_editor_data()
	 *
	 * @return void
	 */
	public function test_get_attachment_editor_data_returns_regenerate_mode(): void {
		// Simulate clicking "Regenerate Alt Text" link (with nonce for security).
		set_current_screen( 'attachment' );
		$_GET['post']                   = $this->attachment_id;
		$_GET['tp_regenerate_alt_text'] = true;
		$_GET['tp_nonce']               = wp_create_nonce( 'generate_alt_text_' . $this->attachment_id );

		// Call function to get editor data with regenerate flag.
		$result = get_attachment_editor_data();

		// CRITICAL: Mode must be 'regenerate' to show correct UI workflow.
		$this->assertIsArray( $result );
		$this->assertEquals( 'regenerate', $result['mode'] );

		// Cleanup.
		unset( $_GET['post'], $_GET['tp_regenerate_alt_text'], $_GET['tp_nonce'] );
		set_current_screen( 'front' );
	}

	/**
	 * Test: admin_enqueue_scripts() doesn't load assets on non-attachment screens.
	 *
	 * WHY: Loading unnecessary JavaScript/CSS slows down other admin pages.
	 * BREAKS IF: Scripts load everywhere, causing performance issues and conflicts.
	 *
	 * @covers Travelopia_WordPress_AI\admin_enqueue_scripts()
	 *
	 * @return void
	 */
	public function test_admin_enqueue_scripts_does_nothing_when_not_on_attachment_screen(): void {
		// Call the function without being on attachment screen.
		admin_enqueue_scripts();

		// CRITICAL: Scripts must NOT be enqueued on wrong pages.
		$this->assertFalse( wp_script_is( 'trav-ai-editor', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'trav-ai-editor', 'enqueued' ) );
	}

	/**
	 * Test: admin_enqueue_scripts() loads assets on attachment edit screen.
	 *
	 * WHY: JavaScript and CSS are required for the alt text editor UI to function.
	 * BREAKS IF: Assets don't load, UI is broken, users can't generate alt text.
	 *
	 * @covers Travelopia_WordPress_AI\admin_enqueue_scripts()
	 *
	 * @return void
	 */
	public function test_admin_enqueue_scripts_enqueues_on_attachment_screen(): void {
		// Setup: Create conditions where function should load assets.
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', 'Test alt text' );

		// Set up the attachment edit screen environment.
		set_current_screen( 'attachment' );
		$_GET['post'] = $this->attachment_id;

		// Set the nonce.
		$_GET['tp_nonce'] = wp_create_nonce( 'generate_alt_text_' . $this->attachment_id );

		// Call the enqueue function.
		admin_enqueue_scripts();

		// CRITICAL: Both JavaScript and CSS must be enqueued for UI to work.
		$this->assertTrue( wp_script_is( 'travelopia-wp-ai-editor', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'travelopia-wp-ai-editor', 'enqueued' ) );

		// Cleanup.
		unset( $_GET['post'] );
		set_current_screen( 'front' );
	}

	/**
	 * Test: media_row_actions() doesn't add alt text action to non-images.
	 *
	 * WHY: Only images need alt text, adding links to PDFs/videos confuses users.
	 * BREAKS IF: Users see "Generate Alt Text" on non-images, leading to errors.
	 *
	 * @covers Travelopia_WordPress_AI\media_row_actions()
	 *
	 * @return void
	 */
	public function test_media_row_actions_returns_unchanged_for_non_image(): void {
		// Create a PDF attachment.
		$pdf_id = $this->factory()->attachment->create_object(
			[
				'file'           => 'test.pdf',
				'post_mime_type' => 'application/pdf',
				'post_type'      => 'attachment',
			]
		);

		// Get the PDF post object and prepare actions array.
		$post    = get_post( $pdf_id );
		$actions = [ 'edit' => '<a>Edit</a>' ];

		// Call the media_row_actions function.
		$result = media_row_actions( $actions, $post );

		// CRITICAL: Actions array should remain unchanged for non-images.
		$this->assertEquals( $actions, $result );
		$this->assertArrayNotHasKey( 'generate_alt_text', $result );

		// Cleanup.
		wp_delete_attachment( $pdf_id, true );
	}

	/**
	 * Test: media_row_actions() adds "Generate" link for images without alt text.
	 *
	 * WHY: Primary entry point for users to generate alt text from media library.
	 * BREAKS IF: Users can't discover the feature, accessibility goals not met.
	 *
	 * @covers Travelopia_WordPress_AI\media_row_actions()
	 *
	 * @return void
	 */
	public function test_media_row_actions_adds_generate_action_for_empty_alt(): void {
		// Setup: Existing actions array.
		$actions = [ 'edit' => '<a>Edit</a>' ];

		// Call the media_row_actions function.
		$result = media_row_actions( $actions, $this->attachment );

		// CRITICAL: Link must be present with correct text and security nonce.
		$this->assertArrayHasKey( 'generate_alt_text', $result );
		$this->assertStringContainsString( 'Generate Alt Text', $result['generate_alt_text'] );
		$this->assertStringContainsString( 'tp_generate_alt_text=true', $result['generate_alt_text'] );
		$this->assertStringContainsString( 'tp_nonce=', $result['generate_alt_text'] );
	}

	/**
	 * Test: media_row_actions() adds "Regenerate" link for images with existing alt text.
	 *
	 * WHY: Users need ability to improve existing alt text with updated AI models.
	 * BREAKS IF: No way to regenerate, users stuck with poor quality alt text.
	 *
	 * @covers Travelopia_WordPress_AI\media_row_actions()
	 *
	 * @return void
	 */
	public function test_media_row_actions_adds_regenerate_action_for_existing_alt(): void {
		// Setup: Add existing alt text.
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', 'Existing alt text' );

		// Setup: Existing actions array.
		$actions = [ 'edit' => '<a>Edit</a>' ];

		// Call the media_row_actions function.
		$result = media_row_actions( $actions, $this->attachment );

		// CRITICAL: Link text changes to "Regenerate" with different URL parameter.
		$this->assertArrayHasKey( 'generate_alt_text', $result );
		$this->assertStringContainsString( 'Regenerate Alt Text', $result['generate_alt_text'] );
		$this->assertStringContainsString( 'tp_regenerate_alt_text=true', $result['generate_alt_text'] );
		$this->assertStringContainsString( 'tp_nonce=', $result['generate_alt_text'] );
	}

	/**
	 * Test: get_alt_text_action_url() builds correct URL for generation.
	 *
	 * WHY: URL must have correct parameters for backend to process request properly.
	 * BREAKS IF: Wrong URL parameters, backend can't identify generation vs regeneration.
	 *
	 * @covers Travelopia_WordPress_AI\get_alt_text_action_url()
	 *
	 * @return void
	 */
	public function test_get_alt_text_action_url_returns_generate_url(): void {
		// Call function with regeneration flag set to false.
		$url = get_alt_text_action_url( $this->attachment, false );

		// CRITICAL: URL must contain all required parameters for generation workflow.
		$this->assertStringContainsString( 'post=' . $this->attachment_id, $url );
		$this->assertStringContainsString( 'action=edit', $url );
		$this->assertStringContainsString( 'tp_generate_alt_text=true', $url );
		$this->assertStringContainsString( 'tp_nonce=', $url );

		// Must NOT contain regenerate flag (mutually exclusive).
		$this->assertStringNotContainsString( 'tp_regenerate_alt_text', $url );
	}

	/**
	 * Test: get_alt_text_action_url() builds correct URL for regeneration.
	 *
	 * WHY: Regeneration has different logic (overwrites existing, needs confirmation).
	 * BREAKS IF: Wrong parameters, existing alt text accidentally overwritten.
	 *
	 * @covers Travelopia_WordPress_AI\get_alt_text_action_url()
	 *
	 * @return void
	 */
	public function test_get_alt_text_action_url_returns_regenerate_url(): void {
		// Call function with regeneration flag set to true.
		$url = get_alt_text_action_url( $this->attachment, true );

		// CRITICAL: URL must contain regenerate flag, not generate flag.
		$this->assertStringContainsString( 'post=' . $this->attachment_id, $url );
		$this->assertStringContainsString( 'action=edit', $url );
		$this->assertStringContainsString( 'tp_regenerate_alt_text=true', $url );
		$this->assertStringContainsString( 'tp_nonce=', $url );

		// Must NOT contain generate flag (mutually exclusive).
		$this->assertStringNotContainsString( 'tp_generate_alt_text', $url );
	}

	/**
	 * Test: get_alt_text_action_url() includes valid WordPress nonce for CSRF protection.
	 *
	 * WHY: Prevents malicious sites from generating alt text via CSRF attacks.
	 * BREAKS IF: Missing/invalid nonce allows unauthorized alt text generation, security risk.
	 *
	 * @covers Travelopia_WordPress_AI\get_alt_text_action_url()
	 *
	 * @return void
	 */
	public function test_get_alt_text_action_url_includes_valid_nonce(): void {
		// Call function to generate URL.
		$url = get_alt_text_action_url( $this->attachment, false );

		// Extract nonce from URL.
		$parsed_url = wp_parse_url( $url );

		// Parse query string into array.
		parse_str( $parsed_url['query'], $query_params );

		// Verify nonce exists in query parameters.
		$this->assertArrayHasKey( 'tp_nonce', $query_params );

		// CRITICAL: Nonce must be valid and tied to specific attachment.
		$nonce_valid = wp_verify_nonce( $query_params['tp_nonce'], 'generate_alt_text_' . $this->attachment_id );
		$this->assertNotFalse( $nonce_valid );
	}

	/**
	 * Test: handle_rest_alt_text_update() ignores requests without alt_text parameter.
	 *
	 * WHY: Avoids firing tracking action for unrelated attachment updates.
	 * BREAKS IF: Action fires on every REST update, causing unnecessary processing.
	 *
	 * @covers Travelopia_WordPress_AI\handle_rest_alt_text_update()
	 *
	 * @return void
	 */
	public function test_handle_rest_alt_text_update_does_nothing_without_alt_text_param(): void {
		// Create REST request without alt_text parameter.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $this->attachment_id );

		// Track whether action fires.
		$action_fired = false;

		// Add action to track if it fires.
		add_action(
			'trav_ai_alt_text_modified',
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		// Call the function to handle REST update.
		handle_rest_alt_text_update( $this->attachment, $request );

		// CRITICAL: Action must NOT fire when alt_text is not being updated.
		$this->assertFalse( $action_fired );
	}

	/**
	 * Test: handle_rest_alt_text_update() fires action when alt text is updated via REST.
	 *
	 * WHY: Allows tracking/logging when alt text is modified programmatically.
	 * BREAKS IF: No tracking of alt text changes, audit trail is lost.
	 *
	 * @covers Travelopia_WordPress_AI\handle_rest_alt_text_update()
	 *
	 * @return void
	 */
	public function test_handle_rest_alt_text_update_fires_action_with_alt_text_param(): void {
		// Create REST request with alt_text parameter.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $this->attachment_id );

		// Set the alt_text parameter on the request.
		$request->set_param( 'alt_text', 'New alt text from REST' );

		// Track action parameters.
		$action_fired         = false;
		$action_attachment_id = null;
		$action_alt_text      = null;

		// Add action to capture parameters.
		add_action(
			'trav_ai_alt_text_modified',
			function ( $attachment_id, $alt_text ) use ( &$action_fired, &$action_attachment_id, &$action_alt_text ) {
				$action_fired         = true;
				$action_attachment_id = $attachment_id;
				$action_alt_text      = $alt_text;
			},
			10,
			2
		);

		// Call the function to handle REST update with alt text.
		handle_rest_alt_text_update( $this->attachment, $request );

		// CRITICAL: Action must fire with correct attachment ID and alt text value.
		$this->assertTrue( $action_fired );
		$this->assertEquals( $this->attachment_id, $action_attachment_id );
		$this->assertEquals( 'New alt text from REST', $action_alt_text );
	}

	/**
	 * Test: bootstrap() registers all WordPress hooks at correct priorities.
	 *
	 * WHY: Plugin features only work if hooks are properly registered on initialization.
	 * BREAKS IF: Hooks not registered, entire plugin is non-functional.
	 *
	 * @covers Travelopia_WordPress_AI\bootstrap()
	 *
	 * @return void
	 */
	public function test_bootstrap_registers_hooks(): void {
		// Clear all hooks to test registration from scratch.
		remove_all_actions( 'admin_enqueue_scripts' );
		remove_all_actions( 'rest_after_insert_attachment' );
		remove_all_filters( 'media_row_actions' );

		// Enable AI alt text generation for this test.
		update_option(
			'travelopia_wp_ai_settings',
			[
				'ai_alt_text_enabled' => true,
				'ai_alt_text_prompt'  => 'Test prompt',
			]
		);

		// Call bootstrap to register hooks.
		bootstrap();

		// Verify hooks are registered.
		// CRITICAL: All hooks must be registered at correct priorities.
		// Standard priority 10 for admin scripts.
		$this->assertEquals( 10, has_action( 'admin_enqueue_scripts', 'Travelopia_WordPress_AI\Alt_Text\admin_enqueue_scripts' ) );

		// Standard priority 10 for REST API integration.
		$this->assertEquals( 10, has_action( 'rest_after_insert_attachment', 'Travelopia_WordPress_AI\Alt_Text\handle_rest_alt_text_update' ) );

		// Standard priority 10 for media row actions filter.
		$this->assertEquals( 10, has_filter( 'media_row_actions', 'Travelopia_WordPress_AI\Alt_Text\media_row_actions' ) );
	}
}
