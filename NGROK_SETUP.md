# Local ngrok sandbox setup

The isolated demo supports ngrok's free plan without paid header rewriting. This integration is specific to one ngrok agent connecting directly to `127.0.0.1:8086`; it is not a generic trusted-proxy or arbitrary forwarded-chain policy.

## Start

Configure the current account token privately with the command shown in the ngrok dashboard. Never commit its config or paste the token into chat. The legacy token originally installed on this workstation failed with `ERR_NGROK_106`; replacing it through the account dashboard resolved authentication.

```powershell
cd C:\xampp\htdocs\vmt_payfast_demo
php artisan serve --host=127.0.0.1 --port=8086
```

In another terminal:

```powershell
ngrok http http://127.0.0.1:8086 --inspect=false
```

Keep both running. Use the returned **HTTPS** URL for APP_URL and all three PAYFAST callback URLs in the demo's private `.env`. Preserve credentials and APP_KEY. Enable:

```dotenv
SESSION_SECURE_COOKIE=true
PAYFAST_MODE=sandbox
PAYFAST_INGRESS_ENABLED=true
PAYFAST_INGRESS_TRUSTED_PEERS=127.0.0.1
DEMO_NGROK_INGRESS=true
```

Run `php artisan config:clear` after updates. Run the existing scheduler and database worker as described in DEMO_APPLICATION.md. If the ngrok hostname changes, update APP_URL and all callback URLs again. Use the public HTTPS login rather than localhost when secure cookies are enabled.

## Source verification

Official ngrok documentation specifies that it appends its observed client IP and scheme to `X-Forwarded-For` and `X-Forwarded-Proto`; applications must read the last values. The demo's opt-in `NormalizeNgrokIngress` middleware runs before Laravel's trusted-proxy middleware. It removes a supplied `X-PayFast-Client-IP`, accepts only the actual loopback socket peer and exact configured ngrok host, validates the final appended IP and requires final scheme `https`. It then supplies the normalized IP to the package's existing `IngressProviderSource`. That service still performs the PayFast DNS source validation, and all signature/merchant/amount/server-confirmation checks remain unchanged.

The adapter fails closed for missing/malformed headers, wrong hosts, non-loopback peers, non-HTTPS requests and unsupported domains. Do not use it with an extra proxy hop, public application listener, custom domain, or another tunnel provider without a separate source-path review. Do not trust the first forwarded value or enable generic `trustProxies('*')`.

`examples/ngrok-payfast.yml` illustrates the alternative edge overwrite policy, but ngrok rejected those actions on this account with `ERR_NGROK_2263` because they require paid traffic-policy features. The running setup uses documented appended headers instead; it does not disable source verification or spoof PayFast's IP.

## Observed acceptance — 2026-10-18

- Real public HTTPS tunnel established; public demo login returned HTTP 200.
- A temporary diagnostic route verified loopback upstream peer, recognized HTTPS, valid appended original IP, rejection of a supplied forged source IP, and normalization of a forged `http` scheme prefix. All six checks passed; the diagnostic route was removed afterward.
- Demo suite: **10 passed / 53 assertions**, including local HTTPS trust and forged/malformed ngrok header cases. Provider network calls in those tests remain mocked.
- PayFast accepted the signed checkout and displayed its sandbox wallet page for R1.05. After the user's wallet action it reported “Unable to verify payment”; no provider ITN reached the demo. The payment remains PENDING with no fulfilment. Public invalid-POST probes reach Laravel and are correctly rejected, but do not prove provider delivery.
- The demo now generates R5 orders, retaining accurate display of the prior R1.05 attempt. This rules out the lower amount in a subsequent trial; it does not establish that amount caused the original failure. The R5 checkout is accepted and ready for the user's wallet action. Completion/ITN acceptance remains unverified. No real funds or production approval.

Reference: https://ngrok.com/docs/gateway/endpoints/http#upstream-headers (documented append behavior). Background processes started for this local session are not Windows services; stopping ngrok, the server, or the computer ends reachability. No authtoken or raw payment body is stored in this repository.
