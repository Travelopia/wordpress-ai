<?php
/**
 * TravAI Settings Page Template
 *
 * @package trav-ai
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check user capabilities.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'trav-ai' ) );
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors( 'travai_settings' ); ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'travai_settings_group' );
		do_settings_sections( 'travai-settings' );
		submit_button( esc_html__( 'Save Settings', 'trav-ai' ) );
		?>
	</form>
</div>
