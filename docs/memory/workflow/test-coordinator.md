---
name: test-coordinator
description: Test coordinator (2026-10-31): starts no workers, tests and opens `bug` issues, which wait for the owner (no worker takes them since 2026-11-06); the merging session closes a fixed one
---

## Why a test coordinator?
- 2026-10-31: the owner's local account became a coordinator that starts no workers. It tests the product and reports what it finds, so faults are written up without the owner doing it.

## What does it do?
- Tests the product and opens one issue per fault, labelled `bug`: the steps it took, what it expected, what it saw, the version and the screen or endpoint. It writes no code, opens no PR and merges nothing.
- It runs on the owner's own computer and tests on `dev.pinegrap.com`.
- Searches the open and closed `bug` issues first, so a fault is not reported twice.
- A fault whose fix needs a product or schema decision goes to an `architect` issue instead (`architect-asking.md`).

## What happens to a `bug` issue?
- Since 2026-11-06 it waits for the owner: no coordinator gives it to a worker and no worker claims it (`coordinator-no-plans.md`). The open ones, the earlier hunts' included:
  `gh api 'repos/kodpen/pinegrapcms/issues?state=open&labels=bug&per_page=100' --jq '.[]|select(.pull_request|not)|"#\(.number) \(.title)"'`
- A fix PR the owner starts for one names it (`Fixes #<n>` in the body), fails a test first and finishes as any PR (`CLAUDE.md`, Claim work); an open PR that names the issue holds it.

## Who closes it?
- The merging session, once the train with the fix has landed: `Fixes #<n>` closes nothing by itself, because PRs go to `development` and GitHub closes issues only on merges into the default branch (`main`). It closes the issue as completed with a comment naming the PR (`merge-train-landing.md`).
- The test coordinator checks the fix in a later test round and opens a new issue if the fault is still there.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/working-mode.md, docs/memory/workflow/merge-train-landing.md
