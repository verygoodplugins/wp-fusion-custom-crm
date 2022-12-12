<?php

class WPF_Custom {

	/**
	 * Contains API url
	 *
	 * @var string
	 * @since x.x.x
	 */

	public $url = 'https://myapi.com';

	/**
	 * Declares how this CRM handles tags and fields.
	 *
	 * "add_tags" means that tags are applied over the API as strings (no tag IDs).
	 * With add_tags enabled, WP Fusion will allow users to type new tag names into the tag select boxes.
	 *
	 * "add_fields" means that custom field / attrubute keys don't need to exist first in the CRM to be used.
	 * With add_fields enabled, WP Fusion will allow users to type new filed names into the CRM Field select boxes.
	 *
	 * "events" enables the integration for Event Tracking: https://wpfusion.com/documentation/event-tracking/event-tracking-overview/.
	 *
	 * @var array
	 * @since x.x.x
	 */

	public $supports = array( 'add_tags', 'add_fields', 'events' );

	/**
	 * API parameters
	 *
	 * @var array
	 * @since x.x.x
	 */
	public $params = array();

	/**
	 * Lets us link directly to editing a contact record in the CRM.
	 *
	 * @var string
	 * @since x.x.x
	 */
	public $edit_url = '';


	/**
	 * Client ID for OAuth (if applicable).
	 *
	 * @var string
	 * @since x.x.x
	 */
	public $client_id = '959bd865-5a24-4a43-a8bf-05a69c537938';

	/**
	 * Client secret for OAuth (if applicable).
	 *
	 * @var string
	 * @since x.x.x
	 */
	public $client_secret = '56cc5735-c274-4e43-99d4-3660d816a624';

	/**
	 * Authorization URL for OAuth (if applicable).
	 *
	 * @var string
	 * @since x.x.x
	 */
	public $auth_url = 'https://mycrm.com/oauth/authorize';

	/**
	 * Get things started
	 *
	 * @since x.x.x
	 */
	public function __construct() {

		$this->slug = 'custom';
		$this->name = 'Custom';

		// Set up admin options.
		if ( is_admin() ) {
			require_once dirname( __FILE__ ) . '/class-wpf-custom-admin.php';
			new WPF_Custom_Admin( $this->slug, $this->name, $this );
		}

		// Error handling.
		add_filter( 'http_response', array( $this, 'handle_http_response' ), 50, 3 );

	}

	/**
	 * Sets up hooks specific to this CRM.
	 *
	 * This function only runs if this CRM is the active CRM.
	 *
	 * @since x.x.x
	 */
	public function init() {

		add_filter( 'wpf_format_field_value', array( $this, 'format_field_value' ), 10, 3 );
		add_filter( 'wpf_format_post_data', array( $this, 'format_post_data' ) );

		// Allows for linking directly to contact records in the CRM.
		$this->edit_url = trailingslashit( wp_fusion()->settings->get( 'custom_url' ) ) . 'app/contacts/%d/';
	}


	/**
	 * Format field value.
	 *
	 * Formats outgoing data to match CRM field formats. This will vary
	 * depending on the data formats accepted by the CRM.
	 *
	 * @since  x.x.x
	 *
	 * @param  mixed  $value      The value.
	 * @param  string $field_type The field type.
	 * @param  string $field      The CRM field identifier.
	 * @return mixed  The field value.
	 */
	public function format_field_value( $value, $field_type, $field ) {

		if ( 'date' === $field_type && ! empty( $value ) ) {

			$date = date( 'm/d/Y H:i:s', $value );

			return $date;

		} elseif ( is_array( $value ) ) {

			return implode( ', ', array_filter( $value ) );

		} elseif ( 'multiselect' === $field_type && empty( $value ) ) {

			$value = null;

		} else {

			return $value;

		}

	}


	/**
	 * Formats post data.
	 *
	 * Formats incoming data to match WP Fusion field formats. This will vary
	 * depending on the data formats returned by the CRM.
	 *
	 * @since  x.x.x
	 *
	 * @param  array $post_data The post data.
	 * @return array $post_data The formatted post data.
	 */
	public function format_post_data( $post_data ) {

		$payload = json_decode( file_get_contents( 'php://input' ) );

		if ( ! empty( $payload ) ) {

			$post_data['contact_id'] = $payload->contact->id; // the contact ID is required.

			// You can optionally POST an array of tags to the update or update_tags endpoints.
			// If you do, WP Fusion will skip the API call to load the tags and instead save
			// them directly from the payload to the user's meta.
			$post_data['tags'] = wp_list_pluck( $payload->contact->tags, 'name' );
		}

		return $post_data;

	}

	/**
	 * Gets params for API calls.
	 *
	 * @since  x.x.x
	 *
	 * @param  string $api_url The api URL.
	 * @param  string $api_key The api key.
	 * @return array  $params The API parameters.
	 */
	public function get_params( $api_url = null, $api_key = null ) {

		// If it's already been set up.
		if ( $this->params ) {
			return $this->params;
		}

		// Get saved data from DB.
		if ( empty( $api_url ) || empty( $api_key ) ) {
			$api_url = wpf_get_option( 'custom_url' );
			$api_key = wpf_get_option( 'custom_key' );
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
	 * Refresh an access token from a refresh token. Remove if not using OAuth.
	 *
	 * @since  x.x.x
	 *
	 * @return string An access token.
	 */
	public function refresh_token() {

		$refresh_token = wpf_get_option( "{$this->slug}_refresh_token" );

		$params = array(
			'user-agent' => 'WP Fusion; ' . home_url(),
			'headers'    => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body'       => array(
				'grant_type'    => 'refresh_token',
				'client_id'     => $this->client_id,
				'client_secret' => $this->client_secret,
				'redirect_uri'  => "https://wpfusion.com/oauth/?action=wpf_get_{$this->slug}_token",
				'refresh_token' => $refresh_token,
			),
		);

		$response = wp_safe_remote_post( $this->auth_url, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body_json = json_decode( wp_remote_retrieve_body( $response ) );

		$this->get_params( $body_json->access_token );

		wp_fusion()->settings->set( "{$this->slug}_token", $body_json->access_token );

		return $body_json->access_token;

	}

	/**
	 * Gets the default fields.
	 *
	 * @since  x.x.x
	 *
	 * @return array The default fields in the CRM.
	 */
	public static function get_default_fields() {

		return array(
			'first_name'     => array(
				'crm_label' => 'First Name',
				'crm_field' => 'f_name',
			),
			'last_name'      => array(
				'crm_label' => 'Last Name',
				'crm_field' => 'l_name',
			),
			'user_email'     => array(
				'crm_label' => 'Email',
				'crm_field' => 'email',
			),
			'billing_phone'  => array(
				'crm_label' => 'Phone',
				'crm_field' => 'contact_no',
			),
			'billing_state'  => array(
				'crm_label' => 'State',
				'crm_field' => 'state',
			),
			'billing_counry' => array(
				'crm_label' => 'Country',
				'crm_field' => 'country',
			),
		);

	}


	/**
	 * Check HTTP Response for errors and return WP_Error if found.
	 *
	 * @since  1.0.2
	 *
	 * @param  object $response The HTTP response.
	 * @param  array  $args     The HTTP request arguments.
	 * @param  string $url      The HTTP request URL.
	 * @return object $response The response.
	 */
	public function handle_http_response( $response, $args, $url ) {

		if ( strpos( $url, $this->url ) !== false && 'WP Fusion; ' . home_url() === $args['user-agent'] ) { // check if the request came from us.

			$body_json     = json_decode( wp_remote_retrieve_body( $response ) );
			$response_code = wp_remote_retrieve_response_code( $response );

			if ( 401 === $response_code ) {

				// Handle refreshing an OAuth token. Remove if not using OAuth.

				if ( strpos( $body_json->message, 'expired' ) !== false ) {

					$access_token                     = $this->refresh_token();
					$args['headers']['Authorization'] = 'Bearer ' . $access_token;

					$response = wp_safe_remote_request( $url, $args );

				} else {

					$response = new WP_Error( 'error', 'Invalid API credentials.' );

				}
			} elseif ( isset( $body_json->success ) && false === (bool) $body_json->success ) {

				$response = new WP_Error( 'error', $body_json->message );

			} elseif ( 500 === $response_code ) {

				$response = new WP_Error( 'error', __( 'An error has occurred in API server. [error 500]', 'wp-fusion' ) );

			}
		}

		return $response;

	}


	/**
	 * Initialize connection.
	 *
	 * This is run during the setup process to validate that the user has
	 * entered the correct API credentials.
	 *
	 * @since  x.x.x
	 *
	 * @param  string $api_url The first API credential.
	 * @param  string $api_key The second API credential.
	 * @param  bool   $test    Whether to validate the credentials.
	 * @return bool|WP_Error A WP_Error will be returned if the API credentials are invalid.
	 */
	public function connect( $api_url = null, $api_key = null, $test = false ) {

		if ( false === $test ) {
			return true;
		}

		if ( ! $this->params ) {
			$this->get_params( $api_url, $api_key );
		}

		$request  = $this->url . '/endpoint/';
		$response = wp_safe_remote_get( $request, $this->params );

		// Validate the connection.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}


	/**
	 * Performs initial sync once connection is configured.
	 *
	 * @since x.x.x
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
	 * @since  x.x.x
	 *
	 * @return array|WP_Error Either the available tags in the CRM, or a WP_Error.
	 */
	public function sync_tags() {

		$request  = $this->url . '/endpoint/';
		$response = wp_safe_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		$available_tags = array();

		// Load available tags into $available_tags like 'tag_id' => 'Tag Label'.
		if ( ! empty( $response->tags ) ) {

			foreach ( $response->tags as $tag ) {

				$tag_id                    = (int) $tag->id;
				$available_tags[ $tag_id ] = sanitize_text_field( $tag->label );
			}
		}

		wp_fusion()->settings->set( 'available_tags', $available_tags );

		return $available_tags;
	}


	/**
	 * Loads all custom fields from CRM and merges with local list.
	 *
	 * @since  x.x.x
	 *
	 * @return array|WP_Error Either the available fields in the CRM, or a WP_Error.
	 */
	public function sync_crm_fields() {

		$request  = $this->url . '/endpoint/';
		$response = wp_safe_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$crm_fields = array();

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// Load available fields into $crm_fields like 'field_key' => 'Field Label'.
		asort( $crm_fields );

		wp_fusion()->settings->set( 'crm_fields', $crm_fields );

		return $crm_fields;
	}


	/**
	 * Gets contact ID for a user based on email address.
	 *
	 * @since  x.x.x
	 *
	 * @param  string $email_address The email address to look up.
	 * @return int|WP_Error The contact ID in the CRM.
	 */
	public function get_contact_id( $email_address ) {

		$request  = $this->url . '/endpoint/?email=' . urlencode( $email_address );
		$response = wp_safe_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// Parse response for contact ID here.
		return (int) $response->id;
	}


	/**
	 * Gets all tags currently applied to the contact in the CRM.
	 *
	 * @since x.x.x
	 *
	 * @param int $contact_id The contact ID to load the tags for.
	 * @return array|WP_Error The tags currently applied to the contact in the CRM.
	 */
	public function get_tags( $contact_id ) {

		if ( ! $this->params ) {
			$this->get_params();
		}

		$request  = $this->url . '/endpoint/';
		$response = wp_safe_remote_get( $request, $this->params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// Parse response to create an array of tag ids. $tags = array(123, 678, 543); (should not be an associative array).
		return $tags;
	}

	/**
	 * Applies tags to a contact.
	 *
	 * @since x.x.x
	 *
	 * @param array $tags       A numeric array of tags to apply to the contact.
	 * @param int   $contact_id The contact ID to apply the tags to.
	 * @return bool|WP_Error Either true, or a WP_Error if the API call failed.
	 */
	public function apply_tags( $tags, $contact_id ) {

		$request        = $this->url . '/endpoint/';
		$params         = $this->get_params();
		$params['body'] = wp_json_encode( $tags );

		$response = wp_safe_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Removes tags from a contact.
	 *
	 * @since  x.x.x
	 *
	 * @param  array $tags       A numeric array of tags to remove from the contact.
	 * @param  int   $contact_id The contact ID to remove the tags from.
	 * @return bool|WP_Error Either true, or a WP_Error if the API call failed.
	 */
	public function remove_tags( $tags, $contact_id ) {

		$request        = $this->url . '/endpoint/';
		$params         = $this->get_params();
		$params['body'] = wp_json_encode( $tags );

		$response = wp_safe_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;

	}


	/**
	 * Adds a new contact.
	 *
	 * @since x.x.x
	 *
	 * @param array $contact_data An associative array of contact fields and field values.
	 * @return int|WP_Error Contact ID on success, or WP Error.
	 */
	public function add_contact( $contact_data ) {

		$request        = $this->url . '/endpoint/';
		$params         = $this->get_params();
		$params['body'] = wp_json_encode( $contact_data );

		$response = wp_safe_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		// Get new contact ID out of response.
		return $body->id;

	}

	/**
	 * Updates an existing contact record.
	 *
	 * @since x.x.x
	 *
	 * @param int   $contact_id   The ID of the contact to update.
	 * @param array $contact_data An associative array of contact fields and field values.
	 * @return bool|WP_Error Error if the API call failed.
	 */
	public function update_contact( $contact_id, $contact_data ) {

		$request        = $this->url . '/endpoint/';
		$params         = $this->get_params();
		$params['body'] = wp_json_encode( $contact_data );

		$response = wp_safe_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Loads a contact record from the CRM and maps CRM fields to WordPress fields
	 *
	 * @since x.x.x
	 *
	 * @param int $contact_id The ID of the contact to load.
	 * @return array|WP_Error User meta data that was returned.
	 */
	public function load_contact( $contact_id ) {

		$request  = $this->url . '/endpoint/' . $contact_id;
		$response = wp_safe_remote_get( $request, $this->get_params() );

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
	 * @since x.x.x
	 *
	 * @param string $tag The tag ID or name to search for.
	 * @return array Contact IDs returned.
	 */
	public function load_contacts( $tag ) {

		$request  = $this->url . '/endpoint/tag/' . $tag;
		$response = wp_safe_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$contact_ids = array();
		$response    = json_decode( wp_remote_retrieve_body( $response ) );

		// Iterate over the contacts returned in the response and build an array such that $contact_ids = array(1,3,5,67,890);.
		foreach ( $response as $contact ) {
			$contact_ids[] = $contact->id;
		}

		return $contact_ids;

	}

	/**
	 * Track event.
	 *
	 * Track an event with the AC site tracking API.
	 *
	 * @since  x.x.x
	 *
	 * @link   https://wpfusion.com/documentation/event-tracking/event-tracking-overview/
	 *
	 * @param  string      $event         The event title.
	 * @param  bool|string $event_data    The event description.
	 * @param  bool|string $email_address The user email address.
	 * @return bool|WP_Error True if success, WP_Error if failed.
	 */
	public function track_event( $event, $event_data = false, $email_address = false ) {

		// Get the email address to track.

		if ( empty( $email_address ) ) {
			$email_address = wpf_get_current_user_email();
		}

		if ( false === $email_address ) {
			return; // can't track without an email.
		}

		$data = array(
			'email'       => $email_address,
			'event_name'  => $event,
			'event_value' => $event_data,
		);

		$params             = $this->get_params();
		$params['body']     = wp_json_encode( $data );
		$params['blocking'] = false; // we don't need to wait for a response.

		$response = wp_safe_remote_post( $this->url . '/track-event/', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;

	}

}
