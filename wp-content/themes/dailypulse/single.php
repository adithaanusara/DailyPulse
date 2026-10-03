<?php
/**
 * A single story.
 *
 * @package DailyPulse
 */

get_header();

while ( have_posts() ) :
	the_post();

	$dailypulse_category = dailypulse_primary_category();
	$dailypulse_credit   = (string) dailypulse_field( 'dp_photo_credit' );
	$dailypulse_caption  = has_post_thumbnail() ? wp_get_attachment_caption( get_post_thumbnail_id() ) : '';
	$dailypulse_modified = (int) get_post_modified_time( 'U', true );
	$dailypulse_updated  = $dailypulse_modified - (int) get_post_time( 'U', true ) > HOUR_IN_SECONDS;
	?>

	<?php if ( dailypulse_option( 'show_progress' ) ) : ?>
		<div class="reading-progress" data-progress aria-hidden="true"></div>
	<?php endif; ?>

	<article <?php post_class( 'story' ); ?><?php echo dailypulse_cat_style( $dailypulse_category ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>>
		<header class="container container--narrow story__header">
			<?php dailypulse_kicker(); ?>
			<h1 class="story__title"><?php the_title(); ?></h1>

			<?php if ( has_excerpt() ) : ?>
				<p class="story__deck"><?php echo esc_html( dailypulse_excerpt() ); ?></p>
			<?php endif; ?>

			<div class="story__byline">
				<div class="story__credit">
					<span class="story__avatar" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( dailypulse_byline(), 0, 1 ) ) ); ?></span>
					<div>
						<p class="story__author">
							<?php
							/* translators: %s: author name. */
							echo esc_html( sprintf( __( 'By %s', 'dailypulse' ), dailypulse_byline() ) );
							?>
						</p>
						<p class="story__dates">
							<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() . ' · ' . get_the_time() ); ?></time>
							<?php if ( $dailypulse_updated ) : ?>
								<span class="meta__sep" aria-hidden="true">·</span>
								<?php
								printf(
									/* translators: %s: how long ago or a date. */
									esc_html__( 'Updated %s', 'dailypulse' ),
									dailypulse_time_tag( $dailypulse_modified, get_the_modified_date() ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
								);
								?>
							<?php endif; ?>
							<?php if ( dailypulse_option( 'show_reading_time' ) ) : ?>
								<span class="meta__sep" aria-hidden="true">·</span>
								<?php
								/* translators: %s: number of minutes. */
								echo esc_html( sprintf( __( '%s min read', 'dailypulse' ), number_format_i18n( dailypulse_reading_time() ) ) );
								?>
							<?php endif; ?>
						</p>
					</div>
				</div>
				<?php dailypulse_share(); ?>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="container story__media">
				<?php
				the_post_thumbnail(
					'full',
					array(
						'sizes'         => '(min-width: 1180px) 1120px, 94vw',
						'loading'       => 'eager',
						'decoding'      => 'async',
						'fetchpriority' => 'high',
					)
				);
				?>
				<?php if ( $dailypulse_caption || $dailypulse_credit ) : ?>
					<figcaption>
						<?php echo esc_html( $dailypulse_caption ); ?>
						<?php if ( $dailypulse_credit ) : ?>
							<span class="story__photo-credit"><?php echo dailypulse_icon( 'camera' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $dailypulse_credit ); ?></span>
						<?php endif; ?>
					</figcaption>
				<?php endif; ?>
			</figure>
		<?php endif; ?>

		<div class="container container--narrow story__body prose">
			<?php
			the_content();

			wp_link_pages(
				array(
					'before' => '<nav class="page-links" aria-label="' . esc_attr__( 'Story pages', 'dailypulse' ) . '">',
					'after'  => '</nav>',
				)
			);
			?>
		</div>

		<?php
		get_template_part( 'template-parts/story-gallery' );
		get_template_part( 'template-parts/story-sources' );
		?>

		<footer class="container container--narrow story__footer">
			<?php if ( has_tag() ) : ?>
				<ul class="tags" aria-label="<?php esc_attr_e( 'Topics', 'dailypulse' ); ?>">
					<?php foreach ( get_the_tags() as $dailypulse_tag ) : ?>
						<li><a href="<?php echo esc_url( get_tag_link( $dailypulse_tag ) ); ?>" rel="tag"><?php echo esc_html( $dailypulse_tag->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( dailypulse_option( 'show_share' ) ) : ?>
				<div class="story__share">
					<p><?php esc_html_e( 'Share this story', 'dailypulse' ); ?></p>
					<?php dailypulse_share(); ?>
				</div>
			<?php endif; ?>
		</footer>
	</article>

	<?php
	$dailypulse_related = dailypulse_related_posts( get_the_ID(), (int) dailypulse_option( 'related_count' ) );

	if ( $dailypulse_related ) :
		?>
		<section class="container section related"<?php echo dailypulse_cat_style( $dailypulse_category ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?> aria-labelledby="related-title">
			<?php
			dailypulse_section_head(
				/* translators: %s: category name. */
				$dailypulse_category ? sprintf( __( 'More from %s', 'dailypulse' ), $dailypulse_category->name ) : __( 'More stories', 'dailypulse' ),
				array(
					'url' => $dailypulse_category ? get_category_link( $dailypulse_category ) : '',
					'id'  => 'related-title',
				)
			);
			?>
			<div class="grid grid--3">
				<?php
				foreach ( $dailypulse_related as $dailypulse_index => $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
					setup_postdata( $post );
					dailypulse_card( array( 'index' => $dailypulse_index ) );
				}
				wp_reset_postdata();
				?>
			</div>
		</section>
		<?php
	endif;

	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}

endwhile;

get_footer();
