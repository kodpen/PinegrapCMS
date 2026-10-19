---
name: docker-image
description: Plan (approved 2026-10-19, not started): docker/ folder in the release tree with a PHP-FPM + nginx + MariaDB compose stack, cron container, demo seed; README one-command trial
---

## What did the owner decide?
- 2026-10-19: the `docker/` folder ships in the release tree (unlike `tools/`, `tests/`, `docs/`), so a released tag can be started with one command. The sandbox script `tools/setup_sandbox.sh` stays the development tool.

## Why?
- The repo has no Dockerfile or compose file (CI uses database service containers only). Ghost, Directus and Medusa publish official images; a one-command trial is how most open-source CMSs are first evaluated. Pinegrap's "upload and run" promise is kept: Docker is an addition, not a requirement.

## What is decided?
- Layout: `docker/Dockerfile` (php:8.3-fpm-alpine with mysqli, mbstring, openssl, curl, gd + imagick, zip, intl; `pinegrap/` copied in), `docker/nginx.conf` generated from the same rules `server_config.php` writes (`nginx.conf.sample`), `docker/docker-compose.yml` with `app`, `web`, `db` (MariaDB 10.11), `cron` (same image, runs `php /var/www/pinegrap/job.php` every minute), named volumes for `pinegrap/data/` and uploads.
- First start: the installer runs in the browser as on any host; an optional `PG_INSTALL_*` env set lets `docker compose up` finish the install unattended through `install/index.php` CLI mode (admin, DB, template), with the demo template as default so a trial site has content.
- Config: `data/config.php` is generated into the volume by the installer; environment variables are read only by the install helper, not by the product (no `.env` support in the product itself, see the platform decisions).
- Updates: the image tag follows the release (`v2026.11.0`); in-container panel updates are allowed but the README recommends pulling the new image. Repair Software works unchanged.
- CI: a workflow job builds the image and runs the installer in CLI mode against the MariaDB service on the `development` branch; the image is published to GHCR on a release tag by the owner's release step (documented in `release-status.md`).

## What remains?
1. Dockerfile + compose + nginx config + cron container (S).
2. Unattended install helper reading env vars (S).
3. CI build-and-install job (S).
4. README section (EN/TR) "Try it with Docker" (S).
5. Memory: `docs/memory/platform/docker.md`; release-status note on GHCR publishing.

## Open questions?
- Apache variant: not shipped; nginx only (rules exist for it already).

## Where to start?
- Step 1; read `includes/server_config.php` (nginx rules) and `docs/memory/workflow/sandbox-and-environment.md`.
