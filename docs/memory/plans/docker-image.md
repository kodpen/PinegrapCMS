---
name: docker-image
description: Plan (approved 2026-10-19, steps 1-3 built in PR #565 and #621): docker/ compose stack in the release tree with an unattended install service; README section and GHCR publishing left
---

## What did the owner decide?
- 2026-10-19: the `docker/` folder ships in the release tree (unlike `tools/`, `tests/`, `docs/`), so a released tag can be started with one command. The sandbox script `tools/setup_sandbox.sh` stays the development tool.

## Why?
- Ghost, Directus and Medusa publish official images; a one-command trial is how most open-source CMSs are first evaluated. Pinegrap's "upload and run" promise is kept: Docker is an addition, not a requirement.

## What is decided?
- First start: the installer runs in the browser as on any host (database host `db`), or, with `PG_INSTALL_ADMIN_EMAIL` and `PG_INSTALL_ADMIN_PASSWORD` set, `docker compose up` finishes the install unattended through the `install/index.php` CLI mode (admin, DB, starter site), with the English starter site as default so a trial site has content.
- Config: `data/config.php` is generated into the volume by the installer; environment variables are read only by the install helper and the containers, not by the product (no `.env` support in the product itself).
- Updates: the image tag follows the release (`v2026.11.0`); the README recommends pulling the new image. Repair Software works unchanged.
- Image published to GHCR on a release tag by the owner's release step (to be documented in `release-status.md`).

## What is built?
- Steps 1 and 3 (PR #565): `docker/` (Dockerfile with `app` and `web` targets, compose with db/app/cron/web, nginx.conf, php.ini, cron loop, readiness check), `tools/check_docker.sh` and the Docker check workflow. Rules and reasons: docs/memory/platform/docker.md.
- Step 2 (PR #621): `php install/index.php install` (`includes/install_cli.php`, docs/memory/platform/install-command-line.md), the one-shot `install` service (`docker/install.sh`), `tools/check_docker.sh` installs with it and checks the second start leaves the site alone.
- Differences from the first sketch: the web image carries its own copy of the files (static assets); one data volume covers uploads (they are under `pinegrap/data/files`); the job waits for the database to be at the code version; the unattended install takes a starter site, not a design template (a template's widget layouts are built by the editor's JavaScript in the browser); the install service runs at every start and does nothing once a site is installed.

## What remains?
4. README section (EN/TR) "Try it with Docker" (S): `docker compose -f docker/docker-compose.yml up -d --build`, port 8080, database host `db` with the compose values, `PG_INSTALL_ADMIN_EMAIL`/`PG_INSTALL_ADMIN_PASSWORD` for a site without the wizard, `PG_DB_PASSWORD` for anything but a trial. The plan's one `[NEW]` changelog line goes in with it.
5. GHCR: a release-tag workflow or the owner's release step pushes `ghcr.io/kodpen/pinegrap-app` and `-web`; the compose file then names those images; note in `release-status.md`.

## Open questions?
- Panel updates inside the container last only until it is recreated, and the web container keeps the old static files; recreating then leaves the code older than the database. Options: hide Software Update when running in the image (needs a product flag), or keep the whole web root in a volume (then a new image no longer updates the code). Owner decision.
- Apache variant: not shipped; nginx only.

## Where to start?
- Step 4; the header of `docker/docker-compose.yml` lists the settings the README section needs.
