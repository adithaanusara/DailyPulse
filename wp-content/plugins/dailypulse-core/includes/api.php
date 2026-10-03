<?php
/**
 * Public functions the theme (or any other code) uses to read DailyPulse data.
 *
 * Values are read straight from post meta / options, which is also where ACF
 * stores them, so these work identically with or without ACF.
 *
 * @package DailyPulse_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads a site option, falling back to the default from the schema.
 *
 * @param string $key      Option key, e.g. "ticker_label".
 * @param mixed  $fallback Optional. Overrides the schema default.
 * @return mixed Boolean for toggles, integer for numbers, string otherwise.
 */
function dailypulse_option( $key, $fallback = null ) {
	$fields = dailypulse_option_fields();
	$field  = isset( $fields[ $key ] ) ? $fields[ $key ] : array();
	$type   = isset( $field['type'] ) ? $field['type'] : 'text';

	if ( null === $fallback ) {
		$fallback = isset( $field['default'] ) ? $field['default'] : '';
	}

	// Stored under the same name an ACF options page uses.
	$value = get_option( 'options_dp_' . $key, null );
	$unset = ( null === $value || false === $value );

	switch ( $type ) {
		case 'true_false':
			return $unset ? (bool) $fallback : ( ! empty( $value ) && '0' !== $value );

		case 'number':
			$number = ( $unset || ! is_numeric( $value ) ) ? (int) $fallback : (int) $value;
			if ( isset( $field['min'] ) ) {
				$number = max( (int) $field['min'], $number );
			}
			if ( isset( $field['max'] ) ) {
				$number = min( (int) $field['max'], $number );
			}
			return $number;

		case 'select':
			return ( ! $unset && isset( $field['choices'][ (string) $value ] ) ) ? (string) $value : $fallback;

		case 'color':
			$color = $unset ? '' : sanitize_hex_color( (string) $value );
			return $color ? $color : $fallback;

		default:
			if ( $unset || ( '' === $value && ! empty( $field['required'] ) ) ) {
				return $fallback;
			}
			return (string) $value;
	}
}

/**
 * Reads a story field.
 *
 * @param string $name    Field name, e.g. "dp_kicker".
 * @param int    $post_id Optional. Defaults to the current post.
 * @return mixed Boolean for toggles, integer for numbers, array for gallery and
 *               sources, string otherwise. Null for unknown fields.
 */
function dailypulse_field( $name, $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	$fields  = dailypulse_fields();

	if ( ! $post_id || ! isset( $fields[ $name ] ) ) {
		return null;
	}

	$field = $fields[ $name ];
	$value = get_post_meta( $post_id, $name, true );

	switch ( $field['type'] ) {
		case 'true_false':
			return ! empty( $value ) && '0' !== $value;

		case 'number':
			return is_numeric( $value ) ? (int) $value : ( isset( $field['default'] ) ? (int) $field['default'] : 0 );

		case 'gallery':
		case 'sources':
			return dailypulse_sanitize_value( $field, $value );

		default:
			return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}

/**
 * Source links of a story.
 *
 * @param int $post_id Optional. Defaults to the current post.
 * @return array[] Rows of `label` and `url` (the URL may be empty).
 */
function dailypulse_sources( $post_id = 0 ) {
	$sources = dailypulse_field( 'dp_sources', $post_id );

	return is_array( $sources ) ? $sources : array();
}

/**
 * Gallery images of a story.
 *
 * @param int $post_id Optional. Defaults to the current post.
 * @return int[] IDs of image attachments that still exist.
 */
function dailypulse_gallery_ids( $post_id = 0 ) {
	$ids = dailypulse_field( 'dp_gallery', $post_id );

	if ( ! is_array( $ids ) || ! $ids ) {
		return array();
	}

	_prime_post_caches( $ids, false, true );

	return array_values( array_filter( $ids, 'wp_attachment_is_image' ) );
}

/**
 * Whether a story is currently breaking, i.e. flagged and still inside its window.
 *
 * @param int $post_id Optional. Defaults to the current post.
 * @return bool
 */
function dailypulse_is_breaking( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();

	return $post_id && (int) get_post_meta( $post_id, 'dp_breaking_until', true ) > time();
}

/**
 * What the ticker should show right now.
 *
 * Breaking alerts and breaking stories take priority. When there are none and the
 * fallback is enabled, the latest headlines are returned instead.
 *
 * @return array {
 *     @type string  $mode  "breaking", "latest" or "none".
 *     @type string  $label Label for the ticker.
 *     @type array[] $items Each with `title`, `url` (may be empty), `time` (Unix) and `type`.
 * }
 */
function dailypulse_ticker() {
	static $ticker = null;

	if ( null !== $ticker ) {
		return $ticker;
	}

	$ticker = array(
		'mode'  => 'none',
		'label' => '',
		'items' => array(),
	);

	if ( ! dailypulse_option( 'ticker_enabled' ) ) {
		return $ticker;
	}

	$count = dailypulse_option( 'ticker_count' );
	// Rounded to the minute so the query is cacheable for a moment.
	$now   = time() - ( time() % MINUTE_IN_SECONDS );
	$items = array();

	$alerts = get_posts(
		array(
			'post_type'      => 'dp_alert',
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'no_found_rows'  => true,
			'date_query'     => array(
				array(
					'column' => 'post_date_gmt',
					'after'  => gmdate( 'Y-m-d H:i:s', $now - 72 * HOUR_IN_SECONDS ),
				),
			),
		)
	);

	foreach ( $alerts as $alert ) {
		$published = (int) get_post_time( 'U', true, $alert );
		$hours     = (int) get_post_meta( $alert->ID, 'dp_alert_hours', true );
		$hours     = $hours > 0 ? $hours : 6;

		if ( $published + $hours * HOUR_IN_SECONDS > $now ) {
			$items[] = array(
				'title' => get_the_title( $alert ),
				'url'   => (string) get_post_meta( $alert->ID, 'dp_alert_url', true ),
				'time'  => $published,
				'type'  => 'alert',
			);
		}
	}

	$breaking = get_posts(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $count,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery -- small, bounded lookup.
			'meta_query'          => array(
				array(
					'key'     => 'dp_breaking_until',
					'value'   => $now,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	foreach ( $breaking as $story ) {
		$items[] = array(
			'title' => get_the_title( $story ),
			'url'   => get_permalink( $story ),
			'time'  => (int) get_post_time( 'U', true, $story ),
			'type'  => 'breaking',
		);
	}

	if ( $items ) {
		usort(
			$items,
			static function ( $a, $b ) {
				return $b['time'] <=> $a['time'];
			}
		);

		$ticker = array(
			'mode'  => 'breaking',
			'label' => dailypulse_option( 'ticker_label' ),
			'items' => array_slice( $items, 0, $count ),
		);

		return $ticker;
	}

	if ( ! dailypulse_option( 'ticker_fallback' ) ) {
		return $ticker;
	}

	$latest = get_posts(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $count,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);

	foreach ( $latest as $story ) {
		$items[] = array(
			'title' => get_the_title( $story ),
			'url'   => get_permalink( $story ),
			'time'  => (int) get_post_time( 'U', true, $story ),
			'type'  => 'latest',
		);
	}

	if ( $items ) {
		$ticker = array(
			'mode'  => 'latest',
			'label' => dailypulse_option( 'ticker_fallback_label' ),
			'items' => $items,
		);
	}

	return $ticker;
}

/**
 * Reads a per-category setting.
 *
 * @param int    $term_id Category term ID.
 * @param string $key     Meta key: dp_color, dp_home, dp_order or dp_layout.
 * @return mixed
 */
function dailypulse_category_setting( $term_id, $key ) {
	$fields = dailypulse_category_fields();

	if ( ! isset( $fields[ $key ] ) ) {
		return null;
	}

	$field = $fields[ $key ];
	$value = get_term_meta( (int) $term_id, $key, true );

	if ( '' === $value || false === $value ) {
		$value = $field['default'];
	}

	if ( 'true_false' === $field['type'] ) {
		return ! empty( $value ) && '0' !== $value;
	}

	if ( 'number' === $field['type'] ) {
		return (int) $value;
	}

	return (string) $value;
}

/**
 * Colour of a category. Sub-categories inherit from their parent; categories
 * without a colour get a stable one from a built-in palette.
 *
 * @param int $term_id Category term ID.
 * @return string Hex colour.
 */
function dailypulse_category_color( $term_id ) {
	static $cache = array();

	$term_id = (int) $term_id;

	if ( isset( $cache[ $term_id ] ) ) {
		return $cache[ $term_id ];
	}

	$color = sanitize_hex_color( (string) get_term_meta( $term_id, 'dp_color', true ) );

	if ( ! $color ) {
		$term = get_term( $term_id, 'category' );

		if ( $term instanceof WP_Term && $term->parent ) {
			$color = dailypulse_category_color( $term->parent );
		} else {
			$palette = array( '#0d9488', '#2563eb', '#c026d3', '#4d9f0c', '#d97706', '#db2777', '#0891b2', '#7c3aed' );
			$color   = $palette[ $term_id % count( $palette ) ];
		}
	}

	$cache[ $term_id ] = $color;

	return $color;
}

/**
 * Top-level categories that get a homepage section and a filter tab, in order.
 *
 * @return WP_Term[]
 */
function dailypulse_home_categories() {
	static $categories = null;

	if ( null !== $categories ) {
		return $categories;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => true,
			'parent'     => 0,
		)
	);

	$categories = array();

	if ( is_wp_error( $terms ) ) {
		return $categories;
	}

	foreach ( $terms as $term ) {
		if ( dailypulse_category_setting( $term->term_id, 'dp_home' ) ) {
			$categories[] = $term;
		}
	}

	usort(
		$categories,
		static function ( $a, $b ) {
			$order = dailypulse_category_setting( $a->term_id, 'dp_order' ) <=> dailypulse_category_setting( $b->term_id, 'dp_order' );

			return 0 !== $order ? $order : strcasecmp( $a->name, $b->name );
		}
	);

	return $categories;
}
