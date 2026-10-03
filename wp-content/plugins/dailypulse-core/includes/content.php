<?php
/**
 * Content model: the news categories, the Breaking Alerts post type, meta
 * registration and the "breaking" window.
 *
 * @package DailyPulse_Core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'dailypulse_core_register_alerts' );
add_action( 'init', 'dailypulse_core_register_meta', 11 );
add_filter( 'enter_title_here', 'dailypulse_core_alert_title_placeholder', 10, 2 );

add_action( 'added_post_meta', 'dailypulse_core_track_breaking', 10, 4 );
add_action( 'updated_post_meta', 'dailypulse_core_track_breaking', 10, 4 );
add_action( 'deleted_post_meta', 'dailypulse_core_untrack_breaking', 10, 3 );
add_action( 'transition_post_status', 'dailypulse_core_restart_breaking_on_publish', 10, 3 );

add_filter( 'dailypulse_cleanup_eligible_post_types', 'dailypulse_core_cleanup_post_types' );
add_filter( 'dailypulse_cleanup_media_ids', 'dailypulse_core_cleanup_media', 10, 2 );

/**
 * The categories every DailyPulse site starts with.
 *
 * @return array Slug => name, colour, homepage position and layout.
 */
function dailypulse_core_default_categories() {
	return array(
		'local-news'   => array(
			'name'   => __( 'Local News', 'dailypulse-core' ),
			'color'  => '#0d9488',
			'order'  => 10,
			'layout' => 'lead',
		),
		'foreign-news' => array(
			'name'   => __( 'Foreign News', 'dailypulse-core' ),
			'color'  => '#2563eb',
			'order'  => 20,
			'layout' => 'grid',
		),
		'articles'     => array(
			'name'   => __( 'Articles', 'dailypulse-core' ),
			'color'  => '#c026d3',
			'order'  => 30,
			'layout' => 'magazine',
		),
		'sports'       => array(
			'name'   => __( 'Sports', 'dailypulse-core' ),
			'color'  => '#4d9f0c',
			'order'  => 40,
			'layout' => 'band',
		),
	);
}

/**
 * Creates the default categories (once) and makes the first one the default
 * for new posts while that is still the stock "Uncategorized".
 *
 * Existing categories and any colours already chosen are left untouched.
 *
 * @return int[] Slug => term ID.
 */
function dailypulse_core_install_categories() {
	$ids = array();

	foreach ( dailypulse_core_default_categories() as $slug => $category ) {
		$term = get_term_by( 'slug', $slug, 'category' );

		if ( ! $term ) {
			$created = wp_insert_term( $category['name'], 'category', array( 'slug' => $slug ) );

			if ( is_wp_error( $created ) ) {
				continue;
			}

			$term_id = (int) $created['term_id'];
		} else {
			$term_id = (int) $term->term_id;
		}

		$ids[ $slug ] = $term_id;

		$meta = array(
			'dp_color'  => $category['color'],
			'dp_order'  => $category['order'],
			'dp_layout' => $category['layout'],
			'dp_home'   => '1',
		);

		foreach ( $meta as $key => $value ) {
			if ( '' === get_term_meta( $term_id, $key, true ) ) {
				update_term_meta( $term_id, $key, $value );
			}
		}
	}

	$default = get_term( (int) get_option( 'default_category' ), 'category' );

	if ( isset( $ids['local-news'] ) && ( ! $default instanceof WP_Term || 'uncategorized' === $default->slug ) ) {
		update_option( 'default_category', $ids['local-news'] );
	}

	return $ids;
}

/**
 * Registers the Breaking Alerts post type: one-line alerts for the ticker that
 * do not need a full article behind them.
 */
function dailypulse_core_register_alerts() {
	register_post_type(
		'dp_alert',
		array(
			'labels'              => array(
				'name'               => __( 'Breaking Alerts', 'dailypulse-core' ),
				'singular_name'      => __( 'Breaking Alert', 'dailypulse-core' ),
				'menu_name'          => __( 'Breaking Alerts', 'dailypulse-core' ),
				'add_new'            => __( 'Add Alert', 'dailypulse-core' ),
				'add_new_item'       => __( 'Add Breaking Alert', 'dailypulse-core' ),
				'edit_item'          => __( 'Edit Breaking Alert', 'dailypulse-core' ),
				'new_item'           => __( 'New Breaking Alert', 'dailypulse-core' ),
				'search_items'       => __( 'Search Alerts', 'dailypulse-core' ),
				'not_found'          => __( 'No alerts yet.', 'dailypulse-core' ),
				'not_found_in_trash' => __( 'No alerts in the Trash.', 'dailypulse-core' ),
				'all_items'          => __( 'All Alerts', 'dailypulse-core' ),
			),
			'description'         => __( 'One-line alerts shown in the breaking-news ticker.', 'dailypulse-core' ),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'menu_position'       => 6,
			'menu_icon'           => 'dashicons-megaphone',
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'exclude_from_search' => true,
		)
	);
}

/**
 * Title placeholder on the alert editor.
 *
 * @param string  $text Placeholder.
 * @param WP_Post $post Post being edited.
 * @return string
 */
function dailypulse_core_alert_title_placeholder( $text, $post ) {
	return 'dp_alert' === $post->post_type ? __( 'Type the alert as it should appear in the ticker', 'dailypulse-core' ) : $text;
}

/**
 * Registers every field from the schema as post meta (and the category settings
 * as term meta) so that all writers — native screens, ACF, WP-CLI, importers —
 * pass through the same sanitisation.
 */
function dailypulse_core_register_meta() {
	$types = array(
		'true_false' => 'string',
		'number'     => 'integer',
		'gallery'    => 'array',
		'sources'    => 'array',
	);

	foreach ( dailypulse_fields() as $name => $field ) {
		foreach ( $field['post_types'] as $post_type ) {
			register_post_meta(
				$post_type,
				$name,
				array(
					'type'              => isset( $types[ $field['type'] ] ) ? $types[ $field['type'] ] : 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => static function ( $value ) use ( $field ) {
						return dailypulse_sanitize_value( $field, $value );
					},
				)
			);
		}
	}

	foreach ( dailypulse_category_fields() as $key => $field ) {
		register_term_meta(
			'category',
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) use ( $field ) {
					// An empty colour means "use the automatic colour", so do not force the default.
					if ( 'color' === $field['type'] ) {
						return (string) sanitize_hex_color( (string) $value );
					}
					return dailypulse_sanitize_value( $field, $value );
				},
			)
		);
	}
}

/**
 * Starts (or ends) a story's breaking window when its flag changes.
 *
 * @param int    $meta_id    Meta row ID.
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Meta value.
 */
function dailypulse_core_track_breaking( $meta_id, $post_id, $meta_key, $meta_value ) {
	if ( 'dp_breaking' !== $meta_key ) {
		return;
	}

	if ( ! empty( $meta_value ) && '0' !== (string) $meta_value ) {
		update_post_meta( $post_id, 'dp_breaking_until', time() + dailypulse_option( 'breaking_hours' ) * HOUR_IN_SECONDS );
	} else {
		delete_post_meta( $post_id, 'dp_breaking_until' );
	}
}

/**
 * Ends the breaking window when the flag is removed altogether.
 *
 * @param int[]  $meta_ids Meta row IDs.
 * @param int    $post_id  Post ID.
 * @param string $meta_key Meta key.
 */
function dailypulse_core_untrack_breaking( $meta_ids, $post_id, $meta_key ) {
	if ( 'dp_breaking' === $meta_key ) {
		delete_post_meta( $post_id, 'dp_breaking_until' );
	}
}

/**
 * A story flagged while still a draft (or scheduled) starts its window on publication.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post.
 */
function dailypulse_core_restart_breaking_on_publish( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status || 'post' !== $post->post_type ) {
		return;
	}

	if ( dailypulse_field( 'dp_breaking', $post->ID ) ) {
		update_post_meta( $post->ID, 'dp_breaking_until', time() + dailypulse_option( 'breaking_hours' ) * HOUR_IN_SECONDS );
	}
}

/**
 * Lets the Auto-Cleanup plugin tidy away old alerts too.
 *
 * @param array $types Post type name => label.
 * @return array
 */
function dailypulse_core_cleanup_post_types( $types ) {
	$types['dp_alert'] = __( 'Breaking Alerts', 'dailypulse-core' );

	return $types;
}

/**
 * Tells the Auto-Cleanup plugin that a story's gallery images belong to it.
 *
 * @param int[] $ids     Attachment IDs.
 * @param int   $post_id Post being removed.
 * @return int[]
 */
function dailypulse_core_cleanup_media( $ids, $post_id ) {
	$gallery = dailypulse_field( 'dp_gallery', $post_id );

	return is_array( $gallery ) ? array_merge( $ids, $gallery ) : $ids;
}
