# INTEGRITY CORE — Agent Commerce Readiness

A read-only WordPress/WooCommerce diagnostic plugin for stores preparing for agentic commerce.

## Why this market
WooCommerce is beginning to expose standardized agent interfaces and agentic checkout paths. Merchants must coordinate catalog quality, discovery, authorization, protocol versions, and payment onboarding. INTEGRITY CORE starts as a vendor-neutral diagnostic layer rather than another checkout implementation.

## MVP 0.1.0
- HTTPS and permalink checks
- WordPress Abilities API availability check
- Detection of MCP Adapter and WooCommerce AI integration components
- WordPress REST index reachability test
- Conventional UCP discovery URL reachability test
- Sample audit of published product descriptions, images, and prices
- Administrator-only report, kept temporarily in a per-user transient

## Safety boundaries
- No order or customer data access
- No payment credentials or API keys
- No external telemetry
- No order, product, or payment mutations
- No automatic checkout retry or refund
- Endpoint reachability is not protocol conformance

## Install
Upload the ZIP through Plugins > Add New Plugin > Upload Plugin or copy the plugin folder into wp-content/plugins/, activate it, then open WooCommerce > INTEGRITY CORE.

## Development status
Version 0.1.0 is an alpha prototype. It has not been submitted to WordPress.org or validated against live merchant stores.
