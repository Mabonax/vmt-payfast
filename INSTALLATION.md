# Installation

The repository began as an empty directory, so this package lives at its root. No Laravel application or previous payment implementation existed.

For installation directly from GitHub, add a Composer VCS repository to the consuming application's `composer.json`:

```json
{"repositories": [{"type": "vcs", "url": "https://github.com/Mabonax/vmt-payfast.git"}]}
```

```sh
composer require vmt/payfast:dev-main
```

Until an approved tag exists, `dev-main` is a development dependency, not a production release. Composer records the resolved commit in the consuming application's lock file. Review that commit and deploy with `composer install`, not an unreviewed update. GitHub access must be configured separately if repository visibility requires it.

For local development, retain a path repository instead:

```json
{"repositories": [{"type": "path", "url": "../vmt_payfast", "options": {"symlink": true}}]}
```

```sh
composer require vmt/payfast:dev-main
```

If the local package is checked out on a feature branch, either require that exact `dev-branch-name` or explicitly map its path repository version for local testing:

```json
{"repositories": [{"type": "path", "url": "../vmt_payfast", "options": {"symlink": true, "versions": {"vmt/payfast": "dev-main"}}}]}
```

Then configure either installation:

```sh
php artisan vendor:publish --tag=payfast-config
php artisan migrate
```

The package is not currently published on Packagist. PHP 8.3+, OpenSSL, PDO MySQL (production), and a normal Laravel application are required. SQLite is used for fast unit/feature tests. After sandbox acceptance, MySQL CI, deployment review and explicit release approval, a maintainer can create and push a reviewed semantic version tag (for example `v0.1.0` as an illustrative candidate, not a tag created by this work). Consumers can then pin `vmt/payfast:0.1.0` or an approved compatible constraint and commit their lock file. A tag alone does not establish production approval. Do not tag or publish a production release before acceptance.

For an isolated sandbox application see [DEMO_APPLICATION.md](DEMO_APPLICATION.md). It runs outside the package source and has no production payment path.

Laravel discovers `Vmt\PayFast\Providers\PayFastServiceProvider`. If discovery is disabled, add that class to `bootstrap/providers.php`. Migrations load from the package; do not copy and also load duplicate migrations. Back up the database before deployment. Down migrations drop payment history; never use rollback as a casual production operation.

Configure HTTPS callbacks and credentials as described in CONFIGURATION.md. Retain APP_KEY and any previous encryption keys securely; losing keys makes original credential snapshots unreadable. Do not generate a new application key during deployment.

In `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;
Schedule::command('payfast:dispatch-outbox')->everyMinute()->withoutOverlapping();
```

Run Laravel's scheduler, supervise durable queue workers, and register the example completion listener in the host application. A missed scheduler run is recoverable from the outbox. Alert on undispatched rows and unsuccessful callbacks. Do not fulfil orders from browser redirects.
