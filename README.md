# INTEGRITY CORE — Commerce Assurance

**Early MVP for WooCommerce stores that connect AI agents or external systems.**

Integrity Core is a read-only assurance layer, not an agent firewall. It helps store operators inspect exposed WordPress Abilities, observe selected commerce events, and detect a few inconsistencies in recent orders.

## Current MVP (0.1.0)
- Read-only checks for HTTPS, WooCommerce, the WordPress Abilities API and MCP Adapter.
- Inspection of exposed abilities and their read-only/destructive annotations.
- Warning when application passwords are attached to administrator accounts.
- Recent-order checks for duplicate transaction references, excessive recorded refunds and drift from payment snapshots captured after activation.
- Minimal event log; raw ability inputs/outputs and customer PII are not stored.
- 90-day event retention.

## Install
Download the Integrity Core 0.1.0 ZIP from the repository branch archive, then upload it in WordPress under **Plugins → Add New → Upload Plugin**. The plugin requires WooCommerce, WordPress 6.9+ and PHP 7.4+.

## Important
This MVP has only passed PHP syntax lint so far. It has **not** been installed or tested on a real WordPress/WooCommerce staging store. It does not block agents, approve refunds, verify remote payment-provider records, or guarantee end-to-end transaction correctness. Review the manual test plan before production use.

## Development
- Plugin entry point: integrity-core.php
- Manual staging checklist: tests/MANUAL-TEST-PLAN.md
- CI workflow: .github/workflows/php-lint.yml
