<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

class ShipFlo_Wc_Notices
{
	public static function shipflo_api_key_notice()
	{
		$api_key         = shipflo_get_api_key();
		$shipflo_tab_url = 'admin.php?page=wc-settings&tab=settings_tab_shipflo';
		if (empty($api_key)) {
		?>

			<div class='notice notice-warning is-dismissible'>
				<p>
					<?php esc_html_e('Your ShipFlo API Key Field is blank. To set up API Key,', 'shipflo-wc'); ?>
					<a
						href="<?php echo $shipflo_tab_url; ?>"
						target="_top">
						<?php esc_html_e('Click Here', 'shipflo-wc'); ?>
					</a>.
				</p>
			</div>

		<?php
		}
	}

	public static function shipflo_retry_send_notice()
	{
		$notice = get_transient("shipflo_notice");

		if ($notice) {
			$class = $notice['type'] === 'success' ? 'notice-success' : 'notice-error';
			printf(
				'<div class="notice %s is-dismissible"><p>%s</p></div>',
				esc_attr($class),
				esc_html($notice['msg'])
			);

			delete_transient("shipflo_notice");
		}
	}
}
