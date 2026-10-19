---
name: docker-image
description: Plan (approved 2026-10-19, steps 1-4 built in PRs #565, #621 and #NNN): docker/ compose stack in the release tree, unattended install service, README section; GHCR publishing left
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
- Step 4 (PR #NNN): "Try it with Docker" in README.md and "Docker ile deneyin" in README.tr.md (wizard values, the install variables, `docker/.env`, volumes, updates with the automated upgrade run as www-data), and the plan's `[NEW]` changelog line.
- Differences from the first sketch: the web image carries its own copy of the files (static assets); one data volume covers uploads (they are under `pinegrap/data/files`); the job waits for the database to be at the code version; the unattended install takes a starter site, not a design template (a template's widget layouts are built by the editor's JavaScript in the browser); the install service runs at every start and does nothing once a site is installed.

## What remains?
5. GHCR: a release-tag workflow or the owner's release step pushes `ghcr.io/kodpen/pinegrap-app` and `-web`; the compose file then names those images; note in `release-status.md`.

## Open questions?
- Panel updates inside the container last only until it is recreated, and the web container keeps the old static files; recreating then leaves the code older than the database. Options: hide Software Update when running in the image (needs a product flag), or keep the whole web root in a volume (then a new image no longer updates the code). Owner decision.
- Apache variant: not shipped; nginx only.

## Where to start?
- Step 5: a workflow on release tags (or the owner's release step) that builds both targets and pushes them; then `image:` in the compose file names them and the README section says to pull instead of build.
