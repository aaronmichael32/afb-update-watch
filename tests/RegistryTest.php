<?php
/**
 * Ownership and closure watch.
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch\Tests;

use AFB\UpdateWatch\Plugin;
use AFB\UpdateWatch\Registry;
use AFB\UpdateWatch\Store;

/**
 * Registry tests.
 */
class RegistryTest extends TestCase {

	public function test_owner_is_normalised() {
		$owner = Registry::owner(
			array(
				'author_profile' => 'https://profiles.wordpress.org/Matt/',
				'contributors'   => array(
					'Zed'  => array(),
					'alba' => array(),
				),
			)
		);
		$this->assertSame( 'https://profiles.wordpress.org/matt', $owner['author'] );
		$this->assertSame( array( 'alba', 'zed' ), $owner['committers'] );
	}

	public function test_diff_reports_author_and_committer_changes() {
		$before = array( 'author' => 'a', 'committers' => array( 'a', 'b' ) );
		$after  = array( 'author' => 'c', 'committers' => array( 'b', 'c' ) );
		$diff   = Registry::diff( $before, $after );
		$this->assertSame( 'a', $diff['author_from'] );
		$this->assertSame( 'c', $diff['author_to'] );
		$this->assertSame( array( 'c' ), $diff['added'] );
		$this->assertSame( array( 'a' ), $diff['removed'] );
		$this->assertSame( array(), Registry::diff( $before, $before ) );
	}

	public function test_first_check_takes_a_baseline_and_flags_nothing() {
		$file = $this->make_plugin( 'scg-alpha', array( 'Plugin Name' => 'Alpha', 'Version' => '1.0' ) );
		$this->set_updates( array( $file => array( 'scg-alpha', null ) ) );
		$this->api['scg-alpha'] = $this->live( 'alice', array( 'alice' ) );

		$this->assertSame( array(), Plugin::instance()->run_check() );
		$entry = Store::state()['plugins']['scg-alpha'];
		$this->assertSame( 'ok', $entry['status'] );
		$this->assertSame( array( 'alice' ), $entry['baseline']['committers'] );
		$this->assertCount( 0, tests_retrieve_phpmailer_instance()->mock_sent );
	}

	public function test_owner_change_is_flagged_once_and_emailed() {
		$file = $this->make_plugin( 'scg-alpha', array( 'Plugin Name' => 'Alpha', 'Version' => '1.0' ) );
		$this->set_updates( array( $file => array( 'scg-alpha', null ) ) );
		$this->api['scg-alpha'] = $this->live( 'alice', array( 'alice' ) );
		Plugin::instance()->run_check();

		$this->api['scg-alpha'] = $this->live( 'mallory', array( 'mallory' ) );
		$flagged = Plugin::instance()->run_check();
		$this->assertArrayHasKey( 'scg-alpha', $flagged );
		$entry = Store::state()['plugins']['scg-alpha'];
		$this->assertSame( 'owner_changed', $entry['status'] );
		$this->assertSame( array( 'mallory' ), $entry['changes']['added'] );
		$sent = tests_retrieve_phpmailer_instance()->mock_sent;
		$this->assertCount( 1, $sent );
		$this->assertStringContainsString( 'changed hands', $sent[0]['body'] );

		$this->assertSame( array(), Plugin::instance()->run_check(), 'A plugin already flagged is not flagged or emailed again.' );
		$this->assertCount( 1, tests_retrieve_phpmailer_instance()->mock_sent );
	}

	public function test_new_committer_alone_is_flagged() {
		$file = $this->make_plugin( 'scg-alpha', array( 'Plugin Name' => 'Alpha', 'Version' => '1.0' ) );
		$this->set_updates( array( $file => array( 'scg-alpha', null ) ) );
		$this->api['scg-alpha'] = $this->live( 'alice', array( 'alice' ) );
		Registry::check_all();
		$this->api['scg-alpha'] = $this->live( 'alice', array( 'alice', 'buyer' ) );
		$this->assertArrayHasKey( 'scg-alpha', Registry::check_all() );
	}

	public function test_closed_plugin_is_flagged_even_after_it_leaves_the_update_list() {
		$file = $this->make_plugin( 'scg-alpha', array( 'Plugin Name' => 'Alpha', 'Version' => '1.0' ) );
		$this->set_updates( array( $file => array( 'scg-alpha', null ) ) );
		$this->api['scg-alpha'] = $this->live( 'alice', array( 'alice' ) );
		Registry::check_all();

		$this->set_updates( array() );
		$this->api['scg-alpha'] = $this->closed();
		$flagged                = Registry::check_all();
		$this->assertArrayHasKey( 'scg-alpha', $flagged );
		$entry = Store::state()['plugins']['scg-alpha'];
		$this->assertSame( 'closed', $entry['status'] );
		$this->assertSame( 'Security Issue', $entry['closed']['reason'] );
	}

	public function test_api_failure_flags_nothing_and_keeps_the_baseline() {
		$file = $this->make_plugin( 'scg-alpha', array( 'Plugin Name' => 'Alpha', 'Version' => '1.0' ) );
		$this->set_updates( array( $file => array( 'scg-alpha', null ) ) );
		$this->api['scg-alpha'] = $this->live( 'alice', array( 'alice' ) );
		Registry::check_all();

		$this->api['scg-alpha'] = 'error';
		$this->assertSame( array(), Registry::check_all() );
		$entry = Store::state()['plugins']['scg-alpha'];
		$this->assertSame( 'ok', $entry['status'] );
		$this->assertSame( array( 'alice' ), $entry['baseline']['committers'] );
		$this->assertNotEmpty( $entry['last_error'] );
	}

	public function test_plugins_not_served_by_wordpress_org_are_never_looked_up() {
		$this->make_plugin( 'scg-premium', array( 'Plugin Name' => 'Premium', 'Version' => '1.0' ) );
		$this->set_updates( array() );
		Registry::check_all();
		$this->assertSame( 0, $this->calls );
		$this->assertSame( array(), Store::state()['plugins'] );
	}

	public function test_release_takes_a_new_baseline() {
		$file = $this->make_plugin( 'scg-alpha', array( 'Plugin Name' => 'Alpha', 'Version' => '1.0' ) );
		$this->set_updates( array( $file => array( 'scg-alpha', null ) ) );
		$this->api['scg-alpha'] = $this->live( 'alice', array( 'alice' ) );
		Registry::check_all();
		$this->api['scg-alpha'] = $this->live( 'bob', array( 'bob' ) );
		Registry::check_all();

		$this->assertTrue( Registry::release( 'scg-alpha' ) );
		$entry = Store::state()['plugins']['scg-alpha'];
		$this->assertSame( 'ok', $entry['status'] );
		$this->assertSame( array( 'bob' ), $entry['baseline']['committers'] );
		$this->assertSame( array(), Registry::check_all(), 'The same owner is not flagged again after release.' );
		$this->assertFalse( Registry::release( 'not-a-plugin' ) );
	}

	public function test_uninstalled_plugin_is_dropped_from_state() {
		$file = $this->make_plugin( 'scg-alpha', array( 'Plugin Name' => 'Alpha', 'Version' => '1.0' ) );
		$this->set_updates( array( $file => array( 'scg-alpha', null ) ) );
		$this->api['scg-alpha'] = $this->live( 'alice', array( 'alice' ) );
		Registry::check_all();
		$this->tear_down_plugin( 'scg-alpha' );
		$this->set_updates( array() );
		Registry::check_all();
		$this->assertArrayNotHasKey( 'scg-alpha', Store::state()['plugins'] );
	}

	/**
	 * Deletes one fake plugin mid-test.
	 *
	 * @param string $folder Folder name.
	 * @return void
	 */
	private function tear_down_plugin( $folder ) {
		unlink( WP_PLUGIN_DIR . "/{$folder}/{$folder}.php" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		wp_clean_plugins_cache( false );
	}
}
