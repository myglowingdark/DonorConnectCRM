<?php
/**
 * Plugin Name: DonorConnect Bridge
 * Plugin URI:  https://donorconnect.app
 * Description: Securely sync NGOBuddy donors and Razorpay ledger data to DonorConnect CRM for each organization tenant.
 * Version:     1.2.0
 * Author:      DonorConnect
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: donorconnect-bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DC_BRIDGE_VERSION', '1.2.0' );
define( 'DC_BRIDGE_FILE', __FILE__ );
define( 'DC_BRIDGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'DC_BRIDGE_URL', plugin_dir_url( __FILE__ ) );

require_once DC_BRIDGE_PATH . 'includes/class-dc-auth.php';
require_once DC_BRIDGE_PATH . 'includes/class-dc-source.php';
require_once DC_BRIDGE_PATH . 'includes/class-dc-razorpay.php';
require_once DC_BRIDGE_PATH . 'includes/class-dc-rest-api.php';
require_once DC_BRIDGE_PATH . 'includes/class-dc-pusher.php';
require_once DC_BRIDGE_PATH . 'includes/class-dc-admin.php';
require_once DC_BRIDGE_PATH . 'includes/class-dc-tracking.php';
require_once DC_BRIDGE_PATH . 'includes/class-dc-plugin.php';

register_activation_hook( __FILE__, array( 'DC_Bridge_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DC_Bridge_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', static function () {
	DC_Bridge_Plugin::instance()->boot();
} );
