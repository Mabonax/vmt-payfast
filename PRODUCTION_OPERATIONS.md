# Production deployment and recovery runbook

Status: preparation for the first application; no production environment has been provisioned or enabled.

## Enablement gate

Require an identified host application and owner, approved staging evidence, correct production merchant account/passphrase, permanent HTTPS callbacks, reviewed source-IP ingress, encrypted credentials, retained APP_KEY/previous keys, database backup/restore proof, retention/POPIA review, deployment rollback plan and incident contacts. Never use the temporary local ngrok demo as the production service.

Install a reviewed tag/commit through the application's Composer lock file. Back up before additive migrations; never regenerate APP_KEY. Configure cache privately with least-privilege access. Confirm the production minimum is R5, authoritative pricing/policies, scoped merchant resolution and idempotent real-domain fulfilment. Do not log credentials, raw ITNs, hosted form fields or decrypted snapshots.

## Processes

Run the application's scheduler continuously through its existing infrastructure and schedule `payfast:dispatch-outbox` every minute with overlap protection. Use its durable Redis/database queue and process supervisor; worker timeout must remain below the queue retry interval. Restart workers after deployment. Run outbox dispatch outside application transactions. The package does not install host Supervisor/Docker/Redis configuration automatically.

## Monitoring requirements

Wire these into the host's existing telemetry/command center, using controlled counts/categories rather than payloads:

- Undispatched outbox count and oldest age; alert when delay exceeds the application's service target (initial staging target: five minutes).
- Failed job count, worker/scheduler liveness and queue age.
- Callback 5xx rates, controlled validation rejections and `ignored_terminal` outcomes.
- Scoped overdue pending transactions through ReconciliationService, reviewed against the correct merchant dashboard.
- Order/payment mismatches and multiple successful attempts for one order.

Thresholds and on-call routing are host-specific and must be approved during staging. No external messages/alerts have been provisioned by this package.

## Recovery

1. Preserve state, timestamps and redacted references. Inspect controlled audit categories and infrastructure health.
2. Fix reachability, DNS, TLS, original account/passphrase/key access or DB/queue availability; never disable validation to clear an incident.
3. Restart the supervised worker/scheduler and dispatch the outbox. Inspect failed jobs, repair the cause, then retry the specific failed work. Do not flush failure evidence.
4. Verify payment state, outbox delivery and exactly one host fulfilment. External side effects need their own provider idempotency/outbox.
5. For missing ITNs, compare the scoped exception report with the merchant dashboard and use provider-supported resend when available. Never mark paid from the browser return or a dashboard screenshot alone.
6. Investigate payments arriving after administrative closure; preserve `ignored_terminal` evidence. Refunds/settlements are outside this package's implemented scope.

Roll back application code/config with the previous lock file while retaining all payment tables/history, keys and original credential snapshots. Do not run down migrations or reset data. Pause new checkout initiation at the host boundary when necessary while preserving verified callback processing where operationally safe. Test this procedure in staging before production approval.
