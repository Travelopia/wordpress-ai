<?php
/**
 * Travelopia WordPress AI Settings Page Template
 *
 * @package travelopia-wordpress-ai
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check user capabilities.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'travelopia-wp-ai' ) );
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors( 'travelopia_wp_ai_settings' ); ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'travelopia_wp_ai_settings_group' );
		do_settings_sections( 'travelopia-wp-ai-settings' );
		submit_button( esc_html__( 'Save Settings', 'travelopia-wp-ai' ) );
		?>
	</form>
</div>
