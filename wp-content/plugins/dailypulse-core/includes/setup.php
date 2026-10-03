<?php
/**
 * Quick setup and the demo-content importer. Used by the Setup & Demo admin page
 * and by the `wp dailypulse` commands.
 *
 * @package DailyPulse_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every status a demo item can be in, including the Trash.
 *
 * @return string[]
 */
function dailypulse_core_demo_statuses() {
	return array( 'publish', 'draft', 'pending', 'future', 'private', 'trash', 'inherit' );
}

/*
 * -------------------------------------------------------------------------
 * Quick setup
 * -------------------------------------------------------------------------
 */

/**
 * Creates a menu and assigns it to a theme location, unless that location
 * already has one.
 *
 * @param string $name     Menu name.
 * @param string $location Theme location.
 * @param array  $items    Items as accepted by wp_update_nav_menu_item().
 * @return bool Whether a menu was assigned.
 */
function dailypulse_core_ensure_menu( $name, $location, $items ) {
	$registered = get_registered_nav_menus();
	$locations  = get_nav_menu_locations();

	if ( ! isset( $registered[ $location ] ) || ! $items ) {
		return false;
	}

	if ( ! empty( $locations[ $location ] ) && wp_get_nav_menu_object( $locations[ $location ] ) ) {
		return false;
	}

	$menu = wp_get_nav_menu_object( $name );

	if ( $menu ) {
		$menu_id = (int) $menu->term_id;
	} else {
		$menu_id = wp_create_nav_menu( $name );

		if ( is_wp_error( $menu_id ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			wp_update_nav_menu_item( $menu_id, 0, array_merge( array( 'menu-item-status' => 'publish' ), $item ) );
		}
	}

	$locations[ $location ] = (int) $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );

	return true;
}

/**
 * Brings a fresh WordPress install to a sensible DailyPulse starting point.
 * Only adds what is missing, so it is safe to run repeatedly.
 *
 * @return string[] Human-readable list of what was changed.
 */
function dailypulse_core_run_setup() {
	$report       = array();
	$category_ids = dailypulse_core_install_categories();

	if ( $category_ids ) {
		$report[] = __( 'News categories are in place.', 'dailypulse-core' );
	}

	// Permalinks: only when the site still uses plain ?p=123 links.
	if ( ! get_option( 'permalink_structure' ) ) {
		global $wp_rewrite;

		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		// Category and tag rules are only registered when permalinks were already on at
		// startup, so flushing now would leave them out. Let the next request rebuild them.
		delete_option( 'rewrite_rules' );
		$report[] = __( 'Pretty permalinks switched on.', 'dailypulse-core' );
	}

	// Basic pages for the footer.
	$pages    = array(
		'about'   => array(
			'title'   => __( 'About', 'dailypulse-core' ),
			'content' => __( 'Tell readers who you are, what you cover and how to reach the newsroom.', 'dailypulse-core' ),
		),
		'contact' => array(
			'title'   => __( 'Contact', 'dailypulse-core' ),
			'content' => __( 'Add your news desk email, phone number and address here.', 'dailypulse-core' ),
		),
	);
	$page_ids = array();

	foreach ( $pages as $slug => $page ) {
		$existing = get_page_by_path( $slug );

		if ( $existing ) {
			$page_ids[ $slug ] = (int) $existing->ID;
			continue;
		}

		$page_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $page['title'],
					'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html( $page['content'] ) . "</p>\n<!-- /wp:paragraph -->",
				)
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			$page_ids[ $slug ] = (int) $page_id;
			/* translators: %s: page title. */
			$report[] = sprintf( __( '“%s” page created.', 'dailypulse-core' ), $page['title'] );
		}
	}

	// Menus.
	$primary = array();
	foreach ( $category_ids as $term_id ) {
		$primary[] = array(
			'menu-item-type'      => 'taxonomy',
			'menu-item-object'    => 'category',
			'menu-item-object-id' => $term_id,
		);
	}

	$footer = array();
	foreach ( $page_ids as $page_id ) {
		$footer[] = array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $page_id,
		);
	}

	if ( dailypulse_core_ensure_menu( __( 'DailyPulse Header', 'dailypulse-core' ), 'primary', $primary ) ) {
		$report[] = __( 'Header menu created.', 'dailypulse-core' );
	}

	if ( dailypulse_core_ensure_menu( __( 'DailyPulse Footer', 'dailypulse-core' ), 'footer', $footer ) ) {
		$report[] = __( 'Footer menu created.', 'dailypulse-core' );
	}

	// Stock content and tagline, only while they are still the untouched defaults.
	$hello = get_post( 1 );
	if ( $hello && 'post' === $hello->post_type && 'hello-world' === $hello->post_name && 'publish' === $hello->post_status ) {
		wp_trash_post( $hello->ID );
		$report[] = __( '“Hello world!” moved to the Trash.', 'dailypulse-core' );
	}

	$sample = get_page_by_path( 'sample-page' );
	if ( $sample && 'publish' === $sample->post_status ) {
		wp_trash_post( $sample->ID );
		$report[] = __( '“Sample Page” moved to the Trash.', 'dailypulse-core' );
	}

	if ( in_array( get_option( 'blogdescription' ), array( '', 'Just another WordPress site' ), true ) ) {
		update_option( 'blogdescription', __( 'News that moves with you', 'dailypulse-core' ) );
	}

	// First run only: let Auto-Cleanup tidy away expired ticker alerts as well.
	if ( class_exists( 'DailyPulse_Cleanup' ) && ! get_option( 'dailypulse_core_setup_done' ) ) {
		$settings = DailyPulse_Cleanup::settings();

		if ( ! in_array( 'dp_alert', $settings['post_types'], true ) ) {
			$settings['post_types'][] = 'dp_alert';
			update_option( DailyPulse_Cleanup::OPTION, $settings );
		}
	}

	update_option( 'dailypulse_core_setup_done', time(), false );

	return $report;
}

/*
 * -------------------------------------------------------------------------
 * Demo content
 * -------------------------------------------------------------------------
 */

/**
 * Finds a demo item by its key.
 *
 * @param string $key       Demo key.
 * @param string $post_type Post type.
 * @return int Post ID, or 0.
 */
function dailypulse_core_demo_find( $key, $post_type ) {
	$ids = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => dailypulse_core_demo_statuses(),
			'meta_key'       => '_dailypulse_demo', // phpcs:ignore WordPress.DB.SlowDBQuery -- one-off importer lookup.
			'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);

	return $ids ? (int) $ids[0] : 0;
}

/**
 * How many demo stories exist (including trashed ones).
 *
 * @return int
 */
function dailypulse_core_demo_count() {
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => dailypulse_core_demo_statuses(),
			'meta_key'       => '_dailypulse_demo', // phpcs:ignore WordPress.DB.SlowDBQuery -- admin screen only.
			'fields'         => 'ids',
			'posts_per_page' => 100,
			'no_found_rows'  => true,
		)
	);

	return count( $ids );
}

/**
 * Builds the block markup of a sample story.
 *
 * @param array $story Story definition.
 * @param array $body  Shared body copy.
 * @return string
 */
function dailypulse_core_demo_body( $story, $body ) {
	$paragraph = static function ( $text ) {
		return "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->";
	};

	$items = '';
	foreach ( $body['list'] as $item ) {
		$items .= "<!-- wp:list-item -->\n<li>" . esc_html( $item ) . "</li>\n<!-- /wp:list-item -->";
	}

	$blocks = array(
		$paragraph( $story['lead'] ),
		$paragraph( $body['paragraphs'][0] ),
		$paragraph( $body['paragraphs'][1] ),
		"<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>“" . esc_html( $body['quote'] ) . "”</p>\n<!-- /wp:paragraph --><cite>" . esc_html( $body['cite'] ) . "</cite></blockquote>\n<!-- /wp:quote -->",
		$paragraph( $body['paragraphs'][2] ),
		"<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $body['heading'] ) . "</h2>\n<!-- /wp:heading -->",
		$paragraph( $body['paragraphs'][3] ),
		"<!-- wp:list -->\n<ul class=\"wp-block-list\">" . $items . "</ul>\n<!-- /wp:list -->",
		$paragraph( $body['paragraphs'][4] ),
		"<!-- wp:paragraph -->\n<p><em>" . esc_html__( 'Sample story created by the DailyPulse demo importer. The people and events described are fictional.', 'dailypulse-core' ) . "</em></p>\n<!-- /wp:paragraph -->",
	);

	return implode( "\n\n", $blocks );
}

/**
 * Returns the attachment for a demo photo, downloading it on first use.
 *
 * @param int    $photo_id picsum.photos ID.
 * @param int    $post_id  Post to attach the image to.
 * @param string $title    Title / alt text.
 * @param bool   $is_new   Set to true when the image had to be downloaded.
 * @return int Attachment ID, or 0 when the download failed.
 */
function dailypulse_core_demo_image( $photo_id, $post_id, $title, &$is_new = false ) {
	$key      = 'image-' . (int) $photo_id;
	$existing = dailypulse_core_demo_find( $key, 'attachment' );
	$is_new   = false;

	if ( $existing ) {
		return $existing;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( sprintf( 'https://picsum.photos/id/%d/1280/800.jpg', $photo_id ), 30 );

	if ( is_wp_error( $tmp ) ) {
		return 0;
	}

	$attachment_id = media_handle_sideload(
		array(
			'name'     => 'dailypulse-demo-' . (int) $photo_id . '.jpg',
			'tmp_name' => $tmp,
		),
		$post_id,
		$title
	);

	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $tmp );
		return 0;
	}

	add_post_meta( $attachment_id, '_dailypulse_demo', $key, true );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( $title ) );

	$is_new = true;

	return (int) $attachment_id;
}

/**
 * Imports the sample stories, or refreshes them if they already exist: missing
 * stories are created, trashed ones restored, and all dates are moved back into
 * the last two days.
 *
 * @param array $args {
 *     Optional.
 *
 *     @type bool $images Whether to download photos. Default true.
 * }
 * @return array Counts: created, refreshed, images, image_errors.
 */
function dailypulse_core_demo_import( $args = array() ) {
	$args = wp_parse_args( $args, array( 'images' => true ) );
	$data = require DAILYPULSE_CORE_DIR . 'includes/demo-data.php';

	// Downloading and resizing ~30 photos can outlast the default 30 seconds.
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, Squiz.PHP.DiscouragedFunctions -- best effort; may be disabled by the host.
	}

	$category_ids = dailypulse_core_install_categories();
	$author_id    = get_current_user_id();

	if ( ! $author_id ) {
		$admins    = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		$author_id = $admins ? (int) $admins[0] : 0;
	}

	$result = array(
		'created'      => 0,
		'refreshed'    => 0,
		'images'       => 0,
		'image_errors' => 0,
	);
	$now    = time();

	foreach ( $data['stories'] as $story ) {
		if ( empty( $category_ids[ $story['category'] ] ) ) {
			continue;
		}

		$key       = 'story-' . $story['key'];
		$post_id   = dailypulse_core_demo_find( $key, 'post' );
		$timestamp = $now - $story['age'] * MINUTE_IN_SECONDS;
		$postarr   = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'post_author'    => $author_id,
			'post_title'     => $story['title'],
			'post_excerpt'   => $story['excerpt'],
			'post_content'   => dailypulse_core_demo_body( $story, $data['bodies'][ $story['body'] ] ),
			'post_category'  => array( $category_ids[ $story['category'] ] ),
			'post_date'      => wp_date( 'Y-m-d H:i:s', $timestamp ),
			'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', $timestamp ),
			'comment_status' => 'closed',
		);

		if ( $post_id ) {
			if ( 'trash' === get_post_status( $post_id ) ) {
				wp_untrash_post( $post_id );
				// Auto-Cleanup exempts restored posts; sample stories should keep expiring normally.
				delete_post_meta( $post_id, '_dailypulse_keep' );
			}

			$postarr['ID']        = $post_id;
			$postarr['edit_date'] = true;
			wp_update_post( wp_slash( $postarr ) );
			++$result['refreshed'];
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			add_post_meta( $post_id, '_dailypulse_demo', $key, true );
			++$result['created'];
		}

		$meta = array(
			'dp_kicker'       => isset( $story['kicker'] ) ? $story['kicker'] : '',
			'dp_byline'       => isset( $story['byline'] ) ? $story['byline'] : '',
			'dp_photo_credit' => isset( $story['credit'] ) ? $story['credit'] : '',
			'dp_featured'     => empty( $story['featured'] ) ? '' : '1',
			'dp_sources'      => isset( $story['sources'] ) ? $story['sources'] : array(),
		);

		foreach ( $meta as $meta_key => $meta_value ) {
			if ( '' === $meta_value || array() === $meta_value ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, wp_slash( $meta_value ) );
			}
		}

		// Re-set the flag so the breaking window restarts with every import.
		delete_post_meta( $post_id, 'dp_breaking' );
		if ( ! empty( $story['breaking'] ) ) {
			update_post_meta( $post_id, 'dp_breaking', '1' );
		}

		if ( ! $args['images'] ) {
			continue;
		}

		if ( ! has_post_thumbnail( $post_id ) ) {
			$is_new        = false;
			$attachment_id = dailypulse_core_demo_image( $story['image'], $post_id, $story['title'], $is_new );

			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
				$result['images'] += $is_new ? 1 : 0;
			} else {
				++$result['image_errors'];
			}
		}

		if ( ! empty( $story['gallery'] ) ) {
			$gallery = array();

			foreach ( $story['gallery'] as $photo_id ) {
				$is_new        = false;
				$attachment_id = dailypulse_core_demo_image( $photo_id, $post_id, $story['title'], $is_new );

				if ( $attachment_id ) {
					$gallery[]         = $attachment_id;
					$result['images'] += $is_new ? 1 : 0;
				} else {
					++$result['image_errors'];
				}
			}

			update_post_meta( $post_id, 'dp_gallery', $gallery );
		}
	}

	// One sample alert for the ticker.
	$alert_id  = dailypulse_core_demo_find( 'alert', 'dp_alert' );
	$timestamp = $now - 25 * MINUTE_IN_SECONDS;
	$alert     = array(
		'post_type'     => 'dp_alert',
		'post_status'   => 'publish',
		'post_author'   => $author_id,
		'post_title'    => $data['alert']['title'],
		'post_date'     => wp_date( 'Y-m-d H:i:s', $timestamp ),
		'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $timestamp ),
	);

	if ( $alert_id ) {
		if ( 'trash' === get_post_status( $alert_id ) ) {
			wp_untrash_post( $alert_id );
			delete_post_meta( $alert_id, '_dailypulse_keep' );
		}

		$alert['ID']        = $alert_id;
		$alert['edit_date'] = true;
		wp_update_post( wp_slash( $alert ) );
	} else {
		$alert_id = wp_insert_post( wp_slash( $alert ) );

		if ( $alert_id && ! is_wp_error( $alert_id ) ) {
			add_post_meta( $alert_id, '_dailypulse_demo', 'alert', true );
		}
	}

	if ( $alert_id && ! is_wp_error( $alert_id ) ) {
		update_post_meta( $alert_id, 'dp_alert_hours', (int) $data['alert']['hours'] );
	}

	return $result;
}

/**
 * Permanently deletes everything the demo importer created.
 *
 * @return int Number of items removed.
 */
function dailypulse_core_demo_remove() {
	$ids = get_posts(
		array(
			'post_type'      => array( 'post', 'dp_alert', 'attachment' ),
			'post_status'    => dailypulse_core_demo_statuses(),
			'meta_key'       => '_dailypulse_demo', // phpcs:ignore WordPress.DB.SlowDBQuery -- one-off removal.
			'fields'         => 'ids',
			'posts_per_page' => 500,
			'no_found_rows'  => true,
		)
	);

	foreach ( $ids as $id ) {
		if ( 'attachment' === get_post_type( $id ) ) {
			wp_delete_attachment( $id, true );
		} else {
			wp_delete_post( $id, true );
		}
	}

	return count( $ids );
}
