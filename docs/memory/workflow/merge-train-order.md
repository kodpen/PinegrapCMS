---
name: merge-train-order
description: Which eligible PRs form the next train (merge_train_order()): docs-only alone, small PRs longest-waiting first, an oversized PR alone and at the latest an hour after it became eligible
---

## Which PRs make the next train?
- `merge_train_order()` in `tools/merge_train.php`, which `--list` prints and `--build` without numbers merges. Docs-only PRs land alone; PRs within 1,000 product lines (`working-mode.md`) form the train, longest-waiting first (`eligible_since`; equal waits smaller first, an unknown wait last), at most 12; the rest wait for a later train.

## Why longest-waiting and not smaller first?
- With smaller first each new small PR went to the top, and the trains of 2026-11-07 afternoon took only the first one to three (#7919, #7921, #7924, #7925). #7880 (979 lines, ready and green since 13:55 UTC), #7890 (818, since 14:26) and #7897 (804, since 14:53) starved while PRs ready at 16:46 landed within half an hour, though nothing but the order blocked them.

## When does a PR over the size limit ride?
- Always in a train of its own, so a red train is easy to blame. It is the train when no small PR waits, or once it has been eligible for an hour or more (`MERGE_TRAIN_OVERSIZED_WAIT_SECONDS`): then it goes ahead of the small PRs, which take the next train, stacked with `--start --stack` ([[merger-wake]]) so no wall time is lost. Of several overdue ones the longest-waiting goes first, equal waits the smaller first.

## Why the hour?
- Before #7889 an oversized PR rode only when no small PR waited. Small PRs arrive all day, so on 2026-11-07 #7646, #7650 and #7651 (ready, green since 00:03 UTC), #7727 (since 02:19), #7838 and #7847 were still in "a later train" at 14:00, and their plans' next steps were blocked.

## From when is a PR eligible?
- `merge_train_eligible_since()`, from the check runs of its head: a `ready` PR from its last run's finish (the label's own time is not read, so a label put on later counts from the green checks), a quiet one an hour after its first run's start. Tested in `tests/merge_train_test.php`.
