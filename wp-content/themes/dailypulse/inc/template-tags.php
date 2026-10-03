<?php
/**
 * Template tags: small functions the templates use to print story parts.
 *
 * @package DailyPulse
 */

defined( 'ABSPATH' ) || exit;

/**
 * The category that represents a story: the homepage category that comes first
 * in the site's own ordering.
 *
 * @param int $post_id Optional. Defaults to the current post.
 * @return WP_Term|null
 */
function dailypulse_primary_category( $post_id = 0 ) {
	static $cache = array();

	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();

	if ( array_key_exists( $post_id, $cache ) ) {
		return $cache[ $post_id ];
	}

	$categories = get_the_category( $post_id );

	if ( ! $categories ) {
		$cache[ $post_id ] = null;
		return null;
	}

	usort(
		$categories,
		static function ( $a, $b ) {
			return (int) dailypulse_category_setting( $a->term_id, 'dp_order' ) <=> (int) dailypulse_category_setting( $b->term_id, 'dp_order' );
		}
	);

	$cache[ $post_id ] = $categories[0];

	return $categories[0];
}

/**
 * Inline style that exposes a colour as the --cat custom property.
 *
 * @param WP_Term|int|null $term  Category (or its ID).
 * @param string           $extra Further declarations, e.g. "--i:2".
 * @return string A complete style attribute (with leading space), or an empty string.
 */
function dailypulse_cat_style( $term, $extra = '' ) {
	$term_id = $term instanceof WP_Term ? $term->term_id : (int) $term;
	$style   = $term_id ? '--cat:' . dailypulse_category_color( $term_id ) . ';' : '';
	$style  .= $extra;

	return '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '';
}

/**
 * Prints the kicker: a "Breaking" flag when applicable, then the custom kicker
 * or the category.
 *
 * @param int  $post_id Optional. Defaults to the current post.
 * @param bool $link    Whether the category should link to its archive.
 */
function dailypulse_kicker( $post_id = 0, $link = true ) {
	$post_id  = $post_id ? (int) $post_id : (int) get_the_ID();
	$category = dailypulse_primary_category( $post_id );
	$custom   = (string) dailypulse_field( 'dp_kicker', $post_id );
	$breaking = dailypulse_is_breaking( $post_id );

	if ( ! $category && '' === $custom && ! $breaking ) {
		return;
	}

	echo '<p class="kicker">';

	if ( $breaking ) {
		echo '<span class="kicker__flag">' . dailypulse_icon( 'bolt' ) . esc_html( dailypulse_option( 'ticker_label' ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
	}

	if ( '' !== $custom ) {
		echo '<span class="kicker__cat">' . esc_html( $custom ) . '</span>';
	} elseif ( $category ) {
		if ( $link ) {
			printf( '<a class="kicker__cat" href="%1$s">%2$s</a>', esc_url( get_category_link( $category ) ), esc_html( $category->name ) );
		} else {
			echo '<span class="kicker__cat">' . esc_html( $category->name ) . '</span>';
		}
	}

	echo '</p>';
}

/**
 * Returns a <time> element. Stories from the last 24 hours read "2 hours ago";
 * the script keeps that current even on cached pages.
 *
 * @param int|WP_Post $post Optional. Defaults to the current post.
 * @return string
 */
function dailypulse_time( $post = 0 ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return '';
	}

	$timestamp = (int) get_post_time( 'U', true, $post );

	return dailypulse_time_tag( $timestamp, get_the_date( '', $post ) );
}

/**
 * Builds a <time> element from a Unix timestamp.
 *
 * @param int    $timestamp Unix timestamp (UTC).
 * @param string $date      Optional. Formatted date to show for older items.
 * @return string
 */
function dailypulse_time_tag( $timestamp, $date = '' ) {
	$date     = '' !== $date ? $date : wp_date( get_option( 'date_format' ), $timestamp );
	$age      = time() - $timestamp;
	$relative = dailypulse_option( 'relative_dates' );
	$label    = $date;

	if ( $relative && $age >= 0 && $age < DAY_IN_SECONDS ) {
		$label = $age < MINUTE_IN_SECONDS
			? __( 'Just now', 'dailypulse' )
			/* translators: %s: time difference, e.g. "2 hours". */
			: sprintf( __( '%s ago', 'dailypulse' ), human_time_diff( $timestamp ) );
	}

	return sprintf(
		'<time class="time" datetime="%1$s" title="%2$s"%3$s>%4$s</time>',
		esc_attr( wp_date( 'c', $timestamp ) ),
		esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ),
		$relative ? ' data-ago data-date="' . esc_attr( $date ) . '"' : '',
		esc_html( $label )
	);
}

/**
 * Estimated reading time in minutes.
 *
 * Counts whitespace-separated words, which also works for Sinhala and Tamil.
 *
 * @param int|WP_Post $post Optional. Defaults to the current post.
 * @return int At least 1.
 */
function dailypulse_reading_time( $post = 0 ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return 1;
	}

	$text  = trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
	$words = '' === $text ? 0 : count( preg_split( '/\s+/u', $text ) );

	return max( 1, (int) ceil( $words / 220 ) );
}

/**
 * A short summary of a story: its excerpt if one was written, otherwise the
 * opening words of the text.
 *
 * Deliberately avoids get_the_excerpt(), which would render every block of every
 * story on a listing page just to throw most of it away.
 *
 * @param int|WP_Post $post  Optional. Defaults to the current post.
 * @param int         $words Maximum number of words when generated.
 * @return string Plain text.
 */
function dailypulse_excerpt( $post = 0, $words = 26 ) {
	$post = get_post( $post );

	if ( ! $post || post_password_required( $post ) ) {
		return '';
	}

	if ( '' !== trim( $post->post_excerpt ) ) {
		return wp_strip_all_tags( $post->post_excerpt, true );
	}

	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ), true ), $words, '…' );
}

/**
 * The name a story is credited to: the "Author credit" field, or the WordPress author.
 *
 * @param int|WP_Post $post Optional. Defaults to the current post.
 * @return string
 */
function dailypulse_byline( $post = 0 ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return '';
	}

	$credit = (string) dailypulse_field( 'dp_byline', $post->ID );

	return '' !== $credit ? $credit : (string) get_the_author_meta( 'display_name', (int) $post->post_author );
}

/**
 * Prints the meta line under a headline.
 *
 * @param array $args {
 *     Optional.
 *
 *     @type bool $byline  Show the author credit. Default false.
 *     @type bool $reading Show the reading time. Default true.
 * }
 */
function dailypulse_meta( $args = array() ) {
	$args  = wp_parse_args(
		$args,
		array(
			'byline'  => false,
			'reading' => true,
		)
	);
	$parts = array();

	if ( $args['byline'] ) {
		$byline = dailypulse_byline();

		if ( '' !== $byline ) {
			/* translators: %s: author name. */
			$parts[] = '<span class="meta__byline">' . esc_html( sprintf( __( 'By %s', 'dailypulse' ), $byline ) ) . '</span>';
		}
	}

	$parts[] = dailypulse_time();

	if ( $args['reading'] && dailypulse_option( 'show_reading_time' ) ) {
		/* translators: %s: number of minutes. */
		$parts[] = '<span class="meta__read">' . esc_html( sprintf( __( '%s min read', 'dailypulse' ), number_format_i18n( dailypulse_reading_time() ) ) ) . '</span>';
	}

	echo '<p class="meta">' . implode( '<span class="meta__sep" aria-hidden="true">·</span>', array_filter( $parts ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts are escaped above.
}

/**
 * Prints a story image, or a branded placeholder when the story has none.
 *
 * @param array $args {
 *     Optional.
 *
 *     @type int    $post_id Post ID. Defaults to the current post.
 *     @type string $size    Registered image size used as the fallback src.
 *     @type string $sizes   The sizes attribute: how wide the image is shown.
 *     @type bool   $eager   True for the image most likely to be the largest paint.
 *     @type string $alt     Alt text. Empty by default: next to a headline the image is decorative.
 * }
 */
function dailypulse_image( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'post_id' => 0,
			'size'    => 'medium_large',
			'sizes'   => '(min-width: 1000px) 400px, (min-width: 640px) 46vw, 92vw',
			'eager'   => false,
			'alt'     => '',
		)
	);

	$post_id      = $args['post_id'] ? (int) $args['post_id'] : (int) get_the_ID();
	$thumbnail_id = (int) get_post_thumbnail_id( $post_id );

	if ( ! $thumbnail_id ) {
		echo '<span class="placeholder" aria-hidden="true">' . dailypulse_pulse_mark( 'placeholder__mark' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		return;
	}

	$attributes = array(
		'sizes'    => $args['sizes'],
		'alt'      => $args['alt'],
		'loading'  => $args['eager'] ? 'eager' : 'lazy',
		'decoding' => 'async',
	);

	if ( $args['eager'] ) {
		$attributes['fetchpriority'] = 'high';
	}

	echo wp_get_attachment_image( $thumbnail_id, $args['size'], false, $attributes );
}

/**
 * Renders a story card for the current post.
 *
 * @param array $args See template-parts/card.php.
 */
function dailypulse_card( $args = array() ) {
	get_template_part( 'template-parts/card', null, $args );
}

/**
 * Site name or custom logo, linked to the homepage.
 */
function dailypulse_brand() {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}

	printf(
		'<a class="brand" href="%1$s" rel="home">%2$s<span class="brand__name">%3$s</span></a>',
		esc_url( home_url( '/' ) ),
		dailypulse_pulse_mark(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		esc_html( get_bloginfo( 'name' ) )
	);
}

/**
 * Header navigation shown when no menu is assigned: the homepage categories.
 */
function dailypulse_nav_fallback() {
	$categories = dailypulse_home_categories();

	if ( ! $categories ) {
		return;
	}

	echo '<ul class="site-nav__list">';

	foreach ( $categories as $category ) {
		$current = is_category( $category->term_id ) || ( is_singular( 'post' ) && has_category( $category->term_id ) );

		printf(
			'<li class="menu-item%1$s"><a href="%2$s"%3$s%4$s>%5$s</a></li>',
			$current ? ' current-menu-item' : '',
			esc_url( get_category_link( $category ) ),
			dailypulse_cat_style( $category ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
			is_category( $category->term_id ) ? ' aria-current="page"' : '',
			esc_html( $category->name )
		);
	}

	echo '</ul>';
}

/**
 * Social profiles configured under DailyPulse → Site Options.
 *
 * @return array[] Each with `label`, `url` and `icon`.
 */
function dailypulse_social_links() {
	$networks = array(
		'facebook'  => __( 'Facebook', 'dailypulse' ),
		'x'         => __( 'X', 'dailypulse' ),
		'youtube'   => __( 'YouTube', 'dailypulse' ),
		'instagram' => __( 'Instagram', 'dailypulse' ),
		'whatsapp'  => __( 'WhatsApp', 'dailypulse' ),
		'telegram'  => __( 'Telegram', 'dailypulse' ),
		'tiktok'    => __( 'TikTok', 'dailypulse' ),
	);
	$links    = array();

	foreach ( $networks as $network => $label ) {
		$url = (string) dailypulse_option( 'social_' . $network, '' );

		if ( '' !== $url ) {
			$links[] = array(
				'label' => $label,
				'url'   => $url,
				'icon'  => $network,
			);
		}
	}

	return $links;
}

/**
 * Share targets for a story.
 *
 * @param int $post_id Optional. Defaults to the current post.
 * @return array[] Each with `label`, `url` and `icon`.
 */
function dailypulse_share_links( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	$url     = rawurlencode( (string) get_permalink( $post_id ) );
	$title   = rawurlencode( html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) );

	return array(
		array(
			'label' => __( 'Share on WhatsApp', 'dailypulse' ),
			'url'   => 'https://wa.me/?text=' . $title . '%20' . $url,
			'icon'  => 'whatsapp',
		),
		array(
			'label' => __( 'Share on Facebook', 'dailypulse' ),
			'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
			'icon'  => 'facebook',
		),
		array(
			'label' => __( 'Share on X', 'dailypulse' ),
			'url'   => 'https://twitter.com/intent/tweet?text=' . $title . '&url=' . $url,
			'icon'  => 'x',
		),
		array(
			'label' => __( 'Share on Telegram', 'dailypulse' ),
			'url'   => 'https://t.me/share/url?url=' . $url . '&text=' . $title,
			'icon'  => 'telegram',
		),
	);
}

/**
 * Prints the share buttons for the current story.
 */
function dailypulse_share() {
	if ( ! dailypulse_option( 'show_share' ) ) {
		return;
	}
	?>
	<ul class="share" aria-label="<?php esc_attr_e( 'Share this story', 'dailypulse' ); ?>">
		<?php foreach ( dailypulse_share_links() as $target ) : ?>
			<li>
				<a class="share__btn share__btn--<?php echo esc_attr( $target['icon'] ); ?>" href="<?php echo esc_url( $target['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $target['label'] ); ?>" title="<?php echo esc_attr( $target['label'] ); ?>">
					<?php echo dailypulse_icon( $target['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				</a>
			</li>
		<?php endforeach; ?>
		<li>
			<button class="share__btn" type="button" data-copy-link="<?php echo esc_url( (string) get_permalink() ); ?>" aria-label="<?php esc_attr_e( 'Copy link', 'dailypulse' ); ?>" title="<?php esc_attr_e( 'Copy link', 'dailypulse' ); ?>">
				<?php echo dailypulse_icon( 'link', 'share__icon-link' ) . dailypulse_icon( 'check', 'share__icon-done' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			</button>
		</li>
		<li hidden>
			<button class="share__btn" type="button" data-native-share data-title="<?php echo esc_attr( get_the_title() ); ?>" data-url="<?php echo esc_url( (string) get_permalink() ); ?>" aria-label="<?php esc_attr_e( 'More sharing options', 'dailypulse' ); ?>" title="<?php esc_attr_e( 'More sharing options', 'dailypulse' ); ?>">
				<?php echo dailypulse_icon( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			</button>
		</li>
	</ul>
	<?php
}

/**
 * Prints numbered pagination for archives.
 */
function dailypulse_pagination() {
	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'prev_text'          => dailypulse_icon( 'arrow-left' ) . '<span class="screen-reader-text">' . esc_html__( 'Newer stories', 'dailypulse' ) . '</span>',
			'next_text'          => '<span class="screen-reader-text">' . esc_html__( 'Older stories', 'dailypulse' ) . '</span>' . dailypulse_icon( 'arrow-right' ),
			'screen_reader_text' => __( 'Stories navigation', 'dailypulse' ),
			'class'              => 'pagination',
		)
	);
}

/**
 * The footer copyright line with its placeholders filled in.
 *
 * @return string
 */
function dailypulse_copyright() {
	return strtr(
		(string) dailypulse_option( 'footer_copyright' ),
		array(
			'{year}' => wp_date( 'Y' ),
			'{site}' => get_bloginfo( 'name' ),
		)
	);
}

/**
 * Prints a section heading: coloured marker, title, rule and optional link.
 *
 * @param string $title Heading text.
 * @param array  $args {
 *     Optional.
 *
 *     @type string $url   "View all" target.
 *     @type string $label "View all" text.
 *     @type string $id    ID for the heading element.
 *     @type string $after Extra markup after the rule (already escaped).
 * }
 */
function dailypulse_section_head( $title, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'url'   => '',
			'label' => __( 'View all', 'dailypulse' ),
			'id'    => '',
			'after' => '',
		)
	);
	?>
	<header class="section__head">
		<h2 class="section__title"<?php echo $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : ''; ?>>
			<?php if ( $args['url'] ) : ?>
				<a href="<?php echo esc_url( $args['url'] ); ?>"><?php echo esc_html( $title ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $title ); ?>
			<?php endif; ?>
		</h2>
		<span class="section__rule" aria-hidden="true"></span>
		<?php echo $args['after']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts by the caller. ?>
		<?php if ( $args['url'] ) : ?>
			<a class="section__more" href="<?php echo esc_url( $args['url'] ); ?>">
				<?php echo esc_html( $args['label'] ); ?>
				<span class="screen-reader-text"><?php echo esc_html( $title ); ?></span>
				<?php echo dailypulse_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			</a>
		<?php endif; ?>
	</header>
	<?php
}
