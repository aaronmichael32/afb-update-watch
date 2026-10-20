<?php
/**
 * Stops WooCommerce auto-updating to a new major or minor release while active extensions are not
 * marked as tested with it.
 *
 * WooCommerce used to do this itself. Since it set WC_SSR_PLUGIN_UPDATE_RELEASE_VERSION_TYPE to
 * 'none' by default, wc_prevent_dangerous_auto_updates() lets every release through, so a store
 * can update overnight past extensions that have never been tested with the new version. The
 * check below mirrors WooCommerce's own get_untested_plugins() at 'minor' level.
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce untested-extension guard.
 */
class Woo_Guard {

	const WOO_FILE = 'woocommerce/woocommerce.php';
	const TESTED   = 'WC tested up to';
	const REQUIRES = 'WC requires at least';
	const DEPENDS  = 'Requires Plugins';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'extra_plugin_headers', array( __CLASS__, 'headers' ) );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'guard' ), 100, 2 );
	}

	/**
	 * Makes sure WordPress reads the WooCommerce headers even before WooCommerce has loaded, and
	 * the Requires Plugins header on WordPress versions older than 6.5, which did not parse it.
	 *
	 * @param string[] $headers Extra headers.
	 * @return string[]
	 */
	public static function headers( $headers ) {
		return array_values( array_unique( array_merge( (array) $headers, array( self::TESTED, self::REQUIRES, self::DEPENDS ) ) ) );
	}

	/**
	 * Whether the guard is switched on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Store::settings()['woo_guard'];
	}

	/**
	 * Holds a WooCommerce auto-update when extensions are untested with the new version.
	 *
	 * @param bool|null $update Whether to update.
	 * @param object    $item   The update offer.
	 * @return bool|null
	 */
	public static function guard( $update, $item ) {
		if ( ! self::enabled() || ! is_object( $item ) || empty( $item->plugin ) || self::WOO_FILE !== $item->plugin || empty( $item->new_version ) ) {
			return $update;
		}
		$new_version = (string) $item->new_version;
		$record      = Store::woo();
		if ( ! empty( $record['allowed'] ) && $record['allowed'] === $new_version ) {
			return $update;
		}
		$untested = self::untested( $new_version );
		if ( ! $untested ) {
			if ( $record['version'] ) {
				Store::save_woo( array() );
			}
			return $update;
		}
		if ( $record['version'] !== $new_version ) {
			Store::save_woo(
				array(
					'version'  => $new_version,
					'untested' => $untested,
					'at'       => time(),
				)
			);
			Notifier::woo_held( $new_version, $untested );
		}
		return false;
	}

	/**
	 * Active extensions not marked as tested with a WooCommerce version, at major.minor level.
	 *
	 * Patch releases never trigger the guard. An extension that declares no tested version is
	 * listed as unknown, the same as WooCommerce treats it.
	 *
	 * @param string $new_version The WooCommerce version on offer.
	 * @return array<string,array{name:string,tested:string}>
	 */
	public static function untested( $new_version ) {
		$installed = self::installed_version();
		if ( '' === $installed || ! self::is_minor_or_major_change( $installed, $new_version ) ) {
			return array();
		}
		$target   = self::major_minor( $new_version );
		$untested = array();
		foreach ( self::extensions() as $file => $data ) {
			$tested = isset( $data[ self::TESTED ] ) ? trim( (string) $data[ self::TESTED ] ) : '';
			if ( '' === $tested ) {
				$untested[ $file ] = array(
					'name'   => (string) $data['Name'],
					'tested' => '',
				);
				continue;
			}
			$tested_mm = self::major_minor( $tested );
			if ( '' === $tested_mm ) {
				continue;
			}
			if ( version_compare( $tested_mm, $target, '<' ) ) {
				$untested[ $file ] = array(
					'name'   => (string) $data['Name'],
					'tested' => $tested,
				);
			}
		}
		return $untested;
	}

	/**
	 * Active plugins that declare themselves WooCommerce extensions.
	 *
	 * @return array<string,array>
	 */
	public static function extensions() {
		$all    = Registry::installed_files();
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$out = array();
		foreach ( array_unique( $active ) as $file ) {
			if ( self::WOO_FILE === $file || plugin_basename( AFBUW_FILE ) === $file || empty( $all[ $file ] ) ) {
				continue;
			}
			$data     = $all[ $file ];
			$requires = ! empty( $data['RequiresPlugins'] ) ? (string) $data['RequiresPlugins'] : ( isset( $data[ self::DEPENDS ] ) ? (string) $data[ self::DEPENDS ] : '' );
			$is_ext   = ! empty( $data[ self::TESTED ] ) || ! empty( $data[ self::REQUIRES ] )
				|| in_array( 'woocommerce', array_map( 'trim', explode( ',', strtolower( $requires ) ) ), true );
			if ( $is_ext ) {
				$out[ $file ] = $data;
			}
		}
		return $out;
	}

	/**
	 * Installed WooCommerce version, or empty when it is not installed.
	 *
	 * @return string
	 */
	public static function installed_version() {
		$all = Registry::installed_files();
		return isset( $all[ self::WOO_FILE ]['Version'] ) ? (string) $all[ self::WOO_FILE ]['Version'] : '';
	}

	/**
	 * The current WooCommerce update offer, if there is one.
	 *
	 * @return string
	 */
	public static function offered_version() {
		$updates = get_site_transient( 'update_plugins' );
		if ( is_object( $updates ) && isset( $updates->response[ self::WOO_FILE ] ) ) {
			$offer = (object) $updates->response[ self::WOO_FILE ];
			return isset( $offer->new_version ) ? (string) $offer->new_version : '';
		}
		return '';
	}

	/**
	 * Lets one specific WooCommerce version through, after a person has decided to.
	 *
	 * @param string $version Version to allow.
	 * @return void
	 */
	public static function allow( $version ) {
		$record            = Store::woo();
		$record['allowed'] = (string) $version;
		Store::save_woo( $record );
	}

	/**
	 * Whether two versions differ at major or minor level.
	 *
	 * @param string $from Installed version.
	 * @param string $to   Offered version.
	 * @return bool
	 */
	public static function is_minor_or_major_change( $from, $to ) {
		$a = self::major_minor( $from );
		$b = self::major_minor( $to );
		return '' !== $a && '' !== $b && version_compare( $b, $a, '>' );
	}

	/**
	 * The major.minor part of a version, or empty when it is not numeric.
	 *
	 * @param string $version A version string.
	 * @return string
	 */
	public static function major_minor( $version ) {
		$parts = explode( '.', trim( (string) $version ) );
		if ( ! isset( $parts[0] ) || ! is_numeric( $parts[0] ) ) {
			return '';
		}
		$minor = isset( $parts[1] ) && is_numeric( $parts[1] ) ? (int) $parts[1] : 0;
		return (int) $parts[0] . '.' . $minor;
	}
}
