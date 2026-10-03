<?php
/**
 * Admin pages: DailyPulse → Site Options (native version, used when ACF cannot
 * provide an options page) and DailyPulse → Setup & Demo.
 *
 * @package DailyPulse_Core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'dailypulse_core_admin_menu', 100 );
add_action( 'admin_init', 'dailypulse_core_register_settings' );
add_action( 'admin_post_dailypulse_setup', 'dailypulse_core_handle_setup' );
add_action( 'admin_post_dailypulse_demo_import', 'dailypulse_core_handle_demo_import' );
add_action( 'admin_post_dailypulse_demo_remove', 'dailypulse_core_handle_demo_remove' );
add_filter( 'plugin_action_links_' . plugin_basename( DAILYPULSE_CORE_FILE ), 'dailypulse_core_action_links' );

/**
 * Registers the DailyPulse admin menu.
 */
function dailypulse_core_admin_menu() {
	if ( ! dailypulse_core_acf_has_options_page() ) {
		add_menu_page(
			__( 'DailyPulse Site Options', 'dailypulse-core' ),
			__( 'DailyPulse', 'dailypulse-core' ),
			'manage_options',
			'dailypulse',
			'dailypulse_core_render_options_page',
			dailypulse_core_menu_icon(),
			59
		);
		add_submenu_page( 'dailypulse', __( 'DailyPulse Site Options', 'dailypulse-core' ), __( 'Site Options', 'dailypulse-core' ), 'manage_options', 'dailypulse', 'dailypulse_core_render_options_page' );
	}

	add_submenu_page( 'dailypulse', __( 'Setup & Demo Content', 'dailypulse-core' ), __( 'Setup & Demo', 'dailypulse-core' ), 'manage_options', 'dailypulse-setup', 'dailypulse_core_render_setup_page' );
}

/**
 * Quick links on the Plugins screen.
 *
 * @param array $links Action links.
 * @return array
 */
function dailypulse_core_action_links( $links ) {
	array_unshift(
		$links,
		'<a href="' . esc_url( admin_url( 'admin.php?page=dailypulse' ) ) . '">' . esc_html__( 'Site Options', 'dailypulse-core' ) . '</a>',
		'<a href="' . esc_url( admin_url( 'admin.php?page=dailypulse-setup' ) ) . '">' . esc_html__( 'Setup & Demo', 'dailypulse-core' ) . '</a>'
	);

	return $links;
}

/*
 * -------------------------------------------------------------------------
 * Site Options (native)
 * -------------------------------------------------------------------------
 */

/**
 * Registers every site option with the Settings API.
 *
 * Each option lives in its own row named "options_dp_{key}" — the same row an
 * ACF options page would write — so switching between the two loses nothing.
 */
function dailypulse_core_register_settings() {
	if ( dailypulse_core_acf_has_options_page() ) {
		return;
	}

	foreach ( dailypulse_option_fields() as $key => $field ) {
		register_setting(
			'dailypulse_options',
			'options_dp_' . $key,
			array(
				'sanitize_callback' => static function ( $value ) use ( $field ) {
					return dailypulse_sanitize_value( $field, $value );
				},
			)
		);
	}
}

/**
 * Renders the native Site Options page.
 */
function dailypulse_core_render_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'DailyPulse Site Options', 'dailypulse-core' ); ?></h1>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'dailypulse_options' ); ?>

			<?php foreach ( dailypulse_option_sections() as $section ) : ?>
				<h2 class="title"><?php echo esc_html( $section['title'] ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( $section['fields'] as $key => $field ) : ?>
						<?php
						$name  = 'options_dp_' . $key;
						$value = dailypulse_option( $key );
						?>
						<tr>
							<th scope="row">
								<?php if ( 'true_false' === $field['type'] ) : ?>
									<?php echo esc_html( $field['label'] ); ?>
								<?php else : ?>
									<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
								<?php endif; ?>
							</th>
							<td>
								<?php dailypulse_core_render_control( $name, $field, $value ); ?>
								<?php if ( ! empty( $field['instructions'] ) ) : ?>
									<p class="description"><?php echo esc_html( $field['instructions'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>

			<?php submit_button( __( 'Save options', 'dailypulse-core' ) ); ?>
		</form>
	</div>
	<?php
}

/*
 * -------------------------------------------------------------------------
 * Setup & Demo
 * -------------------------------------------------------------------------
 */

/**
 * Checks that the current request may run a setup action.
 *
 * @param string $action Nonce action.
 */
function dailypulse_core_guard_action( $action ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'dailypulse-core' ), 403 );
	}

	check_admin_referer( $action );
}

/**
 * Sends the browser back to the Setup & Demo page with a result message.
 *
 * @param string $message Message to show.
 */
function dailypulse_core_setup_redirect( $message ) {
	set_transient( 'dailypulse_setup_notice_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
	wp_safe_redirect( admin_url( 'admin.php?page=dailypulse-setup' ) );
	exit;
}

/**
 * Handles "Run quick setup".
 */
function dailypulse_core_handle_setup() {
	dailypulse_core_guard_action( 'dailypulse_setup' );

	$report = dailypulse_core_run_setup();

	dailypulse_core_setup_redirect( $report ? implode( ' ', $report ) : __( 'Everything was already set up — nothing to change.', 'dailypulse-core' ) );
}

/**
 * Handles "Import demo content".
 */
function dailypulse_core_handle_demo_import() {
	dailypulse_core_guard_action( 'dailypulse_demo_import' );

	$result = dailypulse_core_demo_import();

	dailypulse_core_setup_redirect(
		sprintf(
			/* translators: 1: stories created, 2: stories refreshed, 3: images downloaded. */
			__( 'Demo content ready: %1$d stories created, %2$d refreshed, %3$d images downloaded.', 'dailypulse-core' ),
			$result['created'],
			$result['refreshed'],
			$result['images']
		) . ( $result['image_errors'] ? ' ' . __( 'Some images could not be downloaded; run the import again to retry.', 'dailypulse-core' ) : '' )
	);
}

/**
 * Handles "Remove demo content".
 */
function dailypulse_core_handle_demo_remove() {
	dailypulse_core_guard_action( 'dailypulse_demo_remove' );

	$removed = dailypulse_core_demo_remove();

	/* translators: %d: number of items. */
	dailypulse_core_setup_redirect( sprintf( __( 'Demo content removed (%d items).', 'dailypulse-core' ), $removed ) );
}

/**
 * Rows for the "status" table on the setup page.
 *
 * @return array[] Each with `label`, `ok` and `text`.
 */
function dailypulse_core_status_rows() {
	$rows = array();

	$theme_active = 'dailypulse' === get_template();
	$rows[]       = array(
		'label' => __( 'Theme', 'dailypulse-core' ),
		'ok'    => $theme_active,
		'text'  => $theme_active
			? __( 'DailyPulse is active.', 'dailypulse-core' )
			: __( 'The DailyPulse theme is not active. Activate it under Appearance → Themes.', 'dailypulse-core' ),
	);

	if ( dailypulse_core_acf_has_options_page() && function_exists( 'acf_get_field_type' ) && acf_get_field_type( 'gallery' ) ) {
		$fields = __( 'ACF PRO / Secure Custom Fields: story details, gallery and site options use ACF. Source links use the built-in editor.', 'dailypulse-core' );
	} elseif ( function_exists( 'acf_add_local_field_group' ) ) {
		$fields = __( 'ACF (free): story details use ACF. Gallery, source links and site options use the built-in screens.', 'dailypulse-core' );
	} else {
		$fields = __( 'Built-in screens. Install ACF or Secure Custom Fields at any time to edit the same fields through ACF.', 'dailypulse-core' );
	}
	$rows[] = array(
		'label' => __( 'Custom fields', 'dailypulse-core' ),
		'ok'    => true,
		'text'  => $fields,
	);

	$cleanup = class_exists( 'DailyPulse_Cleanup' );
	if ( $cleanup ) {
		$settings = DailyPulse_Cleanup::settings();
		$text     = $settings['enabled']
			/* translators: %d: number of days. */
			? sprintf( __( 'On — stories older than %d days are removed daily.', 'dailypulse-core' ), $settings['days'] )
			: __( 'Installed, but switched off.', 'dailypulse-core' );
	} else {
		$text = __( 'The DailyPulse Auto-Cleanup plugin is not active, so old stories are kept.', 'dailypulse-core' );
	}
	$rows[] = array(
		'label' => __( 'Auto-cleanup', 'dailypulse-core' ),
		'ok'    => $cleanup,
		'text'  => $text,
	);

	$pretty = (bool) get_option( 'permalink_structure' );
	$rows[] = array(
		'label' => __( 'Permalinks', 'dailypulse-core' ),
		'ok'    => $pretty,
		'text'  => $pretty
			? __( 'Pretty permalinks are on.', 'dailypulse-core' )
			: __( 'Plain permalinks. Quick setup switches to /story-name/.', 'dailypulse-core' ),
	);

	$menu   = has_nav_menu( 'primary' );
	$rows[] = array(
		'label' => __( 'Main menu', 'dailypulse-core' ),
		'ok'    => true,
		'text'  => $menu
			? __( 'A menu is assigned to the header.', 'dailypulse-core' )
			: __( 'No menu assigned; the header lists the homepage categories automatically.', 'dailypulse-core' ),
	);

	return $rows;
}

/**
 * Renders the Setup & Demo page.
 */
function dailypulse_core_render_setup_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notice_key = 'dailypulse_setup_notice_' . get_current_user_id();
	$notice     = get_transient( $notice_key );
	$demo_count = dailypulse_core_demo_count();

	if ( $notice ) {
		delete_transient( $notice_key );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Setup & Demo Content', 'dailypulse-core' ); ?></h1>

		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<table class="widefat striped dp-status">
			<tbody>
				<?php foreach ( dailypulse_core_status_rows() as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['label'] ); ?></td>
						<td>
							<span class="dp-pill <?php echo $row['ok'] ? 'dp-pill--ok' : 'dp-pill--warn'; ?>"><?php echo $row['ok'] ? esc_html__( 'OK', 'dailypulse-core' ) : esc_html__( 'Check', 'dailypulse-core' ); ?></span>
							<?php echo esc_html( $row['text'] ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="dp-cards">
			<div class="card">
				<h2><?php esc_html_e( 'Quick setup', 'dailypulse-core' ); ?></h2>
				<p><?php esc_html_e( 'Safe to run more than once. It only adds what is missing:', 'dailypulse-core' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'The Local News, Foreign News, Articles and Sports categories', 'dailypulse-core' ); ?></li>
					<li><?php esc_html_e( 'A header menu with those categories and a footer menu', 'dailypulse-core' ); ?></li>
					<li><?php esc_html_e( 'About and Contact pages', 'dailypulse-core' ); ?></li>
					<li><?php esc_html_e( 'Pretty permalinks, if the site still uses plain ones', 'dailypulse-core' ); ?></li>
				</ul>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="dailypulse_setup">
					<?php wp_nonce_field( 'dailypulse_setup' ); ?>
					<?php submit_button( __( 'Run quick setup', 'dailypulse-core' ), 'primary', 'dailypulse-setup', false ); ?>
				</form>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Demo content', 'dailypulse-core' ); ?></h2>
				<p>
					<?php
					esc_html_e( 'Adds sample stories with photos across the four categories, plus one breaking alert, so you can see the design filled in. Photos are downloaded from picsum.photos, so this needs an internet connection and takes about a minute.', 'dailypulse-core' );
					?>
				</p>
				<p>
					<?php
					if ( class_exists( 'DailyPulse_Cleanup' ) ) {
						esc_html_e( 'Sample stories are dated within the last two days. Auto-Cleanup treats them like any other story, so they disappear after its limit; import again to refresh them.', 'dailypulse-core' );
					}
					?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
					<input type="hidden" name="action" value="dailypulse_demo_import">
					<?php wp_nonce_field( 'dailypulse_demo_import' ); ?>
					<?php submit_button( $demo_count ? __( 'Refresh demo content', 'dailypulse-core' ) : __( 'Import demo content', 'dailypulse-core' ), 'primary', 'dailypulse-demo-import', false ); ?>
				</form>
				<?php if ( $demo_count ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block" onsubmit="return window.confirm( this.dataset.confirm );" data-confirm="<?php esc_attr_e( 'Permanently delete all demo stories, their images and the demo alert?', 'dailypulse-core' ); ?>">
						<input type="hidden" name="action" value="dailypulse_demo_remove">
						<?php wp_nonce_field( 'dailypulse_demo_remove' ); ?>
						<?php submit_button( __( 'Remove demo content', 'dailypulse-core' ), 'delete', 'dailypulse-demo-remove', false ); ?>
					</form>
				<?php endif; ?>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Where things are', 'dailypulse-core' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'Write a story: Posts → Add New. Kicker, breaking, hero, credits, sources and gallery are in the boxes under the editor.', 'dailypulse-core' ); ?></li>
					<li><?php esc_html_e( 'Send a one-line alert to the ticker: Breaking Alerts → Add Alert.', 'dailypulse-core' ); ?></li>
					<li><?php esc_html_e( 'Category colour and homepage layout: Posts → Categories.', 'dailypulse-core' ); ?></li>
					<li><?php esc_html_e( 'Ticker, hero, article and footer settings: DailyPulse → Site Options.', 'dailypulse-core' ); ?></li>
					<li><?php esc_html_e( 'Automatic removal of old stories: Tools → Auto-Cleanup.', 'dailypulse-core' ); ?></li>
				</ul>
			</div>
		</div>
	</div>
	<?php
}
