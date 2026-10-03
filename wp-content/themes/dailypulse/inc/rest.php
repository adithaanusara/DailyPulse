<?php
/**
 * REST endpoint that powers the filter tabs, "Load more" and live search.
 *
 * GET /wp-json/dailypulse/v1/feed?category=3&page=2
 *
 * It returns ready-made card HTML rendered by the same template the page uses,
 * so there is exactly one place that defines what a card looks like.
 *
 * @package DailyPulse
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'dailypulse_register_feed_route' );

/**
 * Registers the route.
 */
function dailypulse_register_feed_route() {
	register_rest_route(
		'dailypulse/v1',
		'/feed',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'dailypulse_rest_feed',
			// Public, read-only: the same published stories anyone can see on the site.
			'permission_callback' => '__return_true',
			'args'                => array(
				'category' => array(
					'type'    => 'integer',
					'default' => 0,
					'minimum' => 0,
				),
				'page'     => array(
					'type'    => 'integer',
					'default' => 1,
					'minimum' => 1,
					'maximum' => 200,
				),
				'per_page' => array(
					'type'    => 'integer',
					'default' => 0,
					'minimum' => 0,
					'maximum' => 24,
				),
				'exclude'  => array(
					'type'     => 'array',
					'default'  => array(),
					'maxItems' => 30,
					'items'    => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				'search'   => array(
					'type'              => 'string',
					'default'           => '',
					'maxLength'         => 100,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'layout'   => array(
					'type'    => 'string',
					'default' => 'stack',
					'enum'    => array( 'stack', 'row' ),
				),
			),
		)
	);
}

/**
 * Renders one page of stories as cards.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function dailypulse_rest_feed( $request ) {
	$per_page = (int) $request['per_page'];
	$query    = dailypulse_feed_query(
		array(
			'category' => (int) $request['category'],
			'page'     => (int) $request['page'],
			'per_page' => $per_page ? $per_page : (int) dailypulse_option( 'feed_count' ),
			'exclude'  => (array) $request['exclude'],
			'search'   => (string) $request['search'],
		)
	);

	$layout = 'row' === $request['layout'] ? 'row' : 'stack';
	$index  = 0;

	ob_start();

	while ( $query->have_posts() ) {
		$query->the_post();

		dailypulse_card(
			array(
				'variant' => $layout,
				'excerpt' => 'stack' === $layout,
				'index'   => $index++,
				// Inserted cards animate in themselves; they must not wait for the scroll observer.
				'reveal'  => false,
				'fresh'   => true,
			)
		);
	}

	wp_reset_postdata();

	$response = rest_ensure_response(
		array(
			'html'  => ob_get_clean(),
			'page'  => (int) $request['page'],
			'pages' => (int) $query->max_num_pages,
			'total' => (int) $query->found_posts,
		)
	);

	// Identical for every visitor, so browsers and CDNs may reuse it briefly.
	$response->header( 'Cache-Control', 'public, max-age=60' );

	return $response;
}
