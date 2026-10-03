<?php
/**
 * Site header: brand, section navigation, search and the light/dark toggle,
 * followed by the breaking-news ticker.
 *
 * @package DailyPulse
 */

?>
<!doctype html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'dailypulse' ); ?></a>

<header class="site-header" data-header>
	<div class="container site-header__inner">
		<?php // The site name is the page heading on the homepage only; elsewhere the story or archive title is. ?>
		<?php if ( is_front_page() && ! is_paged() ) : ?>
			<h1 class="site-header__brand"><?php dailypulse_brand(); ?></h1>
		<?php else : ?>
			<div class="site-header__brand"><?php dailypulse_brand(); ?></div>
		<?php endif; ?>

		<nav class="site-nav" aria-label="<?php esc_attr_e( 'Sections', 'dailypulse' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'site-nav__list',
					'depth'          => 1,
					'fallback_cb'    => 'dailypulse_nav_fallback',
				)
			);
			?>
		</nav>

		<div class="site-header__actions">
			<a class="icon-btn" href="<?php echo esc_url( home_url( '/?s=' ) ); ?>" data-search-open aria-label="<?php esc_attr_e( 'Search', 'dailypulse' ); ?>" aria-keyshortcuts="/">
				<?php echo dailypulse_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			</a>
			<button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="<?php esc_attr_e( 'Switch to dark mode', 'dailypulse' ); ?>">
				<?php echo dailypulse_icon( 'sun' ) . dailypulse_icon( 'moon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			</button>
		</div>
	</div>
</header>

<?php get_template_part( 'template-parts/ticker' ); ?>

<main id="main" class="site-main">
