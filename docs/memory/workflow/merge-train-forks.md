---
name: merge-train-forks
description: A PR from a fork never rides a merge train, never lands alone and is never merged by a session; the owner reviews it. Where tools/merge_train.php enforces it
---

## Which PRs may a session merge?
- Only PRs whose branch lives in this repository. A PR from a fork, or from a deleted fork, never rides a train, never lands alone as a docs-only PR and is never merged by hand: the owner reviews it and lands it himself.

## Why?
- A train picks PRs by green checks and the `ready` label or an hour without a push. Those say nothing about who wrote a change, while only someone with write access can push a branch here. Outside work is reviewed by the maintainer before it lands (`CONTRIBUTING.md`, "What we accept").
- Since 2026-11-04 the repository's Actions setting requires approval for all external contributors, so a fork PR's checks do not run until the owner approves them; no check run is not green (`merge_train_checks_green()`).

## Where does the tool enforce it?
- `merge_train_same_repo($pr)` (#6720): the head and base repository ids that the pulls endpoint gives are equal; no head repository (a deleted fork) counts as a fork.
- `--list` (`merge_train_candidates()`) skips fork PRs; `--build` leaves a fork PR out even when it is named; `merge_train_open_trains()` counts only this repository's `merge-train/*` branches, so a fork's branch of that name holds no lock and carries no PR numbers.
- Tests: `tests/merge_train_test.php` (`test_merge_train_same_repo`, `test_merge_train_open_trains_from_this_repository_only`).

## How does an outside contribution land, then?
- The owner reviews it. To take it through the usual flow, its change is pushed to a branch of this repository and a PR is opened from there; the branch guards then apply as for any PR (`branch-guards.md`).
