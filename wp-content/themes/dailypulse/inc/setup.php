<?php
/**
 * Theme supports, menus and small front-end filters.
 *
 * @package DailyPulse
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'dailypulse_setup' );
add_filter( 'excerpt_length', 'dailypulse_excerpt_length' );
add_filter( 'excerpt_more', 'dailypulse_excerpt_more' );
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
add_filter( 'nav_menu_link_attributes', 'dailypulse_menu_link_attributes', 10, 2 );
add_filter( 'body_class', 'dailypulse_body_class' );

/**
 * Registers what the theme supports.
 */
function dailypulse_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support(
		'custom-logo',
		array(
			'width'       => 360,
			'height'      => 80,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	// Make the block editor look like the published article.
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );

	register_nav_menus(
		array(
			'primary' => __( 'Header', 'dailypulse' ),
			'footer'  => __( 'Footer', 'dailypulse' ),
		)
	);

	// Widest an image can be shown: the wide article image.
	$GLOBALS['content_width'] = 1120;
}

/**
 * Excerpts are short decks, not paragraphs.
 *
 * @return int
 */
function dailypulse_excerpt_length() {
	return 26;
}

/**
 * Ends trimmed excerpts with an ellipsis instead of "[…]".
 *
 * @return string
 */
function dailypulse_excerpt_more() {
	return '…';
}

/**
 * Passes each category's colour to its menu link.
 *
 * @param array   $atts Link attributes.
 * @param WP_Post $item Menu item.
 * @return array
 */
function dailypulse_menu_link_attributes( $atts, $item ) {
	if ( 'taxonomy' === $item->type && 'category' === $item->object ) {
		$style         = isset( $atts['style'] ) ? rtrim( (string) $atts['style'], '; ' ) . ';' : '';
		$atts['style'] = $style . '--cat:' . dailypulse_category_color( (int) $item->object_id );
	}

	return $atts;
}

/**
 * Body classes used by the stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function dailypulse_body_class( $classes ) {
	$ticker = dailypulse_ticker();

	if ( 'none' !== $ticker['mode'] ) {
		$classes[] = 'has-ticker';
	}

	return $classes;
}
