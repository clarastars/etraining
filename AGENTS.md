# eTraining Agent Instructions

## Commands
- **Backend Working Directory**: All `artisan`, `composer`, and `phpunit` commands must be executed from within `backend/`.
- **Setup Dev**: `php artisan setup:dev` (interactive prompt for migrations, seeders, and admin user creation).
- **Run Tests**: `php artisan test` or `vendor/bin/phpunit` (tests use in-memory SQLite by default per `backend/phpunit.xml`).
- **Run Single Test**: `php artisan test --filter <TestName>` or `vendor/bin/phpunit tests/Feature/CompaniesTest.php`.
- **Localization (i18n)**: After adding translation keys, ensure they exist in both Arabic and English, then run `php artisan vue-i18n:generate`.
- **Frontend Build**: `npm run dev` / `npm run prod` inside `backend/` (requires `NODE_OPTIONS=--openssl-legacy-provider`).
- **Training disclosure Drive import**: Share the parent Drive folder with `GOOGLE_DRIVE_CLIENT_EMAIL`, set `TRAINING_DISCLOSURE_DEFAULT_TEAM_ID` (or pass `--team=`), ensure S3 is configured, then from `backend/`: `php artisan recorded-courses:import-from-drive` (use `--dry-run` first). Direct videos and videos inside `.zip` archives are extracted and stored on the `s3` disk.

## Architecture & Conventions
- **Tech Stack**: Laravel 8 (PHP 8.0+), Inertia.js, Vue 2, Tailwind CSS, MySQL/MariaDB, Redis.
- **Project Structure**: Core application is in `backend/`. Docker/ECS deployment configuration and shell scripts are in root (`docker-compose.yml`, `deploy-backend.sh`).
- **Coding Standards**:
  - Adhere to PSR-12 and strict typing (`declare(strict_types=1);`).
  - Use lowercase with dashes for directories (e.g., `app/Http/Controllers`).
  - Leverage Eloquent ORM, service container dependency injection, and Form Requests for request validation.
- **Rules Reference**: Additional architectural guidelines are located in `.cursor/rules/laravel-guidelines.mdc`.
