<?php
/**
 * Share and search metadata: description, Open Graph, Twitter cards and
 * NewsArticle structured data, so stories unfurl properly on WhatsApp, Facebook
 * and X and are understood by search engines.
 *
 * Steps aside automatically when a dedicated SEO plugin is active.
 *
 * @package DailyPulse_Core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', 'dailypulse_core_seo_head', 5 );

/**
 * Whether DailyPulse should print the metadata itself.
 *
 * @return bool
 */
function dailypulse_core_seo_enabled() {
	$seo_plugin = defined( 'WPSEO_VERSION' )
		|| defined( 'RANK_MATH_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| defined( 'SEOPRESS_VERSION' )
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' )
		|| defined( 'SLIM_SEO_VER' );

	/**
	 * Filters whether DailyPulse prints share/search metadata.
	 *
	 * @param bool $enabled False when a known SEO plugin is active.
	 */
	return (bool) apply_filters( 'dailypulse_seo_enabled', ! $seo_plugin );
}

/**
 * Plain-text summary of a post without running the_content filters in the head.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function dailypulse_core_seo_description( $post ) {
	$text = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : excerpt_remove_blocks( strip_shortcodes( $post->post_content ) );
	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text, true ) ) );

	return wp_trim_words( $text, 32, '…' );
}

/**
 * Image data for sharing.
 *
 * @param int $attachment_id Attachment ID.
 * @return array|null `url`, `width` and `height`, or null.
 */
function dailypulse_core_seo_image( $attachment_id ) {
	$image = $attachment_id ? wp_get_attachment_image_src( $attachment_id, 'large' ) : false;

	if ( ! $image ) {
		return null;
	}

	return array(
		'url'    => $image[0],
		'width'  => (int) $image[1],
		'height' => (int) $image[2],
	);
}

/**
 * Collects the metadata for the current request.
 *
 * @return array
 */
function dailypulse_core_seo_data() {
	$data = array(
		'type'        => 'website',
		'title'       => get_bloginfo( 'name' ),
		'description' => get_bloginfo( 'description' ),
		'url'         => home_url( '/' ),
		'image'       => null,
		'post'        => null,
	);

	if ( is_singular() ) {
		$post = get_queried_object();

		if ( $post instanceof WP_Post ) {
			$data['title']       = get_the_title( $post );
			$data['description'] = dailypulse_core_seo_description( $post );
			$data['url']         = get_permalink( $post );
			$data['image']       = dailypulse_core_seo_image( (int) get_post_thumbnail_id( $post ) );

			if ( 'post' === $post->post_type ) {
				$data['type'] = 'article';
				$data['post'] = $post;
			}
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			$link                = get_term_link( $term );
			$data['title']       = $term->name;
			$data['description'] = '' !== $term->description ? wp_strip_all_tags( $term->description, true ) : $data['description'];
			$data['url']         = is_wp_error( $link ) ? $data['url'] : $link;
		}
	} elseif ( is_search() ) {
		/* translators: %s: search query. */
		$data['title'] = sprintf( __( 'Search results for “%s”', 'dailypulse-core' ), get_search_query() );
		$data['url']   = get_search_link();
	}

	if ( ! $data['image'] ) {
		$fallback      = (int) get_theme_mod( 'custom_logo' );
		$data['image'] = dailypulse_core_seo_image( $fallback ? $fallback : (int) get_option( 'site_icon' ) );
	}

	/**
	 * Filters the share/search metadata for the current request.
	 *
	 * @param array $data Metadata.
	 */
	return apply_filters( 'dailypulse_seo_data', $data );
}

/**
 * Prints the metadata.
 */
function dailypulse_core_seo_head() {
	if ( ! dailypulse_core_seo_enabled() || is_404() || is_feed() ) {
		return;
	}

	$data = dailypulse_core_seo_data();
	$post = $data['post'];
	$tags = array(
		array( 'name', 'description', $data['description'] ),
		array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
		array( 'property', 'og:locale', get_locale() ),
		array( 'property', 'og:type', $data['type'] ),
		array( 'property', 'og:title', $data['title'] ),
		array( 'property', 'og:description', $data['description'] ),
		array( 'property', 'og:url', $data['url'] ),
		array( 'name', 'twitter:card', $data['image'] ? 'summary_large_image' : 'summary' ),
		array( 'name', 'twitter:title', $data['title'] ),
		array( 'name', 'twitter:description', $data['description'] ),
	);

	if ( $data['image'] ) {
		$tags[] = array( 'property', 'og:image', $data['image']['url'] );
		$tags[] = array( 'property', 'og:image:width', (string) $data['image']['width'] );
		$tags[] = array( 'property', 'og:image:height', (string) $data['image']['height'] );
		$tags[] = array( 'name', 'twitter:image', $data['image']['url'] );
	}

	$author  = '';
	$section = '';

	if ( $post ) {
		$author     = dailypulse_field( 'dp_byline', $post->ID );
		$author     = $author ? $author : get_the_author_meta( 'display_name', $post->post_author );
		$categories = get_the_category( $post->ID );
		$section    = $categories ? $categories[0]->name : '';

		$tags[] = array( 'property', 'article:published_time', get_post_time( 'c', true, $post ) );
		$tags[] = array( 'property', 'article:modified_time', get_post_modified_time( 'c', true, $post ) );

		if ( $section ) {
			$tags[] = array( 'property', 'article:section', $section );
		}
	}

	echo "\n";

	foreach ( $tags as $tag ) {
		if ( '' === (string) $tag[2] ) {
			continue;
		}
		printf( '<meta %1$s="%2$s" content="%3$s">' . "\n", esc_attr( $tag[0] ), esc_attr( $tag[1] ), esc_attr( $tag[2] ) );
	}

	if ( $post ) {
		$logo   = dailypulse_core_seo_image( (int) get_theme_mod( 'custom_logo' ) );
		$schema = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'NewsArticle',
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => $data['url'],
			),
			'headline'         => mb_substr( $data['title'], 0, 110 ),
			'description'      => $data['description'],
			'datePublished'    => get_post_time( 'c', true, $post ),
			'dateModified'     => get_post_modified_time( 'c', true, $post ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => $author,
			),
			'publisher'        => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
		);

		if ( $section ) {
			$schema['articleSection'] = $section;
		}

		if ( $data['image'] ) {
			$schema['image'] = array( $data['image']['url'] );
		}

		if ( $logo ) {
			$schema['publisher']['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo['url'],
			);
		}
	} elseif ( is_front_page() ) {
		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'WebSite',
			'name'            => get_bloginfo( 'name' ),
			'url'             => home_url( '/' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	} else {
		return;
	}

	// JSON_HEX_TAG keeps a stray "</script>" in a headline from closing the element.
	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( $schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);
}
