# Installation

The repository began as an empty directory, so this package lives at its root. No Laravel application or previous payment implementation existed.

Until published to an authorized Composer registry, add this repository to the consuming application's `composer.json`:

```json
{"repositories": [{"type": "path", "url": "../vmt_payfast", "options": {"symlink": true}}]}
```

```sh
composer require vmt/payfast:dev-codex/vmt-payfast-integration
php artisan vendor:publish --tag=payfast-config
php artisan migrate
```

For deployment use a private VCS repository URL or private Composer registry once configured, pin a reviewed release, and use `composer install --no-dev --optimize-autoloader`. The package is not currently published on Packagist. PHP 8.3+, OpenSSL, PDO MySQL (production), and a normal Laravel application are required. SQLite is used for fast unit/feature tests.

Laravel discovers `Vmt\PayFast\Providers\PayFastServiceProvider`. If discovery is disabled, add that class to `bootstrap/providers.php`. Migrations load from the package; do not copy and also load duplicate migrations. Back up the database before deployment. Down migrations drop payment history; never use rollback as a casual production operation.

Configure HTTPS callbacks and credentials as described in CONFIGURATION.md. Retain APP_KEY and any previous encryption keys securely; losing keys makes original credential snapshots unreadable. Do not generate a new application key during deployment.

In `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;
Schedule::command('payfast:dispatch-outbox')->everyMinute()->withoutOverlapping();
```

Run Laravel's scheduler, supervise durable queue workers, and register the example completion listener in the host application. A missed scheduler run is recoverable from the outbox. Alert on undispatched rows and unsuccessful callbacks. Do not fulfil orders from browser redirects.
