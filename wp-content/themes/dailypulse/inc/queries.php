<?php
/**
 * The queries behind the homepage and article pages.
 *
 * All of them skip the pagination count they do not need (no_found_rows) and
 * ignore sticky posts, so each is a single cheap query.
 *
 * @package DailyPulse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remembers which stories the page has already shown, so later sections can
 * prefer fresh ones.
 *
 * @param int[]|null $ids IDs to add, or null to only read.
 * @return int[] All IDs shown so far.
 */
function dailypulse_shown( $ids = null ) {
	static $shown = array();

	if ( null !== $ids ) {
		$shown = array_values( array_unique( array_merge( $shown, array_map( 'intval', (array) $ids ) ) ) );
	}

	return $shown;
}

/**
 * Stories for the homepage hero: the newest ones marked "Feature in hero",
 * topped up with the latest stories that have an image.
 *
 * @return WP_Post[]
 */
function dailypulse_hero_posts() {
	static $posts = null;

	if ( null !== $posts ) {
		return $posts;
	}

	$count = max( 1, (int) dailypulse_option( 'hero_count' ) );
	$base  = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	$posts = get_posts(
		array_merge(
			$base,
			array(
				'posts_per_page' => $count,
				'meta_key'       => 'dp_featured', // phpcs:ignore WordPress.DB.SlowDBQuery -- small, bounded lookup.
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		)
	);

	if ( count( $posts ) < $count ) {
		$fill = get_posts(
			array_merge(
				$base,
				array(
					'posts_per_page' => $count - count( $posts ),
					'post__not_in'   => wp_list_pluck( $posts, 'ID' ),
					'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- small, bounded lookup.
				)
			)
		);

		$posts = array_merge( $posts, $fill );
	}

	// Newest first, whichever way a story got in.
	usort(
		$posts,
		static function ( $a, $b ) {
			return strcmp( $b->post_date_gmt, $a->post_date_gmt );
		}
	);

	return $posts;
}

/**
 * Stories for a homepage category section.
 *
 * Stories already shown higher up are skipped while enough fresh ones exist; on
 * a quiet day the section is filled with repeats rather than left half empty.
 *
 * @param int $term_id Category term ID.
 * @param int $count   Number of stories wanted.
 * @return WP_Post[]
 */
function dailypulse_section_posts( $term_id, $count ) {
	$shown = dailypulse_shown();
	$found = get_posts(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'cat'                 => (int) $term_id,
			'posts_per_page'      => $count + min( count( $shown ), 12 ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	$fresh   = array();
	$repeats = array();

	foreach ( $found as $story ) {
		if ( in_array( $story->ID, $shown, true ) ) {
			$repeats[] = $story;
		} else {
			$fresh[] = $story;
		}
	}

	$posts = array_slice( array_merge( $fresh, $repeats ), 0, $count );

	dailypulse_shown( wp_list_pluck( $posts, 'ID' ) );

	return $posts;
}

/**
 * The query behind the "Latest" feed and its filter tabs. Used both for the
 * first render and by the REST endpoint, so the two always agree.
 *
 * @param array $args {
 *     Optional.
 *
 *     @type int    $category Category term ID, 0 for all.
 *     @type int    $page     Page number.
 *     @type int    $per_page Stories per page.
 *     @type int[]  $exclude  Story IDs to leave out.
 *     @type string $search   Search phrase.
 * }
 * @return WP_Query
 */
function dailypulse_feed_query( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'category' => 0,
			'page'     => 1,
			'per_page' => (int) dailypulse_option( 'feed_count' ),
			'exclude'  => array(),
			'search'   => '',
		)
	);

	$query = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, min( 24, (int) $args['per_page'] ) ),
		'paged'               => max( 1, (int) $args['page'] ),
		'ignore_sticky_posts' => true,
	);

	if ( $args['category'] ) {
		$query['cat'] = (int) $args['category'];
	}

	if ( $args['exclude'] ) {
		$query['post__not_in'] = array_map( 'intval', (array) $args['exclude'] );
	}

	if ( '' !== $args['search'] ) {
		$query['s'] = $args['search'];
	}

	return new WP_Query( $query );
}

/**
 * Other recent stories from the same category.
 *
 * @param int $post_id Story ID.
 * @param int $count   Number of stories.
 * @return WP_Post[]
 */
function dailypulse_related_posts( $post_id, $count ) {
	$category = dailypulse_primary_category( $post_id );

	if ( ! $category || $count < 1 ) {
		return array();
	}

	return get_posts(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'cat'                 => $category->term_id,
			'posts_per_page'      => $count,
			'post__not_in'        => array( (int) $post_id ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
}
