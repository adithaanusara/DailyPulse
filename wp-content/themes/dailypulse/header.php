<?php
/**
 * Site header, newspaper style: a top bar with the date, the breaking-news
 * ticker and social links; the centred masthead; and a dark, sticky menu bar
 * with search and the light/dark toggle.
 *
 * @package DailyPulse
 */

$dailypulse_social = dailypulse_social_links();
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

<div class="topbar">
	<div class="container topbar__inner">
		<?php /* translators: date format for the top bar, see https://www.php.net/manual/datetime.format.php */ ?>
		<time class="topbar__date" datetime="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"><?php echo dailypulse_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( wp_date( _x( 'l, F j, Y', 'top bar date format', 'dailypulse' ) ) ); ?></time>

		<?php get_template_part( 'template-parts/ticker' ); ?>

		<?php if ( $dailypulse_social ) : ?>
			<ul class="topbar__social">
				<?php foreach ( $dailypulse_social as $dailypulse_link ) : ?>
					<li>
						<a href="<?php echo esc_url( $dailypulse_link['url'] ); ?>" target="_blank" rel="noopener noreferrer me" aria-label="<?php echo esc_attr( $dailypulse_link['label'] ); ?>">
							<?php echo dailypulse_icon( $dailypulse_link['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>

<div class="masthead">
	<div class="container">
		<?php // The site name is the page heading on the homepage only; elsewhere the story or archive title is. ?>
		<?php if ( is_front_page() && ! is_paged() ) : ?>
			<h1 class="masthead__brand"><?php dailypulse_brand(); ?></h1>
		<?php else : ?>
			<div class="masthead__brand"><?php dailypulse_brand(); ?></div>
		<?php endif; ?>
		<?php if ( get_bloginfo( 'description' ) && ! has_custom_logo() ) : ?>
			<p class="masthead__tagline"><?php bloginfo( 'description' ); ?></p>
		<?php endif; ?>
	</div>
</div>

<header class="site-header" data-header>
	<div class="container site-header__inner">
		<nav class="site-nav" aria-label="<?php esc_attr_e( 'Sections', 'dailypulse' ); ?>">
			<a class="site-nav__home<?php echo is_front_page() ? ' is-current' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Home', 'dailypulse' ); ?></a>
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

<main id="main" class="site-main">
