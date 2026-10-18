# Production readiness — Phase 2

2026-10-18. **HOLD production release/tagging.** Automated and MySQL acceptance passed; real PayFast sandbox acceptance and deployment checks remain outstanding. No production approval or version tag was created.

**Local ngrok follow-up:** HTTPS is now established and all callback URLs are configured privately. Public source-header spoofing/HTTPS checks passed. The demo now passes 10 tests / 53 assertions and prepares fixed R5 sandbox orders. PayFast accepted the initial R1.05 checkout but displayed “Unable to verify payment” after the wallet action; no provider ITN reached Laravel for that attempt, and it remains PENDING with zero fulfilments. A fresh R5 attempt is prepared to rule out the documented payment minimum; its outcome is not yet verified. See NGROK_SETUP.md. Earlier CI counts below remain the evidence for their specified commits.

| Capability | Implemented | Automated test passed | Real sandbox verified | MySQL verified | Blocked / limitation |
|---|---|---|---|---|---|
| One-time ZAR checkout and ordered signatures | Yes | Laravel 12/13 | Checkout accepted; completion not verified | Payment storage exercised | Provider wallet verification failed on first attempt |
| Signature/source/merchant/amount/server validation | Yes | Mocked provider responses and rejection tests | No | Concurrent verified writes exercised | No actual provider ITN received |
| Strict ingress source adapter | Yes, opt-in | Trusted/untrusted/malformed/missing IP cases; live ngrok spoofing checks | No provider ITN yet | Not DB-dependent | Real provider source still needs acceptance |
| Locks, one completion/outbox/audit | Yes | Two independent PHP processes | No provider claim | **MySQL 8.4 passed in both framework jobs** | Local MySQL/Docker unavailable |
| Durable fulfilment ledger and queue replay | Yes | Package replay/rollback; real demo DB queue worker | No | **One marker in concurrent processing** | External effects require host idempotency |
| Outbox recovery | Yes | Listener retry; rejects unsafe outer transaction | Not provider-dependent | Completion uniqueness verified | At-least-once delivery; supervise scheduler/workers |
| Authenticated isolated demo, server-priced orders | Yes | **10 tests / 53 assertions locally** | Checkout reached; wallet verification failed | Demo uses dedicated SQLite | Completed provider flow remains blocked |
| Return/cancel, scoped status and audit views | Yes | Browser cannot complete; cross-user denial | No | No provider claim | External return/cancel acceptance pending |
| Tenant/merchant isolation and credential rotation | Yes | Scoped checks and original snapshot tests | No | Scoped concurrent fulfilment | Real host resolver/ownership review pending |
| Pending exception report and diagnostics | Yes | Scoped report; controlled failure categories | No | Not settlement proof | Live missing-ITN diagnosis pending |
| APP_KEY and credentials | Encrypted snapshots; preserved demo key | Encryption/rotation tests | Account validity not verified | Snapshot used by both workers | Key backup/restore review required |
| VCS/path installation | `dev-main` documented | Local path and CI demo dependency install | Not applicable | Not applicable | No approved tag |
| Refunds, recurring/subscriptions, split/marketplace settlements, analytics, disputes, other gateways | **Not implemented** | Not claimed | Not claimed | Not claimed | Future approved scope only |

## Exact evidence

Baseline `9689962`: local Composer/Pint passed; 29 tests discovered, 27 active passed, 168 assertions, 2 conditional skips. [Baseline CI](https://github.com/Mabonax/vmt-payfast/actions/runs/39112933485) passed Laravel 12/13 and MySQL 8.4 (one active concurrent test / 9 assertions each; worker method separately skipped in parent invocation).

[Phase 2 CI 39130981377](https://github.com/Mabonax/vmt-payfast/actions/runs/39130981377), implementation `4c9e09d`, passed all three jobs:

- Laravel 12 and Laravel 13, PHP 8.3: **32 discovered, 30 active passed / 183 assertions**, 2 conditional tests skipped in ordinary suite.
- Separate MySQL 8.4 acceptance per framework: **1 test / 9 assertions / zero skips**. Two subprocesses raced one payment; assertions verified completed state, one completion audit, one duplicate audit, one outbox row and one fulfilment marker.
- Separate demo: **7 passed / 39 assertions**, mocked PayFast network and real durable database queue worker.
- Composer validation and Pint passed in both framework jobs.

Local final package commands (Laravel 13 / PHP 8.3.35):

```sh
composer validate --strict
php vendor/bin/pint --test
php vendor/bin/phpunit
```

Passed: 32 discovered / 30 active / 183 assertions / 2 conditional skips. Phase 2 Laravel 12 evidence is remote CI, not a second local installation. Matrix jobs independently resolve:

```sh
composer update --with 'laravel/framework:^12.0' --with 'orchestra/testbench:^10.0' --with 'phpunit/phpunit:^11.5' --no-interaction --prefer-dist
composer update --with 'laravel/framework:^13.0' --with 'orchestra/testbench:^11.0' --with 'phpunit/phpunit:^12.0' --no-interaction --prefer-dist
```

MySQL CI uses `PAYFAST_TEST_MYSQL=1`, database/user `payfast_test` and an ephemeral service password:

```sh
php vendor/bin/phpunit --filter test_two_process_duplicate_itn_and_fulfilment --fail-on-skipped
```

The test refuses database names outside `payfast_test` / `payfast_test_*`. No existing application/production database was used. Local Docker was unavailable; MySQL acceptance is explicitly remote CI evidence.

Demo commands in `C:\xampp\htdocs\vmt_payfast_demo`:

```sh
php artisan test --compact
php artisan schedule:list
php artisan route:list --path=payments/payfast
composer validate --strict
```

Passed: 7 tests / 39 assertions, minute-based outbox schedule and three payment routes present, Composer valid. Local login HTTP smoke returned 200 with sandbox banner/form. This is not authenticated manual browser or provider proof.

## Real sandbox result: BLOCKED

User-supplied sandbox settings were privately copied from the ignored package `.env` to the demo `.env`, preserving APP_KEY. The existing ngrok account token was updated by the user, HTTPS callbacks configured and live spoofing checks passed. The user explicitly approved a disposable tester and two-minute single-use login; that temporary route was removed after login. Scheduler and worker run in hidden background processes.

PayFast accepted the signed R1.05 checkout and displayed its sandbox wallet. After the user's wallet action, the provider reported “Unable to verify payment”. No provider ITN reached the demo for that attempt; the payment remains PENDING with no fulfilment. Public callback POST probes reach Laravel and are correctly rejected as malformed; those probes are not provider notifications. A fresh R5 sandbox attempt is prepared, without claiming the amount was the root cause. Real payment completion, ITN signature/source/merchant/amount/server confirmation and fulfilment remain unverified. No security check was bypassed.

## Remaining production checks

- Real checkout, signature/server confirmation, return-before-ITN, cancellation and supported replay acceptance.
- Explicit production merchant/passphrase/mode approval; private sandbox credentials cannot be identified by syntax alone.
- Reviewed TLS/DNS/ingress, origin restrictions and source-header overwrite.
- Host authorization/pricing/tenant resolver and order-level multiple-attempt safeguards.
- Worker/scheduler supervision, failed-job recovery, outbox alerts and terminal-state reconciliation.
- APP_KEY backup/restore/rotation, least privilege, redacted logging, retention and POPIA review.
- Migration/backup/restore and rollback preserving audit/payment history.

Hosted forms necessarily expose merchant ID/key; passphrase remains server-side. Provider DNS resolution defaults to IPv4. Only COMPLETE ITNs establish completion; administrative failed/cancelled states and late payments need review. Pending reporting is not provider polling or settlement reconciliation.
