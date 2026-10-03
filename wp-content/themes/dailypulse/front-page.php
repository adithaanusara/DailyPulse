<?php
/**
 * The homepage: dateline, hero slider, the filterable "Latest" feed, then one
 * section per homepage category.
 *
 * @package DailyPulse
 */

// "/page/2/" of the latest posts is an ordinary archive, not a second homepage.
if ( is_home() && is_paged() ) {
	require DAILYPULSE_DIR . '/index.php';
	return;
}

get_header();

$dailypulse_has_stories = wp_count_posts( 'post' )->publish > 0;
$dailypulse_updated     = $dailypulse_has_stories ? strtotime( get_lastpostmodified( 'gmt' ) . ' UTC' ) : 0;
?>

<div class="container dateline">
	<?php /* translators: date format for the homepage dateline, see https://www.php.net/manual/datetime.format.php */ ?>
	<time datetime="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"><?php echo esc_html( wp_date( _x( 'l, j F Y', 'dateline date format', 'dailypulse' ) ) ); ?></time>
	<span class="dateline__rule" aria-hidden="true"></span>
	<?php if ( $dailypulse_updated ) : ?>
		<span class="dateline__updated">
			<?php
			printf(
				/* translators: %s: how long ago, e.g. "5 minutes ago". */
				esc_html__( 'Updated %s', 'dailypulse' ),
				dailypulse_time_tag( $dailypulse_updated ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
			);
			?>
		</span>
	<?php endif; ?>
</div>

<?php
if ( ! $dailypulse_has_stories ) {
	get_template_part( 'template-parts/empty' );
	get_footer();
	return;
}

get_template_part( 'template-parts/hero' );
get_template_part( 'template-parts/feed' );

// "Automatic" layouts alternate so that neighbouring sections never look the same.
$dailypulse_cycle = array( 'lead', 'grid', 'magazine', 'band' );

foreach ( dailypulse_home_categories() as $dailypulse_index => $dailypulse_term ) {
	$dailypulse_layout = (string) dailypulse_category_setting( $dailypulse_term->term_id, 'dp_layout' );

	if ( ! in_array( $dailypulse_layout, $dailypulse_cycle, true ) ) {
		$dailypulse_layout = $dailypulse_cycle[ $dailypulse_index % count( $dailypulse_cycle ) ];
	}

	get_template_part(
		'template-parts/section',
		null,
		array(
			'term'   => $dailypulse_term,
			'layout' => $dailypulse_layout,
		)
	);
}

get_footer();
