---
name: ci-workflows
description: GitHub Actions workflows: what each runs and when, action versions (major tags, node24) and how to bump them, the Node line of the JavaScript checks, runner labels
---

## Which workflows are there?
- `php-checks.yml` (every pull request, pushes to main and development): the checks listed under "When a task is done" in CLAUDE.md and `node --check` of three large panel scripts. Static analysis: docs/memory/workflow/static-analysis.md.
- `install-check.yml` (same triggers): the installer and every upgrade step on six MySQL/MariaDB images (docs/memory/release/database-server-compatibility.md).
- `docker-check.yml`: the compose stack end to end, on pull requests that touch `docker/` or the files the images depend on and on every push to development (docs/memory/platform/docker.md).
- `docker-publish.yml`: GHCR images when a release is published (docs/memory/platform/docker-image-publishing.md).

## Which action versions, and how is one bumped?
- Major tags (`actions/checkout@v7`), all on the node24 runtime since PR #872: checkout v7, setup-node v7, shivammathur/setup-php v2 (its moving v2 tag is on node24; there is no v3), docker/setup-buildx-action v4, docker/login-action v4, docker/build-push-action v7, upload-artifact v7, download-artifact v8.
- Before a major bump, read the action's changelog; a bump that changes what a step does gets a PR of its own.
- checkout v4 to v7 changed nothing here: v6 keeps the token in a file under `$RUNNER_TEMP` (no step runs authenticated git; `tools/static_analysis.sh` fetches public commits into its own repository), v7 refuses fork code on `pull_request_target` and `workflow_run` (not used). setup-node v5-v7: the automatic npm cache needs a `packageManager` field in a root `package.json` (there is none); `always-auth` and `registry-url` are not used.

## Which Node runs the JavaScript checks?
- Node 24 since PR #889, an LTS line (Node 20 support ended on 2026-04-30). The same Node runs `node --check`, terser through npx (`tools/build_js.php --check`) and `npm ci` + esbuild (`tools/build_code_editor.php --check`). Node 24 with npm 11 builds the same files as Node 22 with npm 10, so the switch needed no rebuild.
- The job name carries the versions ("PHP 8.3, Node 24"); change it with them.

## Which runners?
- `ubuntu-latest`, except the Docker publish builds: `ubuntu-24.04` and `ubuntu-24.04-arm`, one per architecture (docs/memory/platform/docker-image-publishing.md).

Related: docs/memory/workflow/static-analysis.md, docs/memory/workflow/git-branches-and-shared-files.md
