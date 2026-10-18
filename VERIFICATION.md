# Verification evidence

**Current Phase 2 evidence:** see PRODUCTION_READINESS.md. Remote Laravel 12/13 passed 30 active tests / 183 assertions each; separate MySQL 8.4 tests passed 1 test / 9 assertions / zero skips each; the isolated demo passed 7 tests / 39 assertions. The sections below preserve the Phase 1 baseline and do not supersede the current matrix.

Environment: Windows, PHP 8.3.35, Composer 2.10.3; inspected 2026-10-18. Empty repository baseline: no previous code, tests or failures. Package runner is PHPUnit/Testbench; no root Artisan application exists. Pint is installed; PHPStan/Larastan was not preconfigured and is not installed.

## Commands

```sh
composer validate --strict
php vendor/bin/pint --test
php vendor/bin/phpunit
```

Laravel 12 matrix resolution on this Windows machine (calling the PHAR avoids `composer.bat` consuming caret constraints):

```powershell
php C:\composer\composer.phar update --with 'orchestra/testbench:^10.0' --with 'laravel/framework:^12.0' --with 'phpunit/phpunit:^11.5' --no-interaction --prefer-dist
php vendor/bin/phpunit
```

Laravel 13/default restoration:

```powershell
php C:\composer\composer.phar update --no-interaction --prefer-dist
php vendor/bin/phpunit
```

Local framework resolution:

| Framework | Testbench | PHPUnit | Outcome |
|---|---|---|---|
| Laravel 12.70.0 | 10.12.0 | 11.5.57 | 29 tests discovered, 27 active passed, 168 assertions, 2 conditional concurrency tests skipped |
| Laravel 13.37.0 | 11.3.0 | 12.5.40 | 29 tests discovered, 27 active passed, 168 assertions, 2 conditional concurrency tests skipped |

Composer installations reported no security vulnerability advisories. Dependency resolution was not bypassed with advisory ignores or platform overrides. An initial Windows BAT invocation stripped carets and failed resolution; direct PHAR invocation resolved both majors normally. An initial test fixture incorrectly stacked HTTP fakes; corrected by resetting the HTTP factory. No failing tests remain in the final checked suite. PHPUnit/Pint required an unrestricted execution context because PHP could not reliably read/write sandboxed files.

## Coverage and limits

Unit/feature checks cover protocol encoding and state transitions; checkout mode, credentials and amount validation; encrypted snapshots; invalid/modified signature, source, merchant, amount, transaction and status; provider rejection and network timeout; repeated/late ITNs; return-before-notification; original-credential rotation; cross-merchant reference substitution; scoped tenant fulfilment; outbox retries; transactional rollback after injected DB failure; unique provider ID collision; fulfilment retry/rollback; provider bindings, publishing, routes, HTTP confirmation payload, authenticated return status and scoped pending reporting.

Feature tests use SQLite and mocked PayFast calls. They cannot establish MySQL lock behavior, real DNS/provider acceptance, real queue worker operation or live settlement behavior.

## Two-process MySQL concurrency acceptance

`tests/Feature/MySqlConcurrencyTest.php` launches two independent PHP processes against the same transaction and MySQL database, overlaps their validation, and checks one completion audit/outbox/fulfilment marker. Use an isolated disposable database, never a production or application database. The test migrates package tables and deletes only its own payment rows; retain or remove the disposable database under your normal test environment lifecycle.

Set `PAYFAST_TEST_MYSQL=1`, `PAYFAST_TEST_DB_HOST`, `PAYFAST_TEST_DB_PORT`, `PAYFAST_TEST_DB_NAME`, `PAYFAST_TEST_DB_USER`, and `PAYFAST_TEST_DB_PASSWORD` through the environment, then run:

```sh
php vendor/bin/phpunit --filter MySqlConcurrencyTest
```

Local result: not run, local MySQL port 3306 refused connection. The two related tests skip in ordinary runs; they are not counted as acceptance. `.github/workflows/tests.yml` configures Laravel 12/13 with MySQL 8.4 for this check. Workflow source is provided but no remote CI run has occurred.

## Real PayFast acceptance

Not run. No merchant credentials or public HTTPS callback were supplied. Follow SANDBOX_TESTING.md. No automated mock is claimed as a real payment, independent provider signature vector or production approval.
