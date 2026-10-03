<?php
/**
 * Search results.
 *
 * @package DailyPulse
 */

get_header();

global $wp_query;

$dailypulse_total = (int) $wp_query->found_posts;
?>

<div class="container container--medium archive">
	<header class="page-head">
		<p class="page-head__eyebrow">
			<?php
			if ( '' === get_search_query() ) {
				esc_html_e( 'Search', 'dailypulse' );
			} else {
				/* translators: %s: number of results. */
				echo esc_html( sprintf( _n( '%s result', '%s results', $dailypulse_total, 'dailypulse' ), number_format_i18n( $dailypulse_total ) ) );
			}
			?>
		</p>
		<h1 class="page-head__title">
			<?php
			if ( '' === get_search_query() ) {
				esc_html_e( 'Search stories', 'dailypulse' );
			} else {
				/* translators: %s: search query. */
				printf( esc_html__( 'Results for “%s”', 'dailypulse' ), '<span>' . esc_html( get_search_query() ) . '</span>' );
			}
			?>
		</h1>
		<?php get_search_form(); ?>
	</header>

	<?php if ( '' !== get_search_query() && have_posts() ) : ?>

		<div class="results">
			<?php
			$dailypulse_index = 0;
			while ( have_posts() ) {
				the_post();

				if ( 'post' === get_post_type() ) {
					dailypulse_card(
						array(
							'variant' => 'row',
							'tag'     => 'h2',
							'excerpt' => true,
							'index'   => min( 6, $dailypulse_index ),
						)
					);
				} else {
					?>
					<article class="card card--text" data-reveal>
						<div class="card__body">
							<p class="kicker"><span class="kicker__cat"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ); ?></span></p>
							<h2 class="card__title"><a class="card__link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						</div>
					</article>
					<?php
				}

				++$dailypulse_index;
			}
			?>
		</div>

		<?php dailypulse_pagination(); ?>

	<?php elseif ( '' !== get_search_query() ) : ?>

		<?php
		get_template_part(
			'template-parts/empty',
			null,
			array(
				'title'   => __( 'Nothing matched', 'dailypulse' ),
				'message' => __( 'Try fewer or different words — or browse the sections in the menu above.', 'dailypulse' ),
			)
		);
		?>

	<?php endif; ?>
</div>

<?php
get_footer();
