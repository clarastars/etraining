---
name: production
description: >-
  Access and work on the eTraining production server (DigitalOcean) via the
  `etraining` shell alias, including artisan, tinker, logs, and live data
  inspection. Use when the user mentions production, prod, live server,
  DigitalOcean, the etraining SSH alias, or running artisan/tinker against
  production. AWS is not used for this application.
---

# Production (DigitalOcean)

## Access

Production is a DigitalOcean droplet. From the local machine:

```bash
etraining
```

That alias is `ssh deploy@206.189.53.231`. The session starts in `/home/deploy`.

App root for Laravel commands:

```bash
cd /var/www/etraining/backend
```

All `php artisan`, `tinker`, `composer`, and similar commands must run from that directory.

Related local aliases (optional):

| Alias | Purpose |
|-------|---------|
| `etraining` | SSH into production |
| `uetraining` | `cd /var/www/etraining && git pull` on production |
| `tetraining` | SSH into a separate (non-prod) host |

## Non-interactive remote commands

Prefer one-shot SSH from the local shell so output returns to the agent:

```bash
etraining 'cd /var/www/etraining/backend && php artisan about'
etraining 'cd /var/www/etraining/backend && php artisan tinker --execute="echo App\\Models\\User::count();"'
```

For longer exploration, open an interactive `etraining` session, then `cd /var/www/etraining/backend`.

## Common operations

From `/var/www/etraining/backend`:

- Inspect: `php artisan about`, `php artisan route:list`, `php artisan tinker`
- Logs: `storage/logs/laravel.log` (and related log files under `storage/logs/`)
- Queue / cache / config: only when the user asks (`queue:restart`, `cache:clear`, `config:cache`, etc.)

Repo checkout on the server lives at `/var/www/etraining` (backend app under `backend/`).

## AWS

AWS is **not** used by this application anymore. Do not suggest AWS CLI, ECS, S3 deploy paths, or AWS-hosted assumptions for production work. Prefer DigitalOcean + the `etraining` access path above.

## Safety rules

Production holds real user data. Default to read-only investigation.

1. Prefer `tinker` / SELECT-style inspection before any write.
2. Never run destructive commands (`migrate:fresh`, `db:wipe`, mass deletes, `rm`, force-pushes, service wipes) unless the user explicitly requests them.
3. For data changes (updates, deletes, one-off fixes): state the exact plan and wait for confirmation unless the user already gave a clear go-ahead.
4. Do not restart PHP-FPM, Nginx, Redis, MySQL, or queues unless asked.
5. Do not commit or deploy from production unless the user explicitly asks.
6. Keep secrets out of chat transcripts (`.env`, tokens, passwords). Reference names only.

## Workflow

1. Confirm the task is meant for **production** (not local/staging).
2. Reach the server with `etraining` (one-shot or interactive).
3. Work in `/var/www/etraining/backend`.
4. Investigate with read-only artisan/tinker/logs first.
5. Apply changes only with explicit user intent; summarize what changed afterward.
