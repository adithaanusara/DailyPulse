<?php
/**
 * Page not found. On a site that removes old stories this is a page readers
 * will meet, so it explains itself and offers the latest stories instead.
 *
 * @package DailyPulse
 */

get_header();

$dailypulse_latest = get_posts(
	array(
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
?>

<section class="container not-found">
	<p class="not-found__code" aria-hidden="true">4<?php echo dailypulse_pulse_mark( 'not-found__pulse' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>4</p>
	<h1 class="not-found__title"><?php esc_html_e( 'This page has no pulse', 'dailypulse' ); ?></h1>
	<p class="not-found__text">
		<?php
		if ( class_exists( 'DailyPulse_Cleanup' ) ) {
			esc_html_e( 'The story may have been archived — we only keep the most recent news — or the link is mistyped.', 'dailypulse' );
		} else {
			esc_html_e( 'The page may have moved, or the link is mistyped.', 'dailypulse' );
		}
		?>
	</p>
	<?php get_search_form(); ?>
	<p><a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go to the front page', 'dailypulse' ); ?></a></p>
</section>

<?php if ( $dailypulse_latest ) : ?>
	<section class="container section" aria-labelledby="not-found-latest">
		<?php dailypulse_section_head( __( 'Latest stories', 'dailypulse' ), array( 'id' => 'not-found-latest' ) ); ?>
		<div class="grid grid--3">
			<?php
			foreach ( $dailypulse_latest as $dailypulse_index => $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
				setup_postdata( $post );
				dailypulse_card( array( 'index' => $dailypulse_index ) );
			}
			wp_reset_postdata();
			?>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();
