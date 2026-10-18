# Isolated sandbox demonstration

Local application: `C:\xampp\htdocs\vmt_payfast_demo`, Laravel 13, depending on this package via Composer path repository. It uses its own SQLite `database/database.sqlite`, database-backed sessions/cache/jobs/failed jobs, a persistent APP_KEY, and no real funds. The overlay source is versioned under `examples/demo-app`; it is not loaded by the package provider. The generated framework skeleton and local `.env` are outside this repository.

## Reproduce locally

```powershell
php C:\composer\composer.phar create-project 'laravel/laravel:^13.0' C:\xampp\htdocs\vmt_payfast_demo --no-interaction --prefer-dist
pwsh -NoProfile -File scripts/install-demo.ps1
```

The installer is only for a fresh dedicated skeleton or this existing demo. It copies the overlay, binds the local package as `dev-main` for development, preserves existing `.env` credentials and APP_KEY, disables debug rendering, and refuses migrations unless the database is the dedicated demo SQLite file. Never run it over another application. It does not regenerate APP_KEY.

Create your own tester interactively (no default or printed password):

```powershell
cd C:\xampp\htdocs\vmt_payfast_demo
php artisan demo:create-user your-email@example.com
php artisan serve --host=127.0.0.1 --port=8086
```

Open http://127.0.0.1:8086/login for local-only development, or the public HTTPS login when ngrok secure cookies are enabled. Sign in and create a fixed server-priced R5 test order. Incoming amount fields are ignored. Submit its signed form to PayFast sandbox. Order pages show current payment/order state, fulfilment count, and scoped redacted audit rows. Other users cannot inspect the order. Package return/cancel pages never complete a payment; the return page shows verified state only through the demo's ownership gate. Return to “My test orders” to inspect fulfilment and audit details. The earlier R1.05 attempt remains correctly displayed at its original amount.

## Configure secrets privately

Edit `C:\xampp\htdocs\vmt_payfast_demo\.env`, never the package's config file or tracked template:

```dotenv
PAYFAST_MODE=sandbox
PAYFAST_MERCHANT_ID=your_sandbox_merchant_id
PAYFAST_MERCHANT_KEY=your_sandbox_merchant_key
PAYFAST_PASSPHRASE=your_sandbox_passphrase
PAYFAST_RETURN_URL=https://your-approved-host/payments/payfast/return
PAYFAST_CANCEL_URL=https://your-approved-host/payments/payfast/cancel
PAYFAST_NOTIFY_URL=https://your-approved-host/payments/payfast/notify
```

Obtain the real sandbox values from https://sandbox.payfast.co.za/. Set the passphrase there to match exactly. Do not paste credentials in chat or commit the local `.env`. After changing it run `php artisan config:clear`. The committed `.env.example` contains blank placeholders. Keep the generated APP_KEY, dedicated SQLite DB, `QUEUE_CONNECTION=database`, `APP_DEBUG=false`, and a non-production APP_ENV. HTTP middleware and the merchant resolver both prohibit production payments.

A public HTTPS callback is a separate prerequisite. Bind an approved TLS ingress to the loopback demo, then follow INGRESS.md. The user's existing ngrok account is now configured for this local sandbox; NGROK_SETUP.md documents the free-plan source adapter and observed public spoofing checks. No new tunnel account was provisioned. A tunnel URL alone does not prove provider source, and checkout acceptance alone does not establish a completed payment.

## Durable operation and recovery

Run separately:

```sh
php artisan schedule:work
php artisan queue:work database --tries=3 --timeout=30 --backoff=10
```

For a one-off check: `php artisan payfast:dispatch-outbox`, then `php artisan queue:work database --once --tries=3 --timeout=30`. Jobs use a 90-second retry interval, longer than the 30-second worker timeout. The listener locks the order and uses the package fulfilment ledger; queue retries and multiple payment attempts cannot increment completion twice. `php artisan queue:failed` lists failures; retry only after diagnosing the redacted incident. Do not flush failed jobs during acceptance.

The scheduled outbox is recoverable if the scheduler stops; committed rows remain undelivered until dispatch succeeds. Audit pages show `invalid_signature`, `incorrect_amount`, other controlled rejection categories, and retryable processing failures without raw bodies or decrypted snapshots. Review scoped pending payments through `ReconciliationService`; never close old payments solely by age. A payment can be verified while order fulfilment remains queued; the UI distinguishes these states.

## Local acceptance tests

```sh
php artisan test --compact
php artisan schedule:list
php artisan route:list --path=payments/payfast
```

Demo tests use a separate in-memory SQLite database, mocked PayFast HTTP calls and a real database queue worker for fulfilment. They cover authentication, server price, cross-user audit denial, rollback/redaction, production guard, verified notification/queue/fulfilment replay and browser return/cancel safety. They are not real provider acceptance. APP_KEY is created only by the fresh Laravel scaffold, never rotated by the installer or test scripts.
