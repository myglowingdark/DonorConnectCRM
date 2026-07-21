<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend tracking: dcr query → dca cookie, funnel beacons, UTM inject into NGOBuddy orders.
 */
class DC_Bridge_Tracking {
	public const COOKIE = 'dca';
	public const QUERY  = 'dcr';

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue(): void {
		if ( is_admin() ) {
			return;
		}

		$settings = DC_Bridge_Plugin::settings();
		$crm      = rtrim( (string) ( $settings['crm_base_url'] ?? '' ), '/' );
		if ( $crm === '' ) {
			return;
		}

		wp_enqueue_script(
			'dc-bridge-tracking',
			DC_BRIDGE_URL . 'assets/tracking.js',
			array(),
			DC_BRIDGE_VERSION,
			true
		);

		wp_localize_script(
			'dc-bridge-tracking',
			'dcBridgeTracking',
			array(
				'crmBaseUrl'   => $crm,
				'eventsUrl'    => $crm . '/t/events',
				'cookieName'   => self::COOKIE,
				'queryParam'   => self::QUERY,
				'cookieDays'   => 3,
				'utmSource'    => 'donorconnect',
			)
		);
	}
}
