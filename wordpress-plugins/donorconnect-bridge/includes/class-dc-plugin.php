<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DC_Bridge_Plugin {
	/** @var self|null */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate(): void {
		$settings = self::default_settings();
		$existing = get_option( 'dc_bridge_settings', array() );
		if ( empty( $existing['site_id'] ) ) {
			$settings['site_id'] = wp_generate_uuid4();
		}
		if ( empty( $existing['api_key'] ) ) {
			$settings['api_key'] = self::random_token( 48 );
		}
		if ( empty( $existing['hmac_secret'] ) ) {
			$settings['hmac_secret'] = self::random_token( 64 );
		}
		update_option( 'dc_bridge_settings', array_merge( $settings, is_array( $existing ) ? $existing : array() ) );

		if ( ! wp_next_scheduled( 'dc_bridge_push_cron' ) ) {
			wp_schedule_event( time() + 120, 'hourly', 'dc_bridge_push_cron' );
		}
	}

	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'dc_bridge_push_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'dc_bridge_push_cron' );
		}
	}

	/** @return array<string, mixed> */
	public static function default_settings(): array {
		return array(
			'site_id'            => '',
			'api_key'            => '',
			'hmac_secret'        => '',
			'crm_base_url'       => '',
			'crm_org_token'      => '',
			'push_enabled'       => false,
			'require_hmac'       => true,
			'max_clock_skew'     => 300,
			'allowed_ips'        => '',
			'campaign_default'   => '',
			'include_pending'    => false,
			'last_push_at'       => '',
			'last_push_status'   => '',
			'credentials_shown'  => false,
		);
	}

	public static function settings(): array {
		return array_merge( self::default_settings(), get_option( 'dc_bridge_settings', array() ) );
	}

	public static function update_settings( array $patch ): array {
		$settings = array_merge( self::settings(), $patch );
		update_option( 'dc_bridge_settings', $settings );

		return $settings;
	}

	public static function random_token( int $bytes = 32 ): string {
		return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' );
	}

	public function boot(): void {
		( new DC_Bridge_REST_API() )->register();
		( new DC_Bridge_Admin() )->register();
		( new DC_Bridge_Pusher() )->register();
	}
}
