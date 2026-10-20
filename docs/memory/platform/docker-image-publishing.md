---
name: docker-image-publishing
description: Docker images on GHCR (PR #737): docker-publish.yml on a published release, pinegrap-app/-web for amd64 and arm64, tags v<version> and latest, re-run by tag, compose PG_IMAGE_TAG, package visibility
---

## When are the images published?
- `.github/workflows/docker-publish.yml` runs when a GitHub release is published (the owner's release step, release-status.md). The tag must be `v<year>.<month>.<patch>` with a published release behind it, and the job builds that tag's commit. A draft never runs it; a pre-release gets its version tag but not `latest`.
- A `release` event runs the workflow file of the tagged commit; `.github/` is in the release tree, `tools/` is not, so the workflow uses nothing from `tools/`.
- Actions › Docker publish › Run workflow with a release tag publishes it again (a failed run). GitHub shows that button only for a workflow on the default branch (`main`), so after the first release that carries the file.

## What is pushed?
- `ghcr.io/kodpen/pinegrap-app` and `ghcr.io/kodpen/pinegrap-web` (the Dockerfile's `app` and `web` targets), tagged with the release tag (`v2026.11.0`, as the plan names it) and with `latest` only when `GET releases/latest` answers that tag, so an older release published again never moves `latest`.
- linux/amd64 on `ubuntu-24.04` and linux/arm64 on `ubuntu-24.04-arm` (free for public repositories); under QEMU the gd, intl and imagick compiles would run emulated. Each runner pushes its two images by digest; the merge job joins the digests into one index per tag with `docker buildx imagetools create`.
- Image labels: source (links the package to the repository), version, revision (the tag's commit), title, description, MIT. The index carries source, description, licenses and version as annotations: the package page reads those for a multi-platform image.
- Release builds start from an empty builder, so the base images and Alpine packages are the current ones.

## What does it need on GitHub?
- No secret: `GITHUB_TOKEN` with `packages: write`, declared in the workflow. A fork's run builds but does not log in or push.
- A container package created by the first push is private. The owner sets `pinegrap-app` and `pinegrap-web` to public once in the organisation's package settings. A package made by hand before the first run needs this repository given write access under the package's Actions access.

## How do pull requests check it?
- Pull requests that change `docker/` (not the compose file) or the workflow run both builds with a `cacheonly` output: no login, no push, so an arm64 failure shows before a release.

## How does compose use the images?
- Each `image:` is `ghcr.io/kodpen/pinegrap-{app,web}:${PG_IMAGE_TAG:-latest}` next to its `build:` section. Without a `pull_policy` Compose pulls a missing image and builds from the files when the pull fails (before the first release, or while a package is private); `up -d --build` always builds, under the same name. Updating: `docker compose pull`, then `up -d`.
- The README Docker sections (EN and TR) start with `up -d`, so a trial pulls the published images; `--build` is for a copy the reader changed. They name the images, `PG_IMAGE_TAG` and the update commands: change them with the compose file.
- `tools/check_docker.sh` sets `PG_IMAGE_TAG=check`: the CI check runs its own build and never replaces a pulled `latest`.

Related: docs/memory/platform/docker.md, docs/memory/release/release-status.md
