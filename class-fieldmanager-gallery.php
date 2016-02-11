<?php

/*
	Plugin Name: Fieldmanager Gallery
	Plugin URI: https://github.com/alleyinteractive/fieldmanager-gallery
	Description: A temporary Fieldmanager Field extension for image galleries. This will eventually be merged into Fm Core. Forked from https://github.com/fusioneng/fieldmanager-gallery.
	Version: 0.1
	Author: Alley Interactive, Fusion
	Author URI: http://www.alleyinteractive.com/
*/

/*  This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * Version number.
 *
 * @var string
 */
define( 'FM_GALLERY_VERSION', '0.0.1' );

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	wp_die( esc_html__( 'Denied!', 'fieldmanager' ) );
}

function fm_gallery_setup_files() {
	/**
	 * Make sure that FM is active.
	 */
	if ( ! defined( 'FM_VERSION' ) ) {
		return;
	}

	/**
	 * Gallery field
	 *
	 * @package Fieldmanager
	 */
	class Fieldmanager_Gallery extends Fieldmanager_Field {

		/**
		 * @var string
		 * Override field_class
		 */
		public $field_class = 'gallery';

		/**
		 * @var string
		 * Button Label
		 */
		public $button_label;

		/**
		 * @var string
		 * Button label in the gallery modal popup
		 */
		public $modal_button_label;

		/**
		 * @var string
		 * Title of the gallery modal popup
		 */
		public $modal_title;

		/**
		 * @var string
		 * Class to attach to thumbnail gallery display
		 */
		public $thumbnail_class = 'thumbnail';

		/**
		 * @var bool
		 * Whether or not to allow a collection of images
		 */
		public $collection = false;

		/**
		 * @var string
		 * Which size a preview image should be.
		 * Should be a string (e.g. "thumbnail", "large", or some size created with add_image_size)
		 * You can use an array here
		 */
		public $preview_size = 'thumbnail';

		/**
		 * @var string
		 * Static variable so we only load gallery JS once
		 */
		public static $has_registered_gallery = false;

		/**
		 * Plugin directory URL
		 *
		 * @var null
		 */
		private $plugin_url = null;

		/**
		 * Plugin directory Path
		 *
		 * @var null
		 */
		private $plugin_dir = null;

		/**
		 * Construct default attributes
		 *
		 * @param string $label
		 * @param array  $options
		 */
		public function __construct( $label, $options = array() ) {

			$this->plugin_dir = plugin_dir_path( __FILE__ );
			$this->plugin_url = plugin_dir_url( __FILE__ );

			if ( ! empty( $options['collection'] ) ) {
				$this->button_label       = __( 'Attach Gallery', 'fieldmanager' );
				$this->modal_button_label = __( 'Select Attachments', 'fieldmanager' );
				$this->modal_title        = __( 'Choose Attachments', 'fieldmanager' );
			} else {
				$this->button_label       = __( 'Attach a File', 'fieldmanager' );
				$this->modal_button_label = __( 'Select Attachment', 'fieldmanager' );
				$this->modal_title        = __( 'Choose an Attachment', 'fieldmanager' );
			}

			add_action( 'admin_print_scripts', function () {
				$post = get_post();
				$args = array();
				if ( isset( $post ) && $post->ID ) {
					$args['post'] = $post->ID;
				}
				wp_enqueue_media( $args ); // generally on post pages this will not have an impact.
			} );
			if ( ! self::$has_registered_gallery ) {
				add_action( 'admin_enqueue_scripts', function () {
					wp_enqueue_style( 'fm_gallery', $this->plugin_url . 'css/fieldmanager-gallery.css', array() );
					wp_enqueue_script( 'fm_gallery', $this->plugin_url . 'js/fieldmanager-gallery.js', array( 'jquery' ) );
					self::$has_registered_gallery = true;
				} );
			}
			parent::__construct( $label, $options );
		}

		/**
		 * Presave; ensure that the value is an absolute integer
		 */
		public function presave( $value, $current_value = array() ) {

			if ( false !== stripos( $value, ',' ) ) {
				$values       = explode( ',', $value );
				$clean_values = array();
				foreach ( $values as $dirty_value ) {
					if ( is_numeric( $dirty_value ) && $dirty_value > 0 ) {
						$clean_values[] = absint( $dirty_value );
					}
				}

				return $clean_values;
			} else {

				if ( $value == 0 || ! is_numeric( $value ) ) {
					return null;
				}

				return absint( $value );
			}

			return absint( $value );
		}

		/**
		 * Form element
		 *
		 * @param mixed $value
		 *
		 * @return string HTML
		 */
		public function form_element( $value = array() ) {

			$preview = '';
			$values  = is_array( $value ) ? $value : array( $value );

			foreach ( $values as $value ) {

				if ( is_numeric( $value ) && $value > 0 ) {

					$attachment = get_post( $value );
					$out        = '<div class="gallery-item" data-id="' . esc_attr( $value ) . '">';

					if ( strpos( $attachment->post_mime_type, 'image/' ) === 0 ) {

						if ( ! $this->collection ) {
							$out .= sprintf( '%s<br />', esc_html__( 'Uploaded image:', 'fieldmanager' ) );
						}

						$out .= '<a href="#">' . wp_get_attachment_image( $value, $this->preview_size, false, array( 'class' => $this->thumbnail_class ) ) . '</a>';
					} else {
						$out .= sprintf( '%s', esc_html__( 'Uploaded file:', 'fieldmanager' ) ) . '&nbsp;';
						$out .= wp_get_attachment_link( $value, $this->preview_size, true, true, $attachment->post_title );
					}

					if ( ! $this->collection ) {
						$out .= sprintf( '<br /><a href="#" class="fm-gallery-remove fm-delete">%s</a>', __( 'remove' ) );
					}

					$out .= '</div>';

					$preview .= apply_filters( 'fieldmanager_gallery_preview', $out, $values, $attachment );
				}
			}

			$input_value = implode( ',', $values );

			return sprintf(
				'<input type="button" class="fm-gallery-button button-secondary fm-incrementable" id="%1$s" value="%3$s" data-choose="%7$s" data-update="%8$s" data-collection="%9$s" />
		<input type="hidden" name="%2$s" value="%4$s" class="fm-element fm-gallery-id" />
		<div class="gallery-wrapper">%5$s</div>
		<script type="text/javascript">
		var fm_preview_size = fm_preview_size || [];
		fm_preview_size["%1$s"]=%6$s;
		</script>',
				esc_attr( $this->get_element_id() ),
				esc_attr( $this->get_form_name() ),
				esc_attr( $this->button_label ),
				esc_attr( $input_value ),
				wp_kses_post( $preview ),
				json_encode( $this->preview_size ),
				esc_attr( $this->modal_title ),
				esc_attr( $this->modal_button_label ),
				intval( $this->collection )
			);
		}
	}
}

add_action( 'after_setup_theme', 'fm_gallery_setup_files' );
