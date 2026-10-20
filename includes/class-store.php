<?php
/**
 * Settings and state, kept as network-wide options so single site and multisite behave the same.
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the plugin's options.
 */
class Store {

	const SETTINGS = 'afbuw_settings';
	const STATE    = 'afbuw_state';
	const WOO      = 'afbuw_woo';

	/**
	 * Settings merged over their defaults.
	 *
	 * @return array{email:string,hold_on_change:bool,woo_guard:bool}
	 */
	public static function settings() {
		$saved = get_site_option( self::SETTINGS, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'email'          => isset( $saved['email'] ) ? (string) $saved['email'] : '',
			'hold_on_change' => isset( $saved['hold_on_change'] ) ? (bool) $saved['hold_on_change'] : true,
			'woo_guard'      => isset( $saved['woo_guard'] ) ? (bool) $saved['woo_guard'] : true,
		);
	}

	/**
	 * Saves settings after cleaning them.
	 *
	 * @param array $input Raw settings.
	 * @return void
	 */
	public static function save_settings( array $input ) {
		$email = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
		update_site_option(
			self::SETTINGS,
			array(
				'email'          => is_email( $email ) ? $email : '',
				'hold_on_change' => ! empty( $input['hold_on_change'] ),
				'woo_guard'      => ! empty( $input['woo_guard'] ),
			)
		);
	}

	/**
	 * Where alerts go: the saved address, or the network or site admin email.
	 *
	 * @return string
	 */
	public static function alert_email() {
		$email = self::settings()['email'];
		if ( $email ) {
			return $email;
		}
		return is_multisite() ? (string) get_site_option( 'admin_email' ) : (string) get_option( 'admin_email' );
	}

	/**
	 * Per-plugin watch state.
	 *
	 * @return array{plugins:array,last_run:int}
	 */
	public static function state() {
		$state = get_site_option( self::STATE, array() );
		$state = is_array( $state ) ? $state : array();
		return array(
			'plugins'  => isset( $state['plugins'] ) && is_array( $state['plugins'] ) ? $state['plugins'] : array(),
			'last_run' => isset( $state['last_run'] ) ? (int) $state['last_run'] : 0,
		);
	}

	/**
	 * Saves the watch state.
	 *
	 * @param array $state State to store.
	 * @return void
	 */
	public static function save_state( array $state ) {
		update_site_option( self::STATE, $state );
	}

	/**
	 * What the WooCommerce guard last held back.
	 *
	 * @return array{version:string,untested:array,at:int,allowed:string}
	 */
	public static function woo() {
		$woo = get_site_option( self::WOO, array() );
		$woo = is_array( $woo ) ? $woo : array();
		return array(
			'version'  => isset( $woo['version'] ) ? (string) $woo['version'] : '',
			'untested' => isset( $woo['untested'] ) && is_array( $woo['untested'] ) ? $woo['untested'] : array(),
			'at'       => isset( $woo['at'] ) ? (int) $woo['at'] : 0,
			'allowed'  => isset( $woo['allowed'] ) ? (string) $woo['allowed'] : '',
		);
	}

	/**
	 * Saves the WooCommerce guard record.
	 *
	 * @param array $woo Record to store.
	 * @return void
	 */
	public static function save_woo( array $woo ) {
		update_site_option( self::WOO, $woo );
	}
}
