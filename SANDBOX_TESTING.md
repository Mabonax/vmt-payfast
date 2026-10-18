# Real sandbox acceptance procedure

Status: **not executed**. Automated HTTP mocks are not provider proof.

Phase 2 provides a separate app at `C:\xampp\htdocs\vmt_payfast_demo`; see DEMO_APPLICATION.md. User-supplied sandbox settings are present privately, but all callback URLs still use `your-approved-public-host`. Provider acceptance is BLOCKED until approved HTTPS/source handling is configured per INGRESS.md. No source check is disabled.

1. Register/sign in at https://sandbox.payfast.co.za/ and configure your sandbox merchant ID, key and account passphrase. Obtain them through PayFast's dashboard; never paste them into tickets or commit them.
2. Install the package in a Laravel staging application and migrate. Set a valid persistent APP_KEY. Use an isolated test order loaded through the application's normal service/repository.
3. Configure the seven PAYFAST variables, `APP_ENV=local` or `staging`, and `PAYFAST_MODE=sandbox`. Clear/rebuild config cache. Ensure no production orders/accounts are involved.
4. Expose HTTPS staging or use an organization-approved authenticated HTTPS tunnel. PayFast cannot call localhost. Ensure the callback itself is reachable without interactive login. Review source checking: the default checks REMOTE_ADDR and rejects proxy/tunnel peers. Configure and test a strict ingress-aware ProviderSource adapter before checkout; never disable the security check just to make a tunnel work.
5. Mount `examples/sandbox-routes.php` only locally, or adapt its authenticated server-order checkout to staging. Submit the rendered form to the sandbox endpoint and use PayFast's current supported simulated payment workflow.
6. Confirm the notify request reached the POST endpoint and returned 200. Verify the transaction is COMPLETED only after signature/source/merchant/amount/server checks. A return-page visit alone must leave it PENDING.
7. Inspect scoped transaction status, provider ID, completed_at and payment audit outcomes. Do not export decrypted snapshots, full request bodies or unnecessary PII as evidence.
8. Run `php artisan payfast:dispatch-outbox`. Confirm exactly one normal outbox row and that the host's idempotent order listener updated the authorized order. Resend the same ITN through the supported provider mechanism; confirm duplicate audit outcome and no second order mutation.
9. Exercise return-before-ITN, cancel/abandonment, callback timeout/retry and unavailable scheduler. Record environment, package commit, provider reference, timestamps, outcomes and redacted screenshots. Test distinct merchant accounts/tenants if applicable.
10. Investigate 400 as validation rejection using audit error categories; 415 as incorrect content type; 5xx as infrastructure/DB/network failure. Inspect HTTPS reachability, source adapter/DNS, original passphrase, exact ZAR amount, configured account, APP_KEY and PayFast server confirmation. Never bypass a failed check or manually mark completed from a browser return.

Pending exception reconciliation: query `ReconciliationService`, compare with the correct merchant dashboard, and request ITN resend. Verified notifications for locally FAILED/CANCELLED attempts are flagged `ignored_terminal`; investigate funds and order state manually. No refund operation is supplied by Phase 1.
