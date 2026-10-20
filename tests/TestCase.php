<?php
/**
 * Shared helpers: fake plugins on disk, a fake update transient and a fake WordPress.org API.
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch\Tests;

use WP_UnitTestCase;

/**
 * Base test case.
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Fake API answers keyed by slug: array{code:int,body:array}|'error'.
	 *
	 * @var array
	 */
	protected $api = array();

	/**
	 * Plugin folders created for a test.
	 *
	 * @var string[]
	 */
	protected $made = array();

	/**
	 * Number of API calls made.
	 *
	 * @var int
	 */
	protected $calls = 0;

	public function set_up() {
		parent::set_up();
		delete_site_option( 'afbuw_settings' );
		delete_site_option( 'afbuw_state' );
		delete_site_option( 'afbuw_woo' );
		reset_phpmailer_instance();
		add_filter( 'pre_http_request', array( $this, 'fake_http' ), 10, 3 );
		add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
	}

	/**
	 * The test site runs on localhost, so WordPress's default From address is invalid there.
	 *
	 * @return string
	 */
	public function mail_from() {
		return 'wordpress@example.com';
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_http' ), 10 );
		foreach ( $this->made as $dir ) {
			$this->remove_dir( $dir );
		}
		$this->made = array();
		wp_clean_plugins_cache( false );
		delete_site_transient( 'update_plugins' );
		parent::tear_down();
	}

	/**
	 * Answers WordPress.org plugin API calls from $this->api.
	 *
	 * @param mixed  $pre  Short-circuit value.
	 * @param array  $args Request arguments.
	 * @param string $url  Request URL.
	 * @return mixed
	 */
	public function fake_http( $pre, $args, $url ) {
		if ( false === strpos( $url, 'api.wordpress.org/plugins/info/1.2/' ) ) {
			return $pre;
		}
		++$this->calls;
		$query = array();
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$slug   = isset( $query['request']['slug'] ) ? $query['request']['slug'] : '';
		$answer = isset( $this->api[ $slug ] ) ? $this->api[ $slug ] : array(
			'code' => 404,
			'body' => array( 'error' => 'Plugin not found.' ),
		);
		if ( 'error' === $answer ) {
			return new \WP_Error( 'http_request_failed', 'cURL error 28: timed out' );
		}
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $answer['body'] ),
			'response' => array(
				'code'    => $answer['code'],
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * An API answer for a live plugin.
	 *
	 * @param string   $author     Author profile username.
	 * @param string[] $committers Committer usernames.
	 * @return array
	 */
	protected function live( $author, array $committers ) {
		$contributors = array();
		foreach ( $committers as $c ) {
			$contributors[ $c ] = array(
				'profile'      => 'https://profiles.wordpress.org/' . $c . '/',
				'display_name' => ucfirst( $c ),
			);
		}
		return array(
			'code' => 200,
			'body' => array(
				'name'           => 'Fake',
				'slug'           => 'fake',
				'author_profile' => 'https://profiles.wordpress.org/' . $author . '/',
				'contributors'   => $contributors,
			),
		);
	}

	/**
	 * An API answer for a closed plugin.
	 *
	 * @return array
	 */
	protected function closed() {
		return array(
			'code' => 404,
			'body' => array(
				'error'       => 'closed',
				'name'        => 'Fake',
				'closed'      => true,
				'closed_date' => '2026-04-14',
				'reason'      => 'security-issue',
				'reason_text' => 'Security Issue',
			),
		);
	}

	/**
	 * Writes a plugin file into the plugins folder.
	 *
	 * @param string $folder  Folder name.
	 * @param array  $headers Header name to value.
	 * @return string Plugin file relative to the plugins folder.
	 */
	protected function make_plugin( $folder, array $headers ) {
		$dir = WP_PLUGIN_DIR . '/' . $folder;
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
			$this->made[] = $dir;
		}
		$lines = "<?php\n/**\n";
		foreach ( $headers as $k => $v ) {
			$lines .= " * {$k}: {$v}\n";
		}
		$lines .= " */\n";
		file_put_contents( $dir . '/' . $folder . '.php', $lines ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		wp_clean_plugins_cache( false );
		return $folder . '/' . $folder . '.php';
	}

	/**
	 * Sets the update_plugins transient. $plugins is file => [slug, new_version|null].
	 *
	 * @param array $plugins Plugins to list.
	 * @return void
	 */
	protected function set_updates( array $plugins ) {
		$t            = new \stdClass();
		$t->last_checked = time();
		$t->response  = array();
		$t->no_update = array();
		$t->checked   = array();
		foreach ( $plugins as $file => $info ) {
			$item         = (object) array(
				'id'     => 'w.org/plugins/' . $info[0],
				'slug'   => $info[0],
				'plugin' => $file,
			);
			$t->checked[ $file ] = '1.0';
			if ( ! empty( $info[1] ) ) {
				$item->new_version     = $info[1];
				$t->response[ $file ] = $item;
			} else {
				$t->no_update[ $file ] = $item;
			}
		}
		set_site_transient( 'update_plugins', $t );
	}

	/**
	 * Removes a folder and its files.
	 *
	 * @param string $dir Folder.
	 * @return void
	 */
	private function remove_dir( $dir ) {
		foreach ( (array) glob( $dir . '/*' ) as $f ) {
			is_dir( $f ) ? $this->remove_dir( $f ) : unlink( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
