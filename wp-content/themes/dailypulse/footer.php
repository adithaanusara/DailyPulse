<?php
/**
 * Site footer, the search dialog and the back-to-top button.
 *
 * @package DailyPulse
 */

$dailypulse_about      = (string) dailypulse_option( 'footer_about' );
$dailypulse_social     = dailypulse_social_links();
$dailypulse_categories = dailypulse_home_categories();
?>
</main>

<footer class="site-footer">
	<div class="container site-footer__top">
		<div class="site-footer__about">
			<?php dailypulse_brand(); ?>
			<?php if ( '' !== $dailypulse_about ) : ?>
				<p><?php echo esc_html( $dailypulse_about ); ?></p>
			<?php endif; ?>

			<ul class="social">
				<?php foreach ( $dailypulse_social as $dailypulse_link ) : ?>
					<li>
						<a class="icon-btn" href="<?php echo esc_url( $dailypulse_link['url'] ); ?>" target="_blank" rel="noopener noreferrer me" aria-label="<?php echo esc_attr( $dailypulse_link['label'] ); ?>">
							<?php echo dailypulse_icon( $dailypulse_link['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						</a>
					</li>
				<?php endforeach; ?>
				<li>
					<a class="icon-btn" href="<?php echo esc_url( get_feed_link() ); ?>" aria-label="<?php esc_attr_e( 'RSS feed', 'dailypulse' ); ?>">
						<?php echo dailypulse_icon( 'rss' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					</a>
				</li>
			</ul>
		</div>

		<?php if ( $dailypulse_categories ) : ?>
			<nav class="site-footer__nav" aria-labelledby="footer-sections">
				<h2 id="footer-sections"><?php esc_html_e( 'Sections', 'dailypulse' ); ?></h2>
				<ul>
					<?php foreach ( $dailypulse_categories as $dailypulse_category ) : ?>
						<li>
							<a href="<?php echo esc_url( get_category_link( $dailypulse_category ) ); ?>"<?php echo dailypulse_cat_style( $dailypulse_category ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>>
								<?php echo esc_html( $dailypulse_category->name ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>

		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<nav class="site-footer__nav" aria-labelledby="footer-more">
				<h2 id="footer-more"><?php esc_html_e( 'More', 'dailypulse' ); ?></h2>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => false,
					)
				);
				?>
			</nav>
		<?php endif; ?>
	</div>

	<p class="site-footer__wordmark" aria-hidden="true"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>

	<div class="container site-footer__bottom">
		<p><?php echo esc_html( dailypulse_copyright() ); ?></p>
		<a href="#main"><?php esc_html_e( 'Back to top', 'dailypulse' ); ?> <?php echo dailypulse_icon( 'arrow-up' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
	</div>
</footer>

<dialog class="search-dialog" data-search aria-label="<?php esc_attr_e( 'Search stories', 'dailypulse' ); ?>">
	<div class="search-dialog__panel">
		<form class="search-dialog__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php echo dailypulse_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<label class="screen-reader-text" for="search-dialog-input"><?php esc_html_e( 'Search stories', 'dailypulse' ); ?></label>
			<input id="search-dialog-input" type="search" name="s" placeholder="<?php esc_attr_e( 'Search stories…', 'dailypulse' ); ?>" autocomplete="off" enterkeyhint="search">
			<button class="icon-btn" type="button" data-search-close aria-label="<?php esc_attr_e( 'Close search', 'dailypulse' ); ?>">
				<?php echo dailypulse_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			</button>
		</form>
		<div class="search-dialog__results" data-search-results aria-live="polite"></div>
		<p class="search-dialog__hint"><?php esc_html_e( 'Press Enter to see all results, Esc to close.', 'dailypulse' ); ?></p>
	</div>
</dialog>

<button class="to-top" type="button" data-to-top aria-label="<?php esc_attr_e( 'Back to top', 'dailypulse' ); ?>">
	<svg class="to-top__ring" viewBox="0 0 48 48" aria-hidden="true" focusable="false"><circle cx="24" cy="24" r="22" pathLength="100"/></svg>
	<?php echo dailypulse_icon( 'arrow-up' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
</button>

<?php wp_footer(); ?>
</body>
</html>
