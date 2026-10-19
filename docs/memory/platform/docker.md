---
name: docker
description: docker/ compose stack (PR #565): app (PHP-FPM 8.3) and web (nginx) images, MariaDB 10.11, cron service gated on the database version, data volume, nginx rules kept in step, tools/check_docker.sh in CI
---

## What is in docker/?
- `Dockerfile` with two targets built from the repository root: `app` (php:8.3-fpm-alpine plus mysqli, pdo_mysql, gd with JPEG/PNG/WebP/AVIF, imagick, zip, intl, exif, opcache, ftp; `docker/php-settings.conf`, named .conf because `.gitignore` drops every `*.ini`) and `web` (nginx:stable-alpine with `docker/nginx.conf` and a copy of the same files for static assets).
- `docker-compose.yml`: `db` (mariadb:10.11, not published), `app`, `cron` (app image, `pinegrap-cron` as www-data), `web` on `PG_HTTP_PORT` (8080). Volumes `data` (`pinegrap/data/`: config.php, uploads, layouts, backups) and `db`.
- The folder ships in the release tree (release-status.md removes only CLAUDE.md, .claude/, docs/, tools/, tests/).

## What does the image take from the repository?
- Only the root `index.php` and `pinegrap/`; `web.config` and `download_assistant.php` stay out. `Dockerfile.dockerignore` (BuildKit) is an allow-list: `index.php`, `pinegrap/`, the copied docker/ files, and of `pinegrap/data/` only the tracked files (guards, `config(default).php`, `cacert.pem`, the two starter sites), so a development machine's real config.php, uploads and backups never reach an image layer. `data/config.php` starts as `<?php ?>`.
- A new named volume is filled from the image on first start (starter sites included). Later images do not refresh what is already in the volume.

## How do nginx and the installer's rules stay the same?
- The rules between the markers in `docker/nginx.conf` are `pg_server_config_default('nginx')` for a site at `/` in `pinegrap`; `tests/docker_nginx_test.php` fails when a directive is missing or out of order, and checks the deny rules come before the PHP location.
- `location ~ \.php$` falls back to `/pinegrap/router.php` for a missing script, as Apache and IIS do. `fastcgi_pass` takes a variable with `resolver 127.0.0.11`: with a fixed `app:9000` nginx kept the address found at start and answered 502 after `app` was recreated.

## When does the cron service run job.php?
- Every `PG_CRON_INTERVAL` seconds (60), one run at a time, only while `pinegrap-job-ready` (`docker/job-ready.php`) says the database `config.version` equals the last version of `versions.php`. The installer writes config.php before it runs the upgrade steps in the same request; a job started then would work on half-built tables. A database behind or ahead of the code waits too.

## What about updates?
- The software is in the image: a release is installed by building or pulling the new image and recreating the containers. An update installed from the panel lasts until the app container is recreated, and the web container keeps the old static files; recreating then leaves the code older than the database (the cron service stops running the job).

## How is it checked?
- `tools/check_docker.sh` (CI: `.github/workflows/docker-check.yml`, every push to development, pull requests that touch docker/ or the files the images depend on): build, a file planted in `pinegrap/data/files/` must not reach the image, extensions and settings, 403 for data/, includes/ and dumps, a static file, installation of the starter site through the installation screen with host `db`, home page, control panel, an uploaded picture over nginx, a recreated app container, a job.php run from the cron container.
- The cloud sandbox reaches no image registry; the same nginx block was also run there over PHP-FPM and MariaDB (pages, files, rules, installer).

Related: docs/memory/plans/docker-image.md, docs/memory/platform/server-config-web-root-rules.md
