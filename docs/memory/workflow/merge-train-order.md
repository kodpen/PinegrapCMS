---
name: merge-train-order
description: Which eligible PRs form the next train (merge_train_order()): docs-only alone, small PRs longest-waiting first, 2,000 lines in all, an oversized PR alone, at the latest an hour after it is eligible
---

## Which PRs make the next train?
- `merge_train_order()` in `tools/merge_train.php`, which `--list` prints and `--build` without numbers merges. Docs-only PRs land alone; PRs each within 1,000 product lines (`working-mode.md`) form the train, longest-waiting first (`eligible_since`; equal waits smaller first, an unknown wait last), at most 12 (`MERGE_TRAIN_MAX_PRS`) and at most 2,000 product lines together (`MERGE_TRAIN_TOTAL_LIMIT`); the rest wait for a later train. A PR that does not fit waits at the head of the next train while a smaller one behind it rides.

## Why longest-waiting and not smaller first?
- With smaller first each new small PR went to the top, and the trains of 2026-11-07 afternoon took only the first one to three (#7919, #7921, #7924, #7925). #7880 (979 lines, ready and green since 13:55 UTC), #7890 (818, since 14:26) and #7897 (804, since 14:53) starved while PRs ready at 16:46 landed within half an hour, though nothing but the order blocked them.

## Why 2,000 product lines together?
- Design-template steps run 300 to 800 product lines, so trains held to 1,000 lines in all carried one or two PRs: on 2026-11-07 (Istanbul day) 164 PRs landed in 122 trains, against 285 to 342 PRs in 77 to 95 trains a day from 2026-10-28 to 2026-11-04, when most PRs were small fixes. A train's CI takes about the same time whatever it carries.
- A trial from 2026-11-07 22:00 UTC. Baseline at 1,000 in all, 2026-11-06 13:00 to 2026-11-07 22:00 UTC (about 130 trains): 4 merges stopped on a conflict the tools could not settle (assembly steps' shared test lists), 1 train push stopped by two PRs' clashing tests (#7770). If that count per train rises clearly, the limit goes back to 1,000.

## When does a PR over the size limit ride?
- Always in a train of its own, so a red train is easy to blame. It is the train when no small PR waits, or once it has been eligible for an hour or more (`MERGE_TRAIN_OVERSIZED_WAIT_SECONDS`): then it goes ahead of the small PRs, which take the next train, stacked with `--start --stack` ([[merger-wake]]) so no wall time is lost. Of several overdue ones the longest-waiting goes first, equal waits the smaller first.

## Why the hour?
- Before #7889 an oversized PR rode only when no small PR waited. Small PRs arrive all day, so on 2026-11-07 #7646, #7650 and #7651 (ready, green since 00:03 UTC), #7727 (since 02:19), #7838 and #7847 were still in "a later train" at 14:00, and their plans' next steps were blocked.

## From when is a PR eligible?
- `merge_train_eligible_since()`, from the check runs of its head: a `ready` PR from its last run's finish (the label's own time is not read, so a label put on later counts from the green checks), a quiet one an hour after its first run's start. Tested in `tests/merge_train_test.php`.
