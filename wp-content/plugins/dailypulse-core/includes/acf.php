<?php
/**
 * ACF bridge.
 *
 * When Advanced Custom Fields (free or PRO) or Secure Custom Fields is active,
 * the schema is registered as ACF field groups, so editors get ACF's interface.
 * Anything the installed ACF edition cannot render — the gallery and the options
 * page need ACF PRO or Secure Custom Fields; source links are always native —
 * falls back to the built-in screens in admin-fields.php / admin-pages.php.
 *
 * Field names are identical in both modes and ACF stores values in the same
 * post meta / option rows the native screens use, so nothing is lost when ACF is
 * switched on or off.
 *
 * @package DailyPulse_Core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', 'dailypulse_core_acf_register' );

/**
 * Remembers (and reports) which fields ACF has taken over.
 *
 * @param string|null $name Field name to mark, or null to only read.
 * @return array Field name => true.
 */
function dailypulse_core_acf_fields( $name = null ) {
	static $handled = array();

	if ( null !== $name ) {
		$handled[ $name ] = true;
	}

	return $handled;
}

/**
 * Whether ACF renders the site options page.
 *
 * @return bool
 */
function dailypulse_core_acf_has_options_page() {
	return function_exists( 'acf_add_options_page' ) && function_exists( 'acf_add_local_field_group' );
}

/**
 * Converts a schema field into an ACF field definition.
 *
 * @param string $name  Field name.
 * @param array  $field Schema field.
 * @return array|null Null when this ACF edition has no matching field type.
 */
function dailypulse_core_acf_field( $name, $field ) {
	$map = array(
		'text'       => 'text',
		'textarea'   => 'textarea',
		'url'        => 'url',
		'number'     => 'number',
		'true_false' => 'true_false',
		'select'     => 'select',
		'color'      => 'color_picker',
		'gallery'    => 'gallery',
	);

	if ( ! isset( $map[ $field['type'] ] ) || ! function_exists( 'acf_get_field_type' ) || ! acf_get_field_type( $map[ $field['type'] ] ) ) {
		return null;
	}

	$acf = array(
		'key'          => 'field_' . $name,
		'name'         => $name,
		'label'        => $field['label'],
		'type'         => $map[ $field['type'] ],
		'instructions' => isset( $field['instructions'] ) ? $field['instructions'] : '',
	);

	if ( isset( $field['default'] ) ) {
		$acf['default_value'] = $field['default'];
	}

	switch ( $field['type'] ) {
		case 'text':
			$acf['maxlength']   = isset( $field['maxlength'] ) ? $field['maxlength'] : '';
			$acf['placeholder'] = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
			break;

		case 'textarea':
			$acf['rows']      = 3;
			$acf['new_lines'] = '';
			break;

		case 'number':
			$acf['min']    = isset( $field['min'] ) ? $field['min'] : '';
			$acf['max']    = isset( $field['max'] ) ? $field['max'] : '';
			$acf['step']   = 1;
			$acf['append'] = isset( $field['append'] ) ? $field['append'] : '';
			break;

		case 'true_false':
			$acf['ui']      = 1;
			$acf['message'] = isset( $field['message'] ) ? $field['message'] : '';
			break;

		case 'select':
			$acf['choices']       = $field['choices'];
			$acf['return_format'] = 'value';
			$acf['allow_null']    = 0;
			break;

		case 'color':
			$acf['enable_opacity'] = 0;
			$acf['return_format']  = 'string';
			break;

		case 'gallery':
			$acf['return_format'] = 'id';
			$acf['preview_size']  = 'thumbnail';
			$acf['library']       = 'all';
			$acf['mime_types']    = 'jpg, jpeg, png, gif, webp, avif';
			$acf['insert']        = 'append';
			break;
	}

	return $acf;
}

/**
 * Registers the field groups (and the options page, where supported) with ACF.
 */
function dailypulse_core_acf_register() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	foreach ( dailypulse_field_groups() as $group_id => $group ) {
		$fields = array();

		foreach ( $group['fields'] as $name => $field ) {
			$acf = dailypulse_core_acf_field( $name, $field );

			if ( $acf ) {
				$fields[] = $acf;
				dailypulse_core_acf_fields( $name );
			}
		}

		if ( ! $fields ) {
			continue;
		}

		$location = array();
		foreach ( $group['post_types'] as $post_type ) {
			$location[] = array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => $post_type,
				),
			);
		}

		acf_add_local_field_group(
			array(
				'key'             => 'group_dailypulse_' . $group_id,
				'title'           => $group['title'],
				'fields'          => $fields,
				'location'        => $location,
				'position'        => 'normal',
				'style'           => 'default',
				'label_placement' => 'left',
				'menu_order'      => 0,
				'show_in_rest'    => 0,
			)
		);
	}

	if ( ! dailypulse_core_acf_has_options_page() ) {
		return;
	}

	acf_add_options_page(
		array(
			'page_title'      => __( 'DailyPulse Site Options', 'dailypulse-core' ),
			'menu_title'      => __( 'DailyPulse', 'dailypulse-core' ),
			'menu_slug'       => 'dailypulse',
			'capability'      => 'manage_options',
			'icon_url'        => dailypulse_core_menu_icon(),
			'position'        => 59,
			'redirect'        => false,
			'autoload'        => true,
			'update_button'   => __( 'Save options', 'dailypulse-core' ),
			'updated_message' => __( 'Options saved.', 'dailypulse-core' ),
		)
	);

	$order = 0;

	foreach ( dailypulse_option_sections() as $section_id => $section ) {
		$fields = array();

		foreach ( $section['fields'] as $key => $field ) {
			// ACF stores an option named "dp_x" in the row "options_dp_x", which is what dailypulse_option() reads.
			$acf = dailypulse_core_acf_field( 'dp_' . $key, $field );

			if ( $acf ) {
				$acf['key'] = 'field_dailypulse_option_' . $key;
				$fields[]   = $acf;
			}
		}

		acf_add_local_field_group(
			array(
				'key'             => 'group_dailypulse_options_' . $section_id,
				'title'           => $section['title'],
				'fields'          => $fields,
				'location'        => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => 'dailypulse',
						),
					),
				),
				'style'           => 'default',
				'label_placement' => 'left',
				'menu_order'      => $order++,
			)
		);
	}
}

/**
 * The admin menu icon: a pulse line, as a base64 SVG WordPress can recolour.
 *
 * @return string Data URI.
 */
function dailypulse_core_menu_icon() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M7.6 2.5c.5 0 .9.3 1 .8l2.7 9.6 1.4-4.1c.1-.4.5-.7 1-.7h4.8a1 1 0 1 1 0 2h-4.1l-2.3 6.7a1 1 0 0 1-1.9-.1L7.5 7.3 6.3 10.9c-.2.4-.5.6-1 .6H1.5a1 1 0 1 1 0-2h3.2l2-6.3c.1-.4.5-.7.9-.7z"/></svg>';

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- data URI for an icon.
	return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}
