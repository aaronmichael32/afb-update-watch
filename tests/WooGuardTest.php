<?php
/**
 * WooCommerce untested-extension guard.
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch\Tests;

use AFB\UpdateWatch\Store;
use AFB\UpdateWatch\Woo_Guard;

/**
 * Woo guard tests.
 */
class WooGuardTest extends TestCase {

	/**
	 * Extension file.
	 *
	 * @var string
	 */
	private $ext;

	public function set_up() {
		parent::set_up();
		$this->make_plugin( 'woocommerce', array( 'Plugin Name' => 'WooCommerce', 'Version' => '10.2.1' ) );
		$this->ext = $this->make_plugin(
			'scg-ext',
			array(
				'Plugin Name'          => 'Shipping Thing',
				'Version'              => '1.0',
				'WC requires at least' => '8.0',
				'WC tested up to'      => '10.2',
			)
		);
		update_option( 'active_plugins', array( 'woocommerce/woocommerce.php', $this->ext ) );
	}

	private function offer( $version ) {
		return (object) array(
			'plugin'      => 'woocommerce/woocommerce.php',
			'new_version' => $version,
		);
	}

	public function test_minor_release_past_tested_version_is_held_and_emailed_once() {
		$this->assertFalse( apply_filters( 'auto_update_plugin', true, $this->offer( '10.3.0' ) ) );
		$record = Store::woo();
		$this->assertSame( '10.3.0', $record['version'] );
		$this->assertSame( '10.2', $record['untested'][ $this->ext ]['tested'] );
		$this->assertFalse( apply_filters( 'auto_update_plugin', true, $this->offer( '10.3.0' ) ) );
		$this->assertCount( 1, tests_retrieve_phpmailer_instance()->mock_sent );
	}

	public function test_patch_release_goes_through() {
		$this->assertTrue( apply_filters( 'auto_update_plugin', true, $this->offer( '10.2.5' ) ) );
	}

	public function test_release_the_extension_is_tested_with_goes_through() {
		$this->make_plugin(
			'scg-ext',
			array(
				'Plugin Name'          => 'Shipping Thing',
				'Version'              => '1.1',
				'WC requires at least' => '8.0',
				'WC tested up to'      => '10.3.0',
			)
		);
		$this->assertTrue( apply_filters( 'auto_update_plugin', true, $this->offer( '10.3.0' ) ) );
	}

	public function test_extension_without_a_tested_version_counts_as_untested() {
		$file = $this->make_plugin(
			'scg-quiet',
			array(
				'Plugin Name'      => 'Quiet Extension',
				'Version'          => '1.0',
				'Requires Plugins' => 'woocommerce',
			)
		);
		update_option( 'active_plugins', array( 'woocommerce/woocommerce.php', $file ) );
		$untested = Woo_Guard::untested( '10.3.0' );
		$this->assertArrayHasKey( $file, $untested );
		$this->assertSame( '', $untested[ $file ]['tested'] );
	}

	public function test_inactive_extensions_are_ignored() {
		update_option( 'active_plugins', array( 'woocommerce/woocommerce.php' ) );
		$this->assertTrue( apply_filters( 'auto_update_plugin', true, $this->offer( '10.3.0' ) ) );
	}

	public function test_a_person_can_let_one_version_through() {
		apply_filters( 'auto_update_plugin', true, $this->offer( '10.3.0' ) );
		Woo_Guard::allow( '10.3.0' );
		$this->assertTrue( apply_filters( 'auto_update_plugin', true, $this->offer( '10.3.0' ) ) );
		$this->assertFalse( apply_filters( 'auto_update_plugin', true, $this->offer( '10.4.0' ) ), 'Allowing one version does not allow the next.' );
	}

	public function test_guard_can_be_switched_off() {
		Store::save_settings( array( 'hold_on_change' => '1', 'woo_guard' => '' ) );
		$this->assertTrue( apply_filters( 'auto_update_plugin', true, $this->offer( '11.0.0' ) ) );
	}

	public function test_other_plugins_pass_straight_through() {
		$item = (object) array(
			'plugin'      => 'akismet/akismet.php',
			'new_version' => '9.0',
		);
		$this->assertNull( apply_filters( 'auto_update_plugin', null, $item ) );
	}

	public function test_major_minor() {
		$this->assertSame( '10.3', Woo_Guard::major_minor( '10.3.2' ) );
		$this->assertSame( '9.0', Woo_Guard::major_minor( '9' ) );
		$this->assertSame( '', Woo_Guard::major_minor( 'trunk' ) );
		$this->assertTrue( Woo_Guard::is_minor_or_major_change( '10.2.1', '10.3.0' ) );
		$this->assertTrue( Woo_Guard::is_minor_or_major_change( '10.2.1', '11.0.0' ) );
		$this->assertFalse( Woo_Guard::is_minor_or_major_change( '10.2.1', '10.2.9' ) );
		$this->assertFalse( Woo_Guard::is_minor_or_major_change( '10.2.1', '10.1.0' ) );
	}
}
