<?php
/**
 * Native editing screens for the schema: post meta boxes and category settings.
 *
 * Any field ACF has taken over (see acf.php) is skipped here, so each field is
 * only ever shown once.
 *
 * @package DailyPulse_Core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', 'dailypulse_core_admin_assets' );
add_action( 'add_meta_boxes', 'dailypulse_core_add_meta_boxes' );
add_action( 'save_post', 'dailypulse_core_save_meta_boxes', 10, 2 );
add_filter( 'display_post_states', 'dailypulse_core_post_states', 10, 2 );

add_action( 'category_add_form_fields', 'dailypulse_core_category_add_fields' );
add_action( 'category_edit_form_fields', 'dailypulse_core_category_edit_fields' );
add_action( 'created_category', 'dailypulse_core_save_category' );
add_action( 'edited_category', 'dailypulse_core_save_category' );
add_filter( 'manage_edit-category_columns', 'dailypulse_core_category_columns' );
add_filter( 'manage_category_custom_column', 'dailypulse_core_category_column', 10, 3 );

add_filter( 'manage_dp_alert_posts_columns', 'dailypulse_core_alert_columns' );
add_action( 'manage_dp_alert_posts_custom_column', 'dailypulse_core_alert_column', 10, 2 );

/**
 * Loads the admin stylesheet and script on DailyPulse screens only.
 *
 * @param string $hook Current admin page.
 */
function dailypulse_core_admin_assets( $hook ) {
	$screen = get_current_screen();

	if ( ! $screen ) {
		return;
	}

	$is_editor = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && in_array( $screen->post_type, array( 'post', 'dp_alert' ), true );
	$is_term   = 'category' === $screen->taxonomy && in_array( $hook, array( 'edit-tags.php', 'term.php' ), true );
	$is_list   = 'edit.php' === $hook && 'dp_alert' === $screen->post_type;
	$is_page   = false !== strpos( $screen->id, 'dailypulse' );

	if ( ! $is_editor && ! $is_term && ! $is_list && ! $is_page ) {
		return;
	}

	$dependencies = array( 'jquery', 'wp-color-picker' );

	if ( $is_editor ) {
		wp_enqueue_media();
		$dependencies[] = 'jquery-ui-sortable';
	}

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_style( 'dailypulse-admin', DAILYPULSE_CORE_URL . 'assets/admin.css', array(), (string) filemtime( DAILYPULSE_CORE_DIR . 'assets/admin.css' ) );
	wp_enqueue_script( 'dailypulse-admin', DAILYPULSE_CORE_URL . 'assets/admin.js', $dependencies, (string) filemtime( DAILYPULSE_CORE_DIR . 'assets/admin.js' ), true );
	wp_localize_script(
		'dailypulse-admin',
		'dailypulseAdmin',
		array(
			'galleryTitle'  => __( 'Choose gallery images', 'dailypulse-core' ),
			'galleryButton' => __( 'Add to gallery', 'dailypulse-core' ),
			'remove'        => __( 'Remove', 'dailypulse-core' ),
		)
	);
}

/**
 * Fields of a group that ACF is not rendering.
 *
 * @param array $group Group definition.
 * @return array Field name => field definition.
 */
function dailypulse_core_native_fields( $group ) {
	return array_diff_key( $group['fields'], dailypulse_core_acf_fields() );
}

/**
 * Prints the form control for one field.
 *
 * @param string $name  Value of the name attribute.
 * @param array  $field Field definition.
 * @param mixed  $value Current value.
 * @param string $id    Optional. Value of the id attribute; derived from the name by default.
 */
function dailypulse_core_render_control( $name, $field, $value, $id = '' ) {
	$id   = $id ? $id : trim( preg_replace( '/[^a-z0-9_-]+/i', '-', $name ), '-' );
	$type = isset( $field['type'] ) ? $field['type'] : 'text';

	switch ( $type ) {
		case 'true_false':
			printf(
				'<label class="dp-check"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> <span>%4$s</span></label>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( ! empty( $value ) && '0' !== $value, true, false ),
				esc_html( isset( $field['message'] ) ? $field['message'] : '' )
			);
			break;

		case 'number':
			printf(
				'<input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$s" step="1"%4$s%5$s> %6$s',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) $value ),
				isset( $field['min'] ) ? ' min="' . esc_attr( (string) $field['min'] ) . '"' : '',
				isset( $field['max'] ) ? ' max="' . esc_attr( (string) $field['max'] ) . '"' : '',
				esc_html( isset( $field['append'] ) ? $field['append'] : '' )
			);
			break;

		case 'select':
			printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
			foreach ( $field['choices'] as $choice => $label ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $choice ), selected( (string) $value, (string) $choice, false ), esc_html( $label ) );
			}
			echo '</select>';
			break;

		case 'color':
			printf(
				'<input type="text" class="dp-color" id="%1$s" name="%2$s" value="%3$s" data-default-color="%4$s">',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) $value ),
				esc_attr( isset( $field['default'] ) ? (string) $field['default'] : '' )
			);
			break;

		case 'textarea':
			printf( '<textarea class="large-text" rows="3" id="%1$s" name="%2$s">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
			break;

		case 'url':
			printf( '<input type="url" class="regular-text code" id="%1$s" name="%2$s" value="%3$s" placeholder="https://">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
			break;

		case 'sources':
			dailypulse_core_render_sources( $name, is_array( $value ) ? $value : array() );
			break;

		case 'gallery':
			dailypulse_core_render_gallery( $name, is_array( $value ) ? $value : array() );
			break;

		default:
			printf(
				'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s"%4$s%5$s>',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) $value ),
				! empty( $field['maxlength'] ) ? ' maxlength="' . esc_attr( (string) $field['maxlength'] ) . '"' : '',
				! empty( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''
			);
	}
}

/**
 * Prints one row of the source-links repeater.
 *
 * @param string $name  Field name.
 * @param string $index Row index, or a placeholder for the template row.
 * @param array  $row   Row values.
 */
function dailypulse_core_render_source_row( $name, $index, $row = array() ) {
	?>
	<div class="dp-sources__row">
		<span class="dp-sources__handle dashicons dashicons-menu" aria-hidden="true"></span>
		<input type="text" name="<?php echo esc_attr( $name . '[' . $index . '][label]' ); ?>" value="<?php echo esc_attr( isset( $row['label'] ) ? $row['label'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Source name, e.g. Reuters', 'dailypulse-core' ); ?>" aria-label="<?php esc_attr_e( 'Source name', 'dailypulse-core' ); ?>">
		<input type="url" class="code" name="<?php echo esc_attr( $name . '[' . $index . '][url]' ); ?>" value="<?php echo esc_attr( isset( $row['url'] ) ? $row['url'] : '' ); ?>" placeholder="https://" aria-label="<?php esc_attr_e( 'Source link', 'dailypulse-core' ); ?>">
		<button type="button" class="button-link dp-sources__remove" aria-label="<?php esc_attr_e( 'Remove this source', 'dailypulse-core' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
	</div>
	<?php
}

/**
 * Prints the source-links repeater.
 *
 * @param string $name Field name.
 * @param array  $rows Saved rows.
 */
function dailypulse_core_render_sources( $name, $rows ) {
	// Always offer one empty row so the field also works without JavaScript.
	$rows[] = array();
	?>
	<div class="dp-sources" data-dp-sources data-next="<?php echo esc_attr( (string) count( $rows ) ); ?>">
		<div class="dp-sources__rows">
			<?php
			foreach ( array_values( $rows ) as $index => $row ) {
				dailypulse_core_render_source_row( $name, (string) $index, $row );
			}
			?>
		</div>
		<template><?php dailypulse_core_render_source_row( $name, '__i__' ); ?></template>
		<button type="button" class="button dp-sources__add"><?php esc_html_e( 'Add another source', 'dailypulse-core' ); ?></button>
	</div>
	<?php
}

/**
 * Prints the gallery picker.
 *
 * @param string $name Field name.
 * @param int[]  $ids  Saved attachment IDs.
 */
function dailypulse_core_render_gallery( $name, $ids ) {
	$ids = array_values( array_filter( array_map( 'absint', $ids ), 'wp_attachment_is_image' ) );
	?>
	<div class="dp-gallery" data-dp-gallery>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
		<ul class="dp-gallery__list">
			<?php foreach ( $ids as $attachment_id ) : ?>
				<li class="dp-gallery__item" data-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
					<?php echo wp_get_attachment_image( $attachment_id, 'thumbnail' ); ?>
					<button type="button" class="dp-gallery__remove" aria-label="<?php esc_attr_e( 'Remove image', 'dailypulse-core' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
				</li>
			<?php endforeach; ?>
		</ul>
		<button type="button" class="button dp-gallery__add"><?php esc_html_e( 'Add images', 'dailypulse-core' ); ?></button>
	</div>
	<?php
}

/*
 * -------------------------------------------------------------------------
 * Post meta boxes
 * -------------------------------------------------------------------------
 */

/**
 * Adds one meta box per field group that still has native fields.
 *
 * @param string $post_type Post type being edited.
 */
function dailypulse_core_add_meta_boxes( $post_type ) {
	foreach ( dailypulse_field_groups() as $group_id => $group ) {
		if ( ! in_array( $post_type, $group['post_types'], true ) || ! dailypulse_core_native_fields( $group ) ) {
			continue;
		}

		add_meta_box(
			'dailypulse-' . $group_id,
			$group['title'],
			'dailypulse_core_render_meta_box',
			$post_type,
			'normal',
			'high',
			array(
				'group'                              => $group_id,
				'__block_editor_compatible_meta_box' => true,
			)
		);
	}
}

/**
 * Renders a field group as a meta box.
 *
 * @param WP_Post $post Post being edited.
 * @param array   $box  Meta box arguments.
 */
function dailypulse_core_render_meta_box( $post, $box ) {
	$groups   = dailypulse_field_groups();
	$group_id = $box['args']['group'];

	wp_nonce_field( 'dailypulse_save_' . $group_id, '_dailypulse_nonce_' . $group_id, false );

	echo '<div class="dp-fields">';

	foreach ( dailypulse_core_native_fields( $groups[ $group_id ] ) as $name => $field ) {
		$value = get_post_meta( $post->ID, $name, true );

		if ( '' === $value && isset( $field['default'] ) ) {
			$value = $field['default'];
		}

		$is_group_like = in_array( $field['type'], array( 'true_false', 'sources', 'gallery' ), true );
		?>
		<div class="dp-field dp-field--<?php echo esc_attr( $field['type'] ); ?>">
			<?php if ( $is_group_like ) : ?>
				<span class="dp-field__label"><?php echo esc_html( $field['label'] ); ?></span>
			<?php else : ?>
				<label class="dp-field__label" for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
			<?php endif; ?>
			<div class="dp-field__control">
				<?php dailypulse_core_render_control( $name, $field, $value ); ?>
				<?php if ( ! empty( $field['instructions'] ) ) : ?>
					<p class="description"><?php echo esc_html( $field['instructions'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	echo '</div>';
}

/**
 * Saves the native meta boxes.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 */
function dailypulse_core_save_meta_boxes( $post_id, $post ) {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( dailypulse_field_groups() as $group_id => $group ) {
		$nonce = '_dailypulse_nonce_' . $group_id;

		if ( ! in_array( $post->post_type, $group['post_types'], true ) || ! isset( $_POST[ $nonce ] ) ) {
			continue;
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ $nonce ] ) ), 'dailypulse_save_' . $group_id ) ) {
			continue;
		}

		foreach ( dailypulse_core_native_fields( $group ) as $name => $field ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field type just below.
			$raw   = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : null;
			$value = dailypulse_sanitize_value( $field, $raw );

			// Keep the table tidy: an empty or switched-off field has no row at all.
			if ( '' === $value || array() === $value || ( 'true_false' === $field['type'] && '0' === $value ) ) {
				delete_post_meta( $post_id, $name );
			} else {
				update_post_meta( $post_id, $name, wp_slash( $value ) );
			}
		}
	}
}

/**
 * Shows "Breaking" and "Hero" next to story titles in the posts list.
 *
 * @param array   $states Post states.
 * @param WP_Post $post   Post.
 * @return array
 */
function dailypulse_core_post_states( $states, $post ) {
	if ( 'post' !== $post->post_type ) {
		return $states;
	}

	if ( dailypulse_is_breaking( $post->ID ) ) {
		$until = (int) get_post_meta( $post->ID, 'dp_breaking_until', true );
		/* translators: %s: time of day. */
		$states['dailypulse_breaking'] = sprintf( __( 'Breaking until %s', 'dailypulse-core' ), wp_date( get_option( 'time_format' ), $until ) );
	}

	if ( dailypulse_field( 'dp_featured', $post->ID ) ) {
		$states['dailypulse_featured'] = __( 'Hero', 'dailypulse-core' );
	}

	return $states;
}

/*
 * -------------------------------------------------------------------------
 * Category settings
 * -------------------------------------------------------------------------
 */

/**
 * Category settings on the "Add New Category" form.
 */
function dailypulse_core_category_add_fields() {
	wp_nonce_field( 'dailypulse_save_category', '_dailypulse_category_nonce', false );

	foreach ( dailypulse_category_fields() as $key => $field ) {
		?>
		<div class="form-field dp-term-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
			<?php dailypulse_core_render_control( $key, $field, $field['default'] ); ?>
			<?php if ( ! empty( $field['instructions'] ) ) : ?>
				<p><?php echo esc_html( $field['instructions'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}

/**
 * Category settings on the "Edit Category" form.
 *
 * @param WP_Term $term Category being edited.
 */
function dailypulse_core_category_edit_fields( $term ) {
	wp_nonce_field( 'dailypulse_save_category', '_dailypulse_category_nonce', false );

	foreach ( dailypulse_category_fields() as $key => $field ) {
		$value = get_term_meta( $term->term_id, $key, true );

		if ( '' === $value ) {
			$value = $field['default'];
		}
		?>
		<tr class="form-field dp-term-field">
			<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
			<td>
				<?php dailypulse_core_render_control( $key, $field, $value ); ?>
				<?php if ( ! empty( $field['instructions'] ) ) : ?>
					<p class="description"><?php echo esc_html( $field['instructions'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}

/**
 * Saves the category settings.
 *
 * @param int $term_id Category term ID.
 */
function dailypulse_core_save_category( $term_id ) {
	if ( ! isset( $_POST['_dailypulse_category_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_dailypulse_category_nonce'] ) ), 'dailypulse_save_category' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	foreach ( dailypulse_category_fields() as $key => $field ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field type just below.
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : null;

		if ( 'color' === $field['type'] ) {
			// Empty is meaningful here: it means "pick a colour for me".
			$value = (string) sanitize_hex_color( (string) $raw );
		} else {
			$value = dailypulse_sanitize_value( $field, $raw );
		}

		update_term_meta( $term_id, $key, $value );
	}
}

/**
 * Adds a colour column to the categories list.
 *
 * @param array $columns Columns.
 * @return array
 */
function dailypulse_core_category_columns( $columns ) {
	$columns['dp_color'] = __( 'Colour', 'dailypulse-core' );

	return $columns;
}

/**
 * Renders the colour column.
 *
 * @param string $content Column content.
 * @param string $column  Column name.
 * @param int    $term_id Term ID.
 * @return string
 */
function dailypulse_core_category_column( $content, $column, $term_id ) {
	if ( 'dp_color' !== $column ) {
		return $content;
	}

	return sprintf( '<span class="dp-swatch" style="background:%s"></span>', esc_attr( dailypulse_category_color( $term_id ) ) );
}

/*
 * -------------------------------------------------------------------------
 * Breaking Alerts list
 * -------------------------------------------------------------------------
 */

/**
 * Adds a status column to the alerts list.
 *
 * @param array $columns Columns.
 * @return array
 */
function dailypulse_core_alert_columns( $columns ) {
	$date = isset( $columns['date'] ) ? $columns['date'] : null;
	unset( $columns['date'] );

	$columns['dp_status'] = __( 'In the ticker', 'dailypulse-core' );

	if ( null !== $date ) {
		$columns['date'] = $date;
	}

	return $columns;
}

/**
 * Renders the status column: live (and until when) or expired.
 *
 * @param string $column  Column name.
 * @param int    $post_id Alert ID.
 */
function dailypulse_core_alert_column( $column, $post_id ) {
	if ( 'dp_status' !== $column ) {
		return;
	}

	if ( 'publish' !== get_post_status( $post_id ) ) {
		echo '<span class="dp-pill">' . esc_html__( 'Not published', 'dailypulse-core' ) . '</span>';
		return;
	}

	$hours = (int) get_post_meta( $post_id, 'dp_alert_hours', true );
	$until = (int) get_post_time( 'U', true, $post_id ) + ( $hours > 0 ? $hours : 6 ) * HOUR_IN_SECONDS;

	if ( $until > time() ) {
		/* translators: %s: date and time. */
		echo '<span class="dp-pill dp-pill--live">' . esc_html( sprintf( __( 'Live until %s', 'dailypulse-core' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $until ) ) ) . '</span>';
	} else {
		echo '<span class="dp-pill">' . esc_html__( 'Expired', 'dailypulse-core' ) . '</span>';
	}
}
