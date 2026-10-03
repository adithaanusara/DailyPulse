<?php
/**
 * DailyPulse theme bootstrap.
 *
 * @package DailyPulse
 */

defined( 'ABSPATH' ) || exit;

define( 'DAILYPULSE_VERSION', '1.0.0' );
define( 'DAILYPULSE_DIR', get_template_directory() );
define( 'DAILYPULSE_URI', get_template_directory_uri() );

require DAILYPULSE_DIR . '/inc/compat.php';
require DAILYPULSE_DIR . '/inc/setup.php';
require DAILYPULSE_DIR . '/inc/assets.php';
require DAILYPULSE_DIR . '/inc/icons.php';
require DAILYPULSE_DIR . '/inc/template-tags.php';
require DAILYPULSE_DIR . '/inc/queries.php';
require DAILYPULSE_DIR . '/inc/rest.php';
