<?php
/**
 * Removes everything AFB Update Watch stored.
 *
 * @package UpdateWatch
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_site_option( 'afbuw_settings' );
delete_site_option( 'afbuw_state' );
delete_site_option( 'afbuw_woo' );
wp_clear_scheduled_hook( 'afbuw_daily_check' );
