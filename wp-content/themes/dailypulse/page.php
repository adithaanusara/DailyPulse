<?php
/**
 * A static page (About, Contact, …).
 *
 * @package DailyPulse
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'story story--page' ); ?>>
		<header class="container container--narrow story__header">
			<h1 class="story__title"><?php the_title(); ?></h1>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="container story__media">
				<?php
				the_post_thumbnail(
					'full',
					array(
						'sizes'         => '(min-width: 1180px) 1120px, 94vw',
						'loading'       => 'eager',
						'fetchpriority' => 'high',
					)
				);
				?>
			</figure>
		<?php endif; ?>

		<div class="container container--narrow story__body prose">
			<?php
			the_content();

			wp_link_pages(
				array(
					'before' => '<nav class="page-links" aria-label="' . esc_attr__( 'Pages', 'dailypulse' ) . '">',
					'after'  => '</nav>',
				)
			);
			?>
		</div>
	</article>
	<?php

	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}

endwhile;

get_footer();
