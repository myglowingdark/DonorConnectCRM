<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DC_Bridge_REST_API {
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'donorconnect/v1',
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			'donorconnect/v1',
			'/donors',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'donors' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 100,
					),
				),
			)
		);

		register_rest_route(
			'donorconnect/v1',
			'/projects',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'projects' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			'donorconnect/v1',
			'/razorpay/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'razorpay_status' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			'donorconnect/v1',
			'/razorpay/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'razorpay_config' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			'donorconnect/v1',
			'/razorpay/payments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'razorpay_payments' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			'donorconnect/v1',
			'/razorpay/payment-links',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'razorpay_payment_link' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			'donorconnect/v1',
			'/razorpay/orders',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'razorpay_order' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
	}

	/** @return true|WP_Error */
	public function permission( WP_REST_Request $request ) {
		return DC_Bridge_Auth::authenticate_rest_request( $request );
	}

	public function health( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( DC_Bridge_Source::health(), 200 );
	}

	public function donors( WP_REST_Request $request ): WP_REST_Response {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );
		$payload  = DC_Bridge_Source::fetch_donors( $page, $per_page );

		// CRM WordPressDonorSyncService accepts list or { donors: [] }.
		return new WP_REST_Response(
			array(
				'donors'   => $payload['donors'],
				'meta'     => array(
					'total'    => $payload['total'],
					'page'     => $payload['page'],
					'per_page' => $payload['per_page'],
					'site_id'  => DC_Bridge_Plugin::settings()['site_id'],
				),
			),
			200
		);
	}

	public function projects( WP_REST_Request $request ): WP_REST_Response {
		$payload = DC_Bridge_Source::fetch_donation_targets();

		return new WP_REST_Response(
			array(
				'ok'                     => true,
				'site_url'               => $payload['site_url'],
				'general_donation_url'   => $payload['general_donation_url'],
				'general_donation_label' => $payload['general_donation_label'],
				'projects'               => $payload['projects'],
				'meta'                   => array(
					'site_id'        => DC_Bridge_Plugin::settings()['site_id'],
					'projects_count' => count( $payload['projects'] ),
				),
			),
			200
		);
	}

	public function razorpay_status( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( DC_Bridge_Razorpay::status(), 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public function razorpay_config( WP_REST_Request $request ) {
		$config = DC_Bridge_Razorpay::export_config_for_crm();
		if ( is_wp_error( $config ) ) {
			return $config;
		}

		return new WP_REST_Response( $config, 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public function razorpay_payments( WP_REST_Request $request ) {
		$result = DC_Bridge_Razorpay::list_payments(
			array(
				'count' => min( 100, max( 1, (int) $request->get_param( 'count' ) ?: 20 ) ),
				'skip'  => max( 0, (int) $request->get_param( 'skip' ) ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public function razorpay_payment_link( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$result = DC_Bridge_Razorpay::create_payment_link( is_array( $params ) ? $params : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'ok'               => true,
				'payment_link_id'  => $result['id'] ?? null,
				'short_url'        => $result['short_url'] ?? null,
				'amount'           => $result['amount'] ?? null,
				'currency'         => $result['currency'] ?? 'INR',
				'status'           => $result['status'] ?? null,
				'raw'              => $result,
			),
			201
		);
	}

	/** @return WP_REST_Response|WP_Error */
	public function razorpay_order( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$result = DC_Bridge_Razorpay::create_order( is_array( $params ) ? $params : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'order_id' => $result['id'] ?? null,
				'key_id'   => $result['key_id'] ?? null,
				'amount'   => $result['amount'] ?? null,
				'currency' => $result['currency'] ?? 'INR',
				'raw'      => $result,
			),
			201
		);
	}
}
