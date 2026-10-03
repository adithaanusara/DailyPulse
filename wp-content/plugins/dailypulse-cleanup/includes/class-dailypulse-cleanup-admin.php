<?php
/**
 * Admin screens: settings page (Tools → Auto-Cleanup), the per-post "keep" box and notices.
 *
 * @package DailyPulse_Cleanup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI for the cleanup.
 */
final class DailyPulse_Cleanup_Admin {

	const PAGE = 'dailypulse-cleanup';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_dailypulse_cleanup_run', array( __CLASS__, 'handle_run' ) );
		add_action( 'admin_notices', array( __CLASS__, 'activation_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DAILYPULSE_CLEANUP_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
	}

	/**
	 * URL of the settings page.
	 *
	 * @param array $args Extra query arguments.
	 * @return string
	 */
	public static function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'tools.php' ) );
	}

	/**
	 * Adds Tools → Auto-Cleanup.
	 */
	public static function menu() {
		add_management_page(
			__( 'Auto-Cleanup', 'dailypulse-cleanup' ),
			__( 'Auto-Cleanup', 'dailypulse-cleanup' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Registers the settings array with the Settings API.
	 */
	public static function register_settings() {
		register_setting(
			'dailypulse_cleanup',
			DailyPulse_Cleanup::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'DailyPulse_Cleanup', 'sanitize' ),
			)
		);
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'dailypulse-cleanup' ) . '</a>' );

		return $links;
	}

	/**
	 * Formats a timestamp in the site's date and time format.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	protected static function format_time( $timestamp ) {
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * One-sentence description of the active rule.
	 *
	 * @param array $settings Cleanup settings.
	 * @return string
	 */
	protected static function rule_summary( $settings ) {
		$labels = array_intersect_key( DailyPulse_Cleanup::eligible_post_types(), array_flip( $settings['post_types'] ) );

		if ( ! $labels ) {
			return __( 'No content type is selected, so nothing will be removed.', 'dailypulse-cleanup' );
		}

		/* translators: %s: number of days. */
		$age = sprintf( _n( '%s day', '%s days', $settings['days'], 'dailypulse-cleanup' ), number_format_i18n( $settings['days'] ) );

		if ( 'delete' === $settings['action'] ) {
			/* translators: 1: content types, 2: age such as "3 days", 3: time of day. */
			$text = __( 'Published %1$s older than %2$s are permanently deleted every day at %3$s.', 'dailypulse-cleanup' );
		} else {
			/* translators: 1: content types, 2: age such as "3 days", 3: time of day. */
			$text = __( 'Published %1$s older than %2$s are moved to the Trash every day at %3$s.', 'dailypulse-cleanup' );
		}

		$run_at = wp_date( get_option( 'time_format' ), DailyPulse_Cleanup::next_run_timestamp( $settings['run_at'] ) );

		return sprintf( $text, wp_sprintf_l( '%l', array_values( $labels ) ), $age, $run_at );
	}

	/**
	 * One-line description of a finished run.
	 *
	 * @param array $run Run summary.
	 * @return string
	 */
	protected static function run_summary( $run ) {
		$count = isset( $run['processed'] ) ? (int) $run['processed'] : 0;

		if ( isset( $run['action'] ) && 'delete' === $run['action'] ) {
			/* translators: %s: number of posts. */
			$text = sprintf( _n( '%s post deleted permanently', '%s posts deleted permanently', $count, 'dailypulse-cleanup' ), number_format_i18n( $count ) );
		} else {
			/* translators: %s: number of posts. */
			$text = sprintf( _n( '%s post moved to the Trash', '%s posts moved to the Trash', $count, 'dailypulse-cleanup' ), number_format_i18n( $count ) );
		}

		if ( ! empty( $run['failed'] ) ) {
			/* translators: %s: number of posts. */
			$text .= ' · ' . sprintf( _n( '%s could not be removed', '%s could not be removed', (int) $run['failed'], 'dailypulse-cleanup' ), number_format_i18n( (int) $run['failed'] ) );
		}

		if ( ! empty( $run['remaining'] ) ) {
			/* translators: %s: number of posts. */
			$text .= ' · ' . sprintf( _n( '%s still queued', '%s still queued', (int) $run['remaining'], 'dailypulse-cleanup' ), number_format_i18n( (int) $run['remaining'] ) );
		}

		return $text;
	}

	/**
	 * Human label for what started a run.
	 *
	 * @param string $trigger Trigger key.
	 * @return string
	 */
	protected static function trigger_label( $trigger ) {
		$labels = array(
			'cron'   => __( 'Scheduled', 'dailypulse-cleanup' ),
			'manual' => __( 'Run now', 'dailypulse-cleanup' ),
			'cli'    => __( 'WP-CLI', 'dailypulse-cleanup' ),
		);

		return isset( $labels[ $trigger ] ) ? $labels[ $trigger ] : $trigger;
	}

	/**
	 * Handles the "Run cleanup now" button.
	 */
	public static function handle_run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run the cleanup.', 'dailypulse-cleanup' ), 403 );
		}

		check_admin_referer( 'dailypulse_cleanup_run' );

		$result = DailyPulse_Cleanup::run( array( 'trigger' => 'manual' ) );

		wp_safe_redirect( self::page_url( array( 'ran' => $result['status'] ) ) );
		exit;
	}

	/**
	 * Settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = DailyPulse_Cleanup::settings();
		$log      = DailyPulse_Cleanup::get_log();
		$next     = wp_next_scheduled( DailyPulse_Cleanup::HOOK );
		$waiting  = DailyPulse_Cleanup::count_expired();
		$name     = DailyPulse_Cleanup::OPTION;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display flags.
		$ran     = isset( $_GET['ran'] ) ? sanitize_key( wp_unslash( $_GET['ran'] ) ) : '';
		$preview = isset( $_GET['preview'] );
		// phpcs:enable
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Auto-Cleanup', 'dailypulse-cleanup' ); ?></h1>

			<?php if ( 'ok' === $ran && $log ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					/* translators: %s: run summary, e.g. "12 posts moved to the Trash". */
					printf( esc_html__( 'Cleanup finished: %s.', 'dailypulse-cleanup' ), esc_html( self::run_summary( $log[0] ) ) );
					?>
				</p></div>
			<?php elseif ( 'locked' === $ran ) : ?>
				<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'A cleanup is already running. Try again in a few minutes.', 'dailypulse-cleanup' ); ?></p></div>
			<?php endif; ?>

			<div class="card" style="max-width:820px">
				<h2 class="title">
					<?php
					echo $settings['enabled']
						? esc_html__( 'Cleanup is on', 'dailypulse-cleanup' )
						: esc_html__( 'Cleanup is off', 'dailypulse-cleanup' );
					?>
				</h2>
				<p><?php echo esc_html( self::rule_summary( $settings ) ); ?></p>
				<table class="widefat striped" style="border:0;box-shadow:none">
					<tbody>
						<tr>
							<th scope="row" style="width:160px"><?php esc_html_e( 'Next run', 'dailypulse-cleanup' ); ?></th>
							<td>
								<?php
								if ( $settings['enabled'] && $next ) {
									printf(
										/* translators: 1: date and time, 2: time difference such as "5 hours". */
										esc_html__( '%1$s (in %2$s)', 'dailypulse-cleanup' ),
										esc_html( self::format_time( $next ) ),
										esc_html( human_time_diff( time(), max( time(), $next ) ) )
									);
								} else {
									esc_html_e( 'Not scheduled', 'dailypulse-cleanup' );
								}
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Last run', 'dailypulse-cleanup' ); ?></th>
							<td>
								<?php
								if ( $log ) {
									echo esc_html( self::format_time( $log[0]['time'] ) . ' — ' . self::run_summary( $log[0] ) );
								} else {
									esc_html_e( 'Has not run yet', 'dailypulse-cleanup' );
								}
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Due right now', 'dailypulse-cleanup' ); ?></th>
							<td>
								<?php
								/* translators: %s: number of posts. */
								echo esc_html( sprintf( _n( '%s post', '%s posts', $waiting, 'dailypulse-cleanup' ), number_format_i18n( $waiting ) ) );
								?>
							</td>
						</tr>
					</tbody>
				</table>
				<p>
					<a class="button" href="<?php echo esc_url( self::page_url( array( 'preview' => 1 ) ) ); ?>"><?php esc_html_e( 'Preview what would be removed', 'dailypulse-cleanup' ); ?></a>
				</p>
				<?php
				$confirm = 'delete' === $settings['action']
					? __( 'This permanently deletes every post that is due. It cannot be undone. Continue?', 'dailypulse-cleanup' )
					: __( 'Move every post that is due to the Trash now?', 'dailypulse-cleanup' );
				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm( this.dataset.confirm );" data-confirm="<?php echo esc_attr( $confirm ); ?>">
					<input type="hidden" name="action" value="dailypulse_cleanup_run">
					<?php wp_nonce_field( 'dailypulse_cleanup_run' ); ?>
					<?php submit_button( __( 'Run cleanup now', 'dailypulse-cleanup' ), 'secondary', 'dailypulse-cleanup-run', false, $waiting ? array() : array( 'disabled' => 'disabled' ) ); ?>
				</form>
			</div>

			<?php
			if ( $preview ) {
				self::render_preview( $waiting );
			}
			?>

			<form method="post" action="options.php">
				<?php settings_fields( 'dailypulse_cleanup' ); ?>
				<h2><?php esc_html_e( 'Settings', 'dailypulse-cleanup' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Automatic cleanup', 'dailypulse-cleanup' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>>
								<?php esc_html_e( 'Run the cleanup automatically once a day', 'dailypulse-cleanup' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dailypulse-cleanup-days"><?php esc_html_e( 'Remove stories older than', 'dailypulse-cleanup' ); ?></label></th>
						<td>
							<input type="number" class="small-text" id="dailypulse-cleanup-days" name="<?php echo esc_attr( $name ); ?>[days]" min="1" max="3650" step="1" value="<?php echo esc_attr( $settings['days'] ); ?>">
							<?php esc_html_e( 'days', 'dailypulse-cleanup' ); ?>
							<p class="description"><?php esc_html_e( 'Counted from the publish date.', 'dailypulse-cleanup' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'What to do with them', 'dailypulse-cleanup' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="<?php echo esc_attr( $name ); ?>[action]" value="trash" <?php checked( $settings['action'], 'trash' ); ?>>
									<?php esc_html_e( 'Move to the Trash', 'dailypulse-cleanup' ); ?>
								</label>
								<p class="description" style="margin:2px 0 10px 24px">
									<?php
									if ( defined( 'EMPTY_TRASH_DAYS' ) && EMPTY_TRASH_DAYS > 0 ) {
										printf(
											/* translators: %s: number of days. */
											esc_html__( 'Recoverable. WordPress empties the Trash by itself after %s days.', 'dailypulse-cleanup' ),
											esc_html( number_format_i18n( EMPTY_TRASH_DAYS ) )
										);
									} else {
										esc_html_e( 'The Trash is disabled on this site (EMPTY_TRASH_DAYS is 0), so this deletes permanently.', 'dailypulse-cleanup' );
									}
									?>
								</p>
								<label>
									<input type="radio" name="<?php echo esc_attr( $name ); ?>[action]" value="delete" <?php checked( $settings['action'], 'delete' ); ?>>
									<?php esc_html_e( 'Delete permanently', 'dailypulse-cleanup' ); ?>
								</label>
								<p class="description" style="margin:2px 0 0 24px"><?php esc_html_e( 'Skips the Trash. Cannot be undone.', 'dailypulse-cleanup' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Content types', 'dailypulse-cleanup' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( DailyPulse_Cleanup::eligible_post_types() as $type => $label ) : ?>
									<label style="display:block;margin-bottom:4px">
										<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[post_types][]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, $settings['post_types'], true ) ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Pages and media library items are never removed.', 'dailypulse-cleanup' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Categories to keep', 'dailypulse-cleanup' ); ?></th>
						<td>
							<fieldset>
								<?php
								$categories = get_categories( array( 'hide_empty' => false ) );
								foreach ( $categories as $category ) :
									?>
									<label style="display:block;margin-bottom:4px">
										<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[exclude_terms][]" value="<?php echo esc_attr( $category->term_id ); ?>" <?php checked( in_array( (int) $category->term_id, $settings['exclude_terms'], true ) ); ?>>
										<?php echo esc_html( $category->name ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Stories in a ticked category are never removed. To keep a single story, tick “Keep this story” in its editor sidebar.', 'dailypulse-cleanup' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Media', 'dailypulse-cleanup' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_media]" value="1" <?php checked( $settings['delete_media'] ); ?>>
								<?php esc_html_e( 'Also delete a story’s images when the story is permanently deleted', 'dailypulse-cleanup' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Covers the featured image and files uploaded to the story. Anything still used by another post is left alone. With “Move to the Trash”, the images go when the Trash is emptied.', 'dailypulse-cleanup' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dailypulse-cleanup-run-at"><?php esc_html_e( 'Daily run time', 'dailypulse-cleanup' ); ?></label></th>
						<td>
							<input type="time" id="dailypulse-cleanup-run-at" name="<?php echo esc_attr( $name ); ?>[run_at]" value="<?php echo esc_attr( $settings['run_at'] ); ?>">
							<p class="description">
								<?php
								printf(
									/* translators: %s: timezone name. */
									esc_html__( 'In the site timezone (%s). WordPress runs scheduled tasks on the first visit after this time.', 'dailypulse-cleanup' ),
									esc_html( wp_timezone_string() )
								);
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php if ( $log ) : ?>
				<h2><?php esc_html_e( 'Recent runs', 'dailypulse-cleanup' ); ?></h2>
				<table class="widefat striped" style="max-width:820px">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'When', 'dailypulse-cleanup' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Started by', 'dailypulse-cleanup' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Result', 'dailypulse-cleanup' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Took', 'dailypulse-cleanup' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $log as $run ) : ?>
							<tr>
								<td><?php echo esc_html( self::format_time( $run['time'] ) ); ?></td>
								<td><?php echo esc_html( self::trigger_label( $run['trigger'] ) ); ?></td>
								<td><?php echo esc_html( self::run_summary( $run ) ); ?></td>
								<td>
									<?php
									/* translators: %s: number of seconds. */
									echo esc_html( sprintf( __( '%ss', 'dailypulse-cleanup' ), number_format_i18n( (float) $run['duration'], 2 ) ) );
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Dry-run list of the posts the next run would remove.
	 *
	 * @param int $total Number of posts due.
	 */
	protected static function render_preview( $total ) {
		$ids = DailyPulse_Cleanup::find_expired( 50 );
		?>
		<h2><?php esc_html_e( 'Preview', 'dailypulse-cleanup' ); ?></h2>
		<?php if ( ! $ids ) : ?>
			<p><?php esc_html_e( 'Nothing is due for cleanup right now.', 'dailypulse-cleanup' ); ?></p>
			<?php
			return;
		endif;
		?>
		<p>
			<?php
			printf(
				/* translators: 1: number of posts listed, 2: total number of posts due. */
				esc_html__( 'Showing %1$s of %2$s posts the next run would remove, oldest first. Nothing has been changed.', 'dailypulse-cleanup' ),
				esc_html( number_format_i18n( count( $ids ) ) ),
				esc_html( number_format_i18n( $total ) )
			);
			?>
		</p>
		<table class="widefat striped" style="max-width:820px">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Title', 'dailypulse-cleanup' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'dailypulse-cleanup' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Published', 'dailypulse-cleanup' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $ids as $post_id ) : ?>
					<?php
					$type  = get_post_type_object( get_post_type( $post_id ) );
					$title = get_the_title( $post_id );
					?>
					<tr>
						<td>
							<a href="<?php echo esc_url( (string) get_edit_post_link( $post_id ) ); ?>">
								<?php echo esc_html( '' !== $title ? $title : __( '(no title)', 'dailypulse-cleanup' ) ); ?>
							</a>
						</td>
						<td><?php echo esc_html( $type ? $type->labels->singular_name : '' ); ?></td>
						<td>
							<?php
							printf(
								/* translators: 1: date, 2: time difference such as "4 days". */
								esc_html__( '%1$s (%2$s ago)', 'dailypulse-cleanup' ),
								esc_html( get_the_date( '', $post_id ) ),
								esc_html( human_time_diff( (int) get_post_time( 'U', true, $post_id ) ) )
							);
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * One-time notice after activation spelling out what is about to happen.
	 */
	public static function activation_notice() {
		if ( ! get_transient( 'dailypulse_cleanup_activated' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		delete_transient( 'dailypulse_cleanup_activated' );

		$settings = DailyPulse_Cleanup::settings();
		$waiting  = DailyPulse_Cleanup::count_expired();
		$next     = wp_next_scheduled( DailyPulse_Cleanup::HOOK );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'DailyPulse Auto-Cleanup is on.', 'dailypulse-cleanup' ); ?></strong>
				<?php echo esc_html( self::rule_summary( $settings ) ); ?>
				<?php
				if ( $waiting && $next ) {
					printf(
						/* translators: 1: number of posts, 2: date and time. */
						esc_html( _n( '%1$s post is already older than that and will be removed at the first run on %2$s.', '%1$s posts are already older than that and will be removed at the first run on %2$s.', $waiting, 'dailypulse-cleanup' ) ),
						esc_html( number_format_i18n( $waiting ) ),
						esc_html( self::format_time( $next ) )
					);
				}
				?>
				<a href="<?php echo esc_url( self::page_url( array( 'preview' => 1 ) ) ); ?>"><?php esc_html_e( 'Review settings and preview', 'dailypulse-cleanup' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Adds the "keep" box to the editor of every cleaned-up content type.
	 *
	 * @param string $post_type Post type.
	 */
	public static function add_meta_box( $post_type ) {
		$settings = DailyPulse_Cleanup::settings();

		if ( in_array( $post_type, $settings['post_types'], true ) ) {
			add_meta_box( 'dailypulse-cleanup', __( 'Auto-Cleanup', 'dailypulse-cleanup' ), array( __CLASS__, 'render_meta_box' ), $post_type, 'side' );
		}
	}

	/**
	 * Renders the "keep" box.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public static function render_meta_box( $post ) {
		$settings = DailyPulse_Cleanup::settings();
		$expires  = DailyPulse_Cleanup::expiry_time( $post->ID );

		wp_nonce_field( 'dailypulse_cleanup_keep', 'dailypulse_cleanup_keep_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="dailypulse_keep" value="1" <?php checked( DailyPulse_Cleanup::is_kept( $post->ID ) ); ?>>
				<?php esc_html_e( 'Keep this story — never remove it automatically', 'dailypulse-cleanup' ); ?>
			</label>
		</p>
		<p class="description">
			<?php
			if ( ! $settings['enabled'] ) {
				esc_html_e( 'Auto-cleanup is currently switched off.', 'dailypulse-cleanup' );
			} elseif ( $expires ) {
				$text = 'delete' === $settings['action']
					/* translators: %s: date and time. */
					? __( 'Will be permanently deleted at the first daily run after %s.', 'dailypulse-cleanup' )
					/* translators: %s: date and time. */
					: __( 'Will be moved to the Trash at the first daily run after %s.', 'dailypulse-cleanup' );
				echo esc_html( sprintf( $text, self::format_time( $expires ) ) );
			} else {
				printf(
					/* translators: %s: number of days. */
					esc_html( _n( 'Other stories are removed %s day after they are published.', 'Other stories are removed %s days after they are published.', $settings['days'], 'dailypulse-cleanup' ) ),
					esc_html( number_format_i18n( $settings['days'] ) )
				);
			}
			?>
		</p>
		<?php
	}

	/**
	 * Saves the "keep" flag.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['dailypulse_cleanup_keep_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dailypulse_cleanup_keep_nonce'] ) ), 'dailypulse_cleanup_keep' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( empty( $_POST['dailypulse_keep'] ) ) {
			delete_post_meta( $post_id, DailyPulse_Cleanup::KEEP_META );
		} else {
			update_post_meta( $post_id, DailyPulse_Cleanup::KEEP_META, '1' );
		}
	}

	/**
	 * Labels kept posts in the list table.
	 *
	 * @param array   $states Post states.
	 * @param WP_Post $post   Post.
	 * @return array
	 */
	public static function post_states( $states, $post ) {
		if ( DailyPulse_Cleanup::is_kept( $post->ID ) ) {
			$states['dailypulse_keep'] = __( 'Kept from cleanup', 'dailypulse-cleanup' );
		}

		return $states;
	}
}
