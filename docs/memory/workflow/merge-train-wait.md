---
name: merge-train-wait
description: tools/merge_train.php --wait (#11186), the merging session's waiter: what it reads, when it exits, the --since key, its interval and timeout, failed gh calls, its GitHub calls
---

## What does it read?
- Every `--interval` seconds (120 by default, at least 30) the open PRs to `development`, as a tick of the merging session would ([[merger-wake]]): each open `merge-train/*` PR with its head's check runs, and only when no train is open the PRs `--list` would take (`merge_train_candidates()`).
- A train's CI is `green`, `red` once every gating run finished and one did not pass, `running` while one is unfinished, and `stuck` when none has started 30 minutes after its head commit (`merge_train_train_state()`): a train whose CI never began would otherwise read as running for ever. The channel report jobs do not count ([[merge-train]]).

## When does it exit 0?
- When a tick would act: an open train whose CI finished or is stuck, or no open train and at least one PR that may ride or land alone. While every open train runs it keeps waiting, as a tick then ends quietly: a docs-only PR and the next train wait for the open train's CI.
- It prints one JSON line: `key` (12 hex digits), `wake` (`train` or `build`) and `state`, such as `train #11190 <sha> green` or `build #11184 <sha>; build #11186 <sha>`, in PR number order.

## What is --since for?
- After acting the session reads the state as it now is with `--wait --timeout=1` (one reading: exit 0 and a line, or exit 3 for a quiet state) and starts the waiter with `--since=<that key>`, or with none when it was quiet. A state it left as it was then does not wake it again: a red train waiting on its fix or a PR it kept out would otherwise wake it every two minutes. A new push changes the PR's sha in the state, so its key too.

## When else does it exit?
- 3 when `--timeout` (3300 seconds by default) passes with nothing new: the session starts it again with the same `--since`, inside the prompt cache's hour (`agent-channel-request-wait.md`). 1 on a usage error.
- A failed `gh api` call (network, GitHub's rate limit) is tried again at the next interval, never an exit (`merge_train_gh_soft()`): only work wakes the session.

## How many GitHub calls does it make?
- One for the open PRs and one per open train each interval; with no train, also one per open PR for its check runs and the files of each that may ride. With 25 open PRs that is about 900 calls an hour at the default interval.

Tests: `tests/merge_train_test.php` (the CI state, a stuck train, the state line, the key, the settings).

Related: docs/memory/workflow/merger-wake.md, docs/memory/workflow/merge-train-tool.md
