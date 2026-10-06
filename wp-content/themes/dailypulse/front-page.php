<?php
/**
 * The homepage, laid out like a newspaper front page:
 *
 *   featured stories (slider + two tiles)
 *   main column: "What's new" with filter tabs, then a box per category
 *   sidebar:     "Just in" headlines, sections, follow links
 *   full-width category sections (for categories set to a full-width layout)
 *
 * @package DailyPulse
 */

// "/page/2/" of the latest posts is an ordinary archive, not a second homepage.
if ( is_home() && is_paged() ) {
	require DAILYPULSE_DIR . '/index.php';
	return;
}

get_header();

if ( wp_count_posts( 'post' )->publish < 1 ) {
	get_template_part( 'template-parts/empty' );
	get_footer();
	return;
}

get_template_part( 'template-parts/hero' );

$dailypulse_boxed  = array();
$dailypulse_wide   = array();
$dailypulse_social = dailypulse_social_links();

foreach ( dailypulse_home_categories() as $dailypulse_term ) {
	$dailypulse_layout = (string) dailypulse_category_setting( $dailypulse_term->term_id, 'dp_layout' );

	if ( in_array( $dailypulse_layout, array( 'lead', 'grid', 'magazine', 'band' ), true ) ) {
		$dailypulse_wide[] = array( $dailypulse_term, $dailypulse_layout );
	} else {
		$dailypulse_boxed[] = $dailypulse_term;
	}
}
?>

<div class="container layout">
	<div class="layout__main">
		<?php get_template_part( 'template-parts/feed' ); ?>

		<?php if ( $dailypulse_boxed ) : ?>
			<div class="boxes">
				<?php
				foreach ( $dailypulse_boxed as $dailypulse_term ) {
					get_template_part(
						'template-parts/section',
						null,
						array(
							'term'   => $dailypulse_term,
							'layout' => 'box',
						)
					);
				}
				?>
			</div>
		<?php endif; ?>
	</div>

	<aside class="layout__side" aria-label="<?php esc_attr_e( 'More headlines', 'dailypulse' ); ?>">
		<section class="box" aria-labelledby="just-in-title">
			<?php dailypulse_section_head( __( 'Just in', 'dailypulse' ), array( 'id' => 'just-in-title' ) ); ?>
			<div class="box__list box__list--numbered">
				<?php
				foreach ( dailypulse_sidebar_posts() as $dailypulse_index => $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
					setup_postdata( $post );
					dailypulse_card(
						array(
							'variant' => 'text',
							'number'  => $dailypulse_index + 1,
							'index'   => $dailypulse_index,
						)
					);
				}
				wp_reset_postdata();
				?>
			</div>
		</section>

		<?php if ( $dailypulse_social ) : ?>
			<section class="box" aria-labelledby="follow-title">
				<?php dailypulse_section_head( __( 'Follow us', 'dailypulse' ), array( 'id' => 'follow-title' ) ); ?>
				<ul class="follow">
					<?php foreach ( $dailypulse_social as $dailypulse_link ) : ?>
						<li>
							<a class="follow__link follow__link--<?php echo esc_attr( $dailypulse_link['icon'] ); ?>" href="<?php echo esc_url( $dailypulse_link['url'] ); ?>" target="_blank" rel="noopener noreferrer me">
								<?php echo dailypulse_icon( $dailypulse_link['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								<?php echo esc_html( $dailypulse_link['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	</aside>
</div>

<?php
foreach ( $dailypulse_wide as $dailypulse_section ) {
	get_template_part(
		'template-parts/section',
		null,
		array(
			'term'   => $dailypulse_section[0],
			'layout' => $dailypulse_section[1],
		)
	);
}

get_footer();
