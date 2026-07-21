<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads donors + Razorpay donations from NGOBuddy tables (preferred)
 * and shapes them for DonorConnect CRM sync.
 */
class DC_Bridge_Source {
	public static function ngobuddy_active(): bool {
		return defined( 'GDNB_DONATIONS_TABLE_DONORS' ) || class_exists( 'GDNB_Donations_DB' );
	}

	public static function donors_table(): string {
		global $wpdb;
		$suffix = defined( 'GDNB_DONATIONS_TABLE_DONORS' ) ? GDNB_DONATIONS_TABLE_DONORS : 'gdnb_donors';

		return $wpdb->prefix . $suffix;
	}

	public static function donations_table(): string {
		global $wpdb;
		$suffix = defined( 'GDNB_DONATIONS_TABLE_DONATIONS' ) ? GDNB_DONATIONS_TABLE_DONATIONS : 'gdnb_donations';

		return $wpdb->prefix . $suffix;
	}

	/**
	 * @return array{donors: list<array<string,mixed>>, total: int, page: int, per_page: int}
	 */
	public static function fetch_donors( int $page = 1, int $per_page = 100 ): array {
		global $wpdb;

		$page     = max( 1, $page );
		$per_page = min( 200, max( 1, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$settings = DC_Bridge_Plugin::settings();

		if ( ! self::table_exists( self::donors_table() ) ) {
			return array(
				'donors'   => array(),
				'total'    => 0,
				'page'     => $page,
				'per_page' => $per_page,
			);
		}

		$donors_table    = self::donors_table();
		$donations_table = self::donations_table();

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$donors_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL

		$donor_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$donors_table} ORDER BY id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$donors = array();
		foreach ( (array) $donor_rows as $row ) {
			$donor_id = (int) ( $row['id'] ?? 0 );
			$donations = array();

			if ( self::table_exists( $donations_table ) && $donor_id > 0 ) {
				$status_sql = ! empty( $settings['include_pending'] )
					? ''
					: " AND status IN ('paid','captured','completed','success') ";

				$donation_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$donations_table} WHERE donor_id = %d {$status_sql} ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL
						$donor_id
					),
					ARRAY_A
				);

				foreach ( (array) $donation_rows as $d ) {
					$donations[] = self::map_donation( $d, $settings );
				}
			}

			// Also include orphaned donations matched by email/phone later via flat scan.
			$donors[] = array(
				'id'              => 'ngobuddy-' . $donor_id,
				'name'            => (string) ( $row['name'] ?? 'Unknown Donor' ),
				'email'           => $row['email'] ?? null,
				'phone'           => $row['phone'] ?? null,
				'alternate_phone' => null,
				'address'         => null,
				'city'            => null,
				'state'           => null,
				'country'         => 'India',
				'campaign'        => $settings['campaign_default'] ?: null,
				'donations'       => $donations,
				'source'          => 'ngobuddy',
			);
		}

		$orphans = array();

		// Include Razorpay donations without donor_id as synthetic donors.
		if ( self::table_exists( $donations_table ) ) {
			$status_sql = ! empty( $settings['include_pending'] )
				? ''
				: " AND status IN ('paid','captured','completed','success') ";

			$orphans = $wpdb->get_results(
				"SELECT * FROM {$donations_table} WHERE (donor_id IS NULL OR donor_id = 0) {$status_sql} ORDER BY id ASC LIMIT 500", // phpcs:ignore
				ARRAY_A
			);
			$orphans = is_array( $orphans ) ? $orphans : array();

			foreach ( $orphans as $d ) {
				$external = 'rzp-orphan-' . ( $d['razorpay_payment_id'] ?: $d['id'] );
				$donors[] = array(
					'id'              => $external,
					'name'            => (string) ( $d['donor_name'] ?? 'Razorpay Donor' ),
					'email'           => $d['donor_email'] ?? null,
					'phone'           => $d['donor_phone'] ?? null,
					'alternate_phone' => null,
					'address'         => null,
					'city'            => $d['geo_city'] ?? null,
					'state'           => $d['geo_region'] ?? null,
					'country'         => $d['geo_country'] ?? 'India',
					'campaign'        => $d['utm_campaign'] ?: ( $settings['campaign_default'] ?: null ),
					'donations'       => array( self::map_donation( $d, $settings ) ),
					'source'          => 'razorpay_orphan',
				);
			}
		}

		return array(
			'donors'   => $donors,
			'total'    => $total + count( $orphans ),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/** @param array<string,mixed> $d @param array<string,mixed> $settings */
	private static function map_donation( array $d, array $settings ): array {
		$payment_id = (string) ( $d['razorpay_payment_id'] ?? '' );
		$order_id   = (string) ( $d['razorpay_order_id'] ?? '' );
		$donation_id = $payment_id !== '' ? $payment_id : ( $order_id !== '' ? $order_id : ( 'gdnb-don-' . ( $d['id'] ?? '' ) ) );

		$status = strtolower( (string) ( $d['status'] ?? 'completed' ) );
		if ( in_array( $status, array( 'paid', 'captured', 'success' ), true ) ) {
			$status = 'completed';
		}

		return array(
			'donation_id'        => $donation_id,
			'amount'             => (float) ( $d['amount'] ?? 0 ),
			'currency'           => (string) ( $d['currency'] ?? 'INR' ),
			'donated_at'         => (string) ( $d['created_at'] ?? current_time( 'mysql' ) ),
			'payment_status'     => $status,
			'payment_method'     => 'Razorpay',
			'razorpay_payment_id'=> $payment_id ?: null,
			'razorpay_order_id'  => $order_id ?: null,
			'campaign'           => $d['utm_campaign'] ?: ( $settings['campaign_default'] ?: null ),
			'project_id'         => $d['project_id'] ?? null,
			'receipt_number'     => $d['receipt_number'] ?? null,
			'is_recurring'       => ! empty( $d['is_recurring'] ),
			'source_data'        => array(
				'utm_source'   => $d['utm_source'] ?? null,
				'utm_medium'   => $d['utm_medium'] ?? null,
				'utm_campaign' => $d['utm_campaign'] ?? null,
				'landing_url'  => $d['landing_url'] ?? null,
			),
		);
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		$like = $wpdb->esc_like( $table );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

		return $found === $table;
	}

	/** @return array<string,mixed> */
	public static function health(): array {
		$settings = DC_Bridge_Plugin::settings();

		return array(
			'ok'              => true,
			'plugin'          => 'donorconnect-bridge',
			'version'         => DC_BRIDGE_VERSION,
			'site_id'         => $settings['site_id'],
			'site_url'        => home_url( '/' ),
			'ngobuddy_active' => self::ngobuddy_active(),
			'donors_table'    => self::table_exists( self::donors_table() ),
			'donations_table' => self::table_exists( self::donations_table() ),
			'razorpay'        => class_exists( 'DC_Bridge_Razorpay' ) ? DC_Bridge_Razorpay::status() : null,
			'require_hmac'    => (bool) $settings['require_hmac'],
			'time'            => gmdate( 'c' ),
		);
	}
}
