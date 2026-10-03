<?php
/**
 * Styles, scripts and everything that affects how fast the page loads.
 *
 * The front end is one stylesheet and one deferred script. No jQuery, no
 * framework, no web-font request to a third party.
 *
 * @package DailyPulse
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'dailypulse_remove_bloat' );
add_action( 'wp_head', 'dailypulse_head_boot', 0 );
add_action( 'wp_enqueue_scripts', 'dailypulse_assets' );
add_action( 'wp_enqueue_scripts', 'dailypulse_trim_block_styles', 100 );
add_filter( 'wp_preload_resources', 'dailypulse_preload_fonts' );
add_filter( 'should_load_separate_core_block_assets', '__return_true' );
add_filter( 'wp_speculation_rules_configuration', 'dailypulse_speculation_rules' );

/**
 * Cache-busting version for a theme file: its modification time.
 *
 * @param string $path Path relative to the theme directory.
 * @return string
 */
function dailypulse_asset_version( $path ) {
	$file = DAILYPULSE_DIR . $path;

	return file_exists( $file ) ? (string) filemtime( $file ) : DAILYPULSE_VERSION;
}

/**
 * Black or white, whichever reads better on the given colour.
 *
 * @param string $hex Hex colour.
 * @return string
 */
function dailypulse_readable_on( $hex ) {
	$hex = ltrim( (string) $hex, '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return '#ffffff';
	}

	$channels = array();

	foreach ( str_split( $hex, 2 ) as $pair ) {
		$value      = hexdec( $pair ) / 255;
		$channels[] = $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}

	$luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

	// Contrast against white is higher than against near-black below this luminance.
	return $luminance < 0.4 ? '#ffffff' : '#15130f';
}

/**
 * Drops head output a news site does not need.
 */
function dailypulse_remove_bloat() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
}

/**
 * Runs before anything paints: picks light or dark so the page never flashes
 * the wrong theme, and marks the document as script-enabled.
 */
function dailypulse_head_boot() {
	$scheme = dailypulse_option( 'color_scheme' );

	echo '<meta name="color-scheme" content="light dark">' . "\n";
	echo '<meta name="theme-color" content="#faf9f6">' . "\n";

	$script = '(function(d,s){var t,m;try{t=localStorage.getItem("dailypulse-theme")}catch(e){}'
		. 'if(t!=="light"&&t!=="dark"){t=s==="auto"?(window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):s}'
		. 'd.setAttribute("data-theme",t);d.className=d.className.replace("no-js","js");'
		. 'm=document.querySelector(\'meta[name="theme-color"]\');if(m){m.content=t==="dark"?"#0d0e11":"#faf9f6"}'
		. '})(document.documentElement,' . wp_json_encode( $scheme ) . ');';

	wp_print_inline_script_tag( $script );
}

/**
 * Enqueues the stylesheet and the script.
 */
function dailypulse_assets() {
	$css = '/assets/css/main.css';
	$js  = '/assets/js/main.js';

	wp_enqueue_style( 'dailypulse', DAILYPULSE_URI . $css, array(), dailypulse_asset_version( $css ) );

	$accent = sanitize_hex_color( (string) dailypulse_option( 'accent_color' ) );

	if ( $accent && '#e5352b' !== strtolower( $accent ) ) {
		wp_add_inline_style( 'dailypulse', sprintf( ':root{--brand:%1$s;--on-brand:%2$s}', $accent, dailypulse_readable_on( $accent ) ) );
	}

	wp_enqueue_script(
		'dailypulse',
		DAILYPULSE_URI . $js,
		array(),
		dailypulse_asset_version( $js ),
		array(
			'strategy'  => 'defer',
			'in_footer' => false,
		)
	);

	$config = array(
		'feedUrl'       => esc_url_raw( rest_url( 'dailypulse/v1/feed' ) ),
		'locale'        => get_bloginfo( 'language' ),
		'scheme'        => dailypulse_option( 'color_scheme' ),
		'relativeDates' => (bool) dailypulse_option( 'relative_dates' ),
		'i18n'          => array(
			'toDark'    => __( 'Switch to dark mode', 'dailypulse' ),
			'toLight'   => __( 'Switch to light mode', 'dailypulse' ),
			'pause'     => __( 'Pause', 'dailypulse' ),
			'play'      => __( 'Play', 'dailypulse' ),
			'justNow'   => __( 'Just now', 'dailypulse' ),
			'loadError' => __( 'Could not load stories. Check your connection and try again.', 'dailypulse' ),
			'noStories' => __( 'No stories here yet.', 'dailypulse' ),
			'noResults' => __( 'No stories match that search.', 'dailypulse' ),
			/* translators: %s: number of stories. */
			'updated'   => __( '%s stories shown', 'dailypulse' ),
			'copied'    => __( 'Link copied', 'dailypulse' ),
		),
	);

	wp_add_inline_script( 'dailypulse', 'window.dailypulseConfig = ' . wp_json_encode( $config ) . ';', 'before' );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

/**
 * The homepage, archives and search contain no blocks, so the block and global
 * stylesheets would be dead weight there. Article and page views keep them.
 */
function dailypulse_trim_block_styles() {
	/**
	 * Filters whether block styles are removed from views without block content.
	 *
	 * @param bool $trim Default true.
	 */
	if ( is_singular() || ! apply_filters( 'dailypulse_trim_block_styles', true ) ) {
		return;
	}

	foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'core-block-supports' ) as $handle ) {
		wp_dequeue_style( $handle );
	}

	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
}

/**
 * Preloads the two fonts every page needs, so text renders in its final face
 * without a visible swap. Sinhala and Tamil sites preload their script instead.
 *
 * @param array $resources Resources to preload.
 * @return array
 */
function dailypulse_preload_fonts( $resources ) {
	$language = strtolower( substr( get_locale(), 0, 2 ) );

	if ( 'si' === $language ) {
		$fonts = array( 'noto-sans-sinhala-sinhala-wght-normal.woff2', 'noto-serif-sinhala-sinhala-wght-normal.woff2' );
	} elseif ( 'ta' === $language ) {
		$fonts = array( 'noto-sans-tamil-tamil-wght-normal.woff2', 'noto-serif-tamil-tamil-wght-normal.woff2' );
	} else {
		$fonts = array( 'inter-latin-wght-normal.woff2', 'fraunces-latin-opsz-normal.woff2' );
	}

	foreach ( $fonts as $font ) {
		$resources[] = array(
			'href'        => DAILYPULSE_URI . '/assets/fonts/' . $font,
			'as'          => 'font',
			'type'        => 'font/woff2',
			'crossorigin' => 'anonymous',
		);
	}

	return $resources;
}

/**
 * Lets the browser fetch a story as soon as the reader hovers its link, so the
 * next page opens almost instantly (WordPress 6.8+ Speculation Rules).
 *
 * @param array|null $config Speculation rules configuration, or null when disabled.
 * @return array|null
 */
function dailypulse_speculation_rules( $config ) {
	if ( is_array( $config ) ) {
		$config['mode']      = 'prefetch';
		$config['eagerness'] = 'moderate';
	}

	return $config;
}
