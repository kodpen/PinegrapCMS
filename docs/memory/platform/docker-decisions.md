---
name: docker-decisions
description: Owner's Docker decisions (2026-10-19/20): docker/ in the release tree, an addition only, no env in the product, nginx only, Software Update in the container left as it is
---

## What did the owner decide, and why?
- 2026-10-19: the `docker/` folder ships in the release tree (unlike `tools/`, `tests/`, `docs/`), so a released tag starts with one command. Ghost, Directus and Medusa publish official images; a one-command trial is how most open-source CMSs are first tried. Docker is an addition, not a requirement: "upload and run" stays the main way, and `tools/setup_sandbox.sh` stays the development tool.
- The images follow the release (`v2026.11.0`, `latest`) and are published with the GitHub release (docker-image-publishing.md); the README tells readers to pull them.

## Does the product read environment variables?
- No. The installer writes `data/config.php` into the data volume; the `PG_*` variables are read only by the install helper (`includes/install_cli.php`) and the containers' scripts. The product has no `.env` support.

## What about Software Update inside the container?
- 2026-10-20: left as it is. A panel update lasts until the app container is recreated and the web container keeps the old static files, so the code then runs older than the database; the README says so and tells readers to pull the new images instead.
- Weighed and not taken: hiding Software Update when the site runs from the image (a marker file baked into the image, since the product reads no environment), and keeping the whole web root in a volume (a new image would then no longer update the code).
- Repair Software works unchanged.

## Which web server?
- nginx only (`docker/nginx.conf`). No Apache variant is shipped, although the Apache rules exist for other hosts.

## How did the build differ from the first sketch?
- The web image carries its own copy of the files (static assets); one data volume covers uploads (`pinegrap/data/files`); the general job waits for the database to be at the code version; the unattended install takes a starter site, not a design template (install-command-line.md); the install service runs at every start and does nothing once a site is installed.
- Built in PRs #565 (stack, CI check), #621 (unattended install), #689 (README section), #737 (GHCR images), #797 (README: pulling the images).

Related: docs/memory/platform/docker.md, docs/memory/platform/docker-image-publishing.md, docs/memory/platform/install-command-line.md
