# Production readiness

Implemented source is a Phase 1 integration candidate. Production enablement requires all applicable items below to have recorded evidence:

- Laravel/PHP target matrix and database engine checks, including the two-process MySQL concurrency test.
- Real sandbox checkout, source verification, ITN server confirmation, replay, return/cancel UX and fulfilment acceptance.
- Production merchant/passphrase approval and explicit mode review; no arbitrary sandbox credential detector exists.
- Reviewed trusted proxy/source adapter where needed, public HTTPS, TLS and DNS reliability.
- Host order authorization, server-derived pricing, tenant isolation, authenticated state display and order-level duplicate-attempt safeguards.
- Registered idempotent listeners, persistent queue workers, scheduler/outbox monitoring and recovery runbook. Outbox delivery is at least once.
- APP_KEY backup/rotation, least-privilege database/config access, redacted logging, retention and POPIA review.
- Migration/backup/restore review and tested deployment rollback that preserves transaction/audit history.
- Payment support procedure for terminal-state conflicts, missing ITNs and reconciliation exceptions.

Limitations: no real provider acceptance yet; IPv4 source validation by default; only COMPLETE ITNs supported; FAILED/CANCELLED are explicit trusted administrative outcomes; pending reporting rather than provider polling/settlement reconciliation; metadata safety and order pricing/authorization remain host responsibilities. Default return/cancel pages are generic; integrate authenticated order status pages for product UX.

Not implemented: refunds, recurring/subscription payments, split payments/marketplace settlements, pooled funds, disputes, analytics or other gateway implementations. Official REST signature logic must be separately designed when an approved future capability is added.
