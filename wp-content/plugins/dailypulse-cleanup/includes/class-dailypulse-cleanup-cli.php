<?php
/**
 * WP-CLI commands.
 *
 * @package DailyPulse_Cleanup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inspect and run the DailyPulse auto-cleanup.
 */
class DailyPulse_Cleanup_CLI {

	/**
	 * Removes every post that is due for cleanup.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Only list what would be removed. Nothing is changed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dailypulse cleanup run --dry-run
	 *     wp dailypulse cleanup run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 */
	public function run( $args, $assoc_args ) {
		$settings = DailyPulse_Cleanup::settings();

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
			$total = DailyPulse_Cleanup::count_expired();
			$rows  = array();

			foreach ( DailyPulse_Cleanup::find_expired( 200 ) as $post_id ) {
				$rows[] = array(
					'ID'        => $post_id,
					'type'      => get_post_type( $post_id ),
					'published' => get_post_field( 'post_date', $post_id ),
					'title'     => get_the_title( $post_id ),
				);
			}

			if ( $rows ) {
				\WP_CLI\Utils\format_items( 'table', $rows, array( 'ID', 'type', 'published', 'title' ) );
			}

			WP_CLI::success( sprintf( 'Dry run: %d post(s) older than %d day(s) would be %s.', $total, $settings['days'], 'delete' === $settings['action'] ? 'permanently deleted' : 'moved to the Trash' ) );
			return;
		}

		$result = DailyPulse_Cleanup::run(
			array(
				'trigger'    => 'cli',
				'time_limit' => 0,
			)
		);

		if ( 'locked' === $result['status'] ) {
			WP_CLI::error( 'Another cleanup is running. Try again in a few minutes.' );
		}

		$message = sprintf( '%d post(s) %s in %ss.', $result['processed'], 'delete' === $result['action'] ? 'permanently deleted' : 'moved to the Trash', $result['duration'] );

		if ( $result['failed'] ) {
			WP_CLI::warning( sprintf( '%s %d could not be removed.', $message, $result['failed'] ) );
			return;
		}

		WP_CLI::success( $message );
	}

	/**
	 * Shows the current rule, the next scheduled run and how many posts are due.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dailypulse cleanup status
	 */
	public function status() {
		$settings = DailyPulse_Cleanup::settings();
		$next     = wp_next_scheduled( DailyPulse_Cleanup::HOOK );
		$log      = DailyPulse_Cleanup::get_log();
		$format   = 'Y-m-d H:i:s T';

		$rows = array(
			array(
				'field' => 'enabled',
				'value' => $settings['enabled'] ? 'yes' : 'no',
			),
			array(
				'field' => 'rule',
				'value' => sprintf( '%s published %s older than %d day(s)', 'delete' === $settings['action'] ? 'permanently delete' : 'trash', implode( ', ', $settings['post_types'] ), $settings['days'] ),
			),
			array(
				'field' => 'next run',
				'value' => $next ? wp_date( $format, $next ) : 'not scheduled',
			),
			array(
				'field' => 'last run',
				'value' => $log ? sprintf( '%s — %d removed (%s)', wp_date( $format, $log[0]['time'] ), $log[0]['processed'], $log[0]['trigger'] ) : 'never',
			),
			array(
				'field' => 'due now',
				'value' => DailyPulse_Cleanup::count_expired(),
			),
		);

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'field', 'value' ) );
	}
}
