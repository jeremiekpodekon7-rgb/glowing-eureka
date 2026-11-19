<?php
/**
 * Plugin Name: INTEGRITY CORE — Agent Commerce Readiness
 * Plugin URI: https://github.com/jeremiekpodekon7-rgb/glowing-eureka
 * Description: Audits WooCommerce stores for agentic-commerce readiness, protocol discoverability, and product-data completeness. Diagnostic only; it never changes orders or payments.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: INTEGRITY CORE
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: integrity-core-agent-readiness
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Integrity_Core_Agent_Readiness {
	const VERSION = '0.1.0';
	const SLUG    = 'integrity-core-agent-readiness';
	const NONCE   = 'ic_agent_readiness_run_audit';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_ic_agent_readiness_run_audit', array( __CLASS__, 'handle_audit' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'plugin_action_links' ) );
	}

	public static function admin_menu() {
		$capability = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';

		if ( class_exists( 'WooCommerce' ) ) {
			add_submenu_page( 'woocommerce', __( 'INTEGRITY CORE', 'integrity-core-agent-readiness' ), __( 'INTEGRITY CORE', 'integrity-core-agent-readiness' ), $capability, self::SLUG, array( __CLASS__, 'render_page' ) );
		} else {
			add_management_page( __( 'INTEGRITY CORE', 'integrity-core-agent-readiness' ), __( 'INTEGRITY CORE', 'integrity-core-agent-readiness' ), $capability, self::SLUG, array( __CLASS__, 'render_page' ) );
		}
	}

	public static function plugin_action_links( $links ) {
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Run audit', 'integrity-core-agent-readiness' ) . '</a>';
		return $links;
	}

	public static function handle_audit() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run this audit.', 'integrity-core-agent-readiness' ) );
		}

		check_admin_referer( self::NONCE );
		set_transient( self::report_key(), self::run_audit(), 15 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'ic_report' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function report_key() {
		return 'ic_agent_readiness_' . get_current_user_id();
	}

	private static function run_audit() {
		$checks = array();

		$checks[] = self::check(
			'woocommerce',
			__( 'WooCommerce is active', 'integrity-core-agent-readiness' ),
			class_exists( 'WooCommerce' ) ? 'pass' : 'fail',
			class_exists( 'WooCommerce' ) ? sprintf( __( 'WooCommerce %s is active.', 'integrity-core-agent-readiness' ), defined( 'WC_VERSION' ) ? WC_VERSION : __( 'version unknown', 'integrity-core-agent-readiness' ) ) : __( 'Install and activate WooCommerce before running an agent-commerce audit.', 'integrity-core-agent-readiness' )
		);

		$https_ok = is_ssl() && 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
		$checks[] = self::check( 'https', __( 'Store uses HTTPS', 'integrity-core-agent-readiness' ), $https_ok ? 'pass' : 'warning', $https_ok ? __( 'The WordPress home URL is HTTPS.', 'integrity-core-agent-readiness' ) : __( 'The store does not appear to be served over HTTPS in this request. Confirm public storefront and reverse-proxy SSL configuration.', 'integrity-core-agent-readiness' ) );

		$pretty = (string) get_option( 'permalink_structure', '' );
		$checks[] = self::check( 'permalinks', __( 'Pretty permalinks are enabled', 'integrity-core-agent-readiness' ), '' !== $pretty ? 'pass' : 'warning', '' !== $pretty ? __( 'A non-default permalink structure is configured.', 'integrity-core-agent-readiness' ) : __( 'Default query-string permalinks may complicate discovery and integrations. Review Settings → Permalinks.', 'integrity-core-agent-readiness' ) );

		$abilities_available = function_exists( 'wp_register_ability' ) || function_exists( 'wp_get_abilities' );
		$checks[] = self::check( 'abilities_api', __( 'WordPress Abilities API is available', 'integrity-core-agent-readiness' ), $abilities_available ? 'pass' : 'info', $abilities_available ? __( 'Abilities API functions were detected. This does not prove that a specific store ability is exposed to an agent.', 'integrity-core-agent-readiness' ) : __( 'Abilities API functions were not detected. Compatibility depends on WordPress version and installed integrations.', 'integrity-core-agent-readiness' ) );

		$plugins     = self::installed_plugins();
		$mcp_active  = self::find_active_plugin_by_name( $plugins, 'MCP Adapter' );
		$woo_ai      = self::find_active_plugin_by_name( $plugins, 'WooCommerce AI' );
		$checks[]    = self::check( 'mcp_adapter', __( 'MCP integration component is detected', 'integrity-core-agent-readiness' ), $mcp_active ? 'pass' : 'info', $mcp_active ? __( 'A plugin named MCP Adapter is active. Confirm its endpoint authorization separately.', 'integrity-core-agent-readiness' ) : __( 'No active plugin named MCP Adapter was found. WooCommerce/hosted AI integrations may expose MCP differently; this is a diagnostic hint, not a failure verdict.', 'integrity-core-agent-readiness' ) );
		$checks[]    = self::check( 'woo_ai', __( 'WooCommerce AI integration is detected', 'integrity-core-agent-readiness' ), $woo_ai ? 'pass' : 'info', $woo_ai ? __( 'A plugin named WooCommerce AI is active. Complete official onboarding and payment-method prerequisites before enabling agent checkout.', 'integrity-core-agent-readiness' ) : __( 'WooCommerce AI was not detected by plugin name. Agentic-commerce availability depends on the current WooCommerce release and supported integration path.', 'integrity-core-agent-readiness' ) );

		$checks[] = self::check_wp_rest_api();
		$checks[] = self::check_ucp_discovery();

		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' ) ) {
			$checks = array_merge( $checks, self::check_catalog_quality() );
		} else {
			$checks[] = self::check( 'catalog', __( 'Product catalogue quality', 'integrity-core-agent-readiness' ), 'info', __( 'Skipped because WooCommerce is not active.', 'integrity-core-agent-readiness' ) );
		}

		$counts = array( 'pass' => 0, 'warning' => 0, 'fail' => 0, 'info' => 0 );
		foreach ( $checks as $item ) {
			$counts[ $item['status'] ]++;
		}

		return array(
			'generated_at' => current_time( 'mysql' ),
			'checks'       => $checks,
			'counts'       => $counts,
			'plugin'       => self::VERSION,
			'wp'           => get_bloginfo( 'version' ),
			'php'          => PHP_VERSION,
			'wc'           => defined( 'WC_VERSION' ) ? WC_VERSION : null,
		);
	}

	private static function check( $id, $title, $status, $detail ) {
		return array(
			'id'     => sanitize_key( $id ),
			'title'  => (string) $title,
			'status' => in_array( $status, array( 'pass', 'warning', 'fail', 'info' ), true ) ? $status : 'info',
			'detail' => (string) $detail,
		);
	}

	private static function installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return function_exists( 'get_plugins' ) ? get_plugins() : array();
	}

	private static function find_active_plugin_by_name( $plugins, $needle ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( $plugins as $file => $plugin ) {
			$name = isset( $plugin['Name'] ) ? wp_strip_all_tags( $plugin['Name'] ) : '';
			if ( 0 === stripos( $name, $needle ) ) {
				return function_exists( 'is_plugin_active' ) && is_plugin_active( $file );
			}
		}
		return false;
	}

	private static function check_wp_rest_api() {
		$response = wp_safe_remote_get( rest_url(), array( 'timeout' => 8, 'redirection' => 2, 'limit_response_size' => 65536 ) );
		if ( is_wp_error( $response ) ) {
			return self::check( 'rest_api', __( 'WordPress REST API responds', 'integrity-core-agent-readiness' ), 'warning', sprintf( __( 'The REST API index could not be fetched from this server: %s', 'integrity-core-agent-readiness' ), $response->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 === $code && is_array( $json ) && isset( $json['namespaces'] ) ) {
			return self::check( 'rest_api', __( 'WordPress REST API responds', 'integrity-core-agent-readiness' ), 'pass', sprintf( __( 'REST index returned HTTP 200 and lists %d namespace(s). This does not verify MCP authorization or commerce operations.', 'integrity-core-agent-readiness' ), count( $json['namespaces'] ) ) );
		}
		return self::check( 'rest_api', __( 'WordPress REST API responds', 'integrity-core-agent-readiness' ), 'warning', sprintf( __( 'REST index returned HTTP %d or an unexpected response. Check security plugins, CDN rules, and REST restrictions.', 'integrity-core-agent-readiness' ), $code ) );
	}

	private static function check_ucp_discovery() {
		$url      = home_url( '/.well-known/ucp' );
		$response = wp_safe_remote_get( $url, array( 'timeout' => 8, 'redirection' => 2, 'limit_response_size' => 65536, 'headers' => array( 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $response ) ) {
			return self::check( 'ucp_discovery', __( 'UCP discovery document is reachable', 'integrity-core-agent-readiness' ), 'info', sprintf( __( 'The plugin could not test the public discovery URL: %s. Some hosts block loopback requests; verify this URL from outside the server.', 'integrity-core-agent-readiness' ), $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 === $code && is_array( $data ) ) {
			return self::check( 'ucp_discovery', __( 'UCP discovery document is reachable', 'integrity-core-agent-readiness' ), 'pass', __( 'The conventional discovery URL returned HTTP 200 with JSON. Schema, signatures, services, checkout, and live payment behavior still require separate conformance tests.', 'integrity-core-agent-readiness' ) );
		}
		if ( 404 === $code ) {
			return self::check( 'ucp_discovery', __( 'UCP discovery document is reachable', 'integrity-core-agent-readiness' ), 'warning', __( 'No UCP discovery document was found at /.well-known/ucp. This may be expected if agentic commerce is not enabled or your provider uses another discovery path.', 'integrity-core-agent-readiness' ) );
		}
		return self::check( 'ucp_discovery', __( 'UCP discovery document is reachable', 'integrity-core-agent-readiness' ), 'warning', sprintf( __( 'The discovery URL returned HTTP %d or non-JSON content. Confirm the endpoint and any CDN/security rules.', 'integrity-core-agent-readiness' ), $code ) );
	}

	private static function check_catalog_quality() {
		$products = wc_get_products( array( 'status' => 'publish', 'limit' => 25, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ) );
		$page     = wc_get_products( array( 'status' => 'publish', 'limit' => 1, 'paginate' => true ) );
		$total    = is_object( $page ) && isset( $page->total ) ? (int) $page->total : count( $products );

		if ( empty( $products ) ) {
			return array( self::check( 'catalog_sample', __( 'Published products are available', 'integrity-core-agent-readiness' ), 'warning', __( 'No published products were found. Add or publish a product before testing agent discovery.', 'integrity-core-agent-readiness' ) ) );
		}

		$missing_description = 0;
		$missing_image       = 0;
		$missing_price       = 0;
		foreach ( $products as $product ) {
			if ( ! $product->get_description() && ! $product->get_short_description() ) {
				$missing_description++;
			}
			if ( ! $product->get_image_id() ) {
				$missing_image++;
			}
			if ( '' === $product->get_price() ) {
				$missing_price++;
			}
		}

		$sample_size = count( $products );
		return array(
			self::check( 'catalog_sample', __( 'Published products are available', 'integrity-core-agent-readiness' ), 'pass', sprintf( __( '%1$d published product(s) found; the latest %2$d were sampled.', 'integrity-core-agent-readiness' ), $total, $sample_size ) ),
			self::check( 'catalog_descriptions', __( 'Product descriptions are complete', 'integrity-core-agent-readiness' ), $missing_description ? 'warning' : 'pass', sprintf( __( '%1$d of %2$d sampled products have neither a short nor a long description.', 'integrity-core-agent-readiness' ), $missing_description, $sample_size ) ),
			self::check( 'catalog_images', __( 'Product images are present', 'integrity-core-agent-readiness' ), $missing_image ? 'warning' : 'pass', sprintf( __( '%1$d of %2$d sampled products have no featured image.', 'integrity-core-agent-readiness' ), $missing_image, $sample_size ) ),
			self::check( 'catalog_prices', __( 'Product prices are available', 'integrity-core-agent-readiness' ), $missing_price ? 'warning' : 'pass', sprintf( __( '%1$d of %2$d sampled products have no price set. Variable products and free products may need human review.', 'integrity-core-agent-readiness' ), $missing_price, $sample_size ) )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$report = ( isset( $_GET['ic_report'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['ic_report'] ) ) ) ? get_transient( self::report_key() ) : false;
		?>
		<div class="wrap ic-wrap">
			<h1>INTEGRITY CORE <span class="ic-version">v<?php echo esc_html( self::VERSION ); ?></span></h1>
			<p class="ic-subtitle"><?php esc_html_e( 'Agent Commerce Readiness Audit for WooCommerce', 'integrity-core-agent-readiness' ); ?></p>
			<div class="ic-notice">
				<strong><?php esc_html_e( 'Read-only diagnostic mode', 'integrity-core-agent-readiness' ); ?></strong>
				<p><?php esc_html_e( 'This alpha checks configuration, public endpoints, and a small sample of published product data. It does not read customer orders, collect payment credentials, change products, retry payments, or send store data to an external service.', 'integrity-core-agent-readiness' ); ?></p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ic_agent_readiness_run_audit" />
				<?php wp_nonce_field( self::NONCE ); ?>
				<?php submit_button( __( 'Run readiness audit', 'integrity-core-agent-readiness' ), 'primary', 'submit', false ); ?>
			</form>
			<?php if ( is_array( $report ) && ! empty( $report['checks'] ) ) : ?>
				<h2><?php esc_html_e( 'Latest report', 'integrity-core-agent-readiness' ); ?></h2>
				<p class="ic-meta"><?php echo esc_html( sprintf( __( 'Generated %1$s · WordPress %2$s · PHP %3$s · WooCommerce %4$s', 'integrity-core-agent-readiness' ), $report['generated_at'], $report['wp'], $report['php'], $report['wc'] ? $report['wc'] : __( 'not detected', 'integrity-core-agent-readiness' ) ) ); ?></p>
				<div class="ic-summary">
					<?php foreach ( array( 'pass' => __( 'Passed', 'integrity-core-agent-readiness' ), 'warning' => __( 'Warnings', 'integrity-core-agent-readiness' ), 'fail' => __( 'Blockers', 'integrity-core-agent-readiness' ), 'info' => __( 'Information', 'integrity-core-agent-readiness' ) ) as $key => $label ) : ?>
						<div class="ic-stat ic-<?php echo esc_attr( $key ); ?>"><span><?php echo esc_html( $report['counts'][ $key ] ); ?></span><small><?php echo esc_html( $label ); ?></small></div>
					<?php endforeach; ?>
				</div>
				<table class="widefat striped ic-results"><thead><tr><th><?php esc_html_e( 'Check', 'integrity-core-agent-readiness' ); ?></th><th><?php esc_html_e( 'Status', 'integrity-core-agent-readiness' ); ?></th><th><?php esc_html_e( 'Details', 'integrity-core-agent-readiness' ); ?></th></tr></thead><tbody>
				<?php foreach ( $report['checks'] as $item ) : ?>
					<tr><td><strong><?php echo esc_html( $item['title'] ); ?></strong></td><td><span class="ic-pill ic-<?php echo esc_attr( $item['status'] ); ?>"><?php echo esc_html( strtoupper( $item['status'] ) ); ?></span></td><td><?php echo esc_html( $item['detail'] ); ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
				<p class="ic-footnote"><?php esc_html_e( 'A passing endpoint check is not proof of protocol conformance, agent acceptance, payment authorization, or a successful end-to-end purchase. Run external conformance and sandbox checkout tests before enabling live agent checkout.', 'integrity-core-agent-readiness' ); ?></p>
			<?php else : ?>
				<div class="ic-empty"><h2><?php esc_html_e( 'Your first readiness report', 'integrity-core-agent-readiness' ); ?></h2><p><?php esc_html_e( 'Run the audit to inspect the store public integration surface and a limited sample of product data. No changes will be made.', 'integrity-core-agent-readiness' ); ?></p></div>
			<?php endif; ?>
			<p class="ic-footer">INTEGRITY CORE · <?php esc_html_e( 'Early access build. Protocol coverage will expand as official specifications stabilize.', 'integrity-core-agent-readiness' ); ?></p>
		</div>
		<style>
			.ic-wrap{max-width:1120px}.ic-version{font-size:12px;background:#e8edf5;color:#26364d;padding:4px 8px;border-radius:20px;vertical-align:middle}.ic-subtitle{color:#566477;font-size:14px;margin-top:4px}.ic-notice,.ic-empty{background:#fff;border:1px solid #dce3ec;border-left:4px solid #4263a5;border-radius:8px;padding:16px 18px;margin:20px 0}.ic-notice p,.ic-empty p{margin-bottom:0}.ic-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.ic-stat{background:#fff;border:1px solid #dce3ec;border-radius:8px;padding:14px}.ic-stat span{display:block;font-size:25px;font-weight:700}.ic-stat small{color:#566477}.ic-pass span{color:#16804a}.ic-warning span{color:#9a6700}.ic-fail span{color:#c0392b}.ic-info span{color:#4263a5}.ic-pill{font-size:11px;font-weight:700;letter-spacing:.04em;padding:4px 8px;border-radius:20px;background:#edf1f5;color:#39485c}.ic-pill.ic-pass{background:#e5f5ec;color:#126b3e}.ic-pill.ic-warning{background:#fff3d6;color:#815600}.ic-pill.ic-fail{background:#fde8e7;color:#a52b25}.ic-pill.ic-info{background:#e9efff;color:#294d9b}.ic-results td{vertical-align:top;padding:12px}.ic-meta,.ic-footnote,.ic-footer{color:#68778b}.ic-footnote{margin-top:14px}.ic-footer{margin-top:26px;font-size:12px}@media(max-width:782px){.ic-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.ic-results td{padding:9px}}
		</style>
		<?php
	}
}

Integrity_Core_Agent_Readiness::init();
