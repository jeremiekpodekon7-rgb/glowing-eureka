=== Integrity Core — Commerce Assurance ===
Contributors: jeremiekpodekon7-rgb
Tags: woocommerce, ai-agents, abilities-api, mcp, audit, integrity
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Read-only assurance for WooCommerce operations performed through WordPress Abilities API, with a privacy-conscious activity log and integrity checks.

== Description ==

Integrity Core is an independent assurance layer for WooCommerce stores that connect AI agents or other external systems.

Version 0.1.0 provides:

* A read-only scan of HTTPS, WooCommerce, the WordPress Abilities API and the MCP Adapter.
* Inspection of externally exposed abilities and their read-only/destructive annotations.
* A warning when application passwords are associated with administrator accounts.
* A limited scan of the 200 most recent orders for duplicate transaction identifiers, refund totals above order totals, and changes against payment snapshots captured after activation.
* A minimal event log for WooCommerce order status changes, refunds, product updates, payment snapshots and executed WooCommerce/public Abilities.
* Automatic removal of log records older than 90 days.

The plugin does not store raw ability inputs or outputs, customer names, email addresses, payment credentials or full transaction IDs. Transaction IDs used for comparison are stored only as an HMAC fingerprint.

== Important limitations ==

This is an early MVP. It does not block agents, approve refunds, verify external payment-provider records, guarantee that every third-party plugin operation is observed, or prove that a transaction is valid outside the data available to WooCommerce. An empty scan means only that the selected checks did not detect a problem.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin, or copy this folder to wp-content/plugins/.
2. Activate Integrity Core.
3. Open WooCommerce > Integrity Core.
4. Run the read-only integrity scan.
5. Review warnings before connecting or expanding AI-agent write access.

== Privacy ==

The event table stores event type, object type and numeric object ID, user ID, actor category, ability name, outcome, timestamp and a SHA-256 fingerprint. It does not store the underlying request or result payload. Records older than 90 days are removed by WordPress scheduled maintenance. The plugin does not send data to an external service.

== Changelog ==

= 0.1.0 =
* Initial MVP: read-only store scan, Abilities API observation, payment snapshot drift checks and minimal event ledger.
