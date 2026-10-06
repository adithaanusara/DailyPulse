<?php
/**
 * A homepage section for one category, in one of five layouts:
 *
 *   box       a bordered box in the main column: top story + headline list
 *   lead      one large story with a list of compact ones beside it
 *   grid      four equal cards
 *   magazine  a feature story with numbered, text-only reads beside it
 *   band      a dark, full-width band with a sideways scroller
 *
 * @package DailyPulse
 *
 * @var array $args {
 *     @type WP_Term $term   Category.
 *     @type string  $layout Layout name.
 * }
 */

if ( empty( $args['term'] ) || ! $args['term'] instanceof WP_Term ) {
	return;
}

$dailypulse_term   = $args['term'];
$dailypulse_layout = isset( $args['layout'] ) ? $args['layout'] : 'box';
$dailypulse_counts = array(
	'box'      => 4,
	'lead'     => 5,
	'grid'     => 4,
	'magazine' => 5,
	'band'     => 6,
);

if ( ! isset( $dailypulse_counts[ $dailypulse_layout ] ) ) {
	$dailypulse_layout = 'box';
}

$dailypulse_posts = dailypulse_section_posts( $dailypulse_term->term_id, $dailypulse_counts[ $dailypulse_layout ] );

if ( ! $dailypulse_posts ) {
	return;
}

$dailypulse_id   = 'section-' . $dailypulse_term->slug;
$dailypulse_head = array(
	'url' => get_category_link( $dailypulse_term ),
	'id'  => $dailypulse_id,
);

if ( 'band' === $dailypulse_layout ) {
	$dailypulse_head['after'] = sprintf(
		'<div class="scroller-nav"><button class="icon-btn" type="button" data-scroller-prev aria-label="%1$s">%2$s</button><button class="icon-btn" type="button" data-scroller-next aria-label="%3$s">%4$s</button></div>',
		esc_attr__( 'Scroll back', 'dailypulse' ),
		dailypulse_icon( 'arrow-left' ),
		esc_attr__( 'Scroll forward', 'dailypulse' ),
		dailypulse_icon( 'arrow-right' )
	);
}
?>
<section
	class="section section--<?php echo esc_attr( $dailypulse_layout ); ?><?php echo 'band' === $dailypulse_layout ? ' band' : ( 'box' === $dailypulse_layout ? ' box' : ' container' ); ?>"
	aria-labelledby="<?php echo esc_attr( $dailypulse_id ); ?>"
	<?php echo dailypulse_cat_style( $dailypulse_term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
	<?php echo 'band' === $dailypulse_layout ? 'data-scroller' : ''; ?>
>
	<?php if ( 'band' === $dailypulse_layout ) : ?>
		<div class="container">
	<?php endif; ?>

	<?php dailypulse_section_head( $dailypulse_term->name, $dailypulse_head ); ?>

	<?php if ( 'box' === $dailypulse_layout ) : ?>

		<?php
		$post = $dailypulse_posts[0]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
		setup_postdata( $post );
		dailypulse_card(
			array(
				'excerpt' => true,
				'sizes'   => '(min-width: 1024px) 400px, (min-width: 700px) 46vw, 92vw',
			)
		);
		?>
		<div class="box__list">
			<?php
			foreach ( array_slice( $dailypulse_posts, 1 ) as $dailypulse_index => $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
				setup_postdata( $post );
				dailypulse_card(
					array(
						'variant' => 'row',
						'index'   => $dailypulse_index + 1,
					)
				);
			}
			?>
		</div>

	<?php elseif ( 'lead' === $dailypulse_layout || 'magazine' === $dailypulse_layout ) : ?>

		<div class="split split--<?php echo esc_attr( $dailypulse_layout ); ?>">
			<div class="split__main">
				<?php
				$post = $dailypulse_posts[0]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
				setup_postdata( $post );
				dailypulse_card(
					array(
						'variant' => 'lead',
						'excerpt' => true,
						'byline'  => true,
						'size'    => 'large',
						'sizes'   => '(min-width: 900px) 680px, 92vw',
					)
				);
				?>
			</div>
			<div class="split__list">
				<?php
				foreach ( array_slice( $dailypulse_posts, 1 ) as $dailypulse_index => $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
					setup_postdata( $post );
					dailypulse_card(
						array(
							'variant' => 'magazine' === $dailypulse_layout ? 'text' : 'row',
							'byline'  => 'magazine' === $dailypulse_layout,
							'number'  => 'magazine' === $dailypulse_layout ? $dailypulse_index + 1 : 0,
							'index'   => $dailypulse_index + 1,
						)
					);
				}
				?>
			</div>
		</div>

	<?php elseif ( 'grid' === $dailypulse_layout ) : ?>

		<div class="grid grid--4">
			<?php
			foreach ( $dailypulse_posts as $dailypulse_index => $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
				setup_postdata( $post );
				dailypulse_card(
					array(
						'index' => $dailypulse_index,
						'sizes' => '(min-width: 1000px) 300px, (min-width: 640px) 46vw, 92vw',
					)
				);
			}
			?>
		</div>

	<?php else : ?>

		<div class="scroller" data-scroller-track tabindex="0" role="group" aria-labelledby="<?php echo esc_attr( $dailypulse_id ); ?>">
			<?php
			foreach ( $dailypulse_posts as $dailypulse_index => $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
				setup_postdata( $post );
				dailypulse_card(
					array(
						'variant' => 'overlay',
						'index'   => $dailypulse_index,
						'sizes'   => '(min-width: 640px) 320px, 78vw',
					)
				);
			}
			?>
		</div>

	<?php endif; ?>

	<?php if ( 'band' === $dailypulse_layout ) : ?>
		</div>
	<?php endif; ?>
</section>
<?php
wp_reset_postdata();
