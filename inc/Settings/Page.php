<?php
/**
 * Settings page.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Settings;

class Page
{
	/**
	 * Render this template.
	 *
	 * @return void
	 */
	public static function render(): void
	{
		if (
			! current_user_can(
				apply_filters( 'travelopia_wordpress_ai_settings_capability', 'manage_options' ),
			)
		) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'travelopia-wordpress-ai' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'travelopia_wp_ai_settings' ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'travelopia_wp_ai_settings_group' );
				do_settings_sections( 'travelopia-wp-ai-settings' );
				submit_button( esc_html__( 'Save Settings', 'travelopia-wordpress-ai' ) );
				?>
			</form>
		</div>
		<?php
	}
}
