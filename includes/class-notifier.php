<?php
/**
 * Email alerts.
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends alert emails.
 */
class Notifier {

	/**
	 * Emails a summary of plugins newly flagged on a check.
	 *
	 * @param array<string,array> $flagged Flagged entries keyed by slug.
	 * @return bool Whether the mail was accepted for delivery.
	 */
	public static function owner_flags( array $flagged ) {
		if ( ! $flagged ) {
			return false;
		}
		$lines = array();
		foreach ( $flagged as $slug => $entry ) {
			$lines[] = self::describe( $slug, $entry );
		}
		$held = Holds::enabled()
			? __( 'Updates for these plugins are on hold until you review them.', 'afb-update-watch' )
			: __( 'Update holds are switched off, so these plugins can still update.', 'afb-update-watch' );
		$body = sprintf(
			/* translators: %s: site URL. */
			__( 'AFB Update Watch found changes on %s:', 'afb-update-watch' ),
			network_home_url()
		) . "\n\n" . implode( "\n\n", $lines ) . "\n\n" . $held . "\n\n" . Admin::page_url();
		$count = count( $flagged );
		return wp_mail(
			Store::alert_email(),
			sprintf(
				/* translators: 1: number of plugins, 2: site name. */
				_n( '[%2$s] %1$d plugin needs checking', '[%2$s] %1$d plugins need checking', $count, 'afb-update-watch' ),
				$count,
				self::site_name()
			),
			$body
		);
	}

	/**
	 * Emails that a WooCommerce auto-update was held back.
	 *
	 * @param string $version  The WooCommerce version held.
	 * @param array  $untested Untested extensions.
	 * @return bool Whether the mail was accepted for delivery.
	 */
	public static function woo_held( $version, array $untested ) {
		$lines = array();
		foreach ( $untested as $ext ) {
			$lines[] = '- ' . $ext['name'] . ': ' . ( '' === $ext['tested']
				? __( 'no tested version declared', 'afb-update-watch' )
				/* translators: %s: version number. */
				: sprintf( __( 'tested up to %s', 'afb-update-watch' ), $ext['tested'] ) );
		}
		$body = sprintf(
			/* translators: %s: WooCommerce version. */
			__( 'WooCommerce %s was not auto-updated because these active extensions are not marked as tested with it:', 'afb-update-watch' ),
			$version
		) . "\n\n" . implode( "\n", $lines ) . "\n\n"
			. __( 'Check each extension for an update, or test the new version on a staging copy first. You can let this version through from the AFB Update Watch screen.', 'afb-update-watch' )
			. "\n\n" . Admin::page_url();
		return wp_mail(
			Store::alert_email(),
			sprintf(
				/* translators: 1: site name, 2: WooCommerce version. */
				__( '[%1$s] WooCommerce %2$s held back', 'afb-update-watch' ),
				self::site_name(),
				$version
			),
			$body
		);
	}

	/**
	 * One plain-text paragraph about a flagged plugin.
	 *
	 * @param string $slug  Plugin slug.
	 * @param array  $entry Watch entry.
	 * @return string
	 */
	public static function describe( $slug, array $entry ) {
		$name = isset( $entry['name'] ) ? $entry['name'] : $slug;
		if ( 'closed' === $entry['status'] ) {
			$reason = ! empty( $entry['closed']['reason'] ) ? $entry['closed']['reason'] : __( 'no reason given', 'afb-update-watch' );
			/* translators: 1: plugin name, 2: closure reason. */
			return sprintf( __( '%1$s has been closed on WordPress.org (%2$s). It will get no further updates, so look for a replacement.', 'afb-update-watch' ), $name, $reason );
		}
		$parts   = array();
		$changes = isset( $entry['changes'] ) ? $entry['changes'] : array();
		if ( isset( $changes['author_to'] ) ) {
			/* translators: 1: old author profile, 2: new author profile. */
			$parts[] = sprintf( __( 'author changed from %1$s to %2$s', 'afb-update-watch' ), $changes['author_from'] ? $changes['author_from'] : '?', $changes['author_to'] ? $changes['author_to'] : '?' );
		}
		if ( ! empty( $changes['added'] ) ) {
			/* translators: %s: list of WordPress.org usernames. */
			$parts[] = sprintf( __( 'new committers: %s', 'afb-update-watch' ), implode( ', ', $changes['added'] ) );
		}
		if ( ! empty( $changes['removed'] ) ) {
			/* translators: %s: list of WordPress.org usernames. */
			$parts[] = sprintf( __( 'committers removed: %s', 'afb-update-watch' ), implode( ', ', $changes['removed'] ) );
		}
		/* translators: 1: plugin name, 2: what changed. */
		return sprintf( __( '%1$s has changed hands on WordPress.org: %2$s.', 'afb-update-watch' ), $name, implode( '; ', $parts ) );
	}

	/**
	 * Site or network name for subject lines.
	 *
	 * @return string
	 */
	private static function site_name() {
		$name = is_multisite() ? get_network()->site_name : get_bloginfo( 'name' );
		return wp_specialchars_decode( (string) $name, ENT_QUOTES );
	}
}
