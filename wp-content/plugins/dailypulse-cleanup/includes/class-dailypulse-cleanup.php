<?php
/**
 * Settings, scheduling and the cleanup run itself.
 *
 * @package DailyPulse_Cleanup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Removes published posts once they are older than the configured number of days.
 */
final class DailyPulse_Cleanup {

	const OPTION       = 'dailypulse_cleanup_settings';
	const LOG_OPTION   = 'dailypulse_cleanup_log';
	const HOOK         = 'dailypulse_cleanup_daily';
	const HOOK_RESUME  = 'dailypulse_cleanup_resume';
	const LOCK         = 'dailypulse_cleanup_lock';
	const KEEP_META    = '_dailypulse_keep';
	const TRASHED_META = '_dailypulse_cleanup_trashed';

	/**
	 * Wires the plugin into WordPress.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'cron_run' ) );
		add_action( self::HOOK_RESUME, array( __CLASS__, 'cron_run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ), 20 );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'reschedule' ), 10, 0 );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'reschedule' ), 10, 0 );
		add_action( 'untrashed_post', array( __CLASS__, 'on_untrash' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_before_delete' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'       => 1,
			'days'          => 3,
			'action'        => 'trash',
			'post_types'    => array( 'post' ),
			'exclude_terms' => array(),
			'delete_media'  => 0,
			'run_at'        => '03:00',
		);
	}

	/**
	 * Current settings merged over the defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved    = get_option( self::OPTION, array() );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );

		$settings['days']          = max( 1, (int) $settings['days'] );
		$settings['post_types']    = array_values( array_filter( (array) $settings['post_types'] ) );
		$settings['exclude_terms'] = array_values( array_filter( array_map( 'absint', (array) $settings['exclude_terms'] ) ) );

		/**
		 * Filters the effective cleanup settings.
		 *
		 * @param array $settings Settings array.
		 */
		return apply_filters( 'dailypulse_cleanup_settings', $settings );
	}

	/**
	 * Sanitizes settings submitted from the settings screen.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$types    = isset( $input['post_types'] ) ? array_map( 'sanitize_key', (array) $input['post_types'] ) : array();
		$terms    = isset( $input['exclude_terms'] ) ? array_map( 'absint', (array) $input['exclude_terms'] ) : array();
		$run_at   = isset( $input['run_at'] ) ? (string) $input['run_at'] : '';

		return array(
			'enabled'       => empty( $input['enabled'] ) ? 0 : 1,
			'days'          => min( 3650, max( 1, isset( $input['days'] ) ? absint( $input['days'] ) : $defaults['days'] ) ),
			'action'        => ( isset( $input['action'] ) && 'delete' === $input['action'] ) ? 'delete' : 'trash',
			'post_types'    => array_values( array_intersect( array_keys( self::eligible_post_types() ), $types ) ),
			'exclude_terms' => array_values( array_filter( $terms ) ),
			'delete_media'  => empty( $input['delete_media'] ) ? 0 : 1,
			'run_at'        => preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $run_at ) ? $run_at : $defaults['run_at'],
		);
	}

	/**
	 * Post types that may be cleaned up: public, non-hierarchical content types.
	 * Pages and attachments are deliberately never offered.
	 *
	 * @return array Post type name => label.
	 */
	public static function eligible_post_types() {
		$types = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( $type->hierarchical || 'attachment' === $type->name ) {
				continue;
			}
			$types[ $type->name ] = $type->labels->name;
		}

		/**
		 * Filters the post types offered on the settings screen.
		 *
		 * @param array $types Post type name => label.
		 */
		return apply_filters( 'dailypulse_cleanup_eligible_post_types', $types );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Scheduling
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Plugin activation: store defaults and schedule the daily event.
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults() );
		}
		self::reschedule();
		set_transient( 'dailypulse_cleanup_activated', 1, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Plugin deactivation: stop all scheduled work.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::HOOK_RESUME );
		delete_transient( self::LOCK );
	}

	/**
	 * Timestamp of the next occurrence of a HH:MM time in the site timezone.
	 *
	 * @param string $run_at Time of day, HH:MM.
	 * @return int Unix timestamp.
	 */
	public static function next_run_timestamp( $run_at ) {
		$now   = new DateTimeImmutable( 'now', wp_timezone() );
		$parts = array_map( 'intval', explode( ':', $run_at ) + array( 0, 0 ) );
		$next  = $now->setTime( $parts[0], $parts[1], 0 );

		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}

		return $next->getTimestamp();
	}

	/**
	 * Makes sure the daily event exists while enabled and is gone while disabled.
	 * Runs on every request so a lost cron entry heals itself.
	 */
	public static function ensure_schedule() {
		$settings  = self::settings();
		$scheduled = wp_next_scheduled( self::HOOK );

		if ( $settings['enabled'] && ! $scheduled ) {
			wp_schedule_event( self::next_run_timestamp( $settings['run_at'] ), 'daily', self::HOOK );
		} elseif ( ! $settings['enabled'] && $scheduled ) {
			wp_clear_scheduled_hook( self::HOOK );
			wp_clear_scheduled_hook( self::HOOK_RESUME );
		}
	}

	/**
	 * Re-creates the schedule, e.g. after the run time changed.
	 */
	public static function reschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::HOOK_RESUME );
		self::ensure_schedule();
	}

	/**
	 * Cron callback.
	 */
	public static function cron_run() {
		$settings = self::settings();

		if ( $settings['enabled'] ) {
			self::run( array( 'trigger' => 'cron' ) );
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Finding expired posts
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Query arguments that match every post due for cleanup.
	 *
	 * @param array $overrides Arguments to override.
	 * @return array|null Null when no post type is selected.
	 */
	public static function query_args( $overrides = array() ) {
		$settings = self::settings();

		// An empty post_type would make WP_Query fall back to "post"; never let that happen.
		if ( empty( $settings['post_types'] ) ) {
			return null;
		}

		$args = array(
			'post_type'              => $settings['post_types'],
			'post_status'            => 'publish',
			'date_query'             => array(
				array(
					'column' => 'post_date_gmt',
					'before' => gmdate( 'Y-m-d H:i:s', time() - $settings['days'] * DAY_IN_SECONDS ),
				),
			),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- runs once a day in the background.
			'meta_query'             => array(
				'relation' => 'OR',
				array(
					'key'     => self::KEEP_META,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::KEEP_META,
					'value'   => '1',
					'compare' => '!=',
				),
			),
			'orderby'                => 'date',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'posts_per_page'         => 100,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'cache_results'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		);

		if ( $settings['exclude_terms'] ) {
			$args['category__not_in'] = $settings['exclude_terms'];
		}

		/**
		 * Filters the query used to find posts that are due for cleanup.
		 *
		 * @param array $args     WP_Query arguments.
		 * @param array $settings Cleanup settings.
		 */
		return apply_filters( 'dailypulse_cleanup_query_args', array_merge( $args, $overrides ), $settings );
	}

	/**
	 * IDs of posts due for cleanup, oldest first.
	 *
	 * @param int   $limit   Maximum number of IDs.
	 * @param int[] $exclude IDs to leave out.
	 * @return int[]
	 */
	public static function find_expired( $limit = 100, $exclude = array() ) {
		$overrides = array( 'posts_per_page' => max( 1, (int) $limit ) );

		if ( $exclude ) {
			$overrides['post__not_in'] = array_map( 'absint', $exclude );
		}

		$args = self::query_args( $overrides );

		if ( null === $args ) {
			return array();
		}

		$query = new WP_Query( $args );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Number of posts currently due for cleanup.
	 *
	 * @return int
	 */
	public static function count_expired() {
		$args = self::query_args(
			array(
				'posts_per_page' => 1,
				'no_found_rows'  => false,
			)
		);

		if ( null === $args ) {
			return 0;
		}

		$query = new WP_Query( $args );

		return (int) $query->found_posts;
	}

	/**
	 * When a post becomes due for cleanup.
	 *
	 * @param int $post_id Post ID.
	 * @return int Unix timestamp, or 0 when the post is not subject to cleanup.
	 */
	public static function expiry_time( $post_id ) {
		$settings = self::settings();
		$post     = get_post( $post_id );

		if ( ! $post || ! $settings['enabled'] || 'publish' !== $post->post_status ) {
			return 0;
		}

		if ( ! in_array( $post->post_type, $settings['post_types'], true ) || self::is_kept( $post->ID ) ) {
			return 0;
		}

		if ( $settings['exclude_terms'] && has_category( $settings['exclude_terms'], $post ) ) {
			return 0;
		}

		return (int) get_post_time( 'U', true, $post ) + $settings['days'] * DAY_IN_SECONDS;
	}

	/**
	 * Whether a post is exempt from cleanup.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_kept( $post_id ) {
		return '1' === (string) get_post_meta( $post_id, self::KEEP_META, true );
	}

	/*
	 * ---------------------------------------------------------------------
	 * The run
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Cleans up every post that is due, in batches, within a time budget.
	 *
	 * @param array $args {
	 *     Optional.
	 *
	 *     @type string $trigger    What started the run: cron, manual or cli.
	 *     @type int    $time_limit Seconds to work before handing over to a follow-up event. 0 = no limit.
	 *     @type int    $batch      Posts fetched per query.
	 * }
	 * @return array Run summary.
	 */
	public static function run( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'trigger'    => 'manual',
				'time_limit' => 20,
				'batch'      => 100,
			)
		);

		$settings = self::settings();
		$result   = array(
			'time'      => time(),
			'trigger'   => $args['trigger'],
			'action'    => $settings['action'],
			'days'      => $settings['days'],
			'processed' => 0,
			'failed'    => 0,
			'remaining' => 0,
			'duration'  => 0,
			'status'    => 'ok',
		);

		// Never let two runs overlap (slow cron + impatient "Run now" click).
		if ( get_transient( self::LOCK ) ) {
			$result['status'] = 'locked';
			return $result;
		}
		set_transient( self::LOCK, 1, 10 * MINUTE_IN_SECONDS );

		$started  = microtime( true );
		$failed   = array();
		$time_out = false;
		$batch    = max( 1, (int) $args['batch'] );

		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		try {
			do {
				$ids = self::find_expired( $batch, $failed );

				foreach ( $ids as $post_id ) {
					if ( self::remove_post( $post_id, $settings ) ) {
						++$result['processed'];
					} else {
						// Remember failures so the next query skips them instead of looping forever.
						$failed[] = $post_id;
					}

					if ( $args['time_limit'] > 0 && ( microtime( true ) - $started ) > $args['time_limit'] ) {
						$time_out = true;
						break 2;
					}
				}
			} while ( count( $ids ) === $batch );
		} finally {
			wp_defer_term_counting( false );
			wp_defer_comment_counting( false );
			delete_transient( self::LOCK );
		}

		$result['failed']    = count( $failed );
		$result['remaining'] = $time_out ? max( 0, self::count_expired() - count( $failed ) ) : 0;
		$result['duration']  = round( microtime( true ) - $started, 2 );

		// Out of time with work left: continue in a couple of minutes rather than waiting a day.
		if ( $result['remaining'] > 0 && ! wp_next_scheduled( self::HOOK_RESUME ) ) {
			wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, self::HOOK_RESUME );
		}

		self::log( $result );

		/**
		 * Fires after a cleanup run.
		 *
		 * @param array $result Run summary.
		 */
		do_action( 'dailypulse_cleanup_finished', $result );

		return $result;
	}

	/**
	 * Trashes or deletes a single post according to the settings.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $settings Cleanup settings.
	 * @return bool Whether the post was removed.
	 */
	protected static function remove_post( $post_id, $settings ) {
		// With the Trash disabled site-wide, wp_trash_post() deletes outright, so treat it as such.
		if ( 'delete' === $settings['action'] || ! EMPTY_TRASH_DAYS ) {
			// Work out which media is safe to remove while the post still exists.
			$media = $settings['delete_media'] ? self::removable_media( $post_id ) : array();

			if ( ! wp_delete_post( $post_id, true ) ) {
				return false;
			}

			foreach ( $media as $attachment_id ) {
				wp_delete_attachment( $attachment_id, true );
			}

			return true;
		}

		if ( ! wp_trash_post( $post_id ) ) {
			return false;
		}

		// Marks the post as trashed by us, so restoring it can exempt it from the next run.
		update_post_meta( $post_id, self::TRASHED_META, time() );

		return true;
	}

	/**
	 * Media belonging to a post that nothing else is using.
	 *
	 * Covers files uploaded to the post plus its featured image. An attachment is kept
	 * when it belongs to another post, is another post's featured image, or is inserted
	 * into other content.
	 *
	 * @param int $post_id Post ID.
	 * @return int[] Attachment IDs that are safe to delete.
	 */
	public static function removable_media( $post_id ) {
		global $wpdb;

		$post_id = (int) $post_id;
		$ids     = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_parent'    => $post_id,
				'fields'         => 'ids',
				'posts_per_page' => 200,
				'no_found_rows'  => true,
			)
		);

		$thumbnail_id = (int) get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			$ids[] = $thumbnail_id;
		}

		/**
		 * Filters the attachments considered for removal together with a post.
		 *
		 * @param int[] $ids     Attachment IDs.
		 * @param int   $post_id Post being removed.
		 */
		$ids       = array_unique( array_filter( array_map( 'absint', (array) apply_filters( 'dailypulse_cleanup_media_ids', $ids, $post_id ) ) ) );
		$removable = array();

		foreach ( $ids as $attachment_id ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) ) {
				continue;
			}

			$parent_id = (int) wp_get_post_parent_id( $attachment_id );
			if ( $parent_id && $parent_id !== $post_id && get_post( $parent_id ) ) {
				continue;
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-off usage checks, nothing to cache.
			$is_thumbnail_elsewhere = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s AND post_id <> %d LIMIT 1",
					(string) $attachment_id,
					$post_id
				)
			);
			if ( $is_thumbnail_elsewhere ) {
				continue;
			}

			$class             = 'wp-image-' . $attachment_id;
			$is_used_elsewhere = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE ID <> %d AND post_status NOT IN ('trash','auto-draft','inherit') AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s ) LIMIT 1",
					$post_id,
					'%' . $wpdb->esc_like( $class . '"' ) . '%',
					'%' . $wpdb->esc_like( $class . ' ' ) . '%',
					'%' . $wpdb->esc_like( $class . "'" ) . '%'
				)
			);
			// phpcs:enable
			if ( $is_used_elsewhere ) {
				continue;
			}

			$removable[] = (int) $attachment_id;
		}

		/**
		 * Filters the attachments that will be deleted together with a post.
		 *
		 * @param int[] $removable Attachment IDs.
		 * @param int   $post_id   Post being removed.
		 */
		return apply_filters( 'dailypulse_cleanup_removable_media', $removable, $post_id );
	}

	/**
	 * Restoring a post that the cleanup trashed means "I want this one": exempt it,
	 * otherwise the next run would simply trash it again.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_untrash( $post_id ) {
		if ( get_post_meta( $post_id, self::TRASHED_META, true ) ) {
			delete_post_meta( $post_id, self::TRASHED_META );
			update_post_meta( $post_id, self::KEEP_META, '1' );
		}
	}

	/**
	 * When a post trashed by the cleanup is finally purged from the Trash,
	 * take its unused media with it (if enabled).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_before_delete( $post_id ) {
		$settings = self::settings();

		if ( ! $settings['delete_media'] || ! get_post_meta( $post_id, self::TRASHED_META, true ) ) {
			return;
		}

		$media = self::removable_media( $post_id );

		// Clear the marker first so nested deletions cannot re-enter this branch.
		delete_post_meta( $post_id, self::TRASHED_META );

		foreach ( $media as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}

	/**
	 * Tells search engines when a story will disappear, so they stop serving a dead link.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( ! is_singular() ) {
			return $robots;
		}

		$expires = self::expiry_time( get_queried_object_id() );

		if ( $expires ) {
			$robots['unavailable_after'] = gmdate( 'Y-m-d\TH:i:s+00:00', $expires );
		}

		return $robots;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Run log
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Stores a run summary, keeping the 15 most recent.
	 *
	 * @param array $result Run summary.
	 */
	protected static function log( $result ) {
		$log = self::get_log();
		array_unshift( $log, $result );
		update_option( self::LOG_OPTION, array_slice( $log, 0, 15 ), false );
	}

	/**
	 * Recent run summaries, newest first.
	 *
	 * @return array
	 */
	public static function get_log() {
		$log = get_option( self::LOG_OPTION, array() );

		return is_array( $log ) ? $log : array();
	}
}
