# SIGNIA Backend

SIGNIA helps learners practice Filipino Sign Language (FSL). This Laravel 12 API serves the Nuxt frontend, manages accounts, lessons, quizzes and story progress, and forwards sign recognition requests to the private Python service.

## Run the API locally

For a **new checkout**, start with PHP 8.3, Composer 2 and a running MySQL server. From the backend repository, run the following in PowerShell.

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
```

Create an empty local MySQL database and set the `DB_*` values in `.env` to match it. The example uses `signia_db` on port `3307`. Then create the tables and start the API.

```powershell
php artisan migrate
php artisan serve
```

Open [the local health check](http://127.0.0.1:8000/up) to verify that Laravel has started. On Windows, `.\dev-local.cmd` also starts the API with PHP OPcache enabled.

For the learner interface, start the separate `signia-frontend` project. For camera recognition, also start `signia-fsl-recognition`. The API uses `RECOGNITION_SERVICE_URL` to reach Python (locally, `http://127.0.0.1:5000`). If you set `FSL_SERVICE_TOKEN`, use the same value in both services.

## Update and test

For an **existing checkout**, keep your current `.env` and application key. Follow the [update guide](docs/pull-update.md) for the order of dependency installation, cache clearing and migrations, including the admin notification migration.

Run `php artisan test` for the backend suite. The tests use in-memory SQLite and require the PHP SQLite driver. The release workflow also installs FFmpeg for media tests.

## Deployment

SIGNIA runs on DigitalOcean with Docker Compose. On `main`, the [release workflow](.github/workflows/signia-release.yml) tests the API, builds the [backend image](Dockerfile) and publishes it to GitHub Container Registry. The backend container expects its persistent media volume at `/data`. The [startup script](deploy/start.sh) checks `RAILWAY_VOLUME_MOUNT_PATH=/data`, a legacy variable still supplied by the DigitalOcean configuration.

- [Deployment security](docs/security-deployment.md)
- [Production diagnostics](docs/production-diagnostics.md)
- [Recognition training settings](docs/training-mode.md)
- [Local camera performance](docs/local-camera-performance.md)
