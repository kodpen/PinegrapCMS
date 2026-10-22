---
name: git-branches-and-shared-files
description: Branch policy (development vs main), who does git work, PR target, commit author, the PR footer (pointer), editing shared files without conflicts, API vs ERP files
---

## Which branch?
- Work branches start from `development`; PRs target `development`. `main` carries released versions only: never open a PR to `main`, never merge or rebase `main`. The release PR is the owner's.
- Threads squash-merge their own CI-green PRs into `development` (standing owner approval); releases to `main` and version numbers stay the owner's.

## Who does git?
- One hand does all repo actions (branch, commit, push, PR, issue): the session with git and GitHub access. An agent that only edits code leaves its change in the working tree and produces no bundle or hand-off file (no `dev/_handoff/`).

## Commit author and messages?
- Author `Erdal Güral <erdaltyy@gmail.com>`. Messages describe the change and its technical reason only, with no AI or session trace (see CLAUDE.md rule 2). If the commit identity cannot be set, squash-merge with an explicit title and message so the `development` commit is authored correctly.

## What about the PR footer and commit trailers?
- Where they come from and who removes them: pr-footer-and-trailers.md.

## How do I edit shared files?
- Shared files: `includes/local/tr.json`, `pinegrap/changelog.txt`, the open version's migration file, `init.php`.
- Append only, in your own section; never rewrite the whole file.
- Right before writing: `git fetch origin development && git merge origin/development`. Right before pushing: rebase on `development`; keep both sides' sections on conflict.
- `docs/memory/README.md` and the folder READMEs are generated: never edit or merge a file list by hand; run `php tools/memory_index.php` after the merge. Only a folder README's key-rules block is hand-written; a conflict inside it is merged by hand (`memory-index-key-rules.md`).
- The top index's folder lines carry no file count (#1081): two branches adding a file to one folder merged into a count matching neither, and the merged tree failed CI's `--check` on other PRs.

## Which files belong to the API and which to ERP?
- The external API: `includes/api/**` (webhook delivery is `includes/api/outbound/webhooks.php`), `integration.php`, `api_settings.php`, `api_docs.php`, the jobs `api_webhook_job.php` and `api_sync_job.php` (`api.php` is the panel's action endpoint, not the API). ERP: `includes/erp/**` and the root `*erp*.php` screens. ERP reaches the API only through `includes/erp/api.php` (`erp/erp-api-seam.md`) and edits no API file; what one side needs from the other is asked of that side.
