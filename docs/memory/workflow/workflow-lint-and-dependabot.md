---
name: workflow-lint-and-dependabot
description: The actionlint job (pinned, SHA-256, shellcheck, what its data lacks) and Dependabot for github-actions (read from main, PRs to development, one per major bump)
---

## What checks the workflow files?
- `.github/workflows/workflow-lint.yml`, on pull requests and pushes to development that change `.github/workflows/**`: actionlint (workflow syntax, `${{ }}` expressions and their types, job and step references, runner labels, known action inputs, every `run:` script through shellcheck).
- actionlint is downloaded per run as the pinned release into `$RUNNER_TEMP` and checked against its SHA-256; nothing enters the repository. A bump changes `ACTIONLINT_VERSION` and `ACTIONLINT_SHA256` (the `linux_amd64` line of the release's `actionlint_<version>_checksums.txt`).
- shellcheck comes from the runner image. The step fails when it is missing, since actionlint would skip the scripts without a word. A failing run prints the report, then repeats it as annotations on the diff.
- In a session: `pip install actionlint-py shellcheck-py` into a venv, then `actionlint` at the repository root.

## What does actionlint not know?
- Its data stops at its release. 1.7.12 has no inputs for checkout v7 or setup-node v7, so a misspelt input there passes; compare each step's `with:` keys with the new tag's `action.yml` by hand when bumping.
- 1.7.12 rejects `ubuntu-26.04` as an unknown label. A workflow that names a label actionlint lacks lists it under `self-hosted-runner: labels:` in `.github/actionlint.yaml` until a release knows it.

## How does Dependabot keep the actions current?
- `.github/dependabot.yml`: ecosystem `github-actions`, directory `/` (the workflow files), weekly on Monday 06:00 Istanbul time, `target-branch: development` (reads the workflows there and opens PRs against it), commit prefix `CI`.
- GitHub reads the file from the default branch, `main`, so it takes effect once a release carries it there (`.github/` is in the release tree). Until then it does nothing.
- Refs are major tags, so every Dependabot PR is a major bump. They are not grouped: read each changelog, check the inputs as above, and squash-merge with a Turkish title like any PR. Dependabot PRs open no claim.
- Not covered: the Dockerfile's base images (`php:8.3-fpm-alpine`, `nginx:stable-alpine`). A `docker` update would move the image's PHP line, a release decision.

Related: docs/memory/workflow/static-analysis.md, docs/memory/platform/docker-image-publishing.md
