# Real sandbox acceptance — 2026-10-18

**PASSED for the tested one-time R5 flow. Production approval remains separate.** Code: `9b05a4c`, branch `codex/payfast-phase2-acceptance`, PR https://github.com/Mabonax/vmt-payfast/pull/1. Isolated application: `C:\xampp\htdocs\vmt_payfast_demo`, dedicated SQLite and durable database queue, user-approved ngrok HTTPS.

## Real payment evidence

- Order: `517a6ad5-f0d2-42e0-a456-60e7899902a7`.
- Payment: `4d80a0f9-e956-4abc-a4ca-25686b5673ea`.
- Server amount: **500 cents / ZAR 5.00**, one-time sandbox wallet.
- Actual provider notification received: **14:33:14 UTC / 16:33:14 SAST**.
- Completed: **14:33:15 UTC / 16:33:15 SAST**.
- Provider status `COMPLETE`; audit outcome `completed`; no error category; provider transaction ID stored privately.
- Payment and order both **COMPLETED**.
- Outbox rows **1**, dispatched rows **1**, fulfilment markers **1**, order completion count **1**, failed jobs **0**.

The real completion path requires signature, original merchant identity, exact amount, actual provider source and successful HTTPS server-to-server `VALID` confirmation before committing. No mocks or security bypass were used in this flow. Scheduler/worker had already delivered and fulfilled the outcome when manual verification found no remaining work. The browser return and authenticated order/audit page independently showed completed status.

## Replay and browser safety

The already verified application's completion event was deliberately re-enqueued through the durable database queue. Its listener ran successfully again; fulfilment marker, completion count and original outbox count all remained **1**, with zero failed jobs. This is application-event/queue replay evidence, not a duplicate provider ITN claim.

A separate unpaid attempt (`977d6a96-1180-4fd4-9c0d-219edcd40d69`) was used to verify real browser behavior: its return URL before ITN showed **PENDING, awaiting verified notification**. Its checkout was then submitted to PayFast and cancelled through the provider's cancellation controls. PayFast returned to the configured HTTPS cancel URL. The package retained **PENDING**, zero completion count and zero fulfilment markers, correctly avoiding status inference from a redirect.

PayFast documents once-only sandbox notifications. No real provider resend/duplicate was performed; duplicate ITNs remain covered by automated tests and two-process MySQL acceptance.

## Ingress and account handling

The user replaced an obsolete ngrok token privately. Paid edge-header policy was unavailable on the account, so the tested demo-specific adapter uses ngrok's documented last appended IP/scheme, only from loopback and the exact configured host, followed by the unchanged package PayFast DNS validation. All six public checks passed: loopback peer, HTTPS recognized, source IP valid, forged source rejected, appended source present and scheme prefix normalized. The diagnostic route was removed afterward.

The user explicitly approved a disposable tester and a two-minute single-use sign-in. Its temporary route was removed after authentication. No merchant credentials, passphrase, APP_KEY, ngrok token or raw ITN body are included in this evidence or committed to Git.

## Initial failed attempt and limits

An earlier R1.05 checkout was accepted, but PayFast displayed “Unable to verify payment” after its wallet action. No provider ITN was observed and that record remains PENDING/unfulfilled. The fresh R5 attempt succeeded. PayFast documents a R5 live minimum, but the exact cause of the earlier sandbox failure was not established; this retry does not prove causation.

[CI 39136531698](https://github.com/Mabonax/vmt-payfast/actions/runs/39136531698) passed on `9b05a4c`: Laravel 12/13, MySQL 8.4 concurrency and isolated demo. Local package: 30 active tests / 183 assertions, 2 conditional skips. Demo: **10 passed / 53 assertions**. Separate MySQL jobs: **1 test / 9 assertions / zero skips each**. Composer/Pint passed.

Screenshot: `build/sandbox-completed.png` (local Git-ignored evidence, displayed in delivery chat). No production money, settlement, refund, recurring billing, split payments, release tag or main merge is claimed. Production account/ingress, key backup/restore, policies, retention and supervised operations still need review.
