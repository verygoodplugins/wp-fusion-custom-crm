<?php

/**
 * WP Fusion Custom CRM class.
 *
 * @since x.x.x
 */
class WPF_Custom {

	/**
	 * The CRM slug.
	 *
	 * @var string
	 * @since x.x.x
	 */

	public $slug = 'custom';

	/**
	 * The CRM name.
	 *
	 * @var string
	 * @since x.x.x
	 */

	public $name = 'Custom';

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
	 * "add_tags_api" means that tags can be created via an API call. Uses the add_tag() method.
	 *
	 * "lists" means contacts can be added to lists in addition to tags. Requires the sync_lists() method.
	 *
	 * "add_fields" means that custom field / attrubute keys don't need to exist first in the CRM to be used.
	 * With add_fields enabled, WP Fusion will allow users to type new filed names into the CRM Field select boxes.
	 *
	 * "events" enables the integration for Event Tracking: https://wpfusion.com/documentation/event-tracking/event-tracking-overview/.
	 *
	 * "events_multi_key" enables the integration for Event Tracking with multiple keys: https://wpfusion.com/documentation/event-tracking/event-tracking-overview/#multi-key-events.
	 *
	 * @var array<string>
	 * @since x.x.x
	 */

	public $supports = array(
		'add_tags',
		'add_tags_api',
		'lists',
		'add_fields',
		'events_multi_key',
	);

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
	 * Get things started.
	 *
	 * @since x.x.x
	 */
	public function __construct() {

		// Set up admin options.
		if ( is_admin() ) {
			require_once __DIR__ . '/class-wpf-custom-admin.php';
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
		add_filter( 'wpf_crm_post_data', array( $this, 'format_post_data' ) );

		// Allows for linking directly to contact records in the CRM.
		$this->edit_url = trailingslashit( wp_fusion()->settings->get( 'custom_url' ) ) . 'app/contacts/%d/';

		// Sets the base URL for API calls.
		$this->url = wpf_get_option( 'custom_url' );
	}


	/**
	 * Format field value.
	 *
	 * Formats outgoing data to match CRM field formats. This will vary
	 * depending on the data formats accepted by the CRM.
	 *
	 * @since  x.x.x
	 *
	 * @link https://wpfusion.com/documentation/getting-started/syncing-contact-fields/#field-types
	 *
	 * @param  mixed  $value      The value.
	 * @param  string $field_type The field type ('text', 'date', 'multiselect', 'checkbox').
	 * @param  string $field      The CRM field identifier.
	 * @return mixed  The field value.
	 */
	public function format_field_value( $value, $field_type, $field ) {

		if ( 'date' === $field_type && ! empty( $value ) ) {

			// Dates come in as a timestamp.

			$date = gmdate( 'm/d/Y H:i:s', $value );

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
	 * This runs when a webhook is received and extracts the contact ID (and optionally
	 * tags) from the webhook payload.
	 *
	 * @since  x.x.x
	 *
	 * @link https://wpfusion.com/documentation/webhooks/about-webhooks/
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
	 * @since x.x.x
	 *
	 * @param string $api_key The API key.
	 * @return array<string|mixed> $params The API parameters.
	 */
	public function get_params( $api_key = null ) {

		// Get saved data from DB.
		if ( ! $api_key ) {
			$api_key = wpf_get_option( 'custom_key' );
		}

		$params = array(
			'user-agent' => 'WP Fusion; ' . home_url(),
			'timeout'    => 15,
			'headers'    => array(
				'Authorization' => 'Bearer ' . $api_key,
			),
		);

		return $params;
	}

	/**
	 * Refresh an access token from a refresh token. Remove if not using OAuth.
	 *
	 * @since x.x.x
	 *
	 * @return string|WP_Error An access token or error.
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
	 * @since x.x.x
	 *
	 * @return array<string, array> The default fields in the CRM.
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
				'crm_type'  => 'email',
			),
			'billing_phone'  => array(
				'crm_label' => 'Phone',
				'crm_field' => 'contact_no',
				'crm_type'  => 'tel',
			),
			'billing_state'  => array(
				'crm_label' => 'State',
				'crm_field' => 'state',
			),
			'billing_country' => array(
				'crm_label' => 'Country',
				'crm_field' => 'country',
				'crm_type'  => 'country',
			),
		);
	}


	/**
	 * Check HTTP Response for errors and return WP_Error if found.
	 *
	 * @since x.x.x
	 *
	 * @param  array  $response The HTTP response.
	 * @param  array  $args     The HTTP request arguments.
	 * @param  string $url      The HTTP request URL.
	 * @return array|WP_Error The response or WP_Error on error.
	 */
	public function handle_http_response( $response, $args, $url ) {

		if ( strpos( $url, $this->url ) !== false && 'WP Fusion; ' . home_url() === $args['user-agent'] ) { // check if the request came from us.

			$body_json     = json_decode( wp_remote_retrieve_body( $response ) );
			$response_code = wp_remote_retrieve_response_code( $response );

			if ( 401 === $response_code ) {

				// Handle refreshing an OAuth token. Remove if not using OAuth.

				if ( strpos( $body_json->message, 'expired' ) !== false ) {

					$access_token = $this->refresh_token();

					if ( is_wp_error( $access_token ) ) {
						return $access_token;
					}

					$args['headers']['Authorization'] = 'Bearer ' . $access_token;

					$response = wp_safe_remote_request( $url, $args );

				} else {

					$response = new WP_Error( 'error', 'Invalid API credentials.' );

				}
			} elseif ( isset( $body_json->success ) && false === (bool) $body_json->success && isset( $body_json->message ) ) {

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

		$request  = $api_url . '/endpoint/';
		$response = wp_safe_remote_get( $request, $this->get_params( $api_key ) );

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

		$this->sync_tags();
		$this->sync_lists(); // if $this->supports( 'lists' );
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
	 * Gets all available lists and saves them to options.
	 *
	 * @since  x.x.x
	 *
	 * @return array|WP_Error Either the available lists in the CRM, or a WP_Error.
	 */
	public function sync_lists() {

		$request  = $this->url . '/endpoint/';
		$response = wp_safe_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		$available_lists = array();

		// Load available lists into $available_lists like 'list_id' => 'list Label'.
		if ( ! empty( $response->lists ) ) {

			foreach ( $response->lists as $list ) {

				$list_id                    = (int) $list->id;
				$available_lists[ $list_id ] = sanitize_text_field( $list->label );
			}
		}

		wp_fusion()->settings->set( 'available_lists', $available_lists );

		return $available_lists;
	}

	/**
	 * Loads all custom fields from CRM and merges with local list.
	 *
	 * @since  x.x.x
	 *
	 * @return array|WP_Error Either the available fields in the CRM, or a WP_Error.
	 */
	public function sync_crm_fields() {

		$standard_fields = array();

		foreach ( $this->get_default_fields() as $field ) {
			$standard_fields[ $field['crm_field'] ] = array(
				'crm_label' => $field['crm_label'],
				'crm_type'  => isset( $field['crm_type'] ) ? $field['crm_type'] : 'text',
			);
		}

		$request  = $this->url . '/endpoint/';
		$response = wp_safe_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$custom_fields = array();

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		foreach ( $response->fields as $field ) {

			$custom_fields[ $field->id ] = array(
				'crm_label' => $field->label,
				'crm_type'  => $field->type,
			);

		}

		$crm_fields = array(
			'Standard Fields' => $standard_fields,
			'Custom Fields'   => $custom_fields,
		);

		uasort( $crm_fields['Custom Fields'], 'wpf_sort_remote_fields' );

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

		$request  = $this->url . '/endpoint/?email=' . rawurlencode( $email_address );
		$response = wp_safe_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// Parse response for contact ID here.
		return (int) $response->id;
	}

	/**
	 * Creates a new tag and returns the ID.
	 *
	 * Requires add_tags_api to be enabled in $this->supports.
	 *
	 * @since  x.x.x
	 *
	 * @param  string       $tag_name The tag name.
	 * @return int|WP_Error $tag_id   The tag id returned from API or WP Error.
	 */
	public function add_tag( $tag_name ) {

		$params = $this->get_params();

		$data = array(
			'name' => $tag_name,
		);

		$params['body'] = wp_json_encode( $data );

		$request  = $this->url . '/tags';
		$response = wp_safe_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		return $response->id;
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

		$request  = $this->url . '/endpoint/' . $contact_id;
		$response = wp_safe_remote_get( $request, $this->get_params() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// Parse response to create an array of tag ids. $tags = array(123, 678, 543); (should not be an associative array).
		$tags = wp_list_pluck( $response->tags, 'id' );

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

		$request        = $this->url . '/endpoint/' . $contact_id;
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

		$request        = $this->url . '/endpoint/' . $contact_id;
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

		$request        = $this->url . '/endpoint/' . $contact_id;
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
	 * @return array|WP_Error Contact IDs returned or error.
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
	 * @param  array       $event_data    The event data (associative array).
	 * @param  bool|string $email_address The user email address.
	 * @return bool|WP_Error True if success, WP_Error if failed.
	 */
	public function track_event( $event, $event_data = array(), $email_address = false ) {

		// Get the email address to track.

		if ( empty( $email_address ) ) {
			$email_address = wpf_get_current_user_email();
		}

		if ( false === $email_address ) {
			return false; // can't track without an email.
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
