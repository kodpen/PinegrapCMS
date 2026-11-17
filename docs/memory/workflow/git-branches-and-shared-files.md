---
name: git-branches-and-shared-files
description: Branch policy (development vs main), who does git, commit author, the PR footer (pointer), shared files without conflicts, generated files a PR leaves out, API vs ERP files
---

## Which branch?
- Branches start from `development`; PRs target it. `main` carries released versions only: no PR to it, never merge or rebase it; the release PR is the owner's.
- Only the merging session lands PRs on `development`, by fast-forward (`merge-train.md`); releases and version numbers are the owner's.

## Who does git?
- The session with git and GitHub access does every repo action; an agent that only edits code leaves its change in the working tree.

## Commit author and messages?
- Author `Erdal Güral <erdaltyy@gmail.com>`. Messages: the change and its technical reason, no AI or session trace (`CLAUDE.md`, Fixed rules 2). Commits reach `development` as they are (fast-forward): set the identity before the first commit (`git config user.name`/`user.email`) and fix a wrong one with `git commit --amend --reset-author` before the PR goes up. A PR with another author or a trailer goes back to its session (`merge-train.md`).

## What about the PR footer and commit trailers?
- Where they come from and who removes them: pr-footer-and-trailers.md.

## How do I edit shared files?
- Shared files: the open version's migration file, `init.php`. `pinegrap/changelog.txt` is not edited by a PR: its body names the `[NEW]` line or the `[SCHEME]` area and the merging session writes it (`working-mode.md`).
- The Turkish shards in `includes/local/tr/` are sorted by key and merge cleanly: keys only with `php tools/tr_add.php`, a conflict settled with `php tools/tr_resolve.php` (`core/lang-files.md`).
- Migration file and `init.php`: append only, in your own section; never rewrite the whole file.
- A file an open PR holds: a bundled script or stylesheet is held by function and rule block (`held-file-bundled-sources.md`); a registry's own rows or an untouched function beside a draft are asked (`held-file-registry-rows.md`). Anything more waits.
- Merge `origin/development` once before the PR goes up, keeping both sides' sections on conflict. After the hand-over only the merging session brings the PR up to date (`session-lifecycle.md`).

## Which generated files does a PR leave out?
- The memory lists (`docs/memory/README.md`, folder READMEs, `INDEX.md` files, `workflow/DECISIONS.md`, `core/fn-modules.md`) and every `.min.js` / `.min.css` of `pg_js_bundles()` / `pg_css_bundles()`.
- A PR commits sources and its own memory files; `tools/check_pr.php` refuses a changed list or bundle, naming the commit to restore it from. A README's key-rules block is hand-written and may change (`memory-index-key-rules.md`); hand-made `.min.js` files (`lazy.min.js`, `chat_backend.min.js`) are no bundles.
- The landing writes them, in its own commits inside the fast-forward push (`merge-train-tool.md`).
- CI on a PR: `memory_index.php --files`, and the bundles built from its sources before the unit tests. On a train and on `development`/`main`: `--check` of the lists and both bundle builds. `git push` does the same (`pre-push-checks.md`).
- To try a bundle in a sandbox or a local run: `php tools/build_js.php` (Node, npx) or `build_css.php`, then `git checkout` the bundles before committing.
- Why: in the census of #9462, 55 merges of `development` into PRs and 54 train merges conflicted on these files alone; accepted by the owner 2026-11-14 (channel message 2645).

## Which files belong to the API and which to ERP?
- The external API: `includes/api/**` (webhook delivery is `includes/api/outbound/webhooks.php`), `integration.php`, `api_settings.php`, `api_docs.php`, the jobs `api_webhook_job.php` and `api_sync_job.php` (`api.php` is the panel's action endpoint, not the API). ERP: `includes/erp/**` and the root `*erp*.php` screens. ERP reaches the API only through `includes/erp/api.php` (`erp/erp-api-seam.md`) and edits no API file; what one side needs from the other is asked of that side.
