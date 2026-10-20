<?php
/**
 * Watches every installed WordPress.org plugin for a change of owner or a closure.
 *
 * Ownership is the plugin's author profile plus its committer list on WordPress.org. A baseline
 * is taken the first time a plugin is seen; any later difference is flagged until a person
 * reviews it and releases the hold, which takes a fresh baseline.
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin ownership registry.
 */
class Registry {

	const API = 'https://api.wordpress.org/plugins/info/1.2/';

	/**
	 * Installed plugins that WordPress.org serves updates for, keyed by slug.
	 *
	 * Uses the update_plugins transient as the source of truth, so a premium plugin that happens
	 * to share a folder name with an unrelated WordPress.org plugin is never mistaken for it.
	 *
	 * @return array<string,string> Slug to plugin file.
	 */
	public static function wporg_plugins() {
		$updates = get_site_transient( 'update_plugins' );
		if ( ! is_object( $updates ) && function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
			$updates = get_site_transient( 'update_plugins' );
		}
		if ( ! is_object( $updates ) ) {
			return array();
		}
		$found = array();
		foreach ( array( 'response', 'no_update' ) as $bucket ) {
			if ( empty( $updates->$bucket ) || ! is_array( $updates->$bucket ) ) {
				continue;
			}
			foreach ( $updates->$bucket as $file => $item ) {
				$item = (object) $item;
				if ( isset( $item->id, $item->slug ) && 0 === strpos( (string) $item->id, 'w.org/plugins/' ) ) {
					$found[ (string) $item->slug ] = (string) $file;
				}
			}
		}
		ksort( $found );
		return $found;
	}

	/**
	 * Every installed plugin file.
	 *
	 * Closed plugins drop out of the update_plugins transient, so a plugin already being watched
	 * is kept on the list for as long as its file is still installed.
	 *
	 * @return array<string,array>
	 */
	public static function installed_files() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugins();
	}

	/**
	 * Looks a plugin up on WordPress.org.
	 *
	 * @param string $slug Plugin slug.
	 * @return array{kind:string,name?:string,owner?:array,reason?:string,date?:string,message?:string}
	 */
	public static function fetch( $slug ) {
		$url      = add_query_arg(
			array(
				'action'  => 'plugin_information',
				'request' => array(
					'slug'   => $slug,
					'fields' => array(
						'contributors' => true,
						'sections'     => false,
						'versions'     => false,
						'reviews'      => false,
						'banners'      => false,
						'icons'        => false,
						'screenshots'  => false,
						'tags'         => false,
						'ratings'      => false,
					),
				),
			),
			self::API
		);
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return array(
				'kind'    => 'error',
				'message' => $response->get_error_message(),
			);
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return array(
				'kind'    => 'error',
				'message' => 'Unreadable response from WordPress.org',
			);
		}
		if ( ! empty( $data['closed'] ) || ( isset( $data['error'] ) && 'closed' === $data['error'] ) ) {
			return array(
				'kind'   => 'closed',
				'name'   => isset( $data['name'] ) ? (string) $data['name'] : $slug,
				'reason' => isset( $data['reason_text'] ) ? (string) $data['reason_text'] : '',
				'date'   => isset( $data['closed_date'] ) ? (string) $data['closed_date'] : '',
			);
		}
		if ( isset( $data['error'] ) ) {
			return array( 'kind' => 'missing' );
		}
		return array(
			'kind'  => 'ok',
			'name'  => isset( $data['name'] ) ? wp_strip_all_tags( (string) $data['name'] ) : $slug,
			'owner' => self::owner( $data ),
		);
	}

	/**
	 * The parts of a plugin's record that say who controls it.
	 *
	 * @param array $data Plugin information from WordPress.org.
	 * @return array{author:string,committers:string[]}
	 */
	public static function owner( array $data ) {
		$author     = isset( $data['author_profile'] ) ? strtolower( untrailingslashit( (string) $data['author_profile'] ) ) : '';
		$committers = isset( $data['contributors'] ) && is_array( $data['contributors'] ) ? array_map( 'strtolower', array_keys( $data['contributors'] ) ) : array();
		sort( $committers );
		return array(
			'author'     => $author,
			'committers' => array_values( array_unique( $committers ) ),
		);
	}

	/**
	 * What changed between two ownership records. Empty means nothing changed.
	 *
	 * @param array $before Earlier record.
	 * @param array $after  Current record.
	 * @return array{author_from?:string,author_to?:string,added?:string[],removed?:string[]}
	 */
	public static function diff( array $before, array $after ) {
		$changes = array();
		if ( $before['author'] !== $after['author'] ) {
			$changes['author_from'] = $before['author'];
			$changes['author_to']   = $after['author'];
		}
		$added   = array_values( array_diff( $after['committers'], $before['committers'] ) );
		$removed = array_values( array_diff( $before['committers'], $after['committers'] ) );
		if ( $added ) {
			$changes['added'] = $added;
		}
		if ( $removed ) {
			$changes['removed'] = $removed;
		}
		return $changes;
	}

	/**
	 * Checks every WordPress.org plugin and records anything new.
	 *
	 * @return array<string,array> Plugins newly flagged on this run, keyed by slug.
	 */
	public static function check_all() {
		$state     = Store::state();
		$plugins   = self::wporg_plugins();
		$installed = self::installed_files();
		$flagged   = array();
		$now       = time();

		foreach ( $state['plugins'] as $slug => $entry ) {
			if ( ! isset( $plugins[ $slug ] ) && ! empty( $entry['file'] ) && isset( $installed[ $entry['file'] ] ) ) {
				$plugins[ $slug ] = $entry['file'];
			}
		}

		foreach ( $plugins as $slug => $file ) {
			$entry         = isset( $state['plugins'][ $slug ] ) ? $state['plugins'][ $slug ] : array(
				'status' => 'ok',
			);
			$entry['file'] = $file;
			$result        = self::fetch( $slug );

			if ( 'error' === $result['kind'] || 'missing' === $result['kind'] ) {
				$entry['last_error']       = 'error' === $result['kind'] ? $result['message'] : 'Not found on WordPress.org';
				$state['plugins'][ $slug ] = $entry;
				continue;
			}
			unset( $entry['last_error'] );
			$entry['checked_at'] = $now;
			$entry['name']       = $result['name'];

			if ( 'closed' === $result['kind'] ) {
				if ( 'closed' !== $entry['status'] ) {
					$entry['status']     = 'closed';
					$entry['flagged_at'] = $now;
					$entry['closed']     = array(
						'reason' => $result['reason'],
						'date'   => $result['date'],
					);
					$flagged[ $slug ]    = $entry;
				}
				$state['plugins'][ $slug ] = $entry;
				continue;
			}

			$entry['owner'] = $result['owner'];
			if ( 'closed' === $entry['status'] ) {
				$entry['status'] = 'ok';
				unset( $entry['closed'], $entry['flagged_at'] );
			}
			if ( empty( $entry['baseline'] ) ) {
				$entry['baseline'] = $result['owner'];
			} elseif ( 'owner_changed' !== $entry['status'] ) {
				$changes = self::diff( $entry['baseline'], $result['owner'] );
				if ( $changes ) {
					$entry['status']     = 'owner_changed';
					$entry['changes']    = $changes;
					$entry['flagged_at'] = $now;
					$flagged[ $slug ]    = $entry;
				}
			}
			$state['plugins'][ $slug ] = $entry;
		}

		foreach ( $state['plugins'] as $slug => $entry ) {
			if ( ! isset( $plugins[ $slug ] ) || empty( $entry['file'] ) || ! isset( $installed[ $entry['file'] ] ) ) {
				unset( $state['plugins'][ $slug ] );
			}
		}
		$state['last_run'] = $now;
		Store::save_state( $state );

		return $flagged;
	}

	/**
	 * Accepts a flagged plugin as reviewed: takes a fresh baseline and lifts the hold.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool Whether anything was released.
	 */
	public static function release( $slug ) {
		$state = Store::state();
		if ( empty( $state['plugins'][ $slug ] ) ) {
			return false;
		}
		$entry = $state['plugins'][ $slug ];
		if ( ! empty( $entry['owner'] ) ) {
			$entry['baseline'] = $entry['owner'];
		}
		$entry['status']      = 'ok';
		$entry['released_at'] = time();
		unset( $entry['changes'], $entry['flagged_at'] );
		$state['plugins'][ $slug ] = $entry;
		Store::save_state( $state );
		return true;
	}

	/**
	 * Plugin files currently flagged, keyed by file.
	 *
	 * @return array<string,array>
	 */
	public static function flagged_files() {
		$out = array();
		foreach ( Store::state()['plugins'] as $slug => $entry ) {
			if ( isset( $entry['status'], $entry['file'] ) && in_array( $entry['status'], array( 'owner_changed', 'closed' ), true ) ) {
				$out[ $entry['file'] ] = $entry + array( 'slug' => $slug );
			}
		}
		return $out;
	}
}
