# Configuration

```dotenv
PAYFAST_MODE=sandbox
PAYFAST_MERCHANT_ID=
PAYFAST_MERCHANT_KEY=
PAYFAST_PASSPHRASE=
PAYFAST_RETURN_URL=https://your-staging-host/payments/payfast/return
PAYFAST_CANCEL_URL=https://your-staging-host/payments/payfast/cancel
PAYFAST_NOTIFY_URL=https://your-staging-host/payments/payfast/notify
```

Use your own sandbox account; no credentials are bundled. This package requires a configured passphrase for both modes as a stricter security policy. PayFast allows optional passphrases for some hosted flows; the account and package must agree exactly. HTTPS callbacks are required. Configure production explicitly with `PAYFAST_MODE=production`. The known shared sandbox ID/key are rejected in production, and sandbox mode is rejected when the Laravel environment is production. Arbitrary private sandbox credentials cannot be identified from their syntax; deployment review must confirm the correct account.

Do not log config, Merchant DTOs, CheckoutResponse fields, request bodies or credential snapshots. `#[SensitiveParameter]` masks constructor parameters in stack traces; it does not make public DTO properties safe to dump. Config cache contains credentials and requires restricted filesystem access. Never commit `.env` or production config cache.

Set `payfast.routes=false` if the host owns these routes. The notify route is stateless and outside Laravel's `web` middleware group; CSRF middleware is therefore absent only for this provider endpoint. Return/cancel GET routes use `web` for session-aware UX. If moving the notify route under `web`, exempt exactly `payments/payfast/notify` with `withMiddleware(fn ($middleware) => $middleware->validateCsrfTokens(except: ['payments/payfast/notify']))` in `bootstrap/app.php`. Never exempt all payments or disable CSRF globally. Custom route middleware must not require login or tenant sessions for ITN delivery.

Checkout appends `payfast_reference` to return/cancel URLs. The default return page displays actual state only for an authenticated user allowed by the host's `view-payfast-payment` gate. Define that gate using the real order ownership, merchant and tenant policy; it denies by default. Other users see a generic pending-verification message with no transaction disclosure. Custom application return pages should apply the same authorization before showing state. Do not log query strings containing payment references unnecessarily.

Hosted checkout uses PayFast-required merchant ID/key form fields. These are necessarily visible in HTML sent to the browser, which conflicts with an absolute requirement to never expose them to a frontend. The passphrase never leaves the server. Return server-rendered HTML, not credential-bearing JSON to React/Flutter; use an authenticated web checkout flow from mobile. If your policy prohibits even these required hidden fields, do not enable hosted checkout until PayFast supplies an approved alternative protocol.

The default source check compares the socket peer (`REMOTE_ADDR`) against IPv4 DNS results for PayFast's documented hosts and fails closed on DNS failure. It ignores spoofable Referer and forwarded headers. Behind a reverse proxy or tunnel, bind `ProviderSource` to a reviewed source verification adapter using the ingress's authenticated peer-IP metadata and strict trusted-proxy configuration. Do not simply accept all tunnel IPs, client-supplied X-Forwarded-For, or turn source checking off. Default source verification does not support IPv6; deployments needing it must implement and test the adapter.
