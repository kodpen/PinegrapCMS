---
name: merger-wake
description: How the merging session wakes: its waiter merge_train.php --wait (#11186), restarted within the cache hour, and an hourly routine that starts it again; GitHub events are not relied on
---

## Why not wait for GitHub events?
- A green CI result on a train did not reach the merging session twice (the night of 2026-10-25 and 2026-10-26 09:43): the subscription's event was lost when the container restarted. The train sat green, no PR landed, every worker waiting on its merge stopped, and the queue fell about five hours behind.
- Events and `send_later` check-ins still help, but nothing waits on them.

## What wakes the merging session?
- Its waiter since 2026-11-23 (#11186): `php tools/merge_train.php --wait --since=<key>` with Bash `run_in_background` (timeout 7200000). It reads GitHub every 2 minutes as a tick and exits only when a tick would act, printing a line with a `key` ([[merge-train-wait]]). A tick reads GitHub, not its own memory:
  - an open `merge-train/*` PR whose CI finished: green → check the PR heads and `origin/development` ancestry, land it by fast-forward ([[merge-train-landing]]), then build the next one; red → find the PR that breaks it; stuck (no check run 30 minutes after its push) → push it again or rebuild it;
  - no open train: build one from the `ready`, green PRs (or green with no push for an hour), or land a docs-only one alone ([[merge-train]]).
- After acting it reads the state once more (`--wait --timeout=1`) and starts the waiter with `--since=<that key>`, so a state it left as it was does not wake it again ([[merge-train-wait]]). Exit 3 after 55 idle minutes: start it again with the same `--since`, a turn inside the prompt cache's hour (`agent-channel-request-wait.md`).
- An hourly routine (`create_trigger`, bound to the session, an off-minute such as `41 * * * *`) starts the waiter when none runs (a container restart ends it) and acts on what `--wait --timeout=1` prints, one reading.
- No separate lander waits beside it: the waiter sees a train's CI result within 2 minutes, and a second watcher would wake the session twice for one green train.
- The hourly routine is a turn of its own besides the waiter's restarts; it stays hourly because a container restart ends the waiter, and an unseen green train once left the queue five hours behind (above).
- Why the waiter: until then a `send_later` chain, `Merger: tick`, woke the session every 3 minutes with more than 3 ready PRs and every 15 otherwise (owner, 2026-11-09), and each tick read the whole context, 125,000 to 146,000 tokens, also when nothing was to do (`quota-use-measured.md`). The chain is deleted.
- No session cron: since 2026-11-03 the accounts' quota is not unlimited, and every wake costs a turn; a cron dies with a restart.

## What does a new merging session set up first?
- The waiter and the hourly routine; it deletes an older routine and any `Merger: tick` chain of a session that is no longer merging. Then it lands or builds as the waiter's line says.
- A fresh merging session per window (a routine that creates a session on each firing) is equally safe: the open `merge-train/*` PR is the lock.
- Reviews of a train run while its CI runs, not before the push: CI takes about ten minutes, a review by sub-agents about as long.

## Is the next train built before the open one lands?
- It may be (2026-10-26), in the turn that builds the first: on the open train's tip, pushed and opened at once, so both CIs run together. Nothing wakes the session later to stack one: while a train runs the waiter waits ([[merge-train-wait]]). The first lands by fast-forward, after which the second's base is `development` itself; its PR then shows only its own merges. A red first train means rebuilding the second on the rebuilt first.
- The old rule against stacked trains came from squash landings, which rewrote the first train's commits and left every train built on it `dirty`.

Related: docs/memory/workflow/merge-train.md, docs/memory/workflow/coordinator.md
