---
name: git-branches-and-shared-files
description: Branch policy (development vs main), who does git work, PR target, commit author, editing shared files without conflicts
---

## Which branch?
- Work branches start from `development`; PRs target `development`. `main` carries released versions only: never open a PR to `main`, never merge or rebase `main`. The release PR is the owner's.
- Threads squash-merge their own CI-green PRs into `development` (standing owner approval); releases to `main` and version numbers stay the owner's.

## Who does git?
- One hand does all repo actions (branch, commit, push, PR, issue): the session with git and GitHub access. An agent that only edits code leaves its change in the working tree and produces no bundle or hand-off file (no `dev/_handoff/`).

## Commit author and messages?
- Author `Erdal Güral <erdaltyy@gmail.com>`. Messages describe the change and its technical reason only, with no AI or session trace (see CLAUDE.md rule 2). If the commit identity cannot be set, squash-merge with an explicit title and message so the `development` commit is authored correctly.

## How do I edit shared files?
- Shared files: `includes/local/tr.json`, `pinegrap/changelog.txt`, the open version's migration file, `init.php`.
- Append only, in your own section; never rewrite the whole file.
- Right before writing: `git fetch origin development && git merge origin/development`. Right before pushing: rebase on `development`; keep both sides' sections on conflict.
- `docs/memory/README.md` is generated: never edit or merge it by hand; run `php tools/memory_index.php` after the merge.
