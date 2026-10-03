/* DailyPulse Core — admin: colour pickers, the source-links repeater and the gallery picker. */
( function ( $ ) {
	'use strict';

	var l10n = window.dailypulseAdmin || {};

	function initColorPickers( $scope ) {
		if ( $.fn.wpColorPicker ) {
			$scope.find( '.dp-color' ).wpColorPicker();
		}
	}

	function initSources( $scope ) {
		$scope.find( '[data-dp-sources]' ).each( function () {
			var $field = $( this );
			var $rows = $field.find( '.dp-sources__rows' );
			var template = $field.find( 'template' ).html();
			var next = parseInt( $field.attr( 'data-next' ), 10 ) || 0;

			$field.on( 'click', '.dp-sources__add', function () {
				var $row = $( template.replace( /__i__/g, String( next++ ) ) );
				$rows.append( $row );
				$row.find( 'input' ).first().trigger( 'focus' );
			} );

			$field.on( 'click', '.dp-sources__remove', function () {
				var $row = $( this ).closest( '.dp-sources__row' );

				// Keep one row on screen; just empty it.
				if ( $rows.children().length > 1 ) {
					$row.remove();
				} else {
					$row.find( 'input' ).val( '' );
				}
			} );

			if ( $.fn.sortable ) {
				// Rows are saved in DOM order, so dragging is all it takes to reorder.
				$rows.sortable( { handle: '.dp-sources__handle', axis: 'y', tolerance: 'pointer' } );
			}
		} );
	}

	function initGalleries( $scope ) {
		$scope.find( '[data-dp-gallery]' ).each( function () {
			var $field = $( this );
			var $input = $field.find( 'input[type="hidden"]' );
			var $list = $field.find( '.dp-gallery__list' );
			var frame;

			function sync() {
				var ids = $list.children().map( function () {
					return $( this ).attr( 'data-id' );
				} ).get();
				$input.val( ids.join( ',' ) );
			}

			function addImage( attachment ) {
				if ( $list.children( '[data-id="' + attachment.id + '"]' ).length ) {
					return;
				}

				var sizes = attachment.sizes || {};
				var url = ( sizes.thumbnail || sizes.medium || sizes.full || {} ).url || attachment.url;
				var $item = $( '<li class="dp-gallery__item"></li>' ).attr( 'data-id', attachment.id );

				$( '<img alt="">' ).attr( 'src', url ).appendTo( $item );
				$( '<button type="button" class="dp-gallery__remove"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>' )
					.attr( 'aria-label', l10n.remove || 'Remove' )
					.appendTo( $item );
				$list.append( $item );
			}

			$field.on( 'click', '.dp-gallery__add', function () {
				if ( ! window.wp || ! wp.media ) {
					return;
				}

				if ( ! frame ) {
					frame = wp.media( {
						title: l10n.galleryTitle,
						button: { text: l10n.galleryButton },
						library: { type: 'image' },
						multiple: 'add'
					} );

					frame.on( 'select', function () {
						frame.state().get( 'selection' ).each( function ( model ) {
							addImage( model.toJSON() );
						} );
						sync();
					} );
				}

				frame.open();
			} );

			$field.on( 'click', '.dp-gallery__remove', function () {
				$( this ).closest( '.dp-gallery__item' ).remove();
				sync();
			} );

			if ( $.fn.sortable ) {
				$list.sortable( { tolerance: 'pointer', update: sync } );
			}
		} );
	}

	$( function () {
		var $doc = $( document.body );

		initColorPickers( $doc );
		initSources( $doc );
		initGalleries( $doc );

		// The "Add New Category" form is submitted over AJAX and then reset; reset the pickers with it.
		$( document ).on( 'ajaxComplete', function ( event, xhr, settings ) {
			if ( settings && typeof settings.data === 'string' && settings.data.indexOf( 'action=add-tag' ) !== -1 ) {
				$( '#addtag .dp-color' ).each( function () {
					var $picker = $( this );
					$picker.wpColorPicker( 'color', $picker.attr( 'data-default-color' ) || '' );
				} );
			}
		} );
	} );
}( jQuery ) );
