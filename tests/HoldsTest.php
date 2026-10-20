<?php
/**
 * Update holds.
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch\Tests;

use AFB\UpdateWatch\Holds;
use AFB\UpdateWatch\Registry;
use AFB\UpdateWatch\Store;

/**
 * Holds tests.
 */
class HoldsTest extends TestCase {

	/**
	 * Plugin file under test.
	 *
	 * @var string
	 */
	private $file;

	public function set_up() {
		parent::set_up();
		$this->file = $this->make_plugin( 'scg-alpha', array( 'Plugin Name' => 'Alpha', 'Version' => '1.0' ) );
		$this->set_updates( array( $this->file => array( 'scg-alpha', '2.0' ) ) );
		$this->api['scg-alpha'] = $this->live( 'alice', array( 'alice' ) );
		Registry::check_all();
		$this->api['scg-alpha'] = $this->live( 'mallory', array( 'mallory' ) );
		Registry::check_all();
	}

	private function offer() {
		return (object) array(
			'plugin'      => $this->file,
			'new_version' => '2.0',
		);
	}

	public function test_flagged_plugin_does_not_auto_update() {
		$this->assertFalse( apply_filters( 'auto_update_plugin', true, $this->offer() ) );
	}

	public function test_manual_update_of_flagged_plugin_is_refused() {
		$reply = apply_filters( 'upgrader_pre_download', false, 'https://downloads.wordpress.org/plugin/scg-alpha.2.0.zip', null, array( 'plugin' => $this->file ) );
		$this->assertWPError( $reply );
		$this->assertSame( 'afbuw_held', $reply->get_error_code() );
	}

	public function test_update_stays_listed() {
		$this->assertArrayHasKey( $this->file, get_site_transient( 'update_plugins' )->response );
	}

	public function test_other_plugins_are_untouched() {
		$other = (object) array(
			'plugin'      => 'something/else.php',
			'new_version' => '3.0',
		);
		$this->assertTrue( apply_filters( 'auto_update_plugin', true, $other ) );
	}

	public function test_holds_can_be_switched_off() {
		Store::save_settings( array( 'hold_on_change' => '' ) );
		$this->assertFalse( Holds::enabled() );
		$this->assertTrue( apply_filters( 'auto_update_plugin', true, $this->offer() ) );
		$this->assertFalse( apply_filters( 'upgrader_pre_download', false, 'x.zip', null, array( 'plugin' => $this->file ) ) );
	}

	public function test_release_lifts_the_hold() {
		Registry::release( 'scg-alpha' );
		$this->assertTrue( apply_filters( 'auto_update_plugin', true, $this->offer() ) );
		$this->assertFalse( apply_filters( 'upgrader_pre_download', false, 'x.zip', null, array( 'plugin' => $this->file ) ) );
	}

	public function test_downloads_for_other_plugins_are_untouched() {
		$this->assertFalse( apply_filters( 'upgrader_pre_download', false, 'x.zip', null, array( 'plugin' => 'other/other.php' ) ) );
		$this->assertFalse( apply_filters( 'upgrader_pre_download', false, 'x.zip', null, array() ) );
	}
}
