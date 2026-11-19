# Integrity Core 0.1.0 — manual verification plan

Run only on a disposable staging site with current WordPress and WooCommerce versions. Do not test against live payments.

1. Activate without WooCommerce: plugin should not fatal; the admin notice should explain the missing dependency.
2. Activate with WooCommerce: the Integrity Core submenu should appear under WooCommerce.
3. Run the scan as a shop manager: nonce/capability checks should pass and results should be saved.
4. Open the scan URL as a subscriber: access must be denied.
5. Verify HTTPS check on an HTTPS staging site and a local HTTP-only test site.
6. With WordPress 6.9+, verify registered public/MCP abilities appear in the exposure count. Missing annotations must be reported as unknown, not assumed safe.
7. Execute a harmless WooCommerce ability in a test environment and confirm a start event and a successful completion event can be logged with the ability name, actor ID/category, result and time; request and response payloads must not appear in the table. Note that the WordPress core after-hook records successful executions only.
8. Complete a test payment using a sandbox gateway. Confirm a payment snapshot is stored without raw transaction ID.
9. Change the paid order total in staging. Run the scan and confirm it reports drift against the stored snapshot.
10. Create a test refund and verify the event is logged. No refund should be initiated by Integrity Core.
11. Verify event cleanup removes records older than 90 days.
12. Confirm the plugin never changes orders, stock, payment settings, user roles or MCP permissions during a scan.

## Release blockers

- PHP syntax checks pass for every PHP file.
- Test on both HPOS and legacy order storage.
- Test current WordPress Abilities API and MCP Adapter versions.
- Review data retention, uninstall behaviour and capability handling.
- Perform a security review before WordPress.org submission or production recommendation.
