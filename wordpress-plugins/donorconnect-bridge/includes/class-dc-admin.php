<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DC_Bridge_Admin {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		add_menu_page(
			__( 'DonorConnect', 'donorconnect-bridge' ),
			__( 'DonorConnect', 'donorconnect-bridge' ),
			'manage_options',
			'donorconnect-bridge',
			array( $this, 'render' ),
			'dashicons-share-alt2',
			58
		);
	}

	public function assets( string $hook ): void {
		if ( $hook !== 'toplevel_page_donorconnect-bridge' ) {
			return;
		}
		wp_enqueue_style( 'dc-bridge-admin', DC_BRIDGE_URL . 'assets/admin.css', array(), DC_BRIDGE_VERSION );
		wp_enqueue_script( 'dc-bridge-admin', DC_BRIDGE_URL . 'assets/admin.js', array( 'jquery' ), DC_BRIDGE_VERSION, true );
		wp_localize_script(
			'dc-bridge-admin',
			'dcBridge',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'dc_bridge_admin' ),
			)
		);
	}

	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_POST['dc_bridge_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		check_admin_referer( 'dc_bridge_save' );

		$action = sanitize_key( wp_unslash( (string) $_POST['dc_bridge_action'] ) );

		if ( $action === 'save' ) {
			$patch = array(
				'crm_base_url'     => esc_url_raw( wp_unslash( (string) ( $_POST['crm_base_url'] ?? '' ) ) ),
				'crm_org_token'    => sanitize_text_field( wp_unslash( (string) ( $_POST['crm_org_token'] ?? '' ) ) ),
				'push_enabled'     => ! empty( $_POST['push_enabled'] ),
				'require_hmac'     => ! empty( $_POST['require_hmac'] ),
				'max_clock_skew'   => max( 60, (int) ( $_POST['max_clock_skew'] ?? 300 ) ),
				'allowed_ips'      => sanitize_textarea_field( wp_unslash( (string) ( $_POST['allowed_ips'] ?? '' ) ) ),
				'campaign_default' => sanitize_text_field( wp_unslash( (string) ( $_POST['campaign_default'] ?? '' ) ) ),
				'include_pending'  => ! empty( $_POST['include_pending'] ),
			);
			// Keep existing token if blank.
			if ( $patch['crm_org_token'] === '' ) {
				unset( $patch['crm_org_token'] );
			}
			DC_Bridge_Plugin::update_settings( $patch );
			add_settings_error( 'dc_bridge', 'saved', __( 'Settings saved.', 'donorconnect-bridge' ), 'updated' );
		}

		if ( $action === 'rotate_secrets' ) {
			DC_Bridge_Plugin::update_settings(
				array(
					'api_key'           => DC_Bridge_Plugin::random_token( 48 ),
					'hmac_secret'       => DC_Bridge_Plugin::random_token( 64 ),
					'credentials_shown' => false,
				)
			);
			add_settings_error( 'dc_bridge', 'rotated', __( 'API key and HMAC secret rotated. Update DonorConnect CRM Sync settings.', 'donorconnect-bridge' ), 'updated' );
		}

		if ( $action === 'reveal' ) {
			DC_Bridge_Plugin::update_settings( array( 'credentials_shown' => true ) );
		}

		if ( $action === 'pair' ) {
			$crm_url       = esc_url_raw( wp_unslash( (string) ( $_POST['pair_crm_url'] ?? '' ) ) );
			$pairing_code  = sanitize_text_field( wp_unslash( (string) ( $_POST['pairing_code'] ?? '' ) ) );
			$result        = self::pair_with_crm( $crm_url, $pairing_code );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'dc_bridge', 'pair_failed', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'dc_bridge', 'paired', $result, 'updated' );
			}
		}
	}

	/**
	 * @return string|WP_Error Success notice text.
	 */
	private static function pair_with_crm( string $crm_url, string $pairing_code ) {
		$crm_url      = rtrim( $crm_url, '/' );
		$pairing_code = trim( $pairing_code );

		if ( $crm_url === '' || $pairing_code === '' ) {
			return new WP_Error( 'dc_pair_missing', __( 'CRM URL and pairing code are required.', 'donorconnect-bridge' ) );
		}

		if ( ! str_starts_with( $pairing_code, 'dc_pair_' ) ) {
			return new WP_Error( 'dc_pair_invalid', __( 'Pairing code must start with dc_pair_. Generate a fresh code in DonorConnect CRM.', 'donorconnect-bridge' ) );
		}

		$settings = DC_Bridge_Plugin::settings();
		$endpoint = self::pair_endpoint_url( $crm_url );

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $pairing_code,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'site_id'       => (string) $settings['site_id'],
						'api_key'       => (string) $settings['api_key'],
						'hmac_secret'   => (string) $settings['hmac_secret'],
						'rest_base_url' => rest_url( 'donorconnect/v1' ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$msg    = is_array( $body ) ? (string) ( $body['message'] ?? '' ) : '';

		if ( $status >= 400 ) {
			return new WP_Error(
				'dc_pair_http',
				$msg !== '' ? $msg : sprintf(
					/* translators: %d: HTTP status code */
					__( 'Pairing failed (HTTP %d). Check CRM URL and that the code has not expired.', 'donorconnect-bridge' ),
					$status
				)
			);
		}

		$patch = array(
			'crm_base_url' => self::crm_base_from_url( $crm_url ),
		);

		if ( is_array( $body ) && ! empty( $body['push_token'] ) ) {
			$patch['crm_org_token'] = sanitize_text_field( (string) $body['push_token'] );
		}

		DC_Bridge_Plugin::update_settings( $patch );

		return __( 'Paired with DonorConnect. Return to CRM and click Test connection.', 'donorconnect-bridge' );
	}

	private static function pair_endpoint_url( string $crm_url ): string {
		if ( str_ends_with( $crm_url, '/api/v1' ) ) {
			return $crm_url . '/bridge/pair';
		}
		if ( str_ends_with( $crm_url, '/api' ) ) {
			return $crm_url . '/v1/bridge/pair';
		}

		return $crm_url . '/api/v1/bridge/pair';
	}

	private static function crm_base_from_url( string $crm_url ): string {
		if ( str_ends_with( $crm_url, '/api/v1' ) ) {
			return substr( $crm_url, 0, -strlen( '/api/v1' ) );
		}
		if ( str_ends_with( $crm_url, '/api' ) ) {
			return substr( $crm_url, 0, -strlen( '/api' ) );
		}

		return $crm_url;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = DC_Bridge_Plugin::settings();
		$health   = DC_Bridge_Source::health();
		$endpoint = rest_url( 'donorconnect/v1' );
		?>
		<div class="wrap dc-bridge-wrap">
			<h1><?php esc_html_e( 'DonorConnect Bridge', 'donorconnect-bridge' ); ?></h1>
			<?php settings_errors( 'dc_bridge' ); ?>

			<div class="dc-grid">
				<div class="dc-card">
					<h2><?php esc_html_e( 'Pair with DonorConnect CRM', 'donorconnect-bridge' ); ?></h2>
					<p><?php esc_html_e( 'In DonorConnect → Organization → WordPress site, click Generate pairing code, then paste the CRM URL and code here (one use, 15 minutes).', 'donorconnect-bridge' ); ?></p>
					<form method="post">
						<?php wp_nonce_field( 'dc_bridge_save' ); ?>
						<input type="hidden" name="dc_bridge_action" value="pair" />
						<div class="dc-fields">
							<div class="dc-field">
								<label for="pair_crm_url"><?php esc_html_e( 'CRM URL', 'donorconnect-bridge' ); ?></label>
								<input type="url" name="pair_crm_url" id="pair_crm_url" value="<?php echo esc_attr( (string) $settings['crm_base_url'] ); ?>" placeholder="https://donorconnect.example.com" required />
								<p class="description"><?php esc_html_e( 'Your DonorConnect site URL (not the WordPress REST URL).', 'donorconnect-bridge' ); ?></p>
							</div>
							<div class="dc-field">
								<label for="pairing_code"><?php esc_html_e( 'Pairing code', 'donorconnect-bridge' ); ?></label>
								<input type="text" class="code" name="pairing_code" id="pairing_code" autocomplete="off" spellcheck="false" placeholder="dc_pair_…" required />
							</div>
						</div>
						<p class="dc-actions">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Pair with DonorConnect', 'donorconnect-bridge' ); ?></button>
						</p>
					</form>
				</div>

				<div class="dc-card">
					<h2><?php esc_html_e( 'Secure link credentials', 'donorconnect-bridge' ); ?></h2>
					<p><?php esc_html_e( 'Manual fallback: paste these into DonorConnect CRM → WordPress site if you are not using pairing.', 'donorconnect-bridge' ); ?></p>
					<ul class="dc-cred-list">
						<li>
							<span class="dc-cred-label"><?php esc_html_e( 'Site ID', 'donorconnect-bridge' ); ?></span>
							<code class="dc-cred-value"><?php echo esc_html( (string) $settings['site_id'] ); ?></code>
						</li>
						<li>
							<span class="dc-cred-label"><?php esc_html_e( 'REST base URL', 'donorconnect-bridge' ); ?></span>
							<code class="dc-cred-value"><?php echo esc_html( $endpoint ); ?></code>
						</li>
						<li>
							<span class="dc-cred-label"><?php esc_html_e( 'API Key', 'donorconnect-bridge' ); ?></span>
							<?php if ( ! empty( $settings['credentials_shown'] ) ) : ?>
								<code class="dc-cred-value dc-secret"><?php echo esc_html( (string) $settings['api_key'] ); ?></code>
							<?php else : ?>
								<code class="dc-cred-value">••••••••••••••••</code>
							<?php endif; ?>
						</li>
						<li>
							<span class="dc-cred-label"><?php esc_html_e( 'HMAC Secret', 'donorconnect-bridge' ); ?></span>
							<?php if ( ! empty( $settings['credentials_shown'] ) ) : ?>
								<code class="dc-cred-value dc-secret"><?php echo esc_html( (string) $settings['hmac_secret'] ); ?></code>
							<?php else : ?>
								<code class="dc-cred-value">••••••••••••••••</code>
							<?php endif; ?>
						</li>
					</ul>
					<form method="post">
						<?php wp_nonce_field( 'dc_bridge_save' ); ?>
						<p class="dc-actions">
							<button class="button" name="dc_bridge_action" value="reveal" type="submit"><?php esc_html_e( 'Reveal secrets', 'donorconnect-bridge' ); ?></button>
							<button class="button button-secondary" name="dc_bridge_action" value="rotate_secrets" type="submit" onclick="return confirm('Rotate secrets? CRM sync will break until credentials are updated.');"><?php esc_html_e( 'Rotate secrets', 'donorconnect-bridge' ); ?></button>
						</p>
					</form>
					<p class="description">
						<?php esc_html_e( 'CRM auth type: HMAC (DonorConnect Bridge). Endpoints path: /donors. Header API key uses X-DC-API-Key; requests must also send signed HMAC headers.', 'donorconnect-bridge' ); ?>
					</p>
				</div>

				<div class="dc-card">
					<h2><?php esc_html_e( 'Source status', 'donorconnect-bridge' ); ?></h2>
					<ul class="dc-status-list">
						<li>NGOBuddy: <?php echo $health['ngobuddy_active'] ? '✅' : '⚠️ not detected'; ?></li>
						<li>Donors table: <?php echo $health['donors_table'] ? '✅' : '❌'; ?></li>
						<li>Donations / Razorpay table: <?php echo $health['donations_table'] ? '✅' : '❌'; ?></li>
						<li>Razorpay keys: <?php echo ! empty( $health['razorpay']['configured'] ) ? '✅ ' . esc_html( (string) ( $health['razorpay']['key_id_masked'] ?? '' ) ) : '❌ not set in NGOBuddy'; ?></li>
						<li>HMAC required: <?php echo $health['require_hmac'] ? 'yes' : 'no'; ?></li>
					</ul>
				</div>
			</div>

			<form method="post" class="dc-card">
				<?php wp_nonce_field( 'dc_bridge_save' ); ?>
				<input type="hidden" name="dc_bridge_action" value="save" />
				<h2><?php esc_html_e( 'Sync settings', 'donorconnect-bridge' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="require_hmac"><?php esc_html_e( 'Require HMAC signatures', 'donorconnect-bridge' ); ?></label></th>
						<td><label><input type="checkbox" name="require_hmac" id="require_hmac" value="1" <?php checked( ! empty( $settings['require_hmac'] ) ); ?> /> <?php esc_html_e( 'Recommended for production', 'donorconnect-bridge' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="max_clock_skew"><?php esc_html_e( 'Max clock skew (seconds)', 'donorconnect-bridge' ); ?></label></th>
						<td><input type="number" min="60" name="max_clock_skew" id="max_clock_skew" value="<?php echo esc_attr( (string) $settings['max_clock_skew'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="allowed_ips"><?php esc_html_e( 'CRM IP allowlist', 'donorconnect-bridge' ); ?></label></th>
						<td>
							<textarea name="allowed_ips" id="allowed_ips" rows="3" class="large-text" placeholder="203.0.113.10, 198.51.100.0/24"><?php echo esc_textarea( (string) $settings['allowed_ips'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Optional. Comma/space separated IPs or CIDRs that may pull data.', 'donorconnect-bridge' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="campaign_default"><?php esc_html_e( 'Default campaign tag', 'donorconnect-bridge' ); ?></label></th>
						<td><input type="text" class="regular-text" name="campaign_default" id="campaign_default" value="<?php echo esc_attr( (string) $settings['campaign_default'] ); ?>" placeholder="website-donations" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Include pending payments', 'donorconnect-bridge' ); ?></th>
						<td><label><input type="checkbox" name="include_pending" value="1" <?php checked( ! empty( $settings['include_pending'] ) ); ?> /> <?php esc_html_e( 'Also sync unpaid/pending Razorpay rows', 'donorconnect-bridge' ); ?></label></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Optional push to CRM', 'donorconnect-bridge' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Usually CRM pulls from this site. Enable push to send on an hourly cron as well.', 'donorconnect-bridge' ); ?></p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable push', 'donorconnect-bridge' ); ?></th>
						<td><label><input type="checkbox" name="push_enabled" value="1" <?php checked( ! empty( $settings['push_enabled'] ) ); ?> /> <?php esc_html_e( 'Hourly push to CRM ingest API', 'donorconnect-bridge' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="crm_base_url"><?php esc_html_e( 'CRM base URL', 'donorconnect-bridge' ); ?></label></th>
						<td><input type="url" class="regular-text" name="crm_base_url" id="crm_base_url" value="<?php echo esc_attr( (string) $settings['crm_base_url'] ); ?>" placeholder="https://crm.example.com" /></td>
					</tr>
					<tr>
						<th><label for="crm_org_token"><?php esc_html_e( 'CRM org API token', 'donorconnect-bridge' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" name="crm_org_token" id="crm_org_token" value="" autocomplete="new-password" placeholder="<?php echo ! empty( $settings['crm_org_token'] ) ? esc_attr__( 'Leave blank to keep existing', 'donorconnect-bridge' ) : ''; ?>" />
							<p class="description"><?php esc_html_e( 'Create in CRM → Platform → API keys (Growth/Enterprise).', 'donorconnect-bridge' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Last push', 'donorconnect-bridge' ); ?></th>
						<td><?php echo esc_html( (string) ( $settings['last_push_at'] ?: '—' ) ); ?> · <?php echo esc_html( (string) ( $settings['last_push_status'] ?: '—' ) ); ?></td>
					</tr>
				</table>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'donorconnect-bridge' ); ?></button>
					<button type="button" class="button" id="dc-bridge-push-now"><?php esc_html_e( 'Push now', 'donorconnect-bridge' ); ?></button>
				</p>
				<pre id="dc-bridge-push-result" class="dc-result" hidden></pre>
			</form>
		</div>
		<?php
	}
}
