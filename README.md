# VMT PayFast — Phase 1

Standalone `vmt/payfast` Composer package for Laravel 12/13 and PHP 8.3+. One-time ZAR hosted checkout, verified ITN completion, encrypted credential snapshots, tenant/merchant context, durable outcome delivery, and database-backed fulfilment idempotency.

**Release status: tested one-time sandbox flow accepted; production deployment approval remains outstanding.** Real R5 checkout/ITN/fulfilment evidence is in [sandbox acceptance](docs/SANDBOX_ACCEPTANCE_2026-10-18.md). MySQL 8.4 concurrency passed on Laravel 12/13 CI; see [PRODUCTION_READINESS.md](PRODUCTION_READINESS.md) for limits and remaining checks.

## Installation and use

See [INSTALLATION.md](INSTALLATION.md), [CONFIGURATION.md](CONFIGURATION.md), [MULTI_MERCHANT.md](MULTI_MERCHANT.md), [SECURITY.md](SECURITY.md), and [SANDBOX_TESTING.md](SANDBOX_TESTING.md).

GitHub installation uses a Composer VCS repository and `vmt/payfast:dev-main` until an approved tag exists. Local sandbox demo: [DEMO_APPLICATION.md](DEMO_APPLICATION.md); optional strict proxy source handling: [INGRESS.md](INGRESS.md).

First-application adoption and staging: [APPLICATION_INTEGRATION.md](APPLICATION_INTEGRATION.md). Deployment/recovery preparation: [PRODUCTION_OPERATIONS.md](PRODUCTION_OPERATIONS.md). Versioned staging candidate: `v0.1.0-rc.1` after publication; live payments require separate host approval.

```php
use Vmt\PayFast\Contracts\PaymentGateway;
use Vmt\PayFast\Data\CheckoutRequest;

// Authorize access and load the payable amount from your order service/repository.
$checkout = app(PaymentGateway::class)->createCheckout(new CheckoutRequest(
    reference: $order->reference,
    amountCents: $order->total_cents,
    itemName: 'Order '.$order->reference,
    orderReference: $order->reference,
));
return response($checkout->toHtml())->header('Cache-Control', 'no-store');
```

`CheckoutRequest` is a trusted server-side boundary, never a request-body DTO. The host owns authorization, order pricing, order-level repeated checkout prevention, and tenant selection. Each call creates a separate payment attempt with a new UUID. No business models or invented order endpoints are included.

## Architecture

`PaymentGateway` → `CheckoutService` → `MerchantResolver` → encrypted pending `PaymentTransaction` → ordered signed HTML form. `NotificationService` parses original form bytes, validates signature/source/merchant/amount and PayFast server confirmation, then locks the transaction to commit its outcome, audit row, and outbox row atomically. `payfast:dispatch-outbox` delivers committed application events. `FulfilmentService::once` protects same-database consumer work across repeated events and queue retries.

Tables: `payfast_transactions`, `payfast_events`, `payfast_outbox`, `payfast_fulfilments`. The provider loads migrations automatically. No existing application tables are changed. Raw notifications, card details, customer details, merchant keys, and passphrases are not stored in audit events. The original merchant ID/passphrase snapshot is encrypted for rotation-safe verification.

## Payment lifecycle

CREATED → PENDING → COMPLETED / FAILED / CANCELLED. Only fully verified `COMPLETE` ITNs complete payments. Browser return/cancel routes never change state. Other provider statuses fail closed until their one-time semantics are confirmed. `PaymentLifecycleService::close` supports scoped, trusted administrative FAILED/CANCELLED decisions, never browser abandonment. Terminal states cannot be overwritten; a subsequent verified completion for an administratively closed payment is audited as `ignored_terminal` and requires operator review. Do not close an in-flight payment merely because it is old.

The default return page displays current state to authenticated users authorized by the host's `view-payfast-payment` gate; everyone else receives a generic pending-verification message. The cancel page does not establish cancellation. Configure application URLs for your full order UX and scoped lookup. See CONFIGURATION.md and `examples/`.

## Delivery and reconciliation

Schedule `payfast:dispatch-outbox` every minute. Events are **at least once**, because a process can fail after a listener ran and before the delivery marker is committed. Normal repeated ITNs create one completion outbox row. Consumers must use the durable fulfilment ledger. No exactly-once claim is made for arbitrary external effects. Callback failure rolls back both the business update and fulfilment marker. Queue consumers must use durable queues; operations on other connections or external APIs need an application outbox and provider idempotency.

Dispatch outbox events outside application database transactions. Phase 2 rejects dispatch inside an outer transaction: deferred after-commit listeners could otherwise fail after the delivery marker was already committed. The command and scheduler naturally run outside such transactions.

`ReconciliationService::pending($merchant, $tenant, $before)` returns a scoped pending exception report. Operators compare it with the merchant dashboard and request a provider ITN resend; resends undergo all validation. There is no speculative REST transaction lookup, manual completion bypass, or settlement reconciliation.

## Verification

```sh
composer install
composer validate --strict
php vendor/bin/pint --test
php vendor/bin/phpunit
```

This is a package: there is no root `artisan`. Network calls in automated tests are mocked. The MySQL two-process test is opt-in; see [VERIFICATION.md](VERIFICATION.md).

## Future extensions

`PaymentGateway` and `MerchantResolver` are extension seams. Refunds, recurring billing, subscriptions, split payments, settlement reconciliation, analytics, disputes and other gateways are **not implemented**. No central wallet or pooled funds. Future REST operations require a distinct REST signature implementation and verified official API contracts.

Protocol evidence and deliberate deviations from SDK implementation: [docs/PROTOCOL.md](docs/PROTOCOL.md).
