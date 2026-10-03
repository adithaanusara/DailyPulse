<?php
/**
 * Search form used on the search results and 404 pages.
 *
 * @package DailyPulse
 */

$dailypulse_id = wp_unique_id( 'search-' );
?>
<form class="search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $dailypulse_id ); ?>"><?php esc_html_e( 'Search stories', 'dailypulse' ); ?></label>
	<?php echo dailypulse_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
	<input id="<?php echo esc_attr( $dailypulse_id ); ?>" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search stories…', 'dailypulse' ); ?>" enterkeyhint="search">
	<button class="btn" type="submit"><?php esc_html_e( 'Search', 'dailypulse' ); ?></button>
</form>
