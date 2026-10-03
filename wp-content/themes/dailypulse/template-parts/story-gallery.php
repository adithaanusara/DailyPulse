<?php
/**
 * The story's media gallery (the "Media Gallery" field), with a lightbox.
 *
 * Each thumbnail links to the full image, so it still works without JavaScript.
 *
 * @package DailyPulse
 */

$dailypulse_ids = dailypulse_gallery_ids();

if ( ! $dailypulse_ids ) {
	return;
}

$dailypulse_count = count( $dailypulse_ids );
?>
<section class="container gallery" data-gallery aria-labelledby="gallery-title">
	<h2 class="gallery__title" id="gallery-title">
		<?php esc_html_e( 'Gallery', 'dailypulse' ); ?>
		<span>
			<?php
			/* translators: %s: number of photos. */
			echo esc_html( sprintf( _n( '%s photo', '%s photos', $dailypulse_count, 'dailypulse' ), number_format_i18n( $dailypulse_count ) ) );
			?>
		</span>
	</h2>

	<ul class="gallery__grid gallery__grid--<?php echo esc_attr( (string) min( 5, $dailypulse_count ) ); ?>">
		<?php foreach ( $dailypulse_ids as $dailypulse_index => $dailypulse_image_id ) : ?>
			<?php
			$dailypulse_full = wp_get_attachment_image_src( $dailypulse_image_id, 'full' );

			if ( ! $dailypulse_full ) {
				continue;
			}

			$dailypulse_alt     = (string) get_post_meta( $dailypulse_image_id, '_wp_attachment_image_alt', true );
			$dailypulse_caption = (string) wp_get_attachment_caption( $dailypulse_image_id );
			?>
			<li class="gallery__cell">
				<a
					class="gallery__item"
					href="<?php echo esc_url( $dailypulse_full[0] ); ?>"
					data-gallery-item
					data-caption="<?php echo esc_attr( $dailypulse_caption ); ?>"
					data-alt="<?php echo esc_attr( $dailypulse_alt ); ?>"
					<?php /* translators: 1: photo number, 2: number of photos. */ ?>
					aria-label="<?php echo esc_attr( sprintf( __( 'Open photo %1$d of %2$d', 'dailypulse' ), $dailypulse_index + 1, $dailypulse_count ) ); ?>"
				>
					<?php
					echo wp_get_attachment_image(
						$dailypulse_image_id,
						'medium_large',
						false,
						array(
							'sizes'   => '(min-width: 800px) 380px, 46vw',
							'loading' => 'lazy',
							'alt'     => $dailypulse_alt,
						)
					);
					?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>

<dialog class="lightbox" data-lightbox aria-label="<?php esc_attr_e( 'Photo gallery', 'dailypulse' ); ?>">
	<figure class="lightbox__figure">
		<img class="lightbox__img" src="" alt="">
		<figcaption class="lightbox__caption"></figcaption>
	</figure>
	<p class="lightbox__count" data-lightbox-count aria-live="polite"></p>
	<button class="lightbox__btn lightbox__btn--close" type="button" data-lightbox-close aria-label="<?php esc_attr_e( 'Close gallery', 'dailypulse' ); ?>"><?php echo dailypulse_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
	<?php if ( $dailypulse_count > 1 ) : ?>
		<button class="lightbox__btn lightbox__btn--prev" type="button" data-lightbox-prev aria-label="<?php esc_attr_e( 'Previous photo', 'dailypulse' ); ?>"><?php echo dailypulse_icon( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
		<button class="lightbox__btn lightbox__btn--next" type="button" data-lightbox-next aria-label="<?php esc_attr_e( 'Next photo', 'dailypulse' ); ?>"><?php echo dailypulse_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
	<?php endif; ?>
</dialog>
