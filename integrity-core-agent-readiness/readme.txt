=== INTEGRITY CORE — Agent Commerce Readiness ===
Contributors: integritycore
Tags: woocommerce, agentic-commerce, ucp, mcp, audit, diagnostics
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Read-only readiness audit for WooCommerce stores preparing for AI-agent commerce.

== Description ==
INTEGRITY CORE checks a WooCommerce store's basic readiness for agentic commerce. It reviews HTTPS, permalink configuration, WordPress Abilities API availability, installed MCP/WooCommerce AI integration components, the WordPress REST index, the conventional UCP discovery URL, and a limited sample of published product data.

This early alpha is diagnostic only. It does not read customer orders, store payment credentials, transmit store data to an external service, modify products, change order status, create payment intents, retry payments, or issue refunds.

A successful HTTP response is not proof of UCP/ACP/MCP conformance or a successful agent checkout. Complete official onboarding, protocol conformance testing, and sandbox checkout tests before enabling live agent commerce.

== Installation ==
1. Upload the plugin ZIP via Plugins > Add New Plugin > Upload Plugin.
2. Activate it. WooCommerce must be installed and active for catalogue checks.
3. Open WooCommerce > INTEGRITY CORE.
4. Click Run readiness audit.

== Privacy ==
The plugin stores the latest report temporarily for the current administrator only. It does not send report data to INTEGRITY CORE or a third-party service. The report inspects only public configuration/endpoints and a sample of published product fields; it does not access customer orders or personal data.

== Frequently Asked Questions ==
= Does this make my store compatible with every AI shopping agent? =
No. This alpha performs preliminary diagnostics. Protocol versions, merchant onboarding, payment-provider eligibility, authentication, signatures, and full checkout flows require separate verification.

= Does it change my payment settings or orders? =
No. All checks are read-only.

= Why might the UCP endpoint test fail even when it is available publicly? =
Some hosts block loopback requests from a site to itself. Check the discovery URL from an external network before treating this as a definitive failure.

== Changelog ==
= 0.1.0 =
* Initial diagnostic alpha: HTTPS, permalinks, Abilities API, integration-component detection, REST API, UCP discovery reachability, and product-data sampling.
