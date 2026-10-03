<?php
/**
 * Homepage hero: a slider of the top stories with a headline rail beside it.
 *
 * Hovering or focusing a headline in the rail previews its slide; clicking opens
 * the story. On small screens the rail is hidden and the slider is swiped.
 *
 * @package DailyPulse
 */

$dailypulse_posts = dailypulse_hero_posts();

if ( ! $dailypulse_posts ) {
	return;
}

dailypulse_shown( wp_list_pluck( $dailypulse_posts, 'ID' ) );

$dailypulse_total    = count( $dailypulse_posts );
$dailypulse_many     = $dailypulse_total > 1;
$dailypulse_interval = (int) dailypulse_option( 'hero_interval' );
?>
<section
	class="container hero<?php echo $dailypulse_many ? '' : ' hero--single'; ?>"
	data-hero
	data-autoplay="<?php echo dailypulse_option( 'hero_autoplay' ) ? '1' : '0'; ?>"
	style="--hero-interval:<?php echo esc_attr( (string) $dailypulse_interval ); ?>s"
	aria-roledescription="<?php esc_attr_e( 'carousel', 'dailypulse' ); ?>"
	aria-label="<?php esc_attr_e( 'Top stories', 'dailypulse' ); ?>"
>
	<div class="hero__stage">
		<?php
		foreach ( $dailypulse_posts as $dailypulse_index => $post ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
			setup_postdata( $post );
			?>
			<article
				class="hero__slide<?php echo 0 === $dailypulse_index ? ' is-active' : ''; ?>"
				data-hero-slide
				aria-roledescription="<?php esc_attr_e( 'slide', 'dailypulse' ); ?>"
				<?php /* translators: 1: slide number, 2: number of slides. */ ?>
				aria-label="<?php echo esc_attr( sprintf( __( '%1$d of %2$d', 'dailypulse' ), $dailypulse_index + 1, $dailypulse_total ) ); ?>"
				<?php echo 0 === $dailypulse_index ? '' : 'inert'; ?>
				<?php echo dailypulse_cat_style( dailypulse_primary_category() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
			>
				<div class="hero__media">
					<?php
					dailypulse_image(
						array(
							'size'  => 'large',
							'sizes' => '(min-width: 1024px) min(860px, 64vw), 100vw',
							// Only the first slide is visible at load; it is the page's largest paint.
							'eager' => 0 === $dailypulse_index,
						)
					);
					?>
				</div>
				<div class="hero__content">
					<?php dailypulse_kicker( 0, false ); ?>
					<h2 class="hero__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php
					$dailypulse_excerpt = dailypulse_excerpt();
					if ( '' !== $dailypulse_excerpt ) :
						?>
						<p class="hero__excerpt"><?php echo esc_html( $dailypulse_excerpt ); ?></p>
					<?php endif; ?>
					<?php dailypulse_meta( array( 'byline' => true ) ); ?>
				</div>
			</article>
		<?php endforeach; ?>

		<?php if ( $dailypulse_many ) : ?>
			<div class="hero__controls">
				<span class="hero__count" aria-hidden="true"><b data-hero-count>01</b> / <?php echo esc_html( sprintf( '%02d', $dailypulse_total ) ); ?></span>
				<button class="hero__btn" type="button" data-hero-toggle aria-label="<?php esc_attr_e( 'Pause', 'dailypulse' ); ?>">
					<?php echo dailypulse_icon( 'pause' ) . dailypulse_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				</button>
				<button class="hero__btn" type="button" data-hero-prev aria-label="<?php esc_attr_e( 'Previous story', 'dailypulse' ); ?>">
					<?php echo dailypulse_icon( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				</button>
				<button class="hero__btn" type="button" data-hero-next aria-label="<?php esc_attr_e( 'Next story', 'dailypulse' ); ?>">
					<?php echo dailypulse_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				</button>
			</div>

			<div class="hero__progress">
				<?php foreach ( $dailypulse_posts as $dailypulse_index => $post ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below. ?>
					<button
						class="hero__dot<?php echo 0 === $dailypulse_index ? ' is-active' : ''; ?>"
						type="button"
						data-hero-go="<?php echo esc_attr( (string) $dailypulse_index ); ?>"
						<?php /* translators: 1: slide number, 2: story title. */ ?>
						aria-label="<?php echo esc_attr( sprintf( __( 'Story %1$d: %2$s', 'dailypulse' ), $dailypulse_index + 1, get_the_title( $post ) ) ); ?>"
					><span class="hero__dot-fill"></span></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $dailypulse_many ) : ?>
		<ol class="hero__rail">
			<?php
			foreach ( $dailypulse_posts as $dailypulse_index => $post ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
				setup_postdata( $post );
				?>
				<li
					class="hero__rail-item<?php echo 0 === $dailypulse_index ? ' is-active' : ''; ?>"
					data-hero-rail="<?php echo esc_attr( (string) $dailypulse_index ); ?>"
					<?php echo dailypulse_cat_style( dailypulse_primary_category(), '--i:' . $dailypulse_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
				>
					<span class="hero__rail-num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $dailypulse_index + 1 ) ); ?></span>
					<div class="hero__rail-body">
						<?php dailypulse_kicker( 0, false ); ?>
						<a class="hero__rail-link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						<?php echo dailypulse_time(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</section>
<?php
wp_reset_postdata();
