<?php
/**
 * The story's source links (the "Source Links" field).
 *
 * @package DailyPulse
 */

$dailypulse_sources = dailypulse_sources();

if ( ! $dailypulse_sources ) {
	return;
}
?>
<aside class="container container--narrow sources" aria-labelledby="sources-title">
	<h2 class="sources__title" id="sources-title">
		<?php echo esc_html( _n( 'Source', 'Sources', count( $dailypulse_sources ), 'dailypulse' ) ); ?>
	</h2>
	<ul class="sources__list">
		<?php foreach ( $dailypulse_sources as $dailypulse_source ) : ?>
			<li>
				<?php if ( '' !== $dailypulse_source['url'] ) : ?>
					<a href="<?php echo esc_url( $dailypulse_source['url'] ); ?>" target="_blank" rel="nofollow noopener noreferrer external">
						<?php echo esc_html( $dailypulse_source['label'] ); ?>
						<?php echo dailypulse_icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'dailypulse' ); ?></span>
					</a>
				<?php else : ?>
					<span><?php echo esc_html( $dailypulse_source['label'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</aside>
