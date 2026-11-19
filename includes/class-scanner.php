<?php
namespace IntegrityCore;

defined( 'ABSPATH' ) || exit;

/** Read-only store and agent-integrity checks. */
class Scanner {
	private $audit_log;

	public function __construct( Audit_Log $audit_log ) {
		$this->audit_log = $audit_log;
	}

	public function run() {
		$checks = array();
		$checks[] = $this->check_https();
		$checks[] = $this->check_woocommerce();
		$checks[] = $this->check_abilities_api();
		$checks[] = $this->check_mcp_adapter();
		$checks[] = $this->check_agent_abilities();
		$checks[] = $this->check_admin_application_passwords();

		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' ) ) {
			$checks[] = $this->check_order_integrity();
		}

		$report = array(
			'generated_at_gmt' => gmdate( 'c' ),
			'checks'           => $checks,
			'events_7_days'    => $this->audit_log->count_since( 7 ),
		);

		update_option( 'ic_core_last_scan', $report, false );
		return $report;
	}

	private function check_https() {
		$is_https = is_ssl() || 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
		return $this->check(
			'https',
			'HTTPS',
			$is_https ? 'ok' : 'critical',
			$is_https ? 'The site is using HTTPS.' : 'The site does not appear to use HTTPS. Do not send credentials over remote connections without HTTPS.',
			array()
		);
	}

	private function check_woocommerce() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return $this->check( 'woocommerce', 'WooCommerce', 'critical', 'WooCommerce is not active. Commerce checks cannot run.', array() );
		}
		$version = defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown';
		return $this->check( 'woocommerce', 'WooCommerce', 'ok', 'WooCommerce is active (version ' . sanitize_text_field( $version ) . ').', array() );
	}

	private function check_abilities_api() {
		if ( function_exists( 'wp_get_abilities' ) ) {
			return $this->check( 'abilities_api', 'WordPress Abilities API', 'ok', 'The Abilities API is available. The plugin can inspect registered abilities.', array() );
		}
		return $this->check( 'abilities_api', 'WordPress Abilities API', 'warning', 'The Abilities API is not available. It is included in WordPress 6.9 and later.', array() );
	}

	private function check_mcp_adapter() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
		$all_active     = array_merge( $active_plugins, array_keys( $network_active ) );
		$found          = false;

		foreach ( $all_active as $plugin_file ) {
			if ( false !== stripos( $plugin_file, 'mcp-adapter' ) ) {
				$found = true;
				break;
			}
		}

		return $this->check(
			'mcp_adapter',
			'WordPress MCP Adapter',
			$found ? 'ok' : 'info',
			$found ? 'The MCP Adapter plugin appears to be active.' : 'MCP Adapter was not detected. This is expected when no MCP agent connects to this store.',
			array()
		);
	}

	private function check_agent_abilities() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return $this->check( 'agent_abilities', 'Abilities exposed to agents', 'info', 'Analysis is unavailable without the Abilities API.', array() );
		}

		$abilities       = wp_get_abilities();
		$exposed_count   = 0;
		$review_count    = 0;
		$unknown_count   = 0;
		$review_examples = array();

		foreach ( $abilities as $ability ) {
			if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_meta' ) ) {
				continue;
			}

			$meta = $ability->get_meta();
			if ( ! is_array( $meta ) ) {
				$meta = array();
			}
			$mcp_public = isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) && array_key_exists( 'public', $meta['mcp'] )
				? true === $meta['mcp']['public']
				: ! empty( $meta['public'] );
			$rest = ! empty( $meta['show_in_rest'] );
			if ( ! $mcp_public && ! $rest ) {
				continue;
			}

			++$exposed_count;
			$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();
			$readonly    = array_key_exists( 'readonly', $annotations ) ? (bool) $annotations['readonly'] : null;
			$destructive = array_key_exists( 'destructive', $annotations ) ? (bool) $annotations['destructive'] : null;

			if ( true === $destructive || false === $readonly ) {
				++$review_count;
				if ( count( $review_examples ) < 5 ) {
					$review_examples[] = sanitize_text_field( $ability->get_name() );
				}
			} elseif ( null === $readonly && null === $destructive ) {
				++$unknown_count;
			}
		}

		if ( $review_count > 0 ) {
			$status  = 'warning';
			$message = sprintf( '%d exposed ability/abilities declare write access or destructive behavior. Review their permissions and approval rules.', $review_count );
		} elseif ( $unknown_count > 0 ) {
			$status  = 'warning';
			$message = sprintf( '%d exposed ability/abilities do not clearly declare read-only behavior. Review their annotations.', $unknown_count );
		} else {
			$status  = 'ok';
			$message = 'No exposed ability was identified as modifying the environment based on its annotations. This does not replace a permissions test.';
		}

		$details = array(
			'Exposed abilities: ' . $exposed_count,
			'Write/destructive annotations: ' . $review_count,
			'Unknown read-only annotations: ' . $unknown_count,
		);
		if ( ! empty( $review_examples ) ) {
			$details[] = 'Review: ' . implode( ', ', $review_examples );
		}

		return $this->check( 'agent_abilities', 'Abilities accessible via REST/MCP', $status, $message, $details );
	}

	private function check_admin_application_passwords() {
		$admins = get_users( array( 'role' => 'administrator', 'fields' => array( 'ID' ) ) );
		$count  = 0;

		foreach ( $admins as $admin ) {
			$passwords = get_user_meta( $admin->ID, '_application_passwords', true );
			if ( is_array( $passwords ) && ! empty( $passwords ) ) {
				$count += count( $passwords );
			}
		}

		if ( $count > 0 ) {
			return $this->check(
				'admin_app_passwords',
				'Administrator application passwords',
				'warning',
				sprintf( '%d application password(s) are associated with administrator accounts. Use a dedicated least-privilege account for each integration.', $count ),
				array()
			);
		}

		return $this->check( 'admin_app_passwords', 'Administrator application passwords', 'ok', 'No application passwords were found on administrator accounts.', array() );
	}

	private function check_order_integrity() {
		$orders = wc_get_orders(
			array(
				'limit'   => 200,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
			)
		);

		$transaction_ids = array();
		$issues          = array();
		$checked         = 0;

		foreach ( $orders as $order ) {
			if ( ! is_a( $order, 'WC_Order' ) ) {
				continue;
			}
			++$checked;
			$order_id       = $order->get_id();
			$transaction_id = trim( (string) $order->get_transaction_id() );

			if ( '' !== $transaction_id ) {
				$gateway_id  = (string) $order->get_payment_method();
				$fingerprint = Audit_Log::fingerprint( $gateway_id . '|' . $transaction_id );
				if ( ! isset( $transaction_ids[ $fingerprint ] ) ) {
					$transaction_ids[ $fingerprint ] = array();
				}
				$transaction_ids[ $fingerprint ][] = $order_id;
			}

			$total    = (float) $order->get_total();
			$refunded = (float) $order->get_total_refunded();
			if ( $refunded > ( $total + 0.01 ) ) {
				$issues[] = sprintf( 'Order #%d: recorded refunds exceed the order total.', $order_id );
			}

			$snapshot = $order->get_meta( '_ic_core_payment_snapshot', true );
			if ( is_array( $snapshot ) && $order->is_paid() ) {
				$amount_changed      = isset( $snapshot['amount'] ) && abs( (float) $snapshot['amount'] - $total ) > 0.01;
				$currency_changed    = isset( $snapshot['currency'] ) && strtoupper( (string) $snapshot['currency'] ) !== strtoupper( (string) $order->get_currency() );
				$transaction_changed = false;
				if ( isset( $snapshot['transaction_fingerprint'] ) && '' !== $snapshot['transaction_fingerprint'] && '' !== $transaction_id ) {
					$current_fingerprint = Audit_Log::fingerprint( $order->get_payment_method() . '|' . $transaction_id );
					$transaction_changed = ! hash_equals( $snapshot['transaction_fingerprint'], $current_fingerprint );
				}
				if ( $amount_changed || $currency_changed || $transaction_changed ) {
					$issues[] = sprintf( 'Order #%d: payment details differ from the snapshot captured at payment time.', $order_id );
				}
			}
		}

		$duplicate_examples = array();
		foreach ( $transaction_ids as $order_ids ) {
			if ( count( $order_ids ) > 1 ) {
				$duplicate_examples[] = 'Orders #' . implode( ', #', array_slice( $order_ids, 0, 5 ) );
			}
		}

		if ( ! empty( $duplicate_examples ) ) {
			$issues[] = 'Transaction identifiers reused: ' . implode( '; ', array_slice( $duplicate_examples, 0, 5 ) );
		}

		if ( ! empty( $issues ) ) {
			return $this->check(
				'order_integrity',
				'Recent order integrity',
				'warning',
				sprintf( '%d issue(s) to review across %d scanned orders. This scan is advisory and does not by itself prove fraud.', count( $issues ), $checked ),
				array_slice( $issues, 0, 12 )
			);
		}

		return $this->check( 'order_integrity', 'Recent order integrity', 'ok', sprintf( 'No inconsistencies were detected by the available checks across %d recent orders.', $checked ), array( 'Sample limited to the 200 most recent orders.' ) );
	}

	private function check( $id, $title, $status, $message, $details ) {
		return array(
			'id'      => sanitize_key( $id ),
			'title'   => sanitize_text_field( $title ),
			'status'  => sanitize_key( $status ),
			'message' => sanitize_text_field( $message ),
			'details' => array_map( 'sanitize_text_field', (array) $details ),
		);
	}
}
