<?php
/**
 * Wires the plugin together and runs the daily check.
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap.
 */
class Plugin {

	const CRON = 'afbuw_daily_check';

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * The instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers every hook.
	 *
	 * @return void
	 */
	public function boot() {
		Holds::register();
		Woo_Guard::register();
		if ( is_admin() ) {
			Admin::register();
		}
		add_action( self::CRON, array( $this, 'run_check' ) );
	}

	/**
	 * Checks every plugin and emails about anything newly flagged.
	 *
	 * @return array<string,array> Newly flagged plugins.
	 */
	public function run_check() {
		$flagged = Registry::check_all();
		if ( $flagged ) {
			Notifier::owner_flags( $flagged );
		}
		return $flagged;
	}

	/**
	 * Schedules the daily check, plus a first run shortly after activation to take the baseline.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON );
	}

	/**
	 * Removes the schedule. Stored data is kept until the plugin is deleted.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}
}
