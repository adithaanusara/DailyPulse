<?php
/**
 * Shown when there is nothing to list: an empty site, an empty archive, or a
 * search with no matches.
 *
 * @package DailyPulse
 *
 * @var array $args {
 *     @type string $title   Heading. Default "No stories yet".
 *     @type string $message Supporting text.
 * }
 */

$args = wp_parse_args(
	$args,
	array(
		'title'   => __( 'No stories yet', 'dailypulse' ),
		'message' => __( 'New stories will appear here as soon as they are published.', 'dailypulse' ),
	)
);
?>
<section class="container empty">
	<svg class="empty__line" viewBox="0 0 320 40" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
		<path pathLength="100" d="M2 22h96l10-16 14 30 12-22 8 8h176"/>
	</svg>
	<h2 class="empty__title"><?php echo esc_html( $args['title'] ); ?></h2>
	<p class="empty__text"><?php echo esc_html( $args['message'] ); ?></p>

	<?php if ( current_user_can( 'edit_posts' ) && ( is_front_page() || is_home() ) ) : ?>
		<p class="empty__actions">
			<a class="btn" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'Write the first story', 'dailypulse' ); ?></a>
			<?php if ( current_user_can( 'manage_options' ) && defined( 'DAILYPULSE_CORE_VERSION' ) ) : ?>
				<a class="btn btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=dailypulse-setup' ) ); ?>"><?php esc_html_e( 'Import demo content', 'dailypulse' ); ?></a>
			<?php endif; ?>
		</p>
	<?php endif; ?>
</section>
