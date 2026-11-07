---
name: merge-train-order
description: Which eligible PRs form the next train (merge_train_order()): docs-only alone, small PRs smaller first, an oversized PR alone and at the latest an hour after it became eligible
---

## Which PRs make the next train?
- `merge_train_order()` in `tools/merge_train.php`, which `--list` prints and `--build` without numbers merges. Docs-only PRs land alone; PRs within 1,000 product lines (`working-mode.md`) form the train, smaller first, at most 12; the rest wait for a later train.

## When does a PR over the size limit ride?
- Always in a train of its own, so a red train is easy to blame. It is the train when no small PR waits, or once it has been eligible for an hour or more (`MERGE_TRAIN_OVERSIZED_WAIT_SECONDS`): then it goes ahead of the small PRs, which take the next train, stacked with `--start --stack` ([[merger-wake]]) so no wall time is lost. Of several overdue ones the longest-waiting goes first, equal waits the smaller first.

## Why the hour?
- Before #7889 an oversized PR rode only when no small PR waited. Small PRs arrive all day, so on 2026-11-07 #7646, #7650 and #7651 (ready, green since 00:03 UTC), #7727 (since 02:19), #7838 and #7847 were still in "a later train" at 14:00, and their plans' next steps were blocked.

## From when is a PR eligible?
- `merge_train_eligible_since()`, from the check runs of its head: a `ready` PR from its last run's finish (the label's own time is not read, so a label put on later counts from the green checks), a quiet one an hour after its first run's start. Tested in `tests/merge_train_test.php`.
