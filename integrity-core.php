<?php
/**
 * Plugin Name: Integrity Core — Commerce Assurance
 * Description: Read-only assurance for WooCommerce operations performed through the WordPress Abilities API, with a privacy-conscious activity log and integrity checks.
 * Version: 0.1.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: integrity-core
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'IC_CORE_VERSION', '0.1.0' );
define( 'IC_CORE_FILE', __FILE__ );
define( 'IC_CORE_PATH', plugin_dir_path( __FILE__ ) );

require_once IC_CORE_PATH . 'includes/class-audit-log.php';
require_once IC_CORE_PATH . 'includes/class-monitor.php';
require_once IC_CORE_PATH . 'includes/class-scanner.php';
require_once IC_CORE_PATH . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'IntegrityCore\\Audit_Log', 'install' ) );
register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'ic_core_daily_maintenance' );
	}
);

add_action(
	'plugins_loaded',
	static function () {
		$audit_log = new IntegrityCore\Audit_Log();
		$monitor   = new IntegrityCore\Monitor( $audit_log );
		$scanner   = new IntegrityCore\Scanner( $audit_log );
		$admin     = new IntegrityCore\Admin( $audit_log, $scanner );

		$monitor->register();
		$admin->register();
	},
	20
);
