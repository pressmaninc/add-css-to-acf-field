<?php
/*
Plugin Name: Add CSS to ACF Field
Plugin URI:
Description: Allow ACF to add a unique css for each field (Can be a field group for block. There are hooks. Can determine if it's a block.)
Version: 1.0.0
Author: PRESSMAN
Author URI: https://www.pressman.ne.jp/
Text Domain: add-css-to-acf-field
Domain Path: /languages
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// don't load directly
if ( ! defined('ABSPATH') ) {
	die();
}

/**
 * Class Add_Css_To_Acf_Field.
 */
class Add_Css_To_Acf_Field {

	protected static $_instance = null;
	public $field_key_counter = array();

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		// Set plugin name & text domain.
		$this->plugin_name = strtolower( __CLASS__ );
		$plugin_data = get_file_data( __FILE__, array('TextDomain' => 'Text Domain'), false );
		$this->plugin_textdomain = $plugin_data['TextDomain'];

		// load textdomain
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// check if ACF is active.
		add_action( 'plugins_loaded', array( $this, 'check_acf' ) );

		// Add original items to field settings.
		add_action( 'acf/render_field_settings', array( $this, 'add_css_input' ), 999 );

		// Output css for each Field.
		add_action( 'acf/render_field', array( $this, 'output_css' ), 100 );

		add_action( 'admin_enqueue_scripts', array( $this, 'add_codemirror_editor' ) );

		add_action( 'pre_post_update', array( $this, 'update_post_custom_values' ), 1, 2 );
	}


	/**
	 * load_textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( $this->plugin_textdomain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Check if ACF is active.
	 * @return void
	 */
	public function check_acf() {
		if ( ! class_exists('ACF') ) {
			// Show admin notice
			add_action( 'admin_notices', array( $this, 'acf_invalid_admin_notices' ) );
		}
	}

	/**
	 * Display admin notice when Advanced Custom Fields is not enabled
	 * @return void
	 */
	public function acf_invalid_admin_notices() {
		echo '<div class="error"><p>' . esc_html__( 'To use the <code>Add CSS to ACF Field</code>, please activate the <code>Advanced Custom Fields</code>.', $this->plugin_textdomain ) . '</p></div>';
	}

	/**
	 * Add original items to field settings
	 * @param array $field
	 */
	public function add_css_input( $field ) {
		$value = ( isset( $field[ $this->plugin_name ] ) ) ? $field[ $this->plugin_name ] : '';
		acf_render_field_wrap( array(
				'label'        => esc_html__( 'Custom CSS', $this->plugin_textdomain ),
				'instructions' => sprintf(
					'<p>%1$s<br>%2$s<br><code><strong style="color:#170">_this_field_key</strong> { background: #ff00ff; }</code></p><p>%3$s</p><p>%4$s</p>',
					sprintf(
						/* translators: 1: Merge tag '_this_field_key'. 2: Merge tag '_this_field_name'. */
						esc_html__( 'You can use %1$s or %2$s as this field\'s selector.', $this->plugin_textdomain ),
						'<strong style="color:#170">_this_field_key</strong>',
						'<strong style="color:#170">_this_field_name</strong>'
					),
					esc_html__( 'Example:', $this->plugin_textdomain ),
					sprintf(
						/* translators: 1: Merge tag '_block_field_key'. 2: Merge tag '_block_field_name'. */
						esc_html__( 'If you want a selector which works only for ACF block, use %1$s or %2$s instead.', $this->plugin_textdomain ),
						'<strong style="color:#170">_block_field_key</strong>',
						'<strong style="color:#170">_block_field_name</strong>'
					),
					esc_html__( '* Properly strip all HTML tags including script and style.', $this->plugin_textdomain )
				),
				'required'     => 0,
				'type'         => 'textarea',
				'name'         => $this->plugin_name,
				'prefix'       => $field['prefix'],
				'value'        => $value,
				'class'        => 'field-' . $this->plugin_name,
			), 'tr'	);
	}

	/**
	 * Check if is block.
	 * @param  string $text
	 * @return boolean
	 */
	public function is_block( $text = '' ) {
		return ( preg_match( '/^acf-block_/', $text ) ) ? true : false;
	}

	/**
	 * Output css for each Field.
	 * @param array $field
	 */
	public function output_css( $field ) {
		// Get the current screen object
		$screen = get_current_screen();

		// Exclude the ACF configuration screen as it results in an error.
		if ( 'acf-field-group' === $screen->post_type ) {
			return;
		}

		// Ajax block call.
		if ( wp_doing_ajax() ) {
			if ( isset( $_POST['action'] ) && 'acf/ajax/fetch-block' !== $_POST['action'] ) {
				return;
			}
		}

		// Block check(Check if the _name contains the name you set for the block.)
		$is_block = $this->is_block( $field['name'] );

		// Only the first action per key.
		// type=post_object goes through acf/render_field twice, which is a known bug on the ACF operation side. (This is a known bug on the ACF operation side)
		$key = ( $is_block ) ? $field['key'] . '_block' : $field['key'];
		if ( isset( $this->field_key_counter[ $key ] ) ) {
			return;
		}

		// No CSS
		if ( ! isset( $field[ $this->plugin_name ] ) || '' === $field[ $this->plugin_name ] ) {
			return;
		}

		// Replace the contraction of selector.
		$css = $field[ $this->plugin_name ];
		$search  = array(
			'_this_field_key',
			'_this_field_name',
			'_block_field_key',
			'_block_field_name',
		);
		$replace = array(
			'.acf-field[data-key="' . $field['key'] . '"]',
			'.acf-field[data-name="' . $field['_name'] . '"]',
			'.acf-block-panel .acf-field[data-key="' . $field['key'] . '"]',
			'.acf-block-panel .acf-field[data-name="' . $field['_name'] . '"]',
		);
		$css = str_replace( $search, $replace, $css );

		// Add filter hook.
		$handles = array(
			$this->plugin_name . '?name=' . $field['_name'],
			$this->plugin_name . '?key=' . $field['key'],
			$this->plugin_name,
		);
		foreach ( $handles as $handle ) {
			$css = apply_filters( $handle, $css, $field['key'], $field['_name'], $is_block );
		}

		// Sanitize css.
		$css = wp_strip_all_tags( $css );

		// Outputs
		$this->echo_css( $css, $key );

		// Processed flag
		$this->field_key_counter[ $key ] = true;
	}

	/**
	 * echo css
	 * @param  string $css
	 * @param  string $key
	 * @return void
	 */
	public function echo_css( $css, $key ) {
		if ( ! empty( $css ) ) {
			echo "\n<style id=\"" . $this->plugin_name . "_for_" . $key . "\" type=\"text/css\">\n" .
				 $css .
				 "\n</style>\n";
		}
	}

	public function add_codemirror_editor( $hook_suffix ) {
		$screen = get_current_screen();
		if ( ! ( 'acf-field-group' === $screen->post_type && 'post' === $screen->base ) ) {
			return;
		}

		$settings['codeEditor'] = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		$settings['codemirror'] = array(
			'indentUnit'        => 4,
			'indentWithTabs'    => 1,
			'lineNumbers'       => 1,
			'lineWrapping'      => 1,
			'styleActiveLine'   => 1,
			'continueComments'  => 1,
			'autoCloseBrackets' => 1,
			'matchBrackets'     => 1,
		);

		if ( false === $settings ) {
			return;
		}

		wp_add_inline_script( 'code-editor', sprintf( 'jQuery.extend( wp.codeEditor.defaultSettings, %s );', wp_json_encode( $settings ) ) );
		wp_localize_script( 'jquery', 'codeEditorSettings', $settings );
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );
		wp_enqueue_script( 'csslint' );

		wp_add_inline_script(
			'wp-theme-plugin-editor',
			'jQuery(document).ready(function($) {
				const target = document.getElementById( \'acf-field-group-fields\' );
				const observer = new MutationObserver( ( mutations ) => {
					mutations.forEach( ( mutation ) => {
						$(\'.acf-field-object.open\').each( function() {
							var textarea = $(this).find( \'.field-add_css_to_acf_field\' );
							if ( textarea.length > 0 ) {
								if ( ! textarea.hasClass(\'is_already\') ) {
									wp.codeEditor.initialize( textarea, codeEditorSettings );
									textarea.addClass(\'is_already\');
								}
							}
						} );
					} );
				} );
				observer.observe( target, { childList: true, subtree: true } );
			})'
		);
		wp_add_inline_style(
			'wp-codemirror',
			'.CodeMirror {border: 1px solid #ddd;} '
		);
	}

	public function update_post_custom_values( $post_id, $data ) {
		if ( 'acf-field-group' !== $data->post_type ) {
			return;
		}

		$fields = acf_get_fields( $post_id );
		if ( ! isset( $fields ) ) {
			return;
		}

		for ( $i = 0; $i < count( $fields ) ; $i++ ) {
			if ( array_key_exists( $this->plugin_name, $fields[ $i ] ) ) {
				$fields[ $i ][ $this->plugin_name ] = sanitize_textarea_field( $fields[ $i ][ $this->plugin_name] );
				acf_update_field( $fields[ $i ] );
			}
		}
	} 

}

Add_Css_To_Acf_Field::instance();
