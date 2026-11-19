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
		$checks   = array();
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
			$is_https ? 'Le site utilise HTTPS.' : 'Le site ne semble pas utiliser HTTPS. Les connexions distantes ne doivent pas transmettre d’identifiants sans HTTPS.',
			array()
		);
	}

	private function check_woocommerce() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return $this->check( 'woocommerce', 'WooCommerce', 'critical', 'WooCommerce n’est pas actif. Les contrôles commerciaux ne peuvent pas être exécutés.', array() );
		}
		$version = defined( 'WC_VERSION' ) ? WC_VERSION : 'inconnue';
		return $this->check( 'woocommerce', 'WooCommerce', 'ok', 'WooCommerce est actif (version ' . sanitize_text_field( $version ) . ').', array() );
	}

	private function check_abilities_api() {
		if ( function_exists( 'wp_get_abilities' ) ) {
			return $this->check( 'abilities_api', 'WordPress Abilities API', 'ok', 'L’API Abilities est disponible. Le plugin peut inspecter les capacités enregistrées.', array() );
		}
		return $this->check( 'abilities_api', 'WordPress Abilities API', 'warning', 'L’API Abilities n’est pas disponible. Elle est intégrée à WordPress 6.9 et versions ultérieures.', array() );
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
			$found ? 'Le plugin MCP Adapter semble actif.' : 'MCP Adapter non détecté. C’est normal si aucun agent MCP ne se connecte à cette boutique.',
			array()
		);
	}

	private function check_agent_abilities() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return $this->check( 'agent_abilities', 'Capacités exposées aux agents', 'info', 'Analyse indisponible sans l’API Abilities.', array() );
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

			$meta   = $ability->get_meta();
			$public = ! empty( $meta['public'] ) || ! empty( $meta['mcp']['public'] );
			$rest   = ! empty( $meta['show_in_rest'] );
			if ( ! $public && ! $rest ) {
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
			$message = sprintf( '%d capacité(s) exposée(s) déclarent une écriture ou une action destructive. Vérifiez leurs permissions et les règles d’approbation.', $review_count );
		} elseif ( $unknown_count > 0 ) {
			$status  = 'warning';
			$message = sprintf( '%d capacité(s) exposée(s) ne déclarent pas clairement leur caractère en lecture seule. Annotation à vérifier.', $unknown_count );
		} else {
			$status  = 'ok';
			$message = 'Aucune capacité exposée n’a été identifiée comme modifiant l’environnement selon ses annotations. Cela ne remplace pas un test de permissions.';
		}

		$details = array(
			'Exposed abilities: ' . $exposed_count,
			'Write/destructive annotations: ' . $review_count,
			'Unknown read-only annotations: ' . $unknown_count,
		);
		if ( ! empty( $review_examples ) ) {
			$details[] = 'À examiner: ' . implode( ', ', $review_examples );
		}

		return $this->check( 'agent_abilities', 'Capacités accessibles via REST/MCP', $status, $message, $details );
	}

	private function check_admin_application_passwords() {
		$admins   = get_users( array( 'role' => 'administrator', 'fields' => array( 'ID', 'display_name' ) ) );
		$affected = array();
		$count    = 0;

		foreach ( $admins as $admin ) {
			$passwords = get_user_meta( $admin->ID, '_application_passwords', true );
			if ( is_array( $passwords ) && ! empty( $passwords ) ) {
				$count += count( $passwords );
				$affected[] = sanitize_text_field( $admin->display_name );
			}
		}

		if ( $count > 0 ) {
			return $this->check(
				'admin_app_passwords',
				'Mots de passe d’application des administrateurs',
				'warning',
				sprintf( '%d mot(s) de passe d’application sont associés à des comptes administrateurs. Privilégiez un compte dédié avec des permissions minimales pour chaque intégration.', $count ),
				array( 'Comptes concernés : ' . implode( ', ', array_slice( $affected, 0, 8 ) ) )
			);
		}

		return $this->check( 'admin_app_passwords', 'Mots de passe d’application des administrateurs', 'ok', 'Aucun mot de passe d’application n’a été trouvé sur les comptes administrateurs.', array() );
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
				$fingerprint = Audit_Log::fingerprint( $transaction_id );
				if ( ! isset( $transaction_ids[ $fingerprint ] ) ) {
					$transaction_ids[ $fingerprint ] = array();
				}
				$transaction_ids[ $fingerprint ][] = $order_id;
			}

			$total    = (float) $order->get_total();
			$refunded = (float) $order->get_total_refunded();
			if ( $refunded > ( $total + 0.01 ) ) {
				$issues[] = sprintf( 'Commande #%d : remboursements supérieurs au total enregistré.', $order_id );
			}

			$snapshot = $order->get_meta( '_ic_core_payment_snapshot', true );
			if ( is_array( $snapshot ) && $order->is_paid() ) {
				$amount_changed      = isset( $snapshot['amount'] ) && abs( (float) $snapshot['amount'] - $total ) > 0.01;
				$currency_changed    = isset( $snapshot['currency'] ) && strtoupper( (string) $snapshot['currency'] ) !== strtoupper( (string) $order->get_currency() );
				$transaction_changed = false;
				if ( isset( $snapshot['transaction_fingerprint'] ) && '' !== $snapshot['transaction_fingerprint'] && '' !== $transaction_id ) {
					$current_fingerprint = Audit_Log::fingerprint( $transaction_id );
					$transaction_changed = ! hash_equals( $snapshot['transaction_fingerprint'], $current_fingerprint );
				}
				if ( $amount_changed || $currency_changed || $transaction_changed ) {
					$issues[] = sprintf( 'Commande #%d : les données de paiement diffèrent de la photographie enregistrée au paiement.', $order_id );
				}
			}
		}

		$duplicate_examples = array();
		foreach ( $transaction_ids as $order_ids ) {
			if ( count( $order_ids ) > 1 ) {
				$duplicate_examples[] = 'Commandes #' . implode( ', #', array_slice( $order_ids, 0, 5 ) );
			}
		}

		if ( ! empty( $duplicate_examples ) ) {
			$issues[] = 'Identifiants de transaction réutilisés : ' . implode( '; ', array_slice( $duplicate_examples, 0, 5 ) );
		}

		if ( ! empty( $issues ) ) {
			return $this->check(
				'order_integrity',
				'Intégrité des commandes récentes',
				'warning',
				sprintf( '%d anomalie(s) à examiner parmi %d commandes analysées. Le scan est indicatif et ne prouve pas à lui seul une fraude.', count( $issues ), $checked ),
				array_slice( $issues, 0, 12 )
			);
		}

		return $this->check( 'order_integrity', 'Intégrité des commandes récentes', 'ok', sprintf( 'Aucune incohérence détectée dans les contrôles disponibles sur %d commandes récentes.', $checked ), array( 'Échantillon limité aux 200 commandes les plus récentes.' ) );
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
