# First application adoption

Use the reviewed `v0.1.0-rc.1` candidate for staging after its tag is published. It is not a production approval. The target application must be selected explicitly; no application repository has been changed by this package merge.

## Read the host before implementation

Inspect its instructions, architecture, dirty worktree, real order/booking models, pricing services, repositories, policies, authentication, migrations, routes, existing payment providers, queue/scheduler infrastructure and API/mobile contracts. Preserve unrelated work and existing providers. Do not introduce payment endpoints or business writes until their real host contracts are verified.

Add the GitHub Composer VCS repository from INSTALLATION.md, require `vmt/payfast:0.1.0-rc.1`, and commit the consuming application's lock file. Migrate additively after backup/review. Keep sandbox mode during integration; configure the application's own HTTPS ingress and private merchant settings.

## Application boundaries

| Boundary | Required implementation |
|---|---|
| Checkout | Authenticated host route calls its existing order policy and pricing service/repository; construct CheckoutRequest only from authorized server data. Never accept browser price or arbitrary merchant/tenant context. |
| Merchant | Default resolver only for one account/no tenant; bind a scoped resolver when the host has multiple merchant accounts. Store credentials encrypted or in its approved secret store. |
| Browser/mobile | Server-render hosted form; passphrase stays private. Merchant ID/key are required hosted form fields. Mobile opens the authenticated web checkout; no invented mobile payment endpoints. |
| Payment display | Scope order/payment reads through actual ownership policies. Define `view-payfast-payment` using real order and context; redirects cannot mutate payment outcome. |
| Fulfilment | Register one queued listener. Use FulfilmentService::once and the host's domain service/repository on the same DB connection. Lock the order and enforce order-level fulfilment idempotency across multiple paid attempts. External effects need an application outbox. |
| Operations | Durable queue, supervised workers, scheduled outbox dispatch outside DB transactions, alerts on backlog/failures/pending exceptions, and recovery ownership. |

The package minimum for **production** one-time payments is 500 cents, following PayFast's documented live minimum. Sandbox still accepts smaller decimal test vectors, although the real accepted flow used R5. The earlier R1.05 failure's cause is not proven.

## Host staging acceptance

Run host tests and a real sandbox journey in staging. Record commit, environment, order/payment references, redacted outcomes and exact commands. Do not replace missing evidence with package tests.

| Scenario | Acceptance |
|---|---|
| Tampered amount/context or unauthorized order | Rejected; authoritative order price and context used. |
| Real completed payment | Verified ITN, correct amount/merchant, one business fulfilment, attributable audit. |
| Return-before-ITN/cancel/abandonment | Pending state displayed; no inferred completion or failure. |
| Duplicate callbacks and multiple paid attempts | No repeated order/booking activation; unexpected overpayment raised for operator review. |
| Worker/scheduler interruption and retry | Durable backlog recovers; order mutation remains once. |
| Provider/network/DB failure | No partial completion; redacted diagnostics and safe retry. |
| Ownership/tenant/account rotation | Cross-context access rejected; in-flight original credentials preserved. |
| Backup/restore and deployment rollback | Payment/audit history and encryption keys recoverable; no financial records dropped. |

Production go-live requires the application owner to approve the evidence and production configuration. Follow PRODUCTION_OPERATIONS.md. A stable version tag follows successful host staging and deployment approval; do not treat the candidate tag or main merge as approval to accept real funds.
