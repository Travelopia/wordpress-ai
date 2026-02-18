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
	 * Render this template.
	 *
	 * @return void
	 */
	public static function render(): void
	{
		if ( ! current_user_can( Settings::get_capability() ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'travelopia-wordpress-ai' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="travelopia-wp-ai-settings"></div>
		</div>
		<?php
	}
}
