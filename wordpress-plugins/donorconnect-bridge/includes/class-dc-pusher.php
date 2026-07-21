<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DC_Bridge_Pusher {
	public function register(): void {
		add_action( 'dc_bridge_push_cron', array( $this, 'cron_push' ) );
		add_action( 'wp_ajax_dc_bridge_push_now', array( $this, 'ajax_push_now' ) );
	}

	public function cron_push(): void {
		$settings = DC_Bridge_Plugin::settings();
		if ( empty( $settings['push_enabled'] ) ) {
			return;
		}
		$this->push_page( 1, 100 );
	}

	public function ajax_push_now(): void {
		check_ajax_referer( 'dc_bridge_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}

		$result = $this->push_page( 1, 100 );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/** @return array<string,mixed>|WP_Error */
	public function push_page( int $page = 1, int $per_page = 100 ) {
		$settings = DC_Bridge_Plugin::settings();
		$base     = rtrim( (string) $settings['crm_base_url'], '/' );
		$token    = (string) $settings['crm_org_token'];

		if ( $base === '' || $token === '' ) {
			return new WP_Error( 'dc_push_config', 'CRM base URL and organization API token are required for push.' );
		}

		$payload = DC_Bridge_Source::fetch_donors( $page, $per_page );
		$body    = wp_json_encode(
			array(
				'site_id' => $settings['site_id'],
				'donors'  => $payload['donors'],
			)
		);

		$path    = '/api/v1/ingest/donors';
		$url     = $base . $path;
		$headers = DC_Bridge_Auth::signed_headers( 'POST', $path, (string) $body );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			DC_Bridge_Plugin::update_settings(
				array(
					'last_push_at'     => current_time( 'mysql' ),
					'last_push_status' => 'error: ' . $response->get_error_message(),
				)
			);

			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$ok   = $code >= 200 && $code < 300;

		DC_Bridge_Plugin::update_settings(
			array(
				'last_push_at'     => current_time( 'mysql' ),
				'last_push_status' => $ok ? 'ok:' . $code : 'fail:' . $code . ' ' . substr( $raw, 0, 200 ),
			)
		);

		if ( ! $ok ) {
			return new WP_Error( 'dc_push_http', 'CRM ingest failed HTTP ' . $code . ': ' . $raw );
		}

		return array(
			'status' => $code,
			'body'   => json_decode( $raw, true ),
			'count'  => count( $payload['donors'] ),
		);
	}
}
