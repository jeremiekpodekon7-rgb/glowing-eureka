<?php
namespace IntegrityCore;

defined( 'ABSPATH' ) || exit;

/**
 * Stores minimal operational events. Inputs, outputs, customer data and secrets are never stored.
 */
class Audit_Log {
	const TABLE_SUFFIX = 'integrity_core_events';

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at_gmt datetime NOT NULL,
			event_type varchar(64) NOT NULL,
			object_type varchar(32) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_type varchar(24) NOT NULL DEFAULT '',
			ability_name varchar(191) NOT NULL DEFAULT '',
			result varchar(20) NOT NULL DEFAULT '',
			fingerprint char(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_at_gmt (created_at_gmt),
			KEY object_lookup (object_type,object_id),
			KEY ability_name (ability_name)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( 'ic_core_db_version', IC_CORE_VERSION, false );
	}

	/** Returns a stable, site-local fingerprint without storing the raw provider transaction ID. */
	public static function fingerprint( $value ) {
		$key = get_option( 'ic_core_fingerprint_key', '' );
		if ( ! is_string( $key ) || '' === $key ) {
			$key = wp_generate_password( 64, false, false );
			add_option( 'ic_core_fingerprint_key', $key, '', false );
			$key = get_option( 'ic_core_fingerprint_key', $key );
		}
		return hash_hmac( 'sha256', (string) $value, $key );
	}

	public function log( $event ) {
		global $wpdb;

		$event = wp_parse_args(
			$event,
			array(
				'event_type'   => 'event',
				'object_type'  => '',
				'object_id'    => 0,
				'user_id'      => get_current_user_id(),
				'actor_type'   => $this->get_actor_type(),
				'ability_name' => '',
				'result'       => '',
			)
		);

		$payload = array(
			'created_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
			'event_type'     => substr( sanitize_key( $event['event_type'] ), 0, 64 ),
			'object_type'    => substr( sanitize_key( $event['object_type'] ), 0, 32 ),
			'object_id'      => absint( $event['object_id'] ),
			'user_id'        => absint( $event['user_id'] ),
			'actor_type'     => substr( sanitize_key( $event['actor_type'] ), 0, 24 ),
			'ability_name'   => substr( sanitize_text_field( $event['ability_name'] ), 0, 191 ),
			'result'         => substr( sanitize_key( $event['result'] ), 0, 20 ),
		);

		// This fingerprint helps compare event records; it is not a cryptographic proof against a database administrator.
		$payload['fingerprint'] = hash( 'sha256', wp_json_encode( $payload ) );

		$wpdb->insert(
			self::table_name(),
			$payload,
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	public function get_recent( $limit = 50 ) {
		global $wpdb;
		$limit = max( 1, min( 200, absint( $limit ) ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				' SELECT id, created_at_gmt, event_type, object_type, object_id, user_id, actor_type, ability_name, result, fingerprint
				  FROM ' . self::table_name() . ' ORDER BY id DESC LIMIT %d',
				$limit
			),
			ARRAY_A
		);
	}

	public function count_since( $days = 7 ) {
		global $wpdb;
		$days = max( 1, min( 365, absint( $days ) ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				' SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE created_at_gmt >= %s',
				gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $days ) )
			)
		);
	}

	public function prune() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				' DELETE FROM ' . self::table_name() . ' WHERE created_at_gmt < %s',
				gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * 90 ) )
			)
		);
	}

	private function get_actor_type() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		return is_admin() ? 'admin' : 'frontend';
	}
}
