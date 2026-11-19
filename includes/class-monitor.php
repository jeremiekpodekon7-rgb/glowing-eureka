<?php
namespace IntegrityCore;

defined( 'ABSPATH' ) || exit;

/** Observes WordPress Abilities and selected WooCommerce lifecycle events without changing store data. */
class Monitor {
	private $audit_log;

	public function __construct( Audit_Log $audit_log ) {
		$this->audit_log = $audit_log;
	}

	public function register() {
		// The Abilities API lifecycle hooks are available from WordPress 6.9.
		add_action( 'wp_before_execute_ability', array( $this, 'record_ability_start' ), 10, 2 );
		add_action( 'wp_after_execute_ability', array( $this, 'record_ability_result' ), 10, 4 );
		add_action( 'woocommerce_payment_complete', array( $this, 'capture_payment_snapshot' ), 10, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'record_order_status_change' ), 10, 4 );
		add_action( 'woocommerce_order_refunded', array( $this, 'record_refund' ), 10, 2 );
		add_action( 'woocommerce_update_product', array( $this, 'record_product_update' ), 10, 1 );

		if ( ! wp_next_scheduled( 'ic_core_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ic_core_daily_maintenance' );
		}
		add_action( 'ic_core_daily_maintenance', array( $this->audit_log, 'prune' ) );
	}

	public function record_ability_start( $ability_name, $input = null ) {
		if ( ! $this->is_trackable_ability( $ability_name ) ) {
			return;
		}

		$this->audit_log->log(
			array(
				'event_type'   => 'ability_started',
				'object_type'  => 'ability',
				'ability_name' => $ability_name,
				'result'       => 'permission_passed',
			)
		);
	}

	/** The after hook fires only after successful execution and output validation. */
	public function record_ability_result( $ability_name, $input, $result, $ability = null ) {
		if ( ! $this->is_trackable_ability( $ability_name, $ability ) ) {
			return;
		}

		$this->audit_log->log(
			array(
				'event_type'   => 'ability_executed',
				'object_type'  => 'ability',
				'ability_name' => $ability_name,
				'result'       => 'success',
			)
		);
	}

	private function is_trackable_ability( $ability_name, $ability = null ) {
		if ( ! is_object( $ability ) && function_exists( 'wp_get_ability' ) ) {
			$ability = wp_get_ability( $ability_name );
		}

		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_meta' ) ) {
			return false;
		}

		$meta = $ability->get_meta();
		if ( ! is_array( $meta ) ) {
			return false;
		}

		$mcp_public = isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) && array_key_exists( 'public', $meta['mcp'] )
			? true === $meta['mcp']['public']
			: ! empty( $meta['public'] );

		return $mcp_public || ! empty( $meta['show_in_rest'] );
	}

	public function capture_payment_snapshot( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_ic_core_payment_snapshot', true ) ) {
			return;
		}

		$transaction_id = (string) $order->get_transaction_id();
		$snapshot       = array(
			'amount'                  => wc_format_decimal( $order->get_total(), 2 ),
			'currency'                => strtoupper( (string) $order->get_currency() ),
			'transaction_fingerprint' => '' !== $transaction_id ? Audit_Log::fingerprint( $order->get_payment_method() . '|' . $transaction_id ) : '',
			'captured_at_gmt'         => gmdate( 'c' ),
		);

		$order->update_meta_data( '_ic_core_payment_snapshot', $snapshot );
		$order->save_meta_data();

		$this->audit_log->log(
			array(
				'event_type'  => 'payment_snapshot',
				'object_type' => 'order',
				'object_id'   => $order->get_id(),
				'result'      => 'captured',
			)
		);
	}

	public function record_order_status_change( $order_id, $from_status, $to_status, $order ) {
		$this->audit_log->log(
			array(
				'event_type'  => 'order_status_change',
				'object_type' => 'order',
				'object_id'   => $order_id,
				'result'      => sanitize_key( $from_status ) . '_to_' . sanitize_key( $to_status ),
			)
		);
	}

	public function record_refund( $order_id, $refund_id ) {
		$this->audit_log->log(
			array(
				'event_type'  => 'refund_created',
				'object_type' => 'order',
				'object_id'   => $order_id,
				'result'      => 'refund_' . absint( $refund_id ),
			)
		);
	}

	public function record_product_update( $product_id ) {
		$this->audit_log->log(
			array(
				'event_type'  => 'product_updated',
				'object_type' => 'product',
				'object_id'   => $product_id,
				'result'      => 'observed',
			)
		);
	}
}
