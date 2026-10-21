---
name: docker
description: docker/ compose stack (PR #565): app (PHP-FPM 8.3), web (nginx), MariaDB 10.11, cron gated on the database version, one-shot install service, data volume, nginx rules in step, CI check
---

## What is in docker/?
- `Dockerfile` with two targets built from the repository root: `app` (php:8.3-fpm-alpine plus mysqli, pdo_mysql, gd with JPEG/PNG/WebP/AVIF, imagick, zip, intl, exif, opcache, ftp; `docker/php-settings.conf`, named .conf as `.gitignore` drops `*.ini`) and `web` (nginx:stable-alpine with `docker/nginx.conf` and a copy of the same files for static assets).
- `docker-compose.yml`: `db` (mariadb:10.11, not published), `app`, `cron` (app image, `pinegrap-cron` as www-data), `install` (app image, `docker/install.sh` as www-data, after `app`; the unattended install, platform/install-command-line.md), `web` on `PG_HTTP_PORT` (8080). Volumes `data` (`pinegrap/data/`: config.php, uploads, layouts, backups) and `db`.
- The folder ships in the release tree (release-status.md removes only CLAUDE.md, .claude/, docs/, tools/, tests/).
- README "Try it with Docker" and README.tr.md repeat the compose header's commands, variables, URLs, volumes: change together.

## Why is imagick not from PECL?
- pecl.php.net's 504s (2026-10-21) broke every build: `IMAGICK_VERSION` tag is fetched from GitHub, `@PACKAGE_VERSION@` filled in (#1080).

## What does the image take from the repository?
- Only the root `index.php` and `pinegrap/`; `web.config` and `download_assistant.php` stay out. `Dockerfile.dockerignore` (BuildKit) is an allow-list: `index.php`, `pinegrap/`, the copied docker/ files, and of `pinegrap/data/` only the tracked files (guards, `config(default).php`, `cacert.pem`, the two starter sites), so a development machine's real config.php, uploads and backups never reach an image layer. `data/config.php` starts as `<?php ?>`.
- A new named volume is filled from the image on first start (starter sites included). Later images do not refresh the volume.

## How do nginx and the installer's rules stay the same?
- The rules between the markers in `docker/nginx.conf` are `pg_server_config_default('nginx')` for a site at `/` in `pinegrap`; `tests/docker_nginx_test.php` fails when a directive is missing or out of order, and checks the deny rules come before the PHP location.
- `location ~ \.php$` falls back to `/pinegrap/router.php` for a missing script, as Apache and IIS do. `fastcgi_pass` takes a variable with `resolver 127.0.0.11`: a fixed `app:9000` kept a stale address (502 after `app` was recreated).

## When does the cron service run job.php?
- Every `PG_CRON_INTERVAL` seconds (60), one run at a time, only while `pinegrap-job-ready` (`docker/job-ready.php`) says the database `config.version` equals the last version of `versions.php`. The installer writes config.php before it runs the upgrade steps; a job started then would work on half-built tables. A database behind or ahead of the code waits too.

## What about updates?
- The software is in the image: a release is installed by pulling (docker-image-publishing.md) or building the new image and recreating the containers. A panel update lasts only until the app container is recreated (web keeps the old static files; the code is then older than the database and cron waits). The owner left it so: docker-decisions.md.

## How is it checked?
- `tools/check_docker.sh` (CI: `.github/workflows/docker-check.yml`, every push to development, pull requests that touch docker/ or the files the images depend on): build, a file planted in `pinegrap/data/files/` must not reach the image, extensions and settings, 403 for data/, includes/ and dumps, a static file, the install service (idle without an administrator, installs the starter site with one, `skipped:` the next time), home page, control panel, an uploaded picture over nginx, a recreated app container, a job.php run from the cron container.
- The cloud sandbox has no Docker daemon: a Dockerfile change is proven by this CI check.

Related: docs/memory/platform/server-config-web-root-rules.md
