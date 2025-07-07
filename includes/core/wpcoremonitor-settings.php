<?php

/**
 * Class WPCoreMonitor_Settings
 */
class WPCoreMonitor_Settings {

	/**
	 * The settings fields
	 *
	 * @var array[]
	 */
	protected $fields;

	/**
	 * The tab ID
	 *
	 * @var string
	 */
	private $tab_id = 'settings';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'wpcoremonitor_admin_tabs', array( $this, 'register_tab' ), 100 );
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		// Define fields and their default values
		$this->fields = array(
			'debug_redirect_mode' => array(
				'default'     => 1,
				'section'     => 'access_control',
				'title'       => __( 'Debug wp_redirect()', 'wpcoremonitor' ),
				'description' => __( 'Uncheck this box, if you do not want to show the redirect backtrace information every time a WordPress redirect is triggered.', 'wpcoremonitor' ),
				'type'        => 'checkbox'
			),
			'user_role_access' => array(
				'default'     => 'edit_theme_options',
				'section'     => 'access_control',
				'title'       => __( 'Choose a User Role', 'wpcoremonitor' ),
				'description' => __( 'Select the user capability needed to access the debug data. Select the last option if you want to show the debug data to all visitors, even those who are not logged in.', 'wpcoremonitor' ),
				'type'        => 'dropdown',
				'choices'     => array(
					'edit_theme_options' => __( 'Administrator (edit_theme_options)', 'wpcoremonitor' ),
					'publish_pages'      => __( 'Editor (publish_pages)', 'wpcoremonitor' ),
					'publish_posts'      => __( 'Author (publish_posts)', 'wpcoremonitor' ),
					'all'                => __( 'All visitors and users', 'wpcoremonitor' ),
				),
			)
		);

		register_setting( 'wpcoremonitor_settings_group', 'wpcoremonitor_settings', array( $this, 'sanitize_settings' ) );

		$this->add_settings_section( __( 'Access Control', 'wpcoremonitor' ) );

		foreach ( $this->fields as $field_id => $field_info ) {
			$this->add_settings_field( $field_id, $field_info['title'], $field_info['section'], array( 'id' => $field_id ) );
		}
	}

	/**
	 * Add settings section.
	 *
	 * @param string $title
	 */
	private function add_settings_section( $title ) {
		add_settings_section( 'access_control', $title, '', 'wpcoremonitor_settings' );
	}

	/**
	 * Add settings field.
	 *
	 * @param string $field_id
	 * @param string $label
	 * @param string $section_id
	 * @param array $args
	 */
	private function add_settings_field( $field_id, $label, $section_id, $args = array() ) {
		add_settings_field( $field_id, $label, array( $this, 'add_settings_field_callback' ), 'wpcoremonitor_settings', $section_id, $args );
	}

	/**
	 * Generic callback function for generating form fields.
	 *
	 * @param array $args
	 */
	public function add_settings_field_callback( $args ) {
		$field_id   = $args['id'];
		$field_info = $this->fields[ $field_id ];
		$settings   = $this->get_option( $field_id );

		// Customize this part based on the field type
		switch ( $field_info['type'] ) {
			case 'dropdown':
				$this->dropdown_field_callback( $field_id, $settings, $field_info );
				break;
			case 'text':
				$this->text_field_callback( $field_id, $settings, $field_info );
				break;
			case 'checkbox':
				$this->checkbox_field_callback( $field_id, $settings, $field_info );
				break;
		}
	}

	/**
	 * Dropdown field callback function.
	 *
	 * @param string $field_id
	 * @param string $value
	 * @param $field_info
	 */
	private function dropdown_field_callback( $field_id, $value, $field_info ) {
		$html = sprintf( '<select id="%s" name="wpcoremonitor_settings[%s]">', esc_attr( $field_id ), esc_attr( $field_id ) );
		if ( ! empty( $field_info['choices'] ) ) {
			foreach ( $field_info['choices'] as $choice_value => $label ) {
				$html .= sprintf( '<option %s value="%s">%s</option>', selected( $choice_value, $value, false ), esc_attr( $choice_value ), esc_html( $label ) );
			}
		}
		$html .= '</select>';
		$html .= ( ! empty( $field_info['description'] ) ) ? sprintf( '<p class="description">%s</p>', esc_html( $field_info['description'] ) ) : '';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}

	/**
	 * Checkbox field callback function.
	 *
	 * @param string $field_id
	 * @param string $value
	 * @param $field_info
	 *
	 * @return void
	 */
	private function checkbox_field_callback( $field_id, $value, $field_info ) {
		$checked = checked( 1, $value, false );

		$html = sprintf( '<input type="hidden" id="%s" name="wpcoremonitor_settings[%s]" value="0"  />', esc_attr( $field_id ), esc_attr( $field_id ) );
		$html .= sprintf( '<input type="checkbox" id="%s" name="wpcoremonitor_settings[%s]" value="1" %s />', esc_attr( $field_id ), esc_attr( $field_id ), $checked );
		$html .= ( ! empty( $field_info['description'] ) ) ? sprintf( '<p class="description">%s</p>', esc_html( $field_info['description'] ) ) : '';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}

	/**
	 * Text field callback function.
	 *
	 * @param string $field_id
	 * @param string $value
	 */
	private function text_field_callback( $field_id, $value, $field_info ) {
		$html = sprintf( '<input type="text" id="%s" name="wpcoremonitor_settings[%s]" value="%s" />', esc_attr( $field_id ), esc_attr( $field_id ), esc_attr( $value ) );
		$html .= ( ! empty( $field_info['description'] ) ) ? sprintf( '<p class="description">%s</p>', esc_html( $field_info['description'] ) ) : '';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}

	/**
	 * Sanitize settings field.
	 *
	 * @param array $input
	 *
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized_input = array();

		foreach ( $input as $key => $value ) {
			$sanitized_input[ $key ] = sanitize_text_field( $value );
		}

		return $sanitized_input;
	}

	/**
	 * Register tab for admin dashboard.
	 *
	 * @param $tabs
	 *
	 * @return void
	 */
	public function register_tab( $tabs ) {
		$tabs[ $this->tab_id ] = array(
			'title'            => __( 'Settings', 'wpcoremonitor' ),
			'content_callback' => array( $this, 'tab_content' )
		);

		return $tabs;
	}

	/**
	 * Display the settings page content.
	 */
	public function tab_content() {
		echo sprintf( "<form method=\"post\" action=\"%s\">", esc_url( add_query_arg( 'tab', $this->tab_id, admin_url( 'options.php' ) ) ) );
		settings_fields( 'wpcoremonitor_settings_group' );
		do_settings_sections( 'wpcoremonitor_settings' );
		submit_button();
		echo '</form>';
	}

	/**
	 * Get the option value.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function get_option( $key ) {
		$settings = get_option( 'wpcoremonitor_settings' );

		// Return the value from the settings array if it exists, otherwise return the default value
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $this->fields[ $key ]['default'];
	}

}