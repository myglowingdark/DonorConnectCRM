<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * High-security request authentication for CRM ↔ WordPress linking.
 *
 * Required headers (when require_hmac is on):
 * - X-DC-API-Key
 * - X-DC-Timestamp (unix seconds)
 * - X-DC-Nonce (unique per request, 16–64 chars)
 * - X-DC-Signature = hex(hmac_sha256("{timestamp}.{nonce}.{METHOD}.{path}.{sha256(body)}", hmac_secret))
 */
class DC_Bridge_Auth {
	public static function authenticate_rest_request( WP_REST_Request $request ): true|WP_Error {
		$settings = DC_Bridge_Plugin::settings();

		$api_key = (string) $request->get_header( 'x-dc-api-key' );
		if ( $api_key === '' ) {
			$auth = (string) $request->get_header( 'authorization' );
			if ( preg_match( '/^Bearer\s+(.+)$/i', $auth, $m ) ) {
				$api_key = trim( $m[1] );
			}
		}

		if ( $api_key === '' ) {
			return new WP_Error( 'dc_unauthorized', 'API key missing.', array( 'status' => 401 ) );
		}

		if ( ! hash_equals( (string) $settings['api_key'], $api_key ) ) {
			return new WP_Error( 'dc_unauthorized', 'API key does not match.', array( 'status' => 401 ) );
		}

		$allowed = trim( (string) $settings['allowed_ips'] );
		if ( $allowed !== '' ) {
			$ip    = self::client_ip();
			$list  = array_filter( array_map( 'trim', preg_split( '/[\s,]+/', $allowed ) ?: array() ) );
			$ok_ip = false;
			foreach ( $list as $entry ) {
				if ( $entry === $ip || self::ip_in_cidr( $ip, $entry ) ) {
					$ok_ip = true;
					break;
				}
			}
			if ( ! $ok_ip ) {
				return new WP_Error( 'dc_forbidden_ip', 'Request IP is not allowlisted.', array( 'status' => 403 ) );
			}
		}

		if ( empty( $settings['require_hmac'] ) ) {
			return true;
		}

		$timestamp = (string) $request->get_header( 'x-dc-timestamp' );
		$nonce     = (string) $request->get_header( 'x-dc-nonce' );
		$signature = (string) $request->get_header( 'x-dc-signature' );

		if ( $timestamp === '' || $nonce === '' || $signature === '' ) {
			return new WP_Error( 'dc_missing_hmac', 'HMAC headers required (X-DC-Timestamp, X-DC-Nonce, X-DC-Signature).', array( 'status' => 401 ) );
		}

		if ( ! ctype_digit( $timestamp ) ) {
			return new WP_Error( 'dc_bad_timestamp', 'Invalid timestamp.', array( 'status' => 401 ) );
		}

		$skew = abs( time() - (int) $timestamp );
		$max  = max( 60, (int) ( $settings['max_clock_skew'] ?? 300 ) );
		if ( $skew > $max ) {
			return new WP_Error( 'dc_timestamp_skew', 'Timestamp outside allowed window.', array( 'status' => 401 ) );
		}

		if ( strlen( $nonce ) < 16 || strlen( $nonce ) > 64 ) {
			return new WP_Error( 'dc_bad_nonce', 'Nonce must be 16–64 characters.', array( 'status' => 401 ) );
		}

		$nonce_key = 'dc_bridge_nonce_' . md5( $nonce );
		if ( get_transient( $nonce_key ) ) {
			return new WP_Error( 'dc_replay', 'Nonce already used.', array( 'status' => 401 ) );
		}
		set_transient( $nonce_key, 1, $max * 2 );

		$method   = strtoupper( $request->get_method() );
		$path     = (string) wp_parse_url( $request->get_route(), PHP_URL_PATH );
		// WP REST route is like /donorconnect/v1/donors — include query for GET.
		$query = $request->get_query_params();
		ksort( $query );
		$query_string = http_build_query( $query );
		$path_with_q  = $path . ( $query_string !== '' ? '?' . $query_string : '' );

		$body      = $request->get_body();
		$body_hash = hash( 'sha256', is_string( $body ) ? $body : '' );
		$payload   = $timestamp . '.' . $nonce . '.' . $method . '.' . $path_with_q . '.' . $body_hash;
		$expected  = hash_hmac( 'sha256', $payload, (string) $settings['hmac_secret'] );

		if ( ! hash_equals( $expected, strtolower( $signature ) ) && ! hash_equals( $expected, $signature ) ) {
			return new WP_Error( 'dc_bad_signature', 'Invalid request signature.', array( 'status' => 401 ) );
		}

		return true;
	}

	/** Build signed headers for outbound push to CRM. */
	public static function signed_headers( string $method, string $path, string $body = '' ): array {
		$settings  = DC_Bridge_Plugin::settings();
		$timestamp = (string) time();
		$nonce     = bin2hex( random_bytes( 16 ) );
		$body_hash = hash( 'sha256', $body );
		$payload   = $timestamp . '.' . $nonce . '.' . strtoupper( $method ) . '.' . $path . '.' . $body_hash;
		$signature = hash_hmac( 'sha256', $payload, (string) $settings['hmac_secret'] );

		return array(
			'Authorization'   => 'Bearer ' . (string) $settings['crm_org_token'],
			'X-DC-Site-Id'    => (string) $settings['site_id'],
			'X-DC-API-Key'    => (string) $settings['api_key'],
			'X-DC-Timestamp'  => $timestamp,
			'X-DC-Nonce'      => $nonce,
			'X-DC-Signature'  => $signature,
			'Content-Type'    => 'application/json',
			'Accept'          => 'application/json',
		);
	}

	private static function client_ip(): string {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );

			return sanitize_text_field( trim( $parts[0] ) );
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	private static function ip_in_cidr( string $ip, string $cidr ): bool {
		if ( strpos( $cidr, '/' ) === false ) {
			return false;
		}
		[ $subnet, $mask ] = explode( '/', $cidr, 2 );
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$ip_long     = ip2long( $ip );
			$subnet_long = ip2long( $subnet );
			$mask        = (int) $mask;
			$mask_long   = -1 << ( 32 - $mask );

			return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
		}

		return false;
	}
}
