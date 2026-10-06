<?php
/**
 * Stand-ins for the DailyPulse Core plugin.
 *
 * The plugin owns the data (story fields, alerts, category settings, options).
 * If it is switched off the theme must keep working, so each of its public
 * functions gets a minimal fallback here: sensible defaults, no extra features.
 *
 * @package DailyPulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dailypulse_option' ) ) {
	/**
	 * Site option with built-in defaults.
	 *
	 * @param string $key      Option key.
	 * @param mixed  $fallback Optional override for the default.
	 * @return mixed
	 */
	function dailypulse_option( $key, $fallback = null ) {
		$defaults = array(
			'accent_color'          => '#0b6fe0',
			'color_scheme'          => 'auto',
			'relative_dates'        => true,
			'ticker_enabled'        => true,
			'ticker_label'          => __( 'Breaking', 'dailypulse' ),
			'breaking_hours'        => 6,
			'ticker_fallback'       => true,
			'ticker_fallback_label' => __( 'Latest', 'dailypulse' ),
			'ticker_count'          => 6,
			'ticker_speed'          => 5,
			'hero_count'            => 5,
			'hero_autoplay'         => true,
			'hero_interval'         => 7,
			'feed_count'            => 8,
			'show_reading_time'     => true,
			'show_share'            => true,
			'show_progress'         => true,
			'related_count'         => 3,
			'footer_about'          => '',
			'footer_copyright'      => '© {year} {site}',
		);

		if ( null !== $fallback ) {
			return $fallback;
		}

		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}
}

if ( ! function_exists( 'dailypulse_field' ) ) {
	/**
	 * Story field. Without the plugin there are none.
	 *
	 * @param string $name    Field name.
	 * @param int    $post_id Post ID.
	 * @return mixed
	 */
	function dailypulse_field( $name, $post_id = 0 ) {
		unset( $post_id );

		if ( in_array( $name, array( 'dp_gallery', 'dp_sources' ), true ) ) {
			return array();
		}

		return in_array( $name, array( 'dp_breaking', 'dp_featured' ), true ) ? false : '';
	}
}

if ( ! function_exists( 'dailypulse_sources' ) ) {
	/**
	 * Source links of a story.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	function dailypulse_sources( $post_id = 0 ) {
		unset( $post_id );

		return array();
	}
}

if ( ! function_exists( 'dailypulse_gallery_ids' ) ) {
	/**
	 * Gallery images of a story.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	function dailypulse_gallery_ids( $post_id = 0 ) {
		unset( $post_id );

		return array();
	}
}

if ( ! function_exists( 'dailypulse_is_breaking' ) ) {
	/**
	 * Whether a story is breaking.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function dailypulse_is_breaking( $post_id = 0 ) {
		unset( $post_id );

		return false;
	}
}

if ( ! function_exists( 'dailypulse_ticker' ) ) {
	/**
	 * Ticker contents: just the latest headlines.
	 *
	 * @return array
	 */
	function dailypulse_ticker() {
		$items = array();

		foreach ( get_posts(
			array(
				'posts_per_page'      => 6,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		) as $story ) {
			$items[] = array(
				'title' => get_the_title( $story ),
				'url'   => get_permalink( $story ),
				'time'  => (int) get_post_time( 'U', true, $story ),
				'type'  => 'latest',
			);
		}

		return array(
			'mode'  => $items ? 'latest' : 'none',
			'label' => __( 'Latest', 'dailypulse' ),
			'items' => $items,
		);
	}
}

if ( ! function_exists( 'dailypulse_category_color' ) ) {
	/**
	 * Colour of a category, from a fixed palette.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	function dailypulse_category_color( $term_id ) {
		$palette = array( '#0d9488', '#2563eb', '#c026d3', '#4d9f0c', '#d97706', '#db2777', '#0891b2', '#7c3aed' );

		return $palette[ (int) $term_id % count( $palette ) ];
	}
}

if ( ! function_exists( 'dailypulse_category_setting' ) ) {
	/**
	 * Per-category setting defaults.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Setting key.
	 * @return mixed
	 */
	function dailypulse_category_setting( $term_id, $key ) {
		unset( $term_id );

		$defaults = array(
			'dp_home'   => true,
			'dp_order'  => 100,
			'dp_layout' => 'auto',
		);

		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}
}

if ( ! function_exists( 'dailypulse_home_categories' ) ) {
	/**
	 * Categories shown on the homepage: every top-level category with posts.
	 *
	 * @return WP_Term[]
	 */
	function dailypulse_home_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => true,
				'parent'     => 0,
			)
		);

		return is_wp_error( $terms ) ? array() : $terms;
	}
}

add_action( 'admin_notices', 'dailypulse_core_missing_notice' );

/**
 * Points administrators at the companion plugin when it is not active.
 */
function dailypulse_core_missing_notice() {
	if ( defined( 'DAILYPULSE_CORE_VERSION' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'themes', 'plugins' ), true ) ) {
		return;
	}

	printf(
		'<div class="notice notice-info"><p>%s</p></div>',
		esc_html__( 'The DailyPulse theme is running without the DailyPulse Core plugin. Activate it under Plugins to get story fields, breaking alerts, category colours and site options.', 'dailypulse' )
	);
}
