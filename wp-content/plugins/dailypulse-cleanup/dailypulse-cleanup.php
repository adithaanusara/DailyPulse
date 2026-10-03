<?php
/**
 * Plugin Name:       DailyPulse Auto-Cleanup
 * Plugin URI:        https://github.com/adithaanusara/DailyPulse
 * Description:       Keeps a news site lean. Once a day, published stories older than a set number of days (3 by default) are moved to the Trash or deleted permanently.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            DailyPulse
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dailypulse-cleanup
 *
 * @package DailyPulse_Cleanup
 */

defined( 'ABSPATH' ) || exit;

define( 'DAILYPULSE_CLEANUP_VERSION', '1.0.0' );
define( 'DAILYPULSE_CLEANUP_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-dailypulse-cleanup.php';

register_activation_hook( __FILE__, array( 'DailyPulse_Cleanup', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DailyPulse_Cleanup', 'deactivate' ) );

DailyPulse_Cleanup::init();

if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-dailypulse-cleanup-admin.php';
	DailyPulse_Cleanup_Admin::init();
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/class-dailypulse-cleanup-cli.php';
	WP_CLI::add_command( 'dailypulse cleanup', 'DailyPulse_Cleanup_CLI' );
}
