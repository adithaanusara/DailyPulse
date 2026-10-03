<?php
/**
 * Story listings: category, tag, author and date archives, and "all stories".
 *
 * Every page opens with one large story followed by a three-column grid, so
 * with WordPress' default of ten per page the grid always ends on a full row.
 *
 * @package DailyPulse
 */

get_header();

$dailypulse_term  = is_category() ? get_queried_object() : null;
$dailypulse_title = is_archive() ? get_the_archive_title() : __( 'All stories', 'dailypulse' );
$dailypulse_about = is_archive() ? get_the_archive_description() : '';
$dailypulse_paged = max( 1, (int) get_query_var( 'paged' ) );
?>

<div class="container archive"<?php echo dailypulse_cat_style( $dailypulse_term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>>
	<header class="page-head">
		<p class="page-head__eyebrow">
			<?php
			if ( is_category() ) {
				esc_html_e( 'Section', 'dailypulse' );
			} elseif ( is_tag() ) {
				esc_html_e( 'Topic', 'dailypulse' );
			} elseif ( is_author() ) {
				esc_html_e( 'Author', 'dailypulse' );
			} else {
				esc_html_e( 'Archive', 'dailypulse' );
			}

			if ( $dailypulse_paged > 1 ) {
				/* translators: %s: page number. */
				echo ' · ' . esc_html( sprintf( __( 'Page %s', 'dailypulse' ), number_format_i18n( $dailypulse_paged ) ) );
			}
			?>
		</p>
		<h1 class="page-head__title"><?php echo wp_kses_post( $dailypulse_title ); ?></h1>
		<?php if ( $dailypulse_about ) : ?>
			<div class="page-head__text"><?php echo wp_kses_post( $dailypulse_about ); ?></div>
		<?php endif; ?>
	</header>

	<?php if ( have_posts() ) : ?>

		<?php
		the_post();
		dailypulse_card(
			array(
				'variant' => 'lead',
				'tag'     => 'h2',
				'excerpt' => true,
				'byline'  => true,
				'size'    => 'large',
				'sizes'   => '(min-width: 900px) 720px, 92vw',
				'eager'   => true,
				'reveal'  => false,
			)
		);
		?>

		<?php if ( have_posts() ) : ?>
			<div class="grid grid--3 archive__grid">
				<?php
				$dailypulse_index = 0;
				while ( have_posts() ) {
					the_post();
					dailypulse_card(
						array(
							'tag'     => 'h2',
							'excerpt' => true,
							'index'   => $dailypulse_index % 3,
						)
					);
					++$dailypulse_index;
				}
				?>
			</div>
		<?php endif; ?>

		<?php dailypulse_pagination(); ?>

	<?php else : ?>

		<?php get_template_part( 'template-parts/empty' ); ?>

	<?php endif; ?>
</div>

<?php
get_footer();
