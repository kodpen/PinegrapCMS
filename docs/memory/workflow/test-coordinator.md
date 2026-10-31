---
name: test-coordinator
description: Test coordinator (2026-10-31): starts no workers, tests and opens `bug` issues; code coordinators give them to workers as free work; the merging session closes them
---

## Why a test coordinator?
- 2026-10-31: the owner's local account became a coordinator that starts no workers. It tests the product and reports what it finds, so faults reach the workers without the owner writing them up.

## What does it do?
- Tests the product and opens one issue per fault, labelled `bug`: the steps it took, what it expected, what it saw, the version and the screen or endpoint. It writes no code, opens no PR and merges nothing.
- It runs on the owner's own computer and tests on `dev.pinegrap.com`.
- Searches the open and closed `bug` issues first, so a fault is not reported twice.
- A fault whose fix needs a product or schema decision goes to an `architect` issue instead (`architect-asking.md`).

## How does a `bug` issue become work?
- The code coordinators read the open `bug` issues beside the plan steps when a worker slot is free and give one to a worker as free work (`coordinator.md`):
  `gh api 'repos/kodpen/pinegrapcms/issues?state=open&labels=bug&per_page=100' --jq '.[]|select(.pull_request|not)|"#\(.number) \(.title)"'`
- An open PR whose body names the issue (`Fixes #<n>`) holds it, as a draft PR holds a plan step: check the open PRs' bodies before giving it out.
- The worker claims it as any task: a draft PR titled `[iş] <topic>` with `Fixes #<n>` in the body, a failing test first, then the usual finish (`CLAUDE.md`, Claim work). It counts in the queue limit like any PR.
- A report that does not reproduce on `development`: the worker comments on the issue with what it tried, leaves it open for the test coordinator and closes its draft.

## Who closes it?
- The merging session, once the train with the fix has landed: `Fixes #<n>` closes nothing by itself, because PRs go to `development` and GitHub closes issues only on merges into the default branch (`main`). It closes the issue as completed with a comment naming the PR (`merge-train-landing.md`).
- The test coordinator checks the fix in a later test round and opens a new issue if the fault is still there.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/working-mode.md, docs/memory/workflow/merge-train-landing.md
