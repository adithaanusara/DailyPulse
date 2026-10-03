<?php
/**
 * Plugin Name:       DailyPulse Core
 * Plugin URI:        https://github.com/adithaanusara/DailyPulse
 * Description:       The newsroom toolkit behind the DailyPulse theme: story fields (kicker, breaking, hero, credits, source links, gallery), breaking alerts, category colours, site options and demo content. Uses ACF / Secure Custom Fields when it is active and native WordPress fields when it is not.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            DailyPulse
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dailypulse-core
 *
 * @package DailyPulse_Core
 */

defined( 'ABSPATH' ) || exit;

define( 'DAILYPULSE_CORE_VERSION', '1.0.0' );
define( 'DAILYPULSE_CORE_FILE', __FILE__ );
define( 'DAILYPULSE_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'DAILYPULSE_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once DAILYPULSE_CORE_DIR . 'includes/schema.php';
require_once DAILYPULSE_CORE_DIR . 'includes/api.php';
require_once DAILYPULSE_CORE_DIR . 'includes/content.php';
require_once DAILYPULSE_CORE_DIR . 'includes/acf.php';
require_once DAILYPULSE_CORE_DIR . 'includes/seo.php';
require_once DAILYPULSE_CORE_DIR . 'includes/setup.php';

if ( is_admin() ) {
	require_once DAILYPULSE_CORE_DIR . 'includes/admin-fields.php';
	require_once DAILYPULSE_CORE_DIR . 'includes/admin-pages.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once DAILYPULSE_CORE_DIR . 'includes/cli.php';
}

register_activation_hook( __FILE__, 'dailypulse_core_activate' );

/**
 * Activation: make sure the four news categories exist.
 */
function dailypulse_core_activate() {
	dailypulse_core_install_categories();
}
