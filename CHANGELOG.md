# Changelog

## 0.1.0-rc.1 — candidate for application staging

- One-time ZAR hosted checkout; Laravel 12/13 and PHP 8.3+.
- Original-merchant encrypted snapshots, strict ITN signature/source/amount/merchant/server validation, locked lifecycle, audit/outbox and idempotent fulfilment.
- Real R5 sandbox acceptance with one completion/fulfilment, queued replay and browser return/cancel evidence.
- Optional reviewed ingress adapter and sandbox-only ngrok free-plan demo normalizer.
- Production minimum of 500 cents enforced before pending records are persisted.
- Laravel/MySQL/demo CI and first-application/staging/production recovery guides.

This is a prerelease candidate. It does not enable live funds or approve any host deployment. Refunds, recurring payments and marketplace settlements are not implemented. See PRODUCTION_READINESS.md and the dated sandbox evidence for verification limits.
