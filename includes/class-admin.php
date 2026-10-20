<?php
/**
 * The admin screen, notices and actions.
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin interface.
 */
class Admin {

	const SLUG = 'afb-update-watch';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'network_admin_notices', array( __CLASS__, 'notices' ) );
		} else {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		}
		add_action( 'after_plugin_row', array( __CLASS__, 'plugin_row' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AFBUW_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'network_admin_plugin_action_links_' . plugin_basename( AFBUW_FILE ), array( __CLASS__, 'action_links' ) );
		foreach ( array( 'check', 'release', 'allow_woo', 'settings' ) as $action ) {
			add_action( 'admin_post_afbuw_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	/**
	 * Capability needed to see and use the screen.
	 *
	 * @return string
	 */
	public static function capability() {
		return is_multisite() ? 'manage_network_plugins' : 'update_plugins';
	}

	/**
	 * URL of the plugin's screen.
	 *
	 * @param array $args Extra query arguments.
	 * @return string
	 */
	public static function page_url( array $args = array() ) {
		$base = is_multisite() ? network_admin_url( 'plugins.php?page=' . self::SLUG ) : admin_url( 'tools.php?page=' . self::SLUG );
		return $args ? add_query_arg( $args, $base ) : $base;
	}

	/**
	 * Adds the screen to the menu.
	 *
	 * @return void
	 */
	public static function menu() {
		$title = __( 'AFB Update Watch', 'afb-update-watch' );
		if ( is_multisite() ) {
			add_submenu_page( 'plugins.php', $title, $title, self::capability(), self::SLUG, array( __CLASS__, 'render' ) );
		} else {
			add_management_page( $title, $title, self::capability(), self::SLUG, array( __CLASS__, 'render' ) );
		}
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Open', 'afb-update-watch' ) . '</a>' );
		return $links;
	}

	/**
	 * Warns on the dashboard, Plugins and Updates screens when something needs checking.
	 *
	 * @return void
	 */
	public static function notices() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$ids    = array( 'dashboard', 'plugins', 'update-core', 'dashboard-network', 'plugins-network', 'update-core-network' );
		if ( ! $screen || ! in_array( $screen->id, $ids, true ) ) {
			return;
		}
		$flagged = Registry::flagged_files();
		if ( $flagged ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
				esc_html__( 'AFB Update Watch:', 'afb-update-watch' ),
				esc_html(
					sprintf(
						/* translators: %d: number of plugins. */
						_n( '%d plugin has changed owner or been closed on WordPress.org.', '%d plugins have changed owner or been closed on WordPress.org.', count( $flagged ), 'afb-update-watch' ),
						count( $flagged )
					)
				),
				esc_url( self::page_url() ),
				esc_html__( 'Review now', 'afb-update-watch' )
			);
		}
		$woo = Store::woo();
		if ( Woo_Guard::enabled() && $woo['version'] && $woo['allowed'] !== $woo['version'] ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
				esc_html__( 'AFB Update Watch:', 'afb-update-watch' ),
				esc_html(
					sprintf(
						/* translators: 1: WooCommerce version, 2: number of extensions. */
						_n( 'WooCommerce %1$s is on hold because %2$d active extension is not tested with it.', 'WooCommerce %1$s is on hold because %2$d active extensions are not tested with it.', count( $woo['untested'] ), 'afb-update-watch' ),
						$woo['version'],
						count( $woo['untested'] )
					)
				),
				esc_url( self::page_url() ),
				esc_html__( 'See which', 'afb-update-watch' )
			);
		}
	}

	/**
	 * Adds a warning row under a flagged plugin on the Plugins screen.
	 *
	 * @param string $file Plugin file.
	 * @return void
	 */
	public static function plugin_row( $file ) {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$flagged = Registry::flagged_files();
		if ( ! isset( $flagged[ $file ] ) ) {
			return;
		}
		global $wp_list_table;
		$cols = ( $wp_list_table && method_exists( $wp_list_table, 'get_column_count' ) ) ? (int) $wp_list_table->get_column_count() : 4;
		$hold = Holds::enabled() ? ' ' . __( 'Updates are on hold until you review it.', 'afb-update-watch' ) : '';
		printf(
			'<tr class="plugin-update-tr active"><td colspan="%1$d" class="plugin-update colspanchange"><div class="notice inline notice-error notice-alt"><p>%2$s %3$s <a href="%4$s">%5$s</a></p></div></td></tr>',
			(int) $cols,
			esc_html( Notifier::describe( $flagged[ $file ]['slug'], $flagged[ $file ] ) ),
			esc_html( $hold ),
			esc_url( self::page_url() ),
			esc_html__( 'Review', 'afb-update-watch' )
		);
	}

	/**
	 * A form that posts one action, with its nonce.
	 *
	 * @param string $action Action name without prefix.
	 * @param string $label  Button label.
	 * @param array  $fields Hidden fields.
	 * @param string $css    Button class.
	 * @return void
	 */
	private static function button( $action, $label, array $fields = array(), $css = 'button' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		echo '<input type="hidden" name="action" value="' . esc_attr( 'afbuw_' . $action ) . '">';
		wp_nonce_field( 'afbuw_' . $action );
		foreach ( $fields as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		echo '<button type="submit" class="' . esc_attr( $css ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Renders the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'afb-update-watch' ) );
		}
		$state    = Store::state();
		$settings = Store::settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only, set by our own redirect.
		$done = isset( $_GET['afbuw'] ) ? sanitize_key( wp_unslash( $_GET['afbuw'] ) ) : '';
		$msgs = array(
			'checked'  => __( 'Check complete.', 'afb-update-watch' ),
			'released' => __( 'Hold released. That plugin can update again and its current owner is the new baseline.', 'afb-update-watch' ),
			'allowed'  => __( 'That WooCommerce version can now update.', 'afb-update-watch' ),
			'saved'    => __( 'Settings saved.', 'afb-update-watch' ),
		);
		echo '<div class="wrap"><h1>' . esc_html__( 'AFB Update Watch', 'afb-update-watch' ) . '</h1>';
		if ( isset( $msgs[ $done ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msgs[ $done ] ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Plugins from WordPress.org are checked once a day. If one changes owner or is closed, you are told and its updates are held until you have reviewed it. Plugins bought by a new owner have been used to push malicious updates to every site running them.', 'afb-update-watch' ) . '</p>';
		echo '<p>' . esc_html(
			$state['last_run']
				/* translators: %s: how long ago. */
				? sprintf( __( 'Last checked %s ago.', 'afb-update-watch' ), human_time_diff( $state['last_run'] ) )
				: __( 'Not checked yet.', 'afb-update-watch' )
		) . ' ';
		self::button( 'check', __( 'Check now', 'afb-update-watch' ), array(), 'button button-primary' );
		echo '</p>';

		self::render_woo();
		self::render_table( $state['plugins'] );
		self::render_settings( $settings );
		echo '</div>';
	}

	/**
	 * The WooCommerce section.
	 *
	 * @return void
	 */
	private static function render_woo() {
		$installed = Woo_Guard::installed_version();
		if ( '' === $installed ) {
			return;
		}
		echo '<h2>' . esc_html__( 'WooCommerce updates', 'afb-update-watch' ) . '</h2>';
		if ( ! Woo_Guard::enabled() ) {
			echo '<p>' . esc_html__( 'The WooCommerce guard is switched off.', 'afb-update-watch' ) . '</p>';
			return;
		}
		$offered  = Woo_Guard::offered_version();
		$untested = $offered ? Woo_Guard::untested( $offered ) : array();
		$record   = Store::woo();
		if ( ! $offered ) {
			/* translators: %s: WooCommerce version. */
			echo '<p>' . esc_html( sprintf( __( 'WooCommerce %s is up to date.', 'afb-update-watch' ), $installed ) ) . '</p>';
			return;
		}
		if ( ! $untested ) {
			/* translators: 1: offered version, 2: installed version. */
			echo '<p>' . esc_html( sprintf( __( 'WooCommerce %1$s is available (you have %2$s). Every active extension is marked as tested with it, so it can update.', 'afb-update-watch' ), $offered, $installed ) ) . '</p>';
			return;
		}
		if ( $record['allowed'] === $offered ) {
			/* translators: %s: WooCommerce version. */
			echo '<p>' . esc_html( sprintf( __( 'You chose to let WooCommerce %s update even though some extensions are untested.', 'afb-update-watch' ), $offered ) ) . '</p>';
			return;
		}
		/* translators: 1: offered version, 2: installed version. */
		echo '<p>' . esc_html( sprintf( __( 'WooCommerce %1$s will not auto-update (you have %2$s) because these active extensions are not marked as tested with it:', 'afb-update-watch' ), $offered, $installed ) ) . '</p><ul class="ul-disc">';
		foreach ( $untested as $ext ) {
			echo '<li>' . esc_html( $ext['name'] ) . ': ' . esc_html(
				'' === $ext['tested']
					? __( 'no tested version declared', 'afb-update-watch' )
					/* translators: %s: version number. */
					: sprintf( __( 'tested up to %s', 'afb-update-watch' ), $ext['tested'] )
			) . '</li>';
		}
		echo '</ul><p>' . esc_html__( 'Update those extensions first if updates are available, or test on a staging copy. When you are happy:', 'afb-update-watch' ) . ' ';
		/* translators: %s: WooCommerce version. */
		self::button( 'allow_woo', sprintf( __( 'Let WooCommerce %s update', 'afb-update-watch' ), $offered ), array( 'version' => $offered ) );
		echo '</p>';
	}

	/**
	 * The plugin table.
	 *
	 * @param array $plugins Watch entries keyed by slug.
	 * @return void
	 */
	private static function render_table( array $plugins ) {
		echo '<h2>' . esc_html__( 'WordPress.org plugins', 'afb-update-watch' ) . '</h2>';
		if ( ! $plugins ) {
			echo '<p>' . esc_html__( 'Nothing checked yet. Run a check to take a baseline of every plugin\'s owner.', 'afb-update-watch' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Plugin', 'afb-update-watch' ) . '</th><th>' . esc_html__( 'Status', 'afb-update-watch' ) . '</th><th>' . esc_html__( 'Owner on WordPress.org', 'afb-update-watch' ) . '</th><th>' . esc_html__( 'Action', 'afb-update-watch' ) . '</th></tr></thead><tbody>';
		uasort(
			$plugins,
			static function ( $a, $b ) {
				$rank = array(
					'owner_changed' => 0,
					'closed'        => 1,
					'ok'            => 2,
				);
				$ra   = isset( $rank[ $a['status'] ] ) ? $rank[ $a['status'] ] : 3;
				$rb   = isset( $rank[ $b['status'] ] ) ? $rank[ $b['status'] ] : 3;
				return $ra <=> $rb;
			}
		);
		foreach ( $plugins as $slug => $entry ) {
			$owner  = isset( $entry['owner'] ) ? $entry['owner'] : ( isset( $entry['baseline'] ) ? $entry['baseline'] : array() );
			$author = ! empty( $owner['author'] ) ? basename( $owner['author'] ) : '';
			echo '<tr><td><strong>' . esc_html( isset( $entry['name'] ) ? $entry['name'] : $slug ) . '</strong><br><code>' . esc_html( $slug ) . '</code></td><td>';
			if ( 'owner_changed' === $entry['status'] || 'closed' === $entry['status'] ) {
				echo '<span style="color:#b32d2e;font-weight:600">' . esc_html( 'closed' === $entry['status'] ? __( 'Closed', 'afb-update-watch' ) : __( 'Owner changed', 'afb-update-watch' ) ) . '</span><br>' . esc_html( Notifier::describe( $slug, $entry ) );
			} elseif ( ! empty( $entry['last_error'] ) ) {
				echo esc_html__( 'Could not check', 'afb-update-watch' ) . '<br><small>' . esc_html( $entry['last_error'] ) . '</small>';
			} else {
				echo esc_html__( 'OK', 'afb-update-watch' );
			}
			echo '</td><td>' . esc_html( $author ? $author : '?' );
			if ( ! empty( $owner['committers'] ) ) {
				$shown = array_slice( $owner['committers'], 0, 6 );
				$more  = count( $owner['committers'] ) - count( $shown );
				$list  = implode( ', ', $shown ) . ( $more > 0
					/* translators: %d: number of further committers. */
					? ' ' . sprintf( _n( 'and %d more', 'and %d more', $more, 'afb-update-watch' ), $more )
					: '' );
				echo '<br><small>' . esc_html__( 'Committers:', 'afb-update-watch' ) . ' ' . esc_html( $list ) . '</small>';
			}
			echo '</td><td>';
			if ( 'owner_changed' === $entry['status'] || 'closed' === $entry['status'] ) {
				self::button( 'release', __( 'I have checked it, release the hold', 'afb-update-watch' ), array( 'slug' => $slug ) );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * The settings form.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function render_settings( array $settings ) {
		echo '<h2>' . esc_html__( 'Settings', 'afb-update-watch' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="afbuw_settings">';
		wp_nonce_field( 'afbuw_settings' );
		echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="afbuw-email">' . esc_html__( 'Send alerts to', 'afb-update-watch' ) . '</label></th><td><input type="email" class="regular-text" id="afbuw-email" name="afbuw[email]" value="' . esc_attr( $settings['email'] ) . '" placeholder="' . esc_attr( Store::alert_email() ) . '"><p class="description">' . esc_html__( 'Leave empty to use the admin email address.', 'afb-update-watch' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Hold updates', 'afb-update-watch' ) . '</th><td><label><input type="checkbox" name="afbuw[hold_on_change]" value="1" ' . checked( $settings['hold_on_change'], true, false ) . '> ' . esc_html__( 'Hold updates for a plugin that has changed owner or been closed, until I release it', 'afb-update-watch' ) . '</label></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'WooCommerce guard', 'afb-update-watch' ) . '</th><td><label><input type="checkbox" name="afbuw[woo_guard]" value="1" ' . checked( $settings['woo_guard'], true, false ) . '> ' . esc_html__( 'Do not auto-update WooCommerce to a new major or minor version while active extensions are not tested with it', 'afb-update-watch' ) . '</label></td></tr></table>';
		submit_button( __( 'Save settings', 'afb-update-watch' ) );
		echo '</form>';
	}

	/**
	 * Stops anyone without the capability. Every action also checks its own nonce.
	 *
	 * @return void
	 */
	private static function require_capability() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'afb-update-watch' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Runs a check now.
	 *
	 * @return void
	 */
	public static function handle_check() {
		self::require_capability();
		check_admin_referer( 'afbuw_check' );
		Plugin::instance()->run_check();
		wp_safe_redirect( self::page_url( array( 'afbuw' => 'checked' ) ) );
		exit;
	}

	/**
	 * Releases a hold.
	 *
	 * @return void
	 */
	public static function handle_release() {
		self::require_capability();
		check_admin_referer( 'afbuw_release' );
		$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		Registry::release( $slug );
		wp_safe_redirect( self::page_url( array( 'afbuw' => 'released' ) ) );
		exit;
	}

	/**
	 * Lets one WooCommerce version through.
	 *
	 * @return void
	 */
	public static function handle_allow_woo() {
		self::require_capability();
		check_admin_referer( 'afbuw_allow_woo' );
		$version = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';
		if ( preg_match( '/^\d+(\.\d+)*$/', $version ) ) {
			Woo_Guard::allow( $version );
		}
		wp_safe_redirect( self::page_url( array( 'afbuw' => 'allowed' ) ) );
		exit;
	}

	/**
	 * Saves settings.
	 *
	 * @return void
	 */
	public static function handle_settings() {
		self::require_capability();
		check_admin_referer( 'afbuw_settings' );
		$input = isset( $_POST['afbuw'] ) && is_array( $_POST['afbuw'] ) ? map_deep( wp_unslash( $_POST['afbuw'] ), 'sanitize_text_field' ) : array();
		Store::save_settings( $input );
		wp_safe_redirect( self::page_url( array( 'afbuw' => 'saved' ) ) );
		exit;
	}
}
