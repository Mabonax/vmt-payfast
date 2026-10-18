# Protocol evidence — inspected 2026-10-18

Official sources:

- https://developers.payfast.co.za/docs#home (JavaScript shell on direct open; indexed official content at https://developers.payfast.co.za/docs?capcode=xMzF was retrieved and cross-checked).
- https://sandbox.payfast.co.za/ (sandbox entry point).
- https://github.com/PayFast/payfast-php-sdk/blob/master/lib/PaymentIntegrations/CustomIntegration.php
- https://github.com/PayFast/payfast-php-sdk/blob/master/lib/Auth.php
- https://github.com/PayFast/payfast-php-sdk/blob/master/lib/PaymentIntegrations/Notification.php
- https://github.com/PayFast/payfast-php-sdk/blob/master/lib/PayFastPayment.php
- https://payfast.io/integration/submit-your-solution/
- https://support.payfast.help/portal/en/kb/articles/what-causes-the-itn-security-check-errors-20-9-2022
- https://github.com/laravel/docs/blob/13.x/packages.md
- https://github.com/laravel/docs/blob/13.x/releases.md

Provider-owned SDK source and indexed official developer documentation were inspected. Sources are moving references; verify against the current provider docs before releasing. No third-party similarly named PayFast gateway was used.

Implemented subset: sandbox/production HTTPS base domains, `/eng/process` HTML POST, merchant ID/key, callbacks, optional customer fields, internal `m_payment_id`, two-decimal amount, item fields, and signature. Payload construction orders those supported fields explicitly. Checkout uses trimmed URL-encoded non-empty values and appends the trimmed passphrase. ITNs use received field order, `stripslashes` normalization, URL encoding without checkout trimming or empty-value removal, and appended URL-encoded passphrase. Signature must be last; suffixes are rejected rather than silently left unsigned. REST alphabetic sorting is not used.

Security requirements were cross-checked against PayFast's integration submission guidance: signature, source IP, amount and server-side data verification. Confirmation POSTs the unsigned ordered parameter string (not the passphrase) to `/eng/query/validate`, accepting only successful `VALID`. Official documentation and SDK addresses define the source allowlist; this package checks socket source rather than the forgeable Referer implementation in their sample. Exact cents equality is stricter than the sample's floating tolerance. Checkout URL-encodes the trimmed passphrase once, as the developer documentation specifies; the SDK's checkout implementation appears to encode it twice when it contains reserved characters. The package follows the documentation. Reserved-character passphrases still need a real sandbox vector before release. No independent provider-produced signature fixture or live acceptance is claimed.

Only `COMPLETE` establishes one-time completion. No speculative mapping of recurring FAILED/CANCELLED notification semantics into one-time payments. Administrative closure has separate auditable semantics and events. Refunds/recurring/split products exist in PayFast's official product listings, but no Phase 1 endpoints or parameters are inferred from those listings.

Laravel documentation confirms mergeConfigFrom/loadRoutesFrom package registration, migrations and PHP 8.3 minimum for Laravel 13. Dependency resolution and test results are recorded separately in VERIFICATION.md.
