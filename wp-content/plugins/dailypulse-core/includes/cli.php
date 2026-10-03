<?php
/**
 * WP-CLI commands: `wp dailypulse setup` and `wp dailypulse demo …`.
 *
 * @package DailyPulse_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prepares a fresh site for DailyPulse: categories, menus, pages and permalinks.
 *
 * Only adds what is missing, so it is safe to run more than once.
 *
 * ## OPTIONS
 *
 * [--demo]
 * : Also import the demo content.
 *
 * ## EXAMPLES
 *
 *     wp dailypulse setup --demo
 *
 * @param array $args       Positional arguments.
 * @param array $assoc_args Flags.
 */
function dailypulse_core_cli_setup( $args, $assoc_args ) {
	$report = dailypulse_core_run_setup();

	foreach ( $report as $line ) {
		WP_CLI::log( '  ' . $line );
	}

	WP_CLI::success( $report ? 'Setup finished.' : 'Everything was already set up.' );

	if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'demo', false ) ) {
		WP_CLI::runcommand( 'dailypulse demo import' );
	}
}

/**
 * Manages the DailyPulse demo content.
 */
class DailyPulse_Demo_CLI {

	/**
	 * Imports the sample stories, or refreshes their dates if they already exist.
	 *
	 * ## OPTIONS
	 *
	 * [--skip-images]
	 * : Do not download photos (stories show the built-in placeholder instead).
	 *
	 * ## EXAMPLES
	 *
	 *     wp dailypulse demo import
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 */
	public function import( $args, $assoc_args ) {
		$images = ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-images', false );

		if ( $images ) {
			WP_CLI::log( 'Downloading photos from picsum.photos — this takes about a minute…' );
		}

		$result = dailypulse_core_demo_import( array( 'images' => $images ) );

		if ( $result['image_errors'] ) {
			WP_CLI::warning( sprintf( '%d photo(s) could not be downloaded. Run the command again to retry.', $result['image_errors'] ) );
		}

		WP_CLI::success( sprintf( 'Demo content ready: %d created, %d refreshed, %d photos downloaded.', $result['created'], $result['refreshed'], $result['images'] ) );
	}

	/**
	 * Permanently deletes the sample stories, their photos and the sample alert.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dailypulse demo remove
	 */
	public function remove() {
		WP_CLI::success( sprintf( 'Removed %d demo item(s).', dailypulse_core_demo_remove() ) );
	}
}

WP_CLI::add_command( 'dailypulse setup', 'dailypulse_core_cli_setup' );
WP_CLI::add_command( 'dailypulse demo', 'DailyPulse_Demo_CLI' );
