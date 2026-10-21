# Updating an existing Windows Laravel checkout

Stop this checkout's API server first. Commit or stash your own edits before pulling. Run from `signia-backend` on `main`, after the maintainer has pushed the release:

```powershell
git pull --ff-only
composer install
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate
.\dev-local.cmd
```

If the local launcher cannot use PHP OPcache, start `php artisan serve` instead. Use only one API server for this checkout. Ensure your existing database is running and `.env` points to the intended local database before migration; back up valuable data first.

The October 21 notification feature requires `2026_10_21_180000_create_admin_notifications_table.php` and the follow-up `2026_10_21_190000_persist_admin_notification_settings_and_events.php`. The follow-up adds persistent preferences and unique training-delivery receipts without changing users, purchases, or learning progress. Installing Composer packages alone does not apply migrations. `migrate` applies all outstanding migrations, so inspect `php artisan migrate:status` if the checkout is older.

The earlier security commit `87fc89a` updated `composer.lock`. `composer install` synchronizes the locked versions without selecting new versions. Do not substitute `composer update`.

Use the targeted clear commands above before migrating. The new migration imports existing notification preferences from the application cache into the database, so do not run `optimize:clear` or `cache:clear` before that migration. Afterward, preferences survive application-cache clearing. Run the migration before restarting the updated API.

Preserve your `.env` and `APP_KEY`. Do not run `key:generate`, `migrate:fresh`, or the general `db:seed` during a routine pull. Changes to `database/seeders/data/labels.csv` are not automatically imported by migration. If curriculum data must be synchronized, review a content-only import separately; the existing seeders can rewrite content and the general seeder resets demo accounts.

For the separate Nuxt application, install dependencies in `signia-frontend` with `.\npm-local.cmd ci` after its Node setup. Composer does not install frontend packages. Backend `npm ci` is only needed if you use Laravel's own Vite assets or the `composer run dev` workflow; this API launcher does not require that workflow.

Local recognition uses `RECOGNITION_SERVICE_URL=http://127.0.0.1:5000`. If `FSL_SERVICE_TOKEN` is configured, its value must match the Python service. Restart both services after changing those values. Pull the matching frontend and Python revisions as part of this release.

For local verification, use `php artisan test`; the repository test configuration uses in-memory SQLite, not production MySQL. New machines and production deployments require additional setup.
