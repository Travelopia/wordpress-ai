<?php
/**
 * Admin namespace functions.
 *
 * @package travelopia-wp-ai
 */

namespace Travelopia_WordPress_AI\Admin;

use function Travelopia_WordPress_AI\Alt_Text\get_default_ai_alt_text_prompt;

/**
 * Bootstrap admin functionality.
 *
 * @return void
 */
function bootstrap(): void {
	// Add settings menu for WordPress AI.
	add_action( 'admin_menu', __NAMESPACE__ . '\\setup_settings' );

	// Initialize settings.
	add_action( 'admin_init', __NAMESPACE__ . '\\initialize_settings' );

	// Enqueue admin styles.
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_styles' );

	// Add settings link to plugin actions.
	add_filter(
		'plugin_action_links_' . plugin_basename( dirname( dirname( __DIR__ ) ) . '/plugin.php' ),
		__NAMESPACE__ . '\\add_settings_link'
	);
}

/**
 * Setup admin settings menu.
 *
 * @return void
 */
function setup_settings(): void {
	// Add WordPress AI settings page to Settings menu.
	add_options_page(
		__( 'Travelopia WordPress AI Settings', 'travelopia-wp-ai' ),
		__( 'Travelopia WP AI', 'travelopia-wp-ai' ),
		'manage_options',
		'travelopia-wp-ai-settings',
		__NAMESPACE__ . '\\render_settings_page'
	);
}

/**
 * Initialize and register settings.
 *
 * @return void
 */
function initialize_settings(): void {
	// Register settings group.
	register_setting(
		'travelopia_wp_ai_settings_group',
		'travelopia_wp_ai_settings',
		[
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_settings',
			'default'           => get_default_settings(),
		]
	);

	// Add settings section.
	add_settings_section(
		'travelopia_wp_ai_main_section',
		__( 'AI Alt Text Generation Settings', 'travelopia-wp-ai' ),
		__NAMESPACE__ . '\\render_section_description',
		'travelopia-wp-ai-settings'
	);

	// Add Enable Alt Text Generation field.
	add_settings_field(
		'ai_alt_text_enabled',
		__( 'Enable AI Alt Text Generation', 'travelopia-wp-ai' ),
		__NAMESPACE__ . '\\render_enable_field',
		'travelopia-wp-ai-settings',
		'travelopia_wp_ai_main_section'
	);

	// Add Alt Text Prompt field.
	add_settings_field(
		'ai_alt_text_prompt',
		__( 'AI Alt Text Prompt', 'travelopia-wp-ai' ),
		__NAMESPACE__ . '\\render_prompt_field',
		'travelopia-wp-ai-settings',
		'travelopia_wp_ai_main_section'
	);
}

/**
 * Get default settings.
 *
 * @return array<string,mixed> Default settings array.
 */
function get_default_settings(): array {
	// Return default settings.
	return [
		'ai_alt_text_enabled' => false,
		'ai_alt_text_prompt'  => get_default_ai_alt_text_prompt(),
	];
}

/**
 * Sanitize settings input.
 *
 * @param array<string,mixed>|null $input Raw input from the form.
 *
 * @return array<string,mixed> Sanitized settings.
 */
function sanitize_settings( ?array $input = null ): array {
	// Initialize sanitized array.
	$sanitized = [];

	// Return defaults if input is null.
	if ( null === $input ) {
		return get_default_settings();
	}

	// Sanitize enable checkbox.
	$sanitized['ai_alt_text_enabled'] = ! empty( $input['ai_alt_text_enabled'] );

	// Sanitize prompt textarea - allow basic HTML but strip scripts.
	$sanitized['ai_alt_text_prompt'] = sanitize_textarea_field( strval( $input['ai_alt_text_prompt'] ?? '' ) );

	// Validate prompt is not empty.
	if ( empty( trim( $sanitized['ai_alt_text_prompt'] ) ) ) {
		$defaults                        = get_default_settings();
		$sanitized['ai_alt_text_prompt'] = $defaults['ai_alt_text_prompt'];
	}

	// Return sanitized settings.
	return $sanitized;
}

/**
 * Render the settings page.
 *
 * @return void
 */
function render_settings_page(): void {
	// Include settings page template.
	include __DIR__ . '/templates/settings-page.php';
}

/**
 * Render section description.
 *
 * @return void
 */
function render_section_description(): void {
	// Output section description.
	esc_html_e( 'Configure the AI-powered alt text generation settings for your WordPress site.', 'travelopia-wp-ai' );
}

/**
 * Render the enable field.
 *
 * @return void
 */
function render_enable_field(): void {
	// Include enable field template.
	include __DIR__ . '/templates/enable-field.php';
}

/**
 * Render the prompt field.
 *
 * @return void
 */
function render_prompt_field(): void {
	// Include prompt field template.
	include __DIR__ . '/templates/prompt-field.php';
}

/**
 * Add settings link to plugin actions.
 *
 * @param array<string> $links Existing plugin action links.
 *
 * @return array<string> Modified plugin action links.
 */
function add_settings_link( array $links = [] ): array {
	// Create settings link.
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=travelopia-wp-ai-settings' ) ),
		esc_html__( 'Settings', 'travelopia-wp-ai' )
	);

	// Add settings link to beginning of array.
	array_unshift( $links, $settings_link );

	// Return modified links.
	return $links;
}

/**
 * Enqueue admin styles for WordPress AI settings page.
 *
 * @param string $hook_suffix The current admin page hook suffix.
 *
 * @return void
 */
function enqueue_admin_styles( string $hook_suffix = '' ): void {
	// Only load on our settings page.
	if ( 'settings_page_travelopia-wp-ai-settings' !== $hook_suffix ) {
		return;
	}

	// Get plugin directory URL.
	$plugin_dir_url = plugin_dir_url( dirname( __DIR__ ) );

	// Enqueue admin styles.
	wp_enqueue_style(
		'travelopia-wp-ai-admin',
		$plugin_dir_url . 'dist/admin.css',
		[],
		'1.0.0'
	);

	// Enqueue admin scripts.
	wp_enqueue_script(
		'travelopia-wp-ai-admin',
		$plugin_dir_url . 'dist/admin.js',
		[],
		'1.0.0',
		true
	);
}
