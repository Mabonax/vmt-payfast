# Security model

Trusted server order data controls amount, currency, order reference and merchant context. The package cannot authorize an application-specific order; the integration must do so before constructing CheckoutRequest. Integer cents avoid float rounding. Only ZAR and positive bounded amounts are accepted.

ITNs use the raw form body, preserve field order/empty values, reject duplicate or malformed keys and unsigned suffixes, enforce a size limit, compare signatures with `hash_equals`, verify source, merchant and exact expected cents, and require successful HTTPS PayFast `VALID` confirmation. Remote redirects are disabled, TLS verification remains enabled, and network timeouts bound callback work. URLs are fixed from the stored sandbox/production mode, never a callback URL supplied by the caller. Unknown provider statuses are rejected.

The official SDK's Referer-based check is not treated as source authentication: HTTP Referer is forgeable. The package instead verifies the socket source against the documented host addresses. Review proxy handling before live integration. Neither a valid-looking reference nor the browser return establishes payment completion.

Atomic state/audit/outbox writes, row locks, globally unique internal/provider references and immutable terminal states protect repeated callbacks. A durable consumer ledger protects same-connection fulfilment. Idempotency is per payment attempt and consumer; the host must also enforce order-level one-fulfilment rules if it allows multiple attempts for the same order. External side effects require separate durable idempotency.

No raw provider body or PII is written to payment events. Rejections store only controlled error categories and an optional transaction FK. Infrastructure failures return 5xx; payment state remains retryable and application monitoring must record a redacted incident. Configure host exception rendering/logging to suppress HTTP bodies, checkout fields and decrypted credentials. Limit callback access by method/body size at ingress without blocking PayFast retries. Audit database access and apply retention/backup controls suitable for POPIA.

APP_KEY protects credential snapshots. Back up keys securely and restrict DB/config access. Metadata is explicitly non-sensitive application data: do not pass clinical information, card data, credentials or customer details. No raw card data is accepted as a named package field. Hosted merchant ID/key visibility is an unavoidable protocol constraint; see CONFIGURATION.md. Passphrases must never be exposed.

Disclosure and remediation policy must be established by VMT before public distribution. No security certification, PCI certification, production provider acceptance or legal compliance certification is claimed.
