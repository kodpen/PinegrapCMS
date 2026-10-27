---
name: ci-workflows
description: GitHub Actions workflows: what each runs and when, the PHP versions the checks run on (8.3, 7.1), action versions (major tags, node24) and how to bump them, the Node line, runner labels
---

## Which workflows are there?
- `php-checks.yml` (every pull request marked ready, a draft only when it is a merge train, pushes to main and development): the checks under "When a task is done" in CLAUDE.md, `node --check` of three panel scripts and, on pull requests to development, the branch guards (`branch-guards.md`). Static analysis: docs/memory/workflow/static-analysis.md. `tools/pre_push.sh` runs its fast steps before a push (pre-push-checks.md); a fast step added here is added there too.
- `install-check.yml` (same triggers): the installer and every upgrade step on six MySQL/MariaDB images (`release/database-server-compatibility.md`); filtered on pull requests (`install-check-filter.md`).
- `docker-check.yml`: the compose stack end to end, on pull requests that touch `docker/` or the files the images depend on and on every push to development (docs/memory/platform/docker.md).
- `docker-publish.yml`: GHCR images when a release is published (docs/memory/platform/docker-image-publishing.md).

## Which PHP versions do the checks run on?
- `php-checks.yml` has two jobs: `checks` on PHP 8.3 (every step) and `php71` (#1534) on 7.1, the oldest PHP the product supports: `php tools/lint.php --vendored` (the bundled libraries too) and the unit tests (`-d memory_limit=-1`).
- Why 7.1: 8.3 and PHPCompatibility (product tree only) missed what a run by hand on 7.1 found: a bundled SDK that did not parse (#1353), and code that behaves differently there (#1354, #1355, #1356, #1371, #1443, #1446; tests #1357).
- Tests run on 7.1 too: no PHP 7.2+ syntax or function in `tests/` (PHPCompatibility does not read it); a check that needs a newer PHP skips itself. GD reads WebP from a string (`imagecreatefromstring()`) from 7.3 and writes lossless WebP (`IMG_WEBP_LOSSLESS`) from 8.1; quality 101 stops PHP 7.1.

## Which action versions, and how is one bumped?
- Major tags (`actions/checkout@v7`), all on the node24 runtime since PR #872: checkout v7, setup-node v7, shivammathur/setup-php v2 (its moving v2 tag is on node24; there is no v3), docker/setup-buildx-action v4, docker/login-action v4, docker/build-push-action v7, upload-artifact v7, download-artifact v8.
- Before a major bump, read the action's changelog; a bump that changes what a step does gets a PR of its own.
- checkout v4 to v7 changed nothing here: v6 keeps the token in a file under `$RUNNER_TEMP` (no step runs authenticated git; `tools/static_analysis.sh` fetches public commits into its own repository), v7 refuses fork code on `pull_request_target` and `workflow_run` (not used). setup-node v5-v7: the automatic npm cache needs a `packageManager` field in a root `package.json` (there is none); `always-auth` and `registry-url` are not used.

## Which Node runs the JavaScript checks?
- Node 24 since PR #889, an LTS line (Node 20 support ended on 2026-04-30). The same Node runs `node --check`, terser through npx (`tools/build_js.php --check`) and `npm ci` + esbuild (`tools/build_code_editor.php --check`). Node 24 with npm 11 builds the same files as Node 22 with npm 10, so the switch needed no rebuild.
- The job name carries the versions ("PHP 8.3, Node 24"); change it with them.

## Which runners?
- `ubuntu-latest`, except the Docker publish builds: `ubuntu-24.04` and `ubuntu-24.04-arm`, one per architecture (docs/memory/platform/docker-image-publishing.md).
- `ubuntu-latest` moves from Ubuntu 24.04 to 26.04 between 2026-10-19 and 2026-11-19 (actions/runner-images#14748), so a run may land on either. A trial on `ubuntu-26.04` (draft PR #888, not merged) passed every job: PHP checks, the install check on all six databases, the Docker check and Docker publish's prepare job. Nothing is pinned; pin to `ubuntu-24.04` only if a job breaks on 26.04.

Related: docs/memory/workflow/static-analysis.md, docs/memory/workflow/git-branches-and-shared-files.md
