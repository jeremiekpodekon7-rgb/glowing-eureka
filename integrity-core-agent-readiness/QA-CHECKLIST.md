# INTEGRITY CORE 0.1.0 — QA checklist

## Automated checks completed locally
- [x] PHP syntax check (php -l) on PHP 8.4.24.
- [x] Local smoke test with WordPress/WooCommerce stubs: 12 checks executed; 12 pass.
- [x] ZIP archive integrity check (unzip -t).

## Required before public beta
- [ ] Install on a clean WordPress + WooCommerce test instance.
- [ ] Test with WooCommerce absent, inactive, and active.
- [ ] Test administrator and shop-manager capabilities.
- [ ] Verify REST API test behind common security plugins and a CDN.
- [ ] Verify UCP discovery test against a known-good profile and a 404 response.
- [ ] Verify product sampling for simple, variable, free, and out-of-stock products.
- [ ] Run WordPress Plugin Check and PHP_CodeSniffer with WordPress Coding Standards.
- [ ] Complete security review and privacy/readme review.
- [ ] Complete official UCP conformance tests separately; HTTP 200 is not conformance.

## Release boundary
This is an alpha diagnostic. Do not market it as a protocol certification or enable autonomous live payments based on this report.
