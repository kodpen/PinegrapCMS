---
name: session-checks
description: Working rule (2026-10-25): how much a session checks before its PR: the tests of its area and what the change touches, a private static-analysis cache, a sandbox only with a reason, no review chains
---

## How much checking is enough?
- The tests of the area you changed (`php tools/test.php <filter>`), plus what your change touches (`check_lang` for texts, `check_bindings` for designer tokens, `node --check` for JS, `memory_index.php --check` for memory). `git push` runs the fast checks (`pre-push-checks.md`); CI runs the full suite, PHPStan and PHPCompatibility.
- `tools/static_analysis.sh` locally only when you changed PHP and want to know before CI, with your own cache folder: `PG_ANALYSIS_TOOLS=<scratch dir> bash tools/static_analysis.sh`. The default `~/.cache/pinegrap-analysis` is shared by sessions on one machine; one run deletes the phpcs report while another reads it ("The report … is missing").
- A sandbox or a browser run only to reproduce a runtime error or a path no unit test can reach; say why in the PR.

## May I use sub-agents or workflows?
- Not by default. One session does the task. A reviewer agent at most once, for a security-sensitive change. No architect, three reviewers and fix rounds per PR: the merging session reviews before it merges (`merge-review.md`).

Related: docs/memory/workflow/working-mode.md, docs/memory/workflow/pre-push-checks.md, docs/memory/workflow/static-analysis.md
