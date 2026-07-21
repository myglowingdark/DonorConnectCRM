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
			$donor_id      = (int) ( $row['id'] ?? 0 );
			$donation_rows = array();

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
				$donation_rows = is_array( $donation_rows ) ? $donation_rows : array();
			}

			// NGOBuddy often collapses many Razorpay checkouts onto one donor_id (same phone).
			// Split CRM identities by donation email/phone snapshot so names match Razorpay.
			$groups = self::group_donations_by_identity( $donation_rows, $row );

			if ( empty( $groups ) ) {
				// No donations: skip token-like junk rows that pollute the calling queue.
				if ( self::looks_like_token_name( (string) ( $row['name'] ?? '' ) ) ) {
					continue;
				}
				$donors[] = self::shape_donor(
					'ngobuddy-' . $donor_id,
					$row,
					array(),
					$settings,
					'ngobuddy'
				);
				continue;
			}

			$group_index = 0;
			foreach ( $groups as $identity_key => $group_rows ) {
				$snapshot = self::best_identity_from_donations( $group_rows, $row );
				$external = ( 0 === $group_index )
					? 'ngobuddy-' . $donor_id
					: 'ngobuddy-' . $donor_id . '-' . substr( md5( (string) $identity_key ), 0, 10 );

				$mapped = array();
				foreach ( $group_rows as $d ) {
					$mapped[] = self::map_donation( $d, $settings );
				}

				$donors[] = self::shape_donor(
					$external,
					$snapshot,
					$mapped,
					$settings,
					'ngobuddy'
				);
				++$group_index;
			}
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
				$donors[] = self::shape_donor(
					$external,
					array(
						'name'  => (string) ( $d['donor_name'] ?? 'Razorpay Donor' ),
						'email' => $d['donor_email'] ?? null,
						'phone' => $d['donor_phone'] ?? null,
						'city'  => $d['geo_city'] ?? null,
						'state' => $d['geo_region'] ?? null,
						'country' => $d['geo_country'] ?? 'India',
					),
					array( self::map_donation( $d, $settings ) ),
					$settings,
					'razorpay_orphan'
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

	/**
	 * @param  list<array<string,mixed>> $donation_rows
	 * @param  array<string,mixed>       $donor_row
	 * @return array<string, list<array<string,mixed>>>
	 */
	private static function group_donations_by_identity( array $donation_rows, array $donor_row ): array {
		$groups = array();
		foreach ( $donation_rows as $d ) {
			$key = self::identity_key_for_donation( $d, $donor_row );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array();
			}
			$groups[ $key ][] = $d;
		}

		return $groups;
	}

	/**
	 * @param  array<string,mixed> $donation
	 * @param  array<string,mixed> $donor_row
	 */
	private static function identity_key_for_donation( array $donation, array $donor_row ): string {
		$email = strtolower( trim( (string) ( $donation['donor_email'] ?? '' ) ) );
		if ( $email !== '' && is_email( $email ) ) {
			return 'email:' . $email;
		}

		$phone = preg_replace( '/\D+/', '', (string) ( $donation['donor_phone'] ?? '' ) );
		if ( is_string( $phone ) && strlen( $phone ) >= 10 ) {
			return 'phone:' . substr( $phone, -10 );
		}

		$fallback_email = strtolower( trim( (string) ( $donor_row['email'] ?? '' ) ) );
		if ( $fallback_email !== '' ) {
			return 'email:' . $fallback_email;
		}

		return 'donor:' . (string) ( $donor_row['id'] ?? '0' );
	}

	/**
	 * Prefer denormalized Razorpay checkout snapshot over gdnb_donors.name
	 * (which is sometimes a random token from bad/anonymous rows).
	 *
	 * @param  list<array<string,mixed>> $donation_rows
	 * @param  array<string,mixed>       $donor_row
	 * @return array<string,mixed>
	 */
	private static function best_identity_from_donations( array $donation_rows, array $donor_row ): array {
		$best = null;
		foreach ( array_reverse( $donation_rows ) as $d ) {
			$name = trim( (string) ( $d['donor_name'] ?? '' ) );
			if ( $name !== '' && ! self::looks_like_token_name( $name ) ) {
				$best = $d;
				break;
			}
		}
		if ( ! $best && ! empty( $donation_rows ) ) {
			$best = $donation_rows[ count( $donation_rows ) - 1 ];
		}

		$donor_name = (string) ( $donor_row['name'] ?? '' );
		$name       = '';
		if ( $best ) {
			$name = trim( (string) ( $best['donor_name'] ?? '' ) );
		}
		if ( $name === '' || self::looks_like_token_name( $name ) ) {
			if ( $donor_name !== '' && ! self::looks_like_token_name( $donor_name ) ) {
				$name = $donor_name;
			} elseif ( $name === '' ) {
				$name = $donor_name !== '' ? $donor_name : 'Unknown Donor';
			}
		}

		return array(
			'name'  => $name,
			'email' => $best['donor_email'] ?? ( $donor_row['email'] ?? null ),
			'phone' => $best['donor_phone'] ?? ( $donor_row['phone'] ?? null ),
			'city'  => $best['geo_city'] ?? null,
			'state' => $best['geo_region'] ?? null,
			'country' => $best['geo_country'] ?? 'India',
		);
	}

	/**
	 * @param  array<string,mixed>       $identity
	 * @param  list<array<string,mixed>> $donations
	 * @param  array<string,mixed>       $settings
	 * @return array<string,mixed>
	 */
	private static function shape_donor(
		string $external_id,
		array $identity,
		array $donations,
		array $settings,
		string $source
	): array {
		return array(
			'id'              => $external_id,
			'name'            => (string) ( $identity['name'] ?? 'Unknown Donor' ),
			'email'           => $identity['email'] ?? null,
			'phone'           => $identity['phone'] ?? null,
			'alternate_phone' => null,
			'address'         => null,
			'city'            => $identity['city'] ?? null,
			'state'           => $identity['state'] ?? null,
			'country'         => $identity['country'] ?? 'India',
			'campaign'        => $settings['campaign_default'] ?: null,
			'donations'       => $donations,
			'source'          => $source,
		);
	}

	/** Random NGOBuddy tokens look like long alphanumeric strings with no spaces. */
	public static function looks_like_token_name( string $name ): bool {
		$name = trim( $name );
		if ( $name === '' ) {
			return true;
		}
		if ( preg_match( '/\s/u', $name ) ) {
			return false;
		}
		// e.g. mUgEIGPXqrBilbsRRlCEFPn
		return (bool) preg_match( '/^[A-Za-z0-9_-]{16,}$/', $name );
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
			'donation_id'         => $donation_id,
			'amount'              => (float) ( $d['amount'] ?? 0 ),
			'currency'            => (string) ( $d['currency'] ?? 'INR' ),
			'donated_at'          => (string) ( $d['created_at'] ?? current_time( 'mysql' ) ),
			'payment_status'      => $status,
			'payment_method'      => 'Razorpay',
			'razorpay_payment_id' => $payment_id ?: null,
			'razorpay_order_id'   => $order_id ?: null,
			'donor_name'          => $d['donor_name'] ?? null,
			'donor_email'         => $d['donor_email'] ?? null,
			'donor_phone'         => $d['donor_phone'] ?? null,
			'campaign'            => $d['utm_campaign'] ?: ( $settings['campaign_default'] ?: null ),
			'project_id'          => $d['project_id'] ?? null,
			'receipt_number'      => $d['receipt_number'] ?? null,
			'is_recurring'        => ! empty( $d['is_recurring'] ),
			'source_data'         => array(
				'utm_source'   => $d['utm_source'] ?? null,
				'utm_medium'   => $d['utm_medium'] ?? null,
				'utm_campaign' => $d['utm_campaign'] ?? null,
				'landing_url'  => $d['landing_url'] ?? null,
				'donor_name'   => $d['donor_name'] ?? null,
				'donor_email'  => $d['donor_email'] ?? null,
				'donor_phone'  => $d['donor_phone'] ?? null,
			),
		);
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		$like  = $wpdb->esc_like( $table );
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
