---
name: working-mode
description: Owner rules (2026-10-25) for every session: one open PR, no stacked branches, hand over when green, light checks, small PRs, one task per session, own analysis cache
---

## Why these rules?
- Owner (2026-10-25): about fifteen sessions on four accounts work at once and one session merges. Quotas ran out while PRs waited: every merge made other open PRs conflict (mostly in `tr.json`), CI does not run on a conflicted PR, and each session merged `development` again, re-ran the full tests and static analysis and was woken by every CI event. Stacked branches (a branch on an open PR) broke again with each squash merge, and multi-agent review chains cost millions of tokens per PR.

## How many PRs may a session have open?
- One. Branch only from `development`; never from an open PR. The next task, or a plan's next step, starts after the PR is merged or handed over. Work that needs an unmerged PR waits for it.

## What do I do when my PR is green?
- Comment once: "Ready to merge" with the merge order when it has one. Then unsubscribe from the PR's activity, cancel your check-ins and stop. Do not merge `development` into it, rebase it or re-run its checks again: keeping it current is the merging session's job (`merge-train.md`). Act again only when that session asks on the PR or CI on your own push is red.
- Before the PR goes up, merge `development` once and run your checks; that is the last refresh from your side.

## How much checking is enough?
- The tests of the area you changed (`php tools/test.php <filter>`), plus what your change touches (`check_lang` for texts, `check_bindings` for designer tokens, `node --check` for JS, `memory_index.php --check` for memory). `git push` runs the fast checks (`pre-push-checks.md`); CI runs the full suite, PHPStan and PHPCompatibility.
- `tools/static_analysis.sh` locally only when you changed PHP and want to know before CI, with your own cache folder: `PG_ANALYSIS_TOOLS=<scratch dir> bash tools/static_analysis.sh`. The default `~/.cache/pinegrap-analysis` is shared by sessions on one machine; one run deletes the phpcs report while another reads it ("The report … is missing").
- A sandbox or a browser run only to reproduce a runtime error or a path no unit test can reach; say why in the PR.

## May I use sub-agents or workflows?
- Not by default. One session does the task. A reviewer agent at most once, for a security-sensitive change. No architect, three reviewers and fix rounds per PR: the merging session reviews before it merges.

## How big is a PR?
- About 500 changed lines or fewer, tests and memory aside. A bigger feature is a plan in `docs/memory/plans/` whose steps are separate PRs, one open at a time.

## How long does a session live?
- One task. Hand over through the plan file, the claim and the PR, then end. A long session pays for its whole context on every wake. At most one safety check-in while a PR waits; never poll.

## Who works where?
- Fewer working sessions merge faster than many: aim for about eight at once, each on its own area (the memory folder table in `CLAUDE.md`). Two sessions never hold the same files; the claim check still applies.

Related: docs/memory/workflow/merge-train.md, docs/memory/workflow/pr-backlog-coordination.md, docs/memory/plans/tr-json-split.md
