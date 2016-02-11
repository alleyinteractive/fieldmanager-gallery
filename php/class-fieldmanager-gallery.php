<?php
/**
 * Fieldmanager Gallery field
 *
 * @package Fieldmanager
 */
class Fieldmanager_Gallery extends Fieldmanager_Field {
	/**
	 * Override field_class
	 *
	 * @var string
	 */
	public $field_class = 'gallery';

	/**
	 * Button Label
	 *
	 * @var string
	 */
	public $button_label;

	/**
	 * Button label in the gallery modal popup
	 *
	 * @var string
	 */
	public $modal_button_label;

	/**
	 * Title of the gallery modal popup
	 *
	 * @var string
	 */
	public $modal_title;

	/**
	 * Class to attach to thumbnail gallery display
	 *
	 * @var string
	 */
	public $thumbnail_class = 'thumbnail';

	/**
	 * Whether or not to allow a collection of images
	 *
	 * @var bool
	 */
	public $collection = false;

	/**
	 * Which size a preview image should be.
	 * Should be a string (e.g. "thumbnail", "large", or some size created with add_image_size)
	 * You can use an array here.
	 *
	 * @var string|array
	 */
	public $preview_size = 'thumbnail';

	/**
	 * Static variable so we only load gallery JS once
	 *
	 * @var string
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
				wp_localize_script( 'fm_gallery', 'fm_gallery', array(
					'uploaded_file'  => __( 'Uploaded file', 'lin' ),
					'remove'         => __( 'remove', 'lin' ),
					'create_gallery' => __( 'Create Gallery', 'lin' ),
				) );

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
	 * @return string HTML
	 */
	public function form_element( $value = array() ) {

		$preview = '';
		$values  = is_array( $value ) ? $value : array( $value );

		foreach ( $values as $value ) {
			if ( ! empty( $value ) && is_numeric( $value ) && $value > 0 ) {
				$attachment = get_post( $value );

				if ( empty( $attachment ) || 'attachment' !== $attachment->post_type ) {
					continue;
				}

				$out = '<div class="gallery-item" data-id="' . intval( $attachment->ID ) . '">';

				if ( 0 === strpos( $attachment->post_mime_type, 'image/' ) ) {
					if ( ! $this->collection ) {
						$out .= sprintf( '%s<br />', esc_html__( 'Uploaded image:', 'fieldmanager' ) );
					}

					$out .= '<a href="#">' . wp_get_attachment_image( $value, $this->preview_size, false, array( 'class' => $this->thumbnail_class ) ) . '</a>';
				} else {
					$out .= sprintf( '%s', esc_html__( 'Uploaded file:', 'fieldmanager' ) ) . '&nbsp;';
					$out .= wp_get_attachment_link( $value, $this->preview_size, true, true, $attachment->post_title );
				}

				if ( ! $this->collection ) {
					$out .= sprintf( '<br /><a href="#" class="fm-gallery-remove fm-delete">%s</a>', __( 'remove', 'fieldmanager' ) );
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
			wp_json_encode( $this->preview_size ),
			esc_attr( $this->modal_title ),
			esc_attr( $this->modal_button_label ),
			intval( $this->collection )
		);
	}
}
