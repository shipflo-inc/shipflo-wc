<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Fired during plugin uninstallation
 *
 * @link       https://sgssandhu.com
 * @since      1.0.0
 * @package    ShipFlo_Wc
 */

/**
 * This class defines all code necessary to run during the plugin's uninstallation.
 *
 * @since 1.0.0
 */
class ShipFlo_Wc_Uninstaller 
{
	/**
	 * Runs on plugin uninstall.
	 */
	public static function uninstall() 
	{
		// Multisite check: Run uninstall logic for all sites if needed
		if ( is_multisite() ) {
			$sites = get_sites();
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				self::run_cleanup();
				restore_current_blog();
			}
		} else {
			shipflo_uninstall_cleanup();
		}
	}
}