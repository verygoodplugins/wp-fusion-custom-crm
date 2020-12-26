<?php

class WPF_Custom_Admin {

	/**
	 * The CRM slug
	 *
	 * @var string
	 * @since 1.0.0
	 */

	private $slug;

	/**
	 * The CRM name
	 *
	 * @var string
	 * @since 1.0.0
	 */

	private $name;

	/**
	 * The CRM object
	 *
	 * @var object
	 * @since 1.0.0
	 */

	private $crm;

	/**
	 * Get things started
	 *
	 * @since 1.0.0
	 */

	public function __construct( $slug, $name, $crm ) {

		$this->slug = $slug;
		$this->name = $name;
		$this->crm  = $crm;

		add_filter( 'wpf_configure_settings', array( $this, 'register_connection_settings' ), 15, 2 );
		add_action( 'show_field_custom_header_begin', array( $this, 'show_field_custom_header_begin' ), 10, 2 );

		// AJAX callback to test the connection
		add_action( 'wp_ajax_wpf_test_connection_' . $this->slug, array( $this, 'test_connection' ) );

		if ( wp_fusion()->settings->get( 'crm' ) == $this->slug ) {
			$this->init();
		}

	}

	/**
	 * Hooks to run when this CRM is selected as active
	 *
	 * @since 1.0.0
	 */

	public function init() {

		// Hooks in init() will run on the admin screen when this CRM is active
	}


	/**
	 * Loads CRM connection information on settings page
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings The registered settings on the options page.
	 * @param array $options  The options saved in the database.
	 * @return array $settings The settings.
	 */

	public function register_connection_settings( $settings, $options ) {

		$new_settings = array();

		$new_settings['custom_header'] = array(
			'title'   => __( 'Custom CRM Configuration', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'setup',
		);

		$new_settings['custom_url'] = array(
			'title'   => __( 'URL', 'wp-fusion' ),
			'desc'    => __( 'URL to your custom CRM', 'wp-fusion' ),
			'type'    => 'text',
			'section' => 'setup',
		);

		$new_settings['custom_key'] = array(
			'title'       => __( 'API Key', 'wp-fusion' ),
			'desc'        => __( 'Your API key', 'wp-fusion' ),
			'section'     => 'setup',
			'class'       => 'api_key',
			'type'        => 'api_validate', // api_validate field type causes the Test Connection button to be appended to the input.
			'post_fields' => array( 'custom_url', 'custom_key' ), // This tells us which settings fields need to be POSTed when the Test Connection button is clicked.
		);

		$settings = wp_fusion()->settings->insert_setting_after( 'crm', $settings, $new_settings );

		return $settings;

	}


	/**
	 * Puts a div around the CRM configuration section so it can be toggled
	 *
	 * @since 1.0.0
	 *
	 * @param string $id    The ID of the field.
	 * @param array  $field The field properties.
	 * @return mixed HTML output.
	 */

	public function show_field_custom_header_begin( $id, $field ) {

		echo '</table>';
		$crm = wp_fusion()->settings->get( 'crm' );
		echo '<div id="' . $this->slug . '" class="crm-config ' . ( $crm == false || $crm != $this->slug ? 'hidden' : 'crm-active' ) . '" data-name="' . $this->name . '" data-crm="' . $this->slug . '">';

	}


	/**
	 * Verify connection credentials.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed JSON response.
	 */

	public function test_connection() {

		$api_url = esc_url( $_POST['custom_url'] );
		$api_key = sanitize_text_field( $_POST['custom_key'] );

		$connection = $this->crm->connect( $api_url, $api_key, true );

		if ( is_wp_error( $connection ) ) {

			// Connection failed

			wp_send_json_error( $connection->get_error_message() );

		} else {

			// Save the API credentials

			$options                          = wp_fusion()->settings->get_all();
			$options['custom_url']            = $api_url;
			$options['custom_key']            = $api_key;
			$options['crm']                   = $this->slug;
			$options['connection_configured'] = true;

			wp_fusion()->settings->set_all( $options );

			wp_send_json_success();

		}

		die();

	}


}
