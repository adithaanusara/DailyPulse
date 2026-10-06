<?php
/**
 * The single source of truth for every custom field and site option.
 *
 * The same definitions drive three things: the stored meta/options (and their
 * sanitisation), the native WordPress editing screens, and — when ACF or Secure
 * Custom Fields is active — the ACF field groups and options page.
 *
 * Supported field types: text, textarea, url, number, true_false, select, color,
 * gallery (attachment IDs) and sources (rows of label + URL).
 *
 * Do not call these before `init`: the labels are translated.
 *
 * @package DailyPulse_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field groups shown on post edit screens.
 *
 * @return array Group ID => group definition.
 */
function dailypulse_field_groups() {
	static $groups = null;

	if ( null !== $groups ) {
		return $groups;
	}

	$groups = array(
		'story'   => array(
			'title'      => __( 'Story Details', 'dailypulse-core' ),
			'post_types' => array( 'post' ),
			'fields'     => array(
				'dp_kicker'       => array(
					'label'        => __( 'Kicker', 'dailypulse-core' ),
					'type'         => 'text',
					'maxlength'    => 40,
					'instructions' => __( 'A short label above the headline, such as “Exclusive”, “Live” or “Analysis”. Leave empty to show the category.', 'dailypulse-core' ),
				),
				'dp_breaking'     => array(
					'label'        => __( 'Breaking news', 'dailypulse-core' ),
					'type'         => 'true_false',
					'message'      => __( 'Show this story in the breaking-news ticker', 'dailypulse-core' ),
					'instructions' => __( 'It leaves the ticker by itself after the number of hours set under DailyPulse → Site Options.', 'dailypulse-core' ),
				),
				'dp_featured'     => array(
					'label'        => __( 'Hero slider', 'dailypulse-core' ),
					'type'         => 'true_false',
					'message'      => __( 'Feature this story in the homepage hero', 'dailypulse-core' ),
					'instructions' => __( 'The hero shows the newest featured stories and tops up with the latest news.', 'dailypulse-core' ),
				),
				'dp_byline'       => array(
					'label'        => __( 'Author credit', 'dailypulse-core' ),
					'type'         => 'text',
					'maxlength'    => 120,
					'placeholder'  => __( 'e.g. Staff Reporter', 'dailypulse-core' ),
					'instructions' => __( 'Shown as “By …”. Leave empty to use the name of the WordPress author.', 'dailypulse-core' ),
				),
				'dp_photo_credit' => array(
					'label'        => __( 'Photo credit', 'dailypulse-core' ),
					'type'         => 'text',
					'maxlength'    => 120,
					'instructions' => __( 'Credit for the featured image: a photographer or an agency.', 'dailypulse-core' ),
				),
			),
		),
		'sources' => array(
			'title'      => __( 'Source Links', 'dailypulse-core' ),
			'post_types' => array( 'post' ),
			'fields'     => array(
				'dp_sources' => array(
					'label'        => __( 'Sources', 'dailypulse-core' ),
					'type'         => 'sources',
					'instructions' => __( 'Where the story comes from. Listed under the article; the link is optional.', 'dailypulse-core' ),
				),
			),
		),
		'gallery' => array(
			'title'      => __( 'Media Gallery', 'dailypulse-core' ),
			'post_types' => array( 'post' ),
			'fields'     => array(
				'dp_gallery' => array(
					'label'        => __( 'Gallery images', 'dailypulse-core' ),
					'type'         => 'gallery',
					'instructions' => __( 'Extra photos shown as a gallery under the story. Drag to reorder.', 'dailypulse-core' ),
				),
			),
		),
		'alert'   => array(
			'title'      => __( 'Alert Details', 'dailypulse-core' ),
			'post_types' => array( 'dp_alert' ),
			'fields'     => array(
				'dp_alert_url'   => array(
					'label'        => __( 'Link', 'dailypulse-core' ),
					'type'         => 'url',
					'instructions' => __( 'Optional. Where the alert leads, for example the full story once it is published.', 'dailypulse-core' ),
				),
				'dp_alert_hours' => array(
					'label'        => __( 'Show for', 'dailypulse-core' ),
					'type'         => 'number',
					'default'      => 6,
					'min'          => 1,
					'max'          => 72,
					'append'       => __( 'hours', 'dailypulse-core' ),
					'instructions' => __( 'How long the alert stays in the ticker after it is published.', 'dailypulse-core' ),
				),
			),
		),
	);

	/**
	 * Filters the post field groups. Add a field here and it is stored, sanitised
	 * and editable (natively or through ACF) without further code.
	 *
	 * @param array $groups Group ID => group definition.
	 */
	$groups = apply_filters( 'dailypulse_field_groups', $groups );

	return $groups;
}

/**
 * Every post field, flattened.
 *
 * @return array Field name => field definition (with `post_types` added).
 */
function dailypulse_fields() {
	static $fields = null;

	if ( null === $fields ) {
		$fields = array();

		foreach ( dailypulse_field_groups() as $group ) {
			foreach ( $group['fields'] as $name => $field ) {
				$field['post_types'] = $group['post_types'];
				$fields[ $name ]     = $field;
			}
		}
	}

	return $fields;
}

/**
 * Site options, grouped into the sections of the options page.
 *
 * @return array Section ID => section definition.
 */
function dailypulse_option_sections() {
	static $sections = null;

	if ( null !== $sections ) {
		return $sections;
	}

	$sections = array(
		'general' => array(
			'title'  => __( 'Look & feel', 'dailypulse-core' ),
			'fields' => array(
				'accent_color'   => array(
					'label'        => __( 'Accent colour', 'dailypulse-core' ),
					'type'         => 'color',
					'default'      => '#0b6fe0',
					'instructions' => __( 'Used for the menu highlight, links and buttons. Category colours are set on each category.', 'dailypulse-core' ),
				),
				'color_scheme'   => array(
					'label'        => __( 'Default appearance', 'dailypulse-core' ),
					'type'         => 'select',
					'default'      => 'auto',
					'choices'      => array(
						'auto'  => __( 'Follow the visitor’s device', 'dailypulse-core' ),
						'light' => __( 'Light', 'dailypulse-core' ),
						'dark'  => __( 'Dark', 'dailypulse-core' ),
					),
					'instructions' => __( 'Visitors can still switch with the toggle in the header; their choice is remembered.', 'dailypulse-core' ),
				),
				'relative_dates' => array(
					'label'   => __( 'Story times', 'dailypulse-core' ),
					'type'    => 'true_false',
					'default' => 1,
					'message' => __( 'Show “2 hours ago” for stories from the last 24 hours', 'dailypulse-core' ),
				),
			),
		),
		'ticker'  => array(
			'title'  => __( 'Breaking-news ticker', 'dailypulse-core' ),
			'fields' => array(
				'ticker_enabled'        => array(
					'label'   => __( 'Ticker', 'dailypulse-core' ),
					'type'    => 'true_false',
					'default' => 1,
					'message' => __( 'Show the ticker under the header', 'dailypulse-core' ),
				),
				'ticker_label'          => array(
					'label'     => __( 'Label while something is breaking', 'dailypulse-core' ),
					'type'      => 'text',
					'default'   => __( 'Breaking', 'dailypulse-core' ),
					'maxlength' => 24,
					'required'  => true,
				),
				'breaking_hours'        => array(
					'label'        => __( 'Breaking stories stay for', 'dailypulse-core' ),
					'type'         => 'number',
					'default'      => 6,
					'min'          => 1,
					'max'          => 72,
					'append'       => __( 'hours', 'dailypulse-core' ),
					'instructions' => __( 'Counted from when a story is marked as breaking (or published, if later).', 'dailypulse-core' ),
				),
				'ticker_fallback'       => array(
					'label'   => __( 'When nothing is breaking', 'dailypulse-core' ),
					'type'    => 'true_false',
					'default' => 1,
					'message' => __( 'Show the latest headlines instead', 'dailypulse-core' ),
				),
				'ticker_fallback_label' => array(
					'label'     => __( 'Label for latest headlines', 'dailypulse-core' ),
					'type'      => 'text',
					'default'   => __( 'Latest', 'dailypulse-core' ),
					'maxlength' => 24,
					'required'  => true,
				),
				'ticker_count'          => array(
					'label'   => __( 'Headlines in the ticker', 'dailypulse-core' ),
					'type'    => 'number',
					'default' => 6,
					'min'     => 1,
					'max'     => 12,
				),
				'ticker_speed'          => array(
					'label'   => __( 'Time per headline', 'dailypulse-core' ),
					'type'    => 'number',
					'default' => 5,
					'min'     => 3,
					'max'     => 20,
					'append'  => __( 'seconds', 'dailypulse-core' ),
				),
			),
		),
		'home'    => array(
			'title'  => __( 'Homepage', 'dailypulse-core' ),
			'fields' => array(
				'hero_count'    => array(
					'label'   => __( 'Stories in the hero slider', 'dailypulse-core' ),
					'type'    => 'number',
					'default' => 5,
					'min'     => 1,
					'max'     => 8,
				),
				'hero_autoplay' => array(
					'label'   => __( 'Hero autoplay', 'dailypulse-core' ),
					'type'    => 'true_false',
					'default' => 1,
					'message' => __( 'Advance the hero slider automatically', 'dailypulse-core' ),
				),
				'hero_interval' => array(
					'label'   => __( 'Time per slide', 'dailypulse-core' ),
					'type'    => 'number',
					'default' => 7,
					'min'     => 4,
					'max'     => 30,
					'append'  => __( 'seconds', 'dailypulse-core' ),
				),
				'feed_count'    => array(
					'label'        => __( 'Stories per page in “Latest”', 'dailypulse-core' ),
					'type'         => 'number',
					'default'      => 8,
					'min'          => 3,
					'max'          => 24,
					'instructions' => __( 'Category sections further down the homepage are configured on each category (Posts → Categories).', 'dailypulse-core' ),
				),
			),
		),
		'article' => array(
			'title'  => __( 'Article page', 'dailypulse-core' ),
			'fields' => array(
				'show_reading_time' => array(
					'label'   => __( 'Reading time', 'dailypulse-core' ),
					'type'    => 'true_false',
					'default' => 1,
					'message' => __( 'Show the estimated reading time', 'dailypulse-core' ),
				),
				'show_share'        => array(
					'label'   => __( 'Share buttons', 'dailypulse-core' ),
					'type'    => 'true_false',
					'default' => 1,
					'message' => __( 'Show WhatsApp, Facebook, X, Telegram and copy-link buttons', 'dailypulse-core' ),
				),
				'show_progress'     => array(
					'label'   => __( 'Reading progress', 'dailypulse-core' ),
					'type'    => 'true_false',
					'default' => 1,
					'message' => __( 'Show a progress bar while reading', 'dailypulse-core' ),
				),
				'related_count'     => array(
					'label'        => __( 'Related stories', 'dailypulse-core' ),
					'type'         => 'number',
					'default'      => 3,
					'min'          => 0,
					'max'          => 6,
					'instructions' => __( 'From the same category. Set to 0 to hide.', 'dailypulse-core' ),
				),
			),
		),
		'footer'  => array(
			'title'  => __( 'Footer & social', 'dailypulse-core' ),
			'fields' => array(
				'footer_about'     => array(
					'label'   => __( 'About text', 'dailypulse-core' ),
					'type'    => 'textarea',
					'default' => __( 'Independent news that moves with you — local and foreign reporting, sport and long reads, updated around the clock.', 'dailypulse-core' ),
				),
				'footer_copyright' => array(
					'label'        => __( 'Copyright line', 'dailypulse-core' ),
					'type'         => 'text',
					'default'      => __( '© {year} {site}. All rights reserved.', 'dailypulse-core' ),
					'instructions' => __( '{year} and {site} are filled in automatically.', 'dailypulse-core' ),
				),
				'social_facebook'  => array(
					'label' => __( 'Facebook URL', 'dailypulse-core' ),
					'type'  => 'url',
				),
				'social_x'         => array(
					'label' => __( 'X (Twitter) URL', 'dailypulse-core' ),
					'type'  => 'url',
				),
				'social_youtube'   => array(
					'label' => __( 'YouTube URL', 'dailypulse-core' ),
					'type'  => 'url',
				),
				'social_instagram' => array(
					'label' => __( 'Instagram URL', 'dailypulse-core' ),
					'type'  => 'url',
				),
				'social_whatsapp'  => array(
					'label' => __( 'WhatsApp channel URL', 'dailypulse-core' ),
					'type'  => 'url',
				),
				'social_telegram'  => array(
					'label' => __( 'Telegram URL', 'dailypulse-core' ),
					'type'  => 'url',
				),
				'social_tiktok'    => array(
					'label' => __( 'TikTok URL', 'dailypulse-core' ),
					'type'  => 'url',
				),
			),
		),
	);

	/**
	 * Filters the site option sections.
	 *
	 * @param array $sections Section ID => section definition.
	 */
	$sections = apply_filters( 'dailypulse_option_sections', $sections );

	return $sections;
}

/**
 * Every site option, flattened.
 *
 * @return array Option key => field definition.
 */
function dailypulse_option_fields() {
	static $fields = null;

	if ( null === $fields ) {
		$fields = array();

		foreach ( dailypulse_option_sections() as $section ) {
			$fields += $section['fields'];
		}
	}

	return $fields;
}

/**
 * Settings stored on each category (as term meta).
 *
 * @return array Meta key => field definition.
 */
function dailypulse_category_fields() {
	return array(
		'dp_color'  => array(
			'label'        => __( 'Colour', 'dailypulse-core' ),
			'type'         => 'color',
			'default'      => '',
			'instructions' => __( 'Tints this category’s labels, tabs and section headings.', 'dailypulse-core' ),
		),
		'dp_home'   => array(
			'label'   => __( 'Homepage', 'dailypulse-core' ),
			'type'    => 'true_false',
			'default' => 1,
			'message' => __( 'Give this category its own section and filter tab on the homepage', 'dailypulse-core' ),
		),
		'dp_order'  => array(
			'label'        => __( 'Position', 'dailypulse-core' ),
			'type'         => 'number',
			'default'      => 100,
			'min'          => 0,
			'max'          => 999,
			'instructions' => __( 'Lower numbers come first on the homepage and in the tabs.', 'dailypulse-core' ),
		),
		'dp_layout' => array(
			'label'        => __( 'Homepage layout', 'dailypulse-core' ),
			'type'         => 'select',
			'default'      => 'auto',
			'choices'      => array(
				'auto'     => __( 'Box beside the sidebar (default)', 'dailypulse-core' ),
				'lead'     => __( 'Full width: lead story + headline list', 'dailypulse-core' ),
				'grid'     => __( 'Full width: four-card grid', 'dailypulse-core' ),
				'magazine' => __( 'Full width: feature + numbered reads', 'dailypulse-core' ),
				'band'     => __( 'Full width: dark band with a sideways scroller', 'dailypulse-core' ),
			),
			'instructions' => __( 'How this category’s section looks on the homepage.', 'dailypulse-core' ),
		),
	);
}

/**
 * Sanitises a value according to its field definition.
 *
 * @param array $field Field definition.
 * @param mixed $value Raw (unslashed) value.
 * @return mixed
 */
function dailypulse_sanitize_value( $field, $value ) {
	$type = isset( $field['type'] ) ? $field['type'] : 'text';

	switch ( $type ) {
		case 'true_false':
			return ( ! empty( $value ) && '0' !== $value && 'false' !== $value ) ? '1' : '0';

		case 'number':
			if ( '' === $value || null === $value || ! is_numeric( $value ) ) {
				return isset( $field['default'] ) ? (int) $field['default'] : '';
			}
			$number = (int) $value;
			if ( isset( $field['min'] ) ) {
				$number = max( (int) $field['min'], $number );
			}
			if ( isset( $field['max'] ) ) {
				$number = min( (int) $field['max'], $number );
			}
			return $number;

		case 'select':
			$choices = isset( $field['choices'] ) ? $field['choices'] : array();
			if ( is_scalar( $value ) && isset( $choices[ (string) $value ] ) ) {
				return (string) $value;
			}
			return isset( $field['default'] ) ? $field['default'] : '';

		case 'color':
			$color = is_string( $value ) ? sanitize_hex_color( $value ) : '';
			return $color ? $color : ( isset( $field['default'] ) ? $field['default'] : '' );

		case 'url':
			return is_string( $value ) ? esc_url_raw( trim( $value ) ) : '';

		case 'textarea':
			return is_string( $value ) ? sanitize_textarea_field( $value ) : '';

		case 'gallery':
			if ( is_string( $value ) ) {
				$value = explode( ',', $value );
			}
			return array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) );

		case 'sources':
			$rows = array();
			foreach ( (array) $value as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
				$url   = isset( $row['url'] ) ? esc_url_raw( trim( (string) $row['url'] ) ) : '';
				if ( '' === $label && '' !== $url ) {
					$label = (string) wp_parse_url( $url, PHP_URL_HOST );
				}
				if ( '' !== $label ) {
					$rows[] = array(
						'label' => $label,
						'url'   => $url,
					);
				}
			}
			return $rows;

		default:
			$text = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
			if ( ! empty( $field['maxlength'] ) ) {
				$text = mb_substr( $text, 0, (int) $field['maxlength'] );
			}
			return $text;
	}
}
