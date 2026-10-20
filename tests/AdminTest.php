<?php
/**
 * Settings, permissions and uninstall.
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch\Tests;

use AFB\UpdateWatch\Admin;
use AFB\UpdateWatch\Store;

/**
 * Admin and lifecycle tests.
 */
class AdminTest extends TestCase {

	public function test_settings_default_to_safe_values() {
		$s = Store::settings();
		$this->assertTrue( $s['hold_on_change'] );
		$this->assertTrue( $s['woo_guard'] );
		$this->assertSame( get_option( 'admin_email' ), Store::alert_email() );
	}

	public function test_bad_email_is_not_saved() {
		Store::save_settings( array( 'email' => 'not an email', 'hold_on_change' => '1', 'woo_guard' => '1' ) );
		$this->assertSame( '', Store::settings()['email'] );
		Store::save_settings( array( 'email' => 'alerts@example.com' ) );
		$this->assertSame( 'alerts@example.com', Store::alert_email() );
	}

	public function test_actions_refuse_users_without_permission() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->expectException( \WPDieException::class );
		Admin::handle_release();
	}

	public function test_actions_refuse_a_missing_nonce() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $admin );
		}
		wp_set_current_user( $admin );
		$this->expectException( \WPDieException::class );
		Admin::handle_settings();
	}

	public function test_screen_renders_for_an_admin() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $admin );
		}
		wp_set_current_user( $admin );
		ob_start();
		Admin::render();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'AFB Update Watch', $html );
		$this->assertStringContainsString( 'afbuw_check', $html );
		$this->assertStringContainsString( '_wpnonce', $html );
	}

	public function test_uninstall_removes_everything() {
		Store::save_settings( array( 'email' => 'a@example.com' ) );
		Store::save_state( array( 'plugins' => array( 'x' => array() ), 'last_run' => 1 ) );
		Store::save_woo( array( 'version' => '1.0' ) );
		wp_schedule_event( time(), 'daily', 'afbuw_daily_check' );
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'afb-update-watch/afb-update-watch.php' );
		}
		include dirname( __DIR__ ) . '/uninstall.php';
		$this->assertFalse( get_site_option( 'afbuw_settings' ) );
		$this->assertFalse( get_site_option( 'afbuw_state' ) );
		$this->assertFalse( get_site_option( 'afbuw_woo' ) );
		$this->assertFalse( wp_next_scheduled( 'afbuw_daily_check' ) );
	}
}
