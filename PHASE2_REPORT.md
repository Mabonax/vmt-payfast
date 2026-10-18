# Phase 2 delivery report

Repository: https://github.com/Mabonax/vmt-payfast. Baseline main `9689962` was pulled with `git pull --ff-only origin main`; no pending changes or baseline failures. Branch: `codex/payfast-phase2-acceptance`. Implementation commit `4c9e09d` was pushed for review. No merge, release tag or production deployment.

## Delivered

- GitHub VCS `dev-main` installation, retained local path instructions, lock-file pinning and release approval guidance.
- Separate running Laravel 13 demo at `C:\xampp\htdocs\vmt_payfast_demo`, versioned overlay under `examples/demo-app` and reproducible installer. Dedicated SQLite, throttled authentication/session rotation, fixed server-priced orders, generated references, hosted form, safe return/cancel, scoped status/audit views and durable database queue fulfilment.
- Placeholder `.env.example`, no default password, interactive tester command, preserved APP_KEY and sandbox-only HTTP/resolver guards. Supplied local PayFast settings privately transferred to the demo; no secrets committed/displayed.
- Optional strict ingress adapter: explicit socket peers, overwritten single source-IP header, then existing PayFast DNS verification. Default disabled; no proxy-chain fallback or source bypass.
- Outbox guard against dispatch inside an outer DB transaction, preventing deferred listener failures after a committed delivery marker. Regression test verifies rejection and recoverable later delivery.
- Dedicated concurrency database name guard; explicit CI supervisor test with `--fail-on-skipped`; separate demo CI job.

Public contracts, Phase 1 migrations and security checks are preserved. Demo business models are outside package source.

## Files

Package: `config/payfast.php`, provider bindings, `IngressProviderSource`, outbox safeguard. Tests/automation: ingress unit tests, payment regression tests, MySQL test guard, `.github/workflows/tests.yml`, `scripts/install-demo.ps1`.

Demo overlay: `.env.example`, bootstrap, controller/middleware, order model/migration, merchant resolver, queued listener, provider, web/console routes, five Blade views and two feature test files under `examples/demo-app`. Generated framework skeleton, APP_KEY, credentials and database are outside this repository.

Docs: README, INSTALLATION, CONFIGURATION, SECURITY, SANDBOX_TESTING, VERIFICATION, historical report banner, PRODUCTION_READINESS, DEMO_APPLICATION, INGRESS and this report.

## Verification

[CI 39130981377](https://github.com/Mabonax/vmt-payfast/actions/runs/39130981377) passed on `4c9e09d`: Laravel 12/13 each **30 active tests / 183 assertions**, 2 ordinary-suite conditional skips; separate **MySQL 8.4 two-process test: 1 passed / 9 assertions / zero skips** each; demo **7 passed / 39 assertions**. Composer and Pint passed. Local final package matches 30 / 183 and demo matches 7 / 39. Route/scheduler inspection and HTTP login 200 smoke passed. Exact commands are in PRODUCTION_READINESS.md.

Local Docker/MySQL were unavailable; remote MySQL used isolated disposable service databases. No local MySQL acceptance claim. A duplicate demo listener was found by its queue test and corrected by disabling automatic discovery in the explicitly wired demo. No unresolved automated failure remains.

## Provider and release decision

**Real sandbox acceptance BLOCKED; HOLD production release.** Supplied credentials are present but not provider-verified. Callback URLs retain `your-approved-public-host`; there is no approved public ingress, actual PayFast ITN/transaction or real fulfilment evidence. No tunnel was provisioned or failed check bypassed. Mocked HTTP responses are not real payments.

Configure public HTTPS/source handling and real callback URLs, clear the demo config cache, create a tester with `php artisan demo:create-user your-email`, run scheduler/worker, and follow SANDBOX_TESTING.md. Complete deployment/key-backup/authorization/retention reviews before release. Refunds/recurring/split/settlement APIs remain unimplemented.

The branch is submitted as a draft PR targeting main while provider acceptance is blocked. Final PR URL and documentation commit hash are in the delivery reply.
