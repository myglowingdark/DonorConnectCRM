<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read NGOBuddy Razorpay credentials and create payment links / orders for CRM.
 */
class DC_Bridge_Razorpay {
	/** @return array<string, mixed> */
	public static function ngobuddy_settings(): array {
		$settings = get_option( 'gdnb_donations_settings', array() );

		return is_array( $settings ) ? $settings : array();
	}

	/** @return array{key_id:string,key_secret:string,webhook_secret:string,mode:string} */
	public static function credentials(): array {
		$s = self::ngobuddy_settings();

		return array(
			'key_id'         => (string) ( $s['razorpay_key_id'] ?? '' ),
			'key_secret'     => (string) ( $s['razorpay_key_secret'] ?? '' ),
			'webhook_secret' => (string) ( $s['razorpay_webhook_secret'] ?? '' ),
			'mode'           => (string) ( $s['razorpay_mode'] ?? 'test' ),
		);
	}

	/** Public status without secrets. */
	public static function status(): array {
		$c = self::credentials();

		return array(
			'configured'       => $c['key_id'] !== '' && $c['key_secret'] !== '',
			'key_id'           => $c['key_id'],
			'key_id_masked'    => $c['key_id'] !== '' ? ( substr( $c['key_id'], 0, 8 ) . '…' ) : '',
			'has_key_secret'   => $c['key_secret'] !== '',
			'has_webhook_secret' => $c['webhook_secret'] !== '',
			'mode'             => $c['mode'],
			'source'           => 'ngobuddy',
		);
	}

	/**
	 * Full config for CRM sync (HMAC-protected callers only).
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function export_config_for_crm() {
		$c = self::credentials();
		if ( $c['key_id'] === '' || $c['key_secret'] === '' ) {
			return new WP_Error( 'dc_rzp_missing', 'Razorpay keys are not configured in NGOBuddy settings.', array( 'status' => 422 ) );
		}

		return array(
			'razorpay_enabled'        => true,
			'razorpay_key_id'         => $c['key_id'],
			'razorpay_key_secret'     => $c['key_secret'],
			'razorpay_webhook_secret' => $c['webhook_secret'] !== '' ? $c['webhook_secret'] : null,
			'razorpay_mode'           => $c['mode'],
			'source'                  => 'ngobuddy',
			'site_url'                => home_url( '/' ),
			'exported_at'             => gmdate( 'c' ),
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_payment_link( array $args ) {
		$c = self::credentials();
		if ( $c['key_id'] === '' || $c['key_secret'] === '' ) {
			return new WP_Error( 'dc_rzp_missing', 'Razorpay keys missing in NGOBuddy.', array( 'status' => 422 ) );
		}

		$amount = (float) ( $args['amount'] ?? 0 );
		if ( $amount < 1 ) {
			return new WP_Error( 'dc_rzp_amount', 'Amount must be at least 1.', array( 'status' => 422 ) );
		}

		$amount_paise = (int) round( $amount * 100 );
		$payload      = array(
			'amount'      => $amount_paise,
			'currency'    => (string) ( $args['currency'] ?? 'INR' ),
			'description' => (string) ( $args['purpose'] ?? 'Donation' ),
			'customer'    => array_filter(
				array(
					'name'    => $args['donor_name'] ?? null,
					'email'   => $args['donor_email'] ?? null,
					'contact' => $args['donor_phone'] ?? null,
				)
			),
			'notify'      => array(
				'sms'   => ! empty( $args['donor_phone'] ),
				'email' => ! empty( $args['donor_email'] ),
			),
			'notes'       => array_filter(
				array(
					'donorconnect' => '1',
					'donor_id'     => $args['external_donor_id'] ?? null,
					'crm_donor_id' => $args['crm_donor_id'] ?? null,
					'purpose'      => $args['purpose'] ?? 'donation',
				)
			),
		);

		if ( ! empty( $args['callback_url'] ) ) {
			$payload['callback_url']    = esc_url_raw( (string) $args['callback_url'] );
			$payload['callback_method'] = 'get';
		}

		return self::api_post( 'payment_links', $payload );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_order( array $args ) {
		$c = self::credentials();
		if ( $c['key_id'] === '' || $c['key_secret'] === '' ) {
			return new WP_Error( 'dc_rzp_missing', 'Razorpay keys missing in NGOBuddy.', array( 'status' => 422 ) );
		}

		$amount = (float) ( $args['amount'] ?? 0 );
		if ( $amount < 1 ) {
			return new WP_Error( 'dc_rzp_amount', 'Amount must be at least 1.', array( 'status' => 422 ) );
		}

		$payload = array(
			'amount'   => (int) round( $amount * 100 ),
			'currency' => (string) ( $args['currency'] ?? 'INR' ),
			'receipt'  => (string) ( $args['receipt'] ?? ( 'dc_' . wp_generate_password( 12, false ) ) ),
			'notes'    => array_filter(
				array(
					'donorconnect' => '1',
					'crm_donor_id' => $args['crm_donor_id'] ?? null,
					'purpose'      => $args['purpose'] ?? 'donation',
				)
			),
		);

		$result = self::api_post( 'orders', $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['key_id'] = $c['key_id'];

		return $result;
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array<string, mixed>|WP_Error
	 */
	public static function list_payments( array $query = array() ) {
		$c = self::credentials();
		if ( $c['key_id'] === '' || $c['key_secret'] === '' ) {
			return new WP_Error( 'dc_rzp_missing', 'Razorpay keys missing in NGOBuddy.', array( 'status' => 422 ) );
		}

		$defaults = array(
			'count' => 20,
			'skip'  => 0,
		);
		$query = array_merge( $defaults, $query );

		return self::api_get( 'payments', $query );
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>|WP_Error
	 */
	private static function api_post( string $endpoint, array $body ) {
		$c = self::credentials();
		$url = 'https://api.razorpay.com/v1/' . ltrim( $endpoint, '/' );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $c['key_id'] . ':' . $c['key_secret'] ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		return self::parse_response( $response );
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array<string, mixed>|WP_Error
	 */
	private static function api_get( string $endpoint, array $query = array() ) {
		$c = self::credentials();
		$url = add_query_arg( $query, 'https://api.razorpay.com/v1/' . ltrim( $endpoint, '/' ) );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $c['key_id'] . ':' . $c['key_secret'] ),
				),
			)
		);

		return self::parse_response( $response );
	}

	/** @param array|WP_Error $response */
	private static function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) ? ( $data['error']['description'] ?? $raw ) : $raw;

			return new WP_Error( 'dc_rzp_http', 'Razorpay error: ' . $message, array( 'status' => $code ) );
		}

		return is_array( $data ) ? $data : array( 'raw' => $raw );
	}
}
