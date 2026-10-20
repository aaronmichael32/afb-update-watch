<?php
/**
 * Holds back updates for plugins that have changed owner or been closed, until someone reviews them.
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Update holds for flagged plugins.
 */
class Holds {

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'auto_update_plugin', array( __CLASS__, 'block_auto_update' ), 101, 2 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'block_held_download' ), 10, 4 );
	}

	/**
	 * Whether holds are switched on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Store::settings()['hold_on_change'];
	}

	/**
	 * Stops WordPress auto-updating a flagged plugin.
	 *
	 * @param bool|null $update Whether to update.
	 * @param object    $item   The update offer.
	 * @return bool|null
	 */
	public static function block_auto_update( $update, $item ) {
		if ( ! self::enabled() || ! is_object( $item ) || empty( $item->plugin ) ) {
			return $update;
		}
		return isset( Registry::flagged_files()[ $item->plugin ] ) ? false : $update;
	}

	/**
	 * Refuses to download an update for a held plugin, so the Update button, bulk update and
	 * WP-CLI all stop with an explanation instead of installing it. The update stays listed, so
	 * nothing about WordPress's own update data is changed.
	 *
	 * @param bool|\WP_Error $reply      Whether to short-circuit the download.
	 * @param string         $package    Package URL.
	 * @param \WP_Upgrader   $upgrader   The upgrader.
	 * @param array          $hook_extra Extra details, including the plugin file being updated.
	 * @return bool|\WP_Error
	 */
	public static function block_held_download( $reply, $package, $upgrader, $hook_extra = array() ) {
		if ( false !== $reply || ! self::enabled() || empty( $hook_extra['plugin'] ) ) {
			return $reply;
		}
		$flagged = Registry::flagged_files();
		$file    = (string) $hook_extra['plugin'];
		if ( ! isset( $flagged[ $file ] ) ) {
			return $reply;
		}
		return new \WP_Error(
			'afbuw_held',
			Notifier::describe( $flagged[ $file ]['slug'], $flagged[ $file ] ) . ' '
			. __( 'AFB Update Watch is holding this update until you review it and release the hold.', 'afb-update-watch' )
		);
	}
}
