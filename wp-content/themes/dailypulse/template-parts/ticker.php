<?php
/**
 * Breaking-news ticker: one headline at a time, with previous / pause / next.
 *
 * Red and labelled "Breaking" while an alert or a breaking story is live;
 * otherwise a quiet strip of the latest headlines.
 *
 * @package DailyPulse
 */

$dailypulse_ticker = dailypulse_ticker();

if ( 'none' === $dailypulse_ticker['mode'] || ! $dailypulse_ticker['items'] ) {
	return;
}

$dailypulse_many = count( $dailypulse_ticker['items'] ) > 1;
?>
<aside class="ticker ticker--<?php echo esc_attr( $dailypulse_ticker['mode'] ); ?>" data-ticker data-interval="<?php echo esc_attr( (string) dailypulse_option( 'ticker_speed' ) ); ?>" aria-label="<?php echo esc_attr( $dailypulse_ticker['label'] ); ?>">
	<div class="ticker__inner">
		<p class="ticker__label">
			<span class="ticker__dot" aria-hidden="true"></span>
			<?php echo esc_html( $dailypulse_ticker['label'] ); ?>
		</p>

		<ul class="ticker__list">
			<?php foreach ( $dailypulse_ticker['items'] as $dailypulse_index => $dailypulse_item ) : ?>
				<li class="ticker__item<?php echo 0 === $dailypulse_index ? ' is-active' : ''; ?>">
					<?php if ( '' !== $dailypulse_item['url'] ) : ?>
						<a class="ticker__link" href="<?php echo esc_url( $dailypulse_item['url'] ); ?>"><?php echo esc_html( $dailypulse_item['title'] ); ?></a>
					<?php else : ?>
						<span class="ticker__link"><?php echo esc_html( $dailypulse_item['title'] ); ?></span>
					<?php endif; ?>
					<?php echo dailypulse_time_tag( $dailypulse_item['time'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $dailypulse_many ) : ?>
			<div class="ticker__controls">
				<button class="ticker__btn" type="button" data-ticker-prev aria-label="<?php esc_attr_e( 'Previous headline', 'dailypulse' ); ?>">
					<?php echo dailypulse_icon( 'chevron-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				</button>
				<button class="ticker__btn" type="button" data-ticker-toggle aria-label="<?php esc_attr_e( 'Pause', 'dailypulse' ); ?>">
					<?php echo dailypulse_icon( 'pause' ) . dailypulse_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				</button>
				<button class="ticker__btn" type="button" data-ticker-next aria-label="<?php esc_attr_e( 'Next headline', 'dailypulse' ); ?>">
					<?php echo dailypulse_icon( 'chevron-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				</button>
			</div>
		<?php endif; ?>
	</div>
</aside>
