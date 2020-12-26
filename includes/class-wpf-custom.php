<?php

class WPF_Custom {

	/**
	 * Contains API url
	 *
	 * @var string
	 * @since 1.0.0
	 */

	public $url;

	/**
	 * Declares how this CRM handles tags and fields.
	 *
	 * "add_tags" means that tags are applied over the API as strings (no tag IDs).
	 * With add_tags enabled, WP Fusion will allow users to type new tag names into the tag select boxes.
	 *
	 * "add_fields" means that custom field / attrubute keys don't need to exist first in the CRM to be used.
	 * With add_fields enabled, WP Fusion will allow users to type new filed names into the CRM Field select boxes.
	 *
	 * @var array
	 * @since 1.0.0
	 */

	public $supports = array( 'add_tags', 'add_fields' );

	/**
	 * API parameters
	 *
	 * @var array
	 * @since 1.0.0
	 */

	public $params = array();

	/**
	 * Get things started
	 *
	 * @since 1.0.0
	 */

	public function __construct() {

		$this->slug = 'custom';
		$this->name = 'Custom';

		// Set up admin options
		if ( is_admin() ) {
			require_once dirname( __FILE__ ) . '/class-wpf-custom-admin.php';
			new WPF_Custom_Admin( $this->slug, $this->name, $this );
		}

		// Error handling
		add_filter( 'http_response', array( $this, 'handle_http_response' ), 50, 3 );

	}

	/**
	 * Sets up hooks specific to this CRM.
	 *
	 * This function only runs if this CRM is the active CRM.
	 *
	 * @since 1.0.0
	 */

	public function init() {

		// add_filter( 'wpf_format_field_value', array( $this, 'format_field_value' ), 10, 3 );
	}


	/**
	 * Gets params for API calls.
	 *
	 * @since 1.0.0
	 *
	 * @return array $params The API parameters.
	 */

	public function get_params( $api_url = null, $api_key = null ) {

		// If it's already been set up

		if ( $this->params ) {
			return $this->params;
		}

		// Get saved data from DB
		if ( empty( $api_url ) || empty( $api_key ) ) {
			$api_url = wp_fusion()->settings->get( 'custom_url' );
			$api_key = wp_fusion()->settings->get( 'custom_key' );
		}

		$this->url = $api_url;

		$this->params = array(
			'user-agent' => 'WP Fusion; ' . home_url(),
			'timeout'    => 15,
			'headers'    => array(
				'Api-Key' => $api_key,
			),
		);

		return $this->params;
	}


	/**
	 * Check HTTP Response for errors and return WP_Error if found
	 *
	 * @since 1.0.2
	 *
	 * @param object $response The HTTP response.
	 * @param array  $args     The HTTP request arguments.
	 * @param string $url      The HTTP request URL.
	 * @return object $response The response.
	 */

	public function handle_http_response( $response, $args, $url ) {

		if ( strpos( $url, $this->url ) !== false && 'WP Fusion; ' . home_url() == $args['user-agent'] ) {

			$body_json = json_decode( wp_remote_retrieve_body( $response ) );

			$response_code = wp_remote_retrieve_response_code( $response );

			if ( isset( $body_json->success ) && false == $body_json->success ) {

				$response = new WP_Error( 'error', $body_json->message );

			} elseif ( 500 == $response_code ) {

				$response = new WP_Error( 'error', __( 'An error has occurred in API server. [error 500]', 'wp-fusion' ) );

			}
		}

		return $response;

	}


	/**
	 * Initialize connection.
	 *
	 * This is run during the setup process to validate that the user has entered the correct API credentials.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_url The first API credential.
	 * @param string $api_key The second API credential.
	 * @param bool   $test    Whether to validate the credentials.
	 * @return bool|WP_Error A WP_Error will be returned if the API credentials are invalid.
	 */

	public function connect( $api_url = null, $api_key = null, $test = false ) {

		if ( false == $test ) {
			return true;
		}

		if ( ! $this->params ) {
			$this->get_params( $api_url, $api_key );
		}

		$request  = $this->url . '/endpoint/';
		$response = wp_remote_get( $request, $this->params );

		// Validate the connection
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}


	/**
	 * Performs initial sync once connection is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */

	public function sync() {

		if ( is_wp_error( $this->connect() ) ) {
			return false;
		}

		$this->sync_tags();
		$this->sync_crm_fields();

		do_action( 'wpf_sync' );

		return true;

	}


	/**
	 * Gets all available tags and saves them to options.
	 *
	 * @since 1.0.0
	 *
	 * @return array|WP_Error Either the available tags in the CRM, or a WP_Error.
	 */

	public function sync_tags() {

		$request  = $this->url . '/endpoint/';
		$response = wp_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$available_tags = array();

		// Load available tags into $available_tags like 'tag_id' => 'Tag Label'
		wp_fusion()->settings->set( 'available_tags', $available_tags );

		return $available_tags;
	}


	/**
	 * Loads all custom fields from CRM and merges with local list.
	 *
	 * @since 1.0.0
	 *
	 * @return array|WP_Error Either the available fields in the CRM, or a WP_Error.
	 */

	public function sync_crm_fields() {

		$request  = $this->url . '/endpoint/';
		$response = wp_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$crm_fields = array();

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// Load available fields into $crm_fields like 'field_key' => 'Field Label'

		asort( $crm_fields );

		wp_fusion()->settings->set( 'crm_fields', $crm_fields );

		return $crm_fields;
	}


	/**
	 * Gets contact ID for a user based on email address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_address The email address to look up.
	 * @return int|WP_Error The contact ID in the CRM.
	 */

	public function get_contact_id( $email_address ) {

		$request  = $this->url . '/endpoint/?email=' . urlencode( $email_address );
		$response = wp_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// Parse response for contact ID here.

		return $response->id;
	}


	/**
	 * Gets all tags currently applied to the contact in the CRM.
	 *
	 * @since 1.0.0
	 *
	 * @param int $contact_id The contact ID to load the tags for.
	 * @return array|WP_Error The tags currently applied to the contact in the CRM.
	 */

	public function get_tags( $contact_id ) {

		if ( ! $this->params ) {
			$this->get_params();
		}

		$request  = $this->url . '/endpoint/';
		$response = wp_remote_get( $request, $this->params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// Parse response to create an array of tag ids. $tags = array(123, 678, 543); (should not be an associative array)

		return $tags;
	}

	/**
	 * Applies tags to a contact.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tags       A numeric array of tags to apply to the contact.
	 * @param int   $contact_id The contact ID to apply the tags to.
	 * @return bool|WP_Error Either true, or a WP_Error if the API call failed.
	 */

	public function apply_tags( $tags, $contact_id ) {

		$request        = $this->url . '/endpoint/';
		$params         = $this->get_params();
		$params['body'] = $tags;

		$response = wp_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Removes tags from a contact.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tags       A numeric array of tags to remove from the contact.
	 * @param int   $contact_id The contact ID to remove the tags from.
	 * @return bool|WP_Error Either true, or a WP_Error if the API call failed.
	 */

	public function remove_tags( $tags, $contact_id ) {

		$request        = $this->url . '/endpoint/';
		$params         = $this->get_params();
		$params['body'] = $tags;

		$response = wp_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;

	}


	/**
	 * Adds a new contact.
	 *
	 * @since 1.0.0
	 *
	 * @param array $contact_data    An associative array of contact fields and field values.
	 * @param bool  $map_meta_fields Whether to map WordPress meta keys to CRM field keys.
	 * @return int|WP_Error Contact ID on success, or WP Error.
	 */

	public function add_contact( $contact_data, $map_meta_fields = true ) {

		if ( true == $map_meta_fields ) {
			$contact_data = wp_fusion()->crm_base->map_meta_fields( $contact_data );
		}

		$request        = $this->url . '/endpoint/';
		$params         = $this->get_params();
		$params['body'] = $contact_data;

		$response = wp_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		// Get new contact ID out of response
		return $contact_id;

	}

	/**
	 * Updates an existing contact record.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $contact_id      The ID of the contact to update.
	 * @param array $contact_data    An associative array of contact fields and field values.
	 * @param bool  $map_meta_fields Whether to map WordPress meta keys to CRM field keys.
	 * @return bool|WP_Error Error if the API call failed.
	 */

	public function update_contact( $contact_id, $contact_data, $map_meta_fields = true ) {

		if ( true == $map_meta_fields ) {
			$contact_data = wp_fusion()->crm_base->map_meta_fields( $contact_data );
		}

		$request        = $this->url . '/endpoint/';
		$params         = $this->get_params();
		$params['body'] = $contact_data;

		$response = wp_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Loads a contact record from the CRM and maps CRM fields to WordPress fields
	 *
	 * @since 1.0.0
	 *
	 * @param int $contact_id The ID of the contact to load.
	 * @return array|WP_Error User meta data that was returned.
	 */

	public function load_contact( $contact_id ) {

		$request  = $this->url . '/endpoint/' . $contact_id;
		$response = wp_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$user_meta      = array();
		$contact_fields = wp_fusion()->settings->get( 'contact_fields' );
		$response       = json_decode( wp_remote_retrieve_body( $response ), true );

		foreach ( $contact_fields as $field_id => $field_data ) {

			if ( $field_data['active'] && isset( $response['data'][ $field_data['crm_field'] ] ) ) {
				$user_meta[ $field_id ] = $response['data'][ $field_data['crm_field'] ];
			}
		}

		return $user_meta;
	}


	/**
	 * Gets a list of contact IDs based on tag
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag The tag ID or name to search for.
	 * @return array Contact IDs returned.
	 */

	public function load_contacts( $tag ) {

		$request  = $this->url . '/endpoint/tag/' . $tag;
		$response = wp_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$contact_ids = array();
		$response    = json_decode( wp_remote_retrieve_body( $response ) );

		// Iterate over the contacts returned in the response and build an array such that $contact_ids = array(1,3,5,67,890);

		foreach ( $response as $contact ) {
			$contact_ids[] = $contact->id;
		}

		return $contact_ids;

	}

}
