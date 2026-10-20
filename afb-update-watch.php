<?php
/**
 * Plugin Name:       AFB Update Watch
 * Description:       Warns you when a plugin from WordPress.org changes owner or is closed, holds its updates until you have checked it, and stops WooCommerce auto-updating past extensions that are not tested with the new version.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            AFB Digital Solutions
 * Author URI:        https://afbdigitalsolutions.co.uk/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       afb-update-watch
 * Network:           true
 *
 * @package UpdateWatch
 */

namespace AFB\UpdateWatch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AFBUW_VERSION', '0.1.0' );
define( 'AFBUW_FILE', __FILE__ );
define( 'AFBUW_DIR', plugin_dir_path( __FILE__ ) );

require_once AFBUW_DIR . 'includes/class-store.php';
require_once AFBUW_DIR . 'includes/class-registry.php';
require_once AFBUW_DIR . 'includes/class-holds.php';
require_once AFBUW_DIR . 'includes/class-woo-guard.php';
require_once AFBUW_DIR . 'includes/class-notifier.php';
require_once AFBUW_DIR . 'includes/class-admin.php';
require_once AFBUW_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

Plugin::instance()->boot();
