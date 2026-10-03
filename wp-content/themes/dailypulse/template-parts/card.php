<?php
/**
 * A story card for the current post. One template, several looks:
 *
 *   stack    image on top, text below (the default grid card)
 *   lead     a larger stack card with the excerpt, for a section's top story
 *   row      small image beside the text, for compact lists
 *   overlay  text over the image, for the dark scroller
 *   text     no image; optionally numbered, for magazine-style lists
 *
 * @package DailyPulse
 *
 * @var array $args {
 *     @type string $variant One of the looks above. Default "stack".
 *     @type bool   $excerpt Show the excerpt. Default false.
 *     @type bool   $byline  Show the author credit. Default false.
 *     @type string $tag     Heading element. Default "h3".
 *     @type string $size    Image size. Default "medium_large".
 *     @type string $sizes   Image sizes attribute.
 *     @type bool   $eager   Load the image eagerly. Default false.
 *     @type int    $index   Position in its group; staggers the entrance animation.
 *     @type int    $number  Number to print on "text" cards. Default 0 (none).
 *     @type bool   $reveal  Fade in when scrolled into view. Default true.
 *     @type bool   $fresh   Card was just inserted by the script. Default false.
 * }
 */

$args = wp_parse_args(
	$args,
	array(
		'variant' => 'stack',
		'excerpt' => false,
		'byline'  => false,
		'tag'     => 'h3',
		'size'    => 'medium_large',
		'sizes'   => '(min-width: 1000px) 400px, (min-width: 640px) 46vw, 92vw',
		'eager'   => false,
		'index'   => 0,
		'number'  => 0,
		'reveal'  => true,
		'fresh'   => false,
	)
);

$dailypulse_variant = in_array( $args['variant'], array( 'stack', 'lead', 'row', 'overlay', 'text' ), true ) ? $args['variant'] : 'stack';
$dailypulse_tag     = in_array( $args['tag'], array( 'h2', 'h3', 'h4' ), true ) ? $args['tag'] : 'h3';
$dailypulse_classes = 'card card--' . $dailypulse_variant . ( $args['fresh'] ? ' is-fresh' : '' ) . ( has_post_thumbnail() ? '' : ' card--no-image' );

if ( 'row' === $dailypulse_variant ) {
	$args['size']  = 'medium';
	$args['sizes'] = '128px';
}
?>
<article
	class="<?php echo esc_attr( $dailypulse_classes ); ?>"
	<?php echo dailypulse_cat_style( dailypulse_primary_category(), '--i:' . min( 8, (int) $args['index'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
	<?php echo $args['reveal'] ? 'data-reveal' : ''; ?>
>
	<?php if ( 'text' !== $dailypulse_variant ) : ?>
		<div class="card__media">
			<?php
			dailypulse_image(
				array(
					'size'  => $args['size'],
					'sizes' => $args['sizes'],
					'eager' => $args['eager'],
				)
			);
			?>
		</div>
	<?php elseif ( $args['number'] ) : ?>
		<span class="card__num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $args['number'] ) ); ?></span>
	<?php endif; ?>

	<div class="card__body">
		<?php dailypulse_kicker(); ?>
		<<?php echo $dailypulse_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted above. ?> class="card__title">
			<a class="card__link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</<?php echo $dailypulse_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted above. ?>>
		<?php
		$dailypulse_excerpt = $args['excerpt'] ? dailypulse_excerpt() : '';
		if ( '' !== $dailypulse_excerpt ) :
			?>
			<p class="card__excerpt"><?php echo esc_html( $dailypulse_excerpt ); ?></p>
		<?php endif; ?>
		<?php
		dailypulse_meta(
			array(
				'byline'  => $args['byline'],
				'reading' => 'row' !== $dailypulse_variant,
			)
		);
		?>
	</div>
</article>
