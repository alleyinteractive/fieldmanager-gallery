var fm_gallery_frame = [];
( function( $ ) {
	var $document = $( document );
	$document.on( 'click', '.fm-gallery-remove', function( event ) {
		event.preventDefault();

		var parent = $( this ).parents( '.fm-item.fm-gallery' );
		parent.find( '.fm-gallery-id' ).val( '' );
		parent.find( '.gallery-wrapper' ).html( '' );
		fm_gallery_frame[ parent.find( '.fm-gallery-button' ).attr( 'id' ) ] = false;
	});

	/**
	 * Clicking an image inside of the gallery preview will trigger the gallery button
	 * to click.
	 */
	$document.on( 'click', '.fm-gallery .gallery-wrapper a', function( event ) {
		event.preventDefault();
		$( this ).closest( '.gallery-wrapper' ).siblings( '.fm-gallery-button' ).trigger( 'click' );
	} );

	/**
	 * Clicking the gallery button.
	 *
	 * This will open a gallery frame to edit or create a gallery.
	 */
	$document.on( 'click', '.fm-gallery-button', function( event ) {
		var $el = $( this );
		event.preventDefault();

		// If the gallery frame already exists, reopen it.
		if ( fm_gallery_frame[ $el.attr( 'id' ) ] ) {
			fm_gallery_frame[ $el.attr( 'id' ) ].open();
			return;
		}

		var selectedImages = [];
		var inputVal = $el.parent().find( '.fm-gallery-id' ).val();

		if ( inputVal.length ) {
			selectedImages = inputVal.split( ',' );
		}

		/**
		 * Modification to gallery editing workflow
		 *
		 * We also need to check for the `post_frame` variable. This is to circumvent
		 * the Media Explorer overlay onto the `wp.media.view.MediaFrame.Post` object.
		 */
		var media_frame_object = ( typeof post_frame === 'function' ) ? post_frame : wp.media.view.MediaFrame.Post;
		var galleryFrame = media_frame_object.extend( {
			createStates: function() {
				var options = this.options;

				this.states.add( [
					new wp.media.controller.Library({
						id:         'gallery',
						title:      options.title,
						priority:   40,
						toolbar:    'main-gallery',
						filterable: 'uploaded',
						multiple:   'add',
						editable:   false,

						library:  wp.media.query( _.defaults({
							type: 'image'
						}, options.library ) )
					}),
					new wp.media.controller.GalleryEdit( {
						library: options.selection,
						editing: options.editing,
						menu:    'gallery'
					} ),
					new wp.media.controller.GalleryAdd()
				] );
			}
		} );

		var query_args = {
			'type':     'image',
			'post__in': selectedImages,
			'orderby':  'post__in',
			'perPage':  '-1'
		};

		var attachments = wp.media.query( query_args );
		var selection = new wp.media.model.Selection( attachments.models, {
			props:    attachments.props.toJSON(),
			multiple: true
		});

		selection.more().done( function() {
			// Break ties with the query.
			selection.props.set( { query: false } );
			selection.unmirror();
			selection.props.unset( 'orderby' );
		});

		var media_args = {
			title:     $el.data( 'choose' ),
			selection: selection,
			button: {
				text: $el.data( 'update' )
			}
		};

		if ( $el.data( 'collection' ) ) {
			if ( selectedImages.length ) {
				media_args.state = 'gallery-edit';
				media_args.editing = true;
			} else {
				media_args.state = 'gallery';
			}

			// Open the modified gallery frame.
			fm_gallery_frame[ $el.attr( 'id' ) ] = new galleryFrame( media_args );
		} else {
			// Use the standard wp.media selector.
			fm_gallery_frame[ $el.attr( 'id' ) ] = wp.media( media_args );
		}

		/**
		 * Handle the selection of one or more images
		 */
		var mediaFrameHandleSelect = function( attachments ) {
			// Normal selection doesn't pass us a collection
			if ( ! $el.data( 'collection' ) ) {
				attachments = fm_gallery_frame[ $el.attr( 'id' ) ].state().get( 'selection' );
			}

			var ids = [];
			var galleryItems = [];

			attachments.each( function( attachment ) {
				attributes = attachment.attributes;
				ids.push( attachment.id );

				var props = { size: fm_preview_size[ $el.attr( 'id' ) ] || 'thumbnail' };
				props = wp.media.string.props( props, attributes );
				props.align = 'none';
				props.link = 'custom';
				props.linkUrl = '#';
				props.caption = '';

				var galleryItem = $( '<div />', {
					'class':  'gallery-item',
					'data-id': attachment.id,
				} );

				galleryItems.push( galleryItem );

				if ( 'image' === attributes.type ) {
					props.url = props.src;

					if ( ! $el.data( 'collection' ) ) {
						galleryItem.append( document.createTextNode( fm_gallery.uploaded_file + ':' ) );
						galleryItem.append( $( '<br />' ) );
						galleryItem.append( wp.media.string.image( props ) );
					} else {
						galleryItem.append( wp.media.string.image( props ) );
					}
				} else {
					galleryItem.append( document.createTextNode( fm_gallery.uploaded_file + ':&nbsp;' ) );
					galleryItem.append( $( '<br />' ) );
					galleryItem.append( wp.media.string.link( props ) );
				}

				if ( ! $el.data( 'collection' ) ) {
					galleryItem.append( $( '<br />' ) );
					galleryItem.append( $( '<a/>', {
						'class': 'fm-gallery-remove fm-delete',
						'href':  '#',
						'html':  fm_gallery.remove
					} ) );
					galleryItem.append( $( '<br />' ) );
				}

			});

			// Store value for saving
			$el.parent().find( '.fm-gallery-id' ).val( ids.join( ',' ) );

			// Enable the "Empty Gallery" button.
			$el.parent().find( '.fm-gallery-button-empty' ).prop( 'disabled', false );

			// Append gallery items
			var $wrapper = $el.parent().find( '.gallery-wrapper' );
			$wrapper
				.html( '' )
				.append( galleryItems )
				.trigger( 'fieldmanager_gallery_preview', [ $wrapper, attachments, wp ] );

			/**
			 * Delete the frame if it's a create gallery state since we want to
			 * recreate with the new attachment IDs it if we reopen it.
			 */
			if ( ids.length > 0 && ! fm_gallery_frame[ $el.attr( 'id' ) ].options.editing ) {
				delete fm_gallery_frame[ $el.attr( 'id' ) ];
			}
		};

		// When an image is selected, run a callback.
		fm_gallery_frame[ $el.attr( 'id' ) ].on( 'select', mediaFrameHandleSelect );
		fm_gallery_frame[ $el.attr( 'id' ) ].on( 'update', mediaFrameHandleSelect );
		fm_gallery_frame[ $el.attr( 'id' ) ].open();

		// Remove Gallery Settings
		$( fm_gallery_frame[ $el.attr( 'id' ) ].$el ).find( '.gallery-settings' ).parent().remove();
	} );

	/**
	 * Clicking on the "Empty Gallery" button
	 */
	$document.on( 'click', '.fm-gallery-button-empty', function( event ) {
		var $el = $( this );

		event.preventDefault();
		$el.prop( 'disabled', true );

		$el.parent().find( '.fm-gallery-id' ).val( '' );

		var galleryId = $el.parent().find( '.fm-gallery-button' ).attr( 'id' );
		delete fm_gallery_frame[ galleryId ];

		var $wrapper = $el.parent().find( '.gallery-wrapper' );
		$wrapper.html( '' );
	} );
} )( jQuery );
