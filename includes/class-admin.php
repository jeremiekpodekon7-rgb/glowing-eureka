<?php
namespace IntegrityCore;

defined( 'ABSPATH' ) || exit;

class Admin {
	private $audit_log;
	private $scanner;

	public function __construct( Audit_Log $audit_log, Scanner $scanner ) {
		$this->audit_log = $audit_log;
		$this->scanner   = $scanner;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_ic_core_run_scan', array( $this, 'handle_scan' ) );
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
	}

	public function add_menu() {
		if ( class_exists( 'WooCommerce' ) ) {
			add_submenu_page(
				'woocommerce',
				__( 'Integrity Core', 'integrity-core' ),
				__( 'Integrity Core', 'integrity-core' ),
				'manage_woocommerce',
				'integrity-core',
				array( $this, 'render_page' )
			);
		} else {
			add_management_page(
				__( 'Integrity Core', 'integrity-core' ),
				__( 'Integrity Core', 'integrity-core' ),
				'manage_options',
				'integrity-core',
				array( $this, 'render_page' )
			);
		}
	}

	public function handle_scan() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run this scan.', 'integrity-core' ) );
		}
		check_admin_referer( 'ic_core_run_scan' );
		$this->scanner->run();
		wp_safe_redirect( add_query_arg( 'ic_core_scan', 'complete', admin_url( 'admin.php?page=integrity-core' ) ) );
		exit;
	}

	public function dependency_notice() {
		if ( ! current_user_can( 'activate_plugins' ) || class_exists( 'WooCommerce' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Integrity Core nécessite WooCommerce pour analyser les opérations commerciales.', 'integrity-core' ) . '</p></div>';
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$report = get_option( 'ic_core_last_scan', array() );
		$events = $this->audit_log->get_recent( 30 );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Integrity Core — Commerce Assurance', 'integrity-core' ); ?></h1>
			<p><?php echo esc_html__( 'Contrôle indépendant, en lecture seule, des capacités IA et de la cohérence des opérations WooCommerce.', 'integrity-core' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ic_core_run_scan">
				<?php wp_nonce_field( 'ic_core_run_scan' ); ?>
				<?php submit_button( __( 'Exécuter le contrôle d’intégrité', 'integrity-core' ), 'primary', 'submit', false ); ?>
			</form>

			<?php if ( isset( $_GET['ic_core_scan'] ) && 'complete' === sanitize_key( wp_unslash( $_GET['ic_core_scan'] ) ) ) : ?>
				<div class="notice notice-success inline"><p><?php echo esc_html__( 'Contrôle terminé. Les résultats ci-dessous ont été actualisés.', 'integrity-core' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $report['generated_at_gmt'] ) ) : ?>
				<p><strong><?php echo esc_html__( 'Dernier contrôle (UTC) :', 'integrity-core' ); ?></strong> <?php echo esc_html( $report['generated_at_gmt'] ); ?> — <strong><?php echo esc_html__( 'Événements observés sur 7 jours :', 'integrity-core' ); ?></strong> <?php echo esc_html( (string) ( isset( $report['events_7_days'] ) ? absint( $report['events_7_days'] ) : 0 ) ); ?></p>
			<?php else : ?>
				<p><?php echo esc_html__( 'Aucun contrôle n’a encore été exécuté.', 'integrity-core' ); ?></p>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Résultats du contrôle', 'integrity-core' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php echo esc_html__( 'Contrôle', 'integrity-core' ); ?></th><th><?php echo esc_html__( 'État', 'integrity-core' ); ?></th><th><?php echo esc_html__( 'Constat', 'integrity-core' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! empty( $report['checks'] ) && is_array( $report['checks'] ) ) : ?>
					<?php foreach ( $report['checks'] as $check ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $check['title'] ); ?></strong>
								<?php if ( ! empty( $check['details'] ) ) : ?><ul><?php foreach ( $check['details'] as $detail ) : ?><li><?php echo esc_html( $detail ); ?></li><?php endforeach; ?></ul><?php endif; ?>
							</td>
							<td><?php echo esc_html( $this->status_label( $check['status'] ) ); ?></td>
							<td><?php echo esc_html( $check['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="3"><?php echo esc_html__( 'Lancez le contrôle pour afficher les résultats.', 'integrity-core' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>

			<h2><?php echo esc_html__( 'Journal des opérations observées', 'integrity-core' ); ?></h2>
			<p><?php echo esc_html__( 'Le journal conserve des identifiants techniques minimaux. Il n’enregistre ni contenu de requête, ni réponse d’agent, ni données personnelles de commande.', 'integrity-core' ); ?></p>
			<table class="widefat striped">
				<thead><tr><th>ID</th><th><?php echo esc_html__( 'Date UTC', 'integrity-core' ); ?></th><th><?php echo esc_html__( 'Événement', 'integrity-core' ); ?></th><th><?php echo esc_html__( 'Objet', 'integrity-core' ); ?></th><th><?php echo esc_html__( 'Acteur', 'integrity-core' ); ?></th><th><?php echo esc_html__( 'Capacité', 'integrity-core' ); ?></th><th><?php echo esc_html__( 'Résultat', 'integrity-core' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! empty( $events ) ) : ?>
					<?php foreach ( $events as $event ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $event['id'] ); ?></td>
							<td><?php echo esc_html( $event['created_at_gmt'] ); ?></td>
							<td><?php echo esc_html( $event['event_type'] ); ?></td>
							<td><?php echo esc_html( $event['object_type'] . ( $event['object_id'] ? ' #' . $event['object_id'] : '' ) ); ?></td>
							<td><?php echo esc_html( $event['actor_type'] . ( $event['user_id'] ? ' / user ' . $event['user_id'] : '' ) ); ?></td>
							<td><?php echo esc_html( $event['ability_name'] ); ?></td>
							<td><?php echo esc_html( $event['result'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="7"><?php echo esc_html__( 'Aucun événement observé pour le moment.', 'integrity-core' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>

			<hr>
			<p><strong><?php echo esc_html__( 'Important :', 'integrity-core' ); ?></strong> <?php echo esc_html__( 'Les contrôles sont indicatifs. Une absence d’anomalie détectée ne garantit ni la sécurité complète de la boutique ni la réussite d’une transaction externe. La version 0.1.0 ne bloque et ne corrige aucune opération.', 'integrity-core' ); ?></p>
		</div>
		<?php
	}

	private function status_label( $status ) {
		$labels = array(
			'ok'       => __( 'OK', 'integrity-core' ),
			'warning'  => __( 'À examiner', 'integrity-core' ),
			'critical' => __( 'Critique', 'integrity-core' ),
			'info'     => __( 'Information', 'integrity-core' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Inconnu', 'integrity-core' );
	}
}
