<?php
/**
 * Settings page.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Settings;

use Travelopia\WordPress_AI\Settings;

class Page
{
	/**
	 * Register settings section.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		add_settings_section(
			Settings::SECTION_ID,
			__( 'AI Alt Text Generation Settings', 'travelopia-wordpress-ai' ),
			fn () => esc_html_e( 'Configure the AI-powered alt text generation settings for your WordPress site.', 'travelopia-wordpress-ai' ),
			Settings::PAGE_SLUG,
		);
	}

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

			<?php settings_errors( Settings::OPTION_NAME ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( Settings::SETTINGS_GROUP );
				do_settings_sections( Settings::PAGE_SLUG );
				submit_button( esc_html__( 'Save Settings', 'travelopia-wordpress-ai' ) );
				?>
			</form>
		</div>
		<?php
	}
}
