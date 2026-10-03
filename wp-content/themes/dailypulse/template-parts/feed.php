<?php
/**
 * "Latest" feed with category filter tabs and a Load more button.
 *
 * Works without JavaScript — the tabs are ordinary links to the category
 * archives and Load more leads to page 2. With JavaScript the grid is filtered
 * in place through the REST endpoint in inc/rest.php.
 *
 * @package DailyPulse
 */

$dailypulse_exclude    = dailypulse_shown();
$dailypulse_per_page   = (int) dailypulse_option( 'feed_count' );
$dailypulse_query      = dailypulse_feed_query(
	array(
		'per_page' => $dailypulse_per_page,
		'exclude'  => $dailypulse_exclude,
	)
);
$dailypulse_categories = dailypulse_home_categories();

if ( ! $dailypulse_query->have_posts() ) {
	return;
}
?>
<section
	class="container section feed"
	id="latest"
	data-feed
	data-per-page="<?php echo esc_attr( (string) $dailypulse_per_page ); ?>"
	data-pages="<?php echo esc_attr( (string) $dailypulse_query->max_num_pages ); ?>"
	data-exclude="<?php echo esc_attr( implode( ',', $dailypulse_exclude ) ); ?>"
	aria-labelledby="latest-title"
>
	<header class="section__head section__head--tabs">
		<h2 class="section__title" id="latest-title"><?php esc_html_e( 'Latest', 'dailypulse' ); ?></h2>

		<?php if ( $dailypulse_categories ) : ?>
			<nav class="tabs" aria-label="<?php esc_attr_e( 'Filter the latest stories', 'dailypulse' ); ?>">
				<span class="tabs__indicator" aria-hidden="true"></span>
				<a class="tab" href="#latest" data-feed-tab data-cat="0" data-label="<?php esc_attr_e( 'All', 'dailypulse' ); ?>" aria-current="true"><?php esc_html_e( 'All', 'dailypulse' ); ?></a>
				<?php foreach ( $dailypulse_categories as $dailypulse_category ) : ?>
					<a
						class="tab"
						href="<?php echo esc_url( get_category_link( $dailypulse_category ) ); ?>"
						data-feed-tab
						data-cat="<?php echo esc_attr( (string) $dailypulse_category->term_id ); ?>"
						data-label="<?php echo esc_attr( $dailypulse_category->name ); ?>"
						<?php echo dailypulse_cat_style( $dailypulse_category ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
					><?php echo esc_html( $dailypulse_category->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</header>

	<div class="grid grid--3 feed__grid" data-feed-grid>
		<?php
		$dailypulse_index = 0;
		while ( $dailypulse_query->have_posts() ) {
			$dailypulse_query->the_post();
			dailypulse_card(
				array(
					'excerpt' => true,
					'index'   => $dailypulse_index % 3,
				)
			);
			++$dailypulse_index;
		}
		wp_reset_postdata();
		?>
	</div>

	<p class="feed__more"<?php echo $dailypulse_query->max_num_pages > 1 ? '' : ' hidden'; ?>>
		<a class="btn" href="<?php echo esc_url( get_pagenum_link( 2 ) ); ?>" data-feed-more>
			<span class="btn__label"><?php esc_html_e( 'Load more stories', 'dailypulse' ); ?></span>
			<span class="btn__spinner" aria-hidden="true"></span>
		</a>
	</p>

	<p class="screen-reader-text" role="status" data-feed-status></p>
</section>
