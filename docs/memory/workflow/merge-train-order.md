---
name: merge-train-order
description: Which eligible PRs form the next train (merge_train_order()): docs-only alone, at most one oversized PR at the head, small PRs longest-waiting first, 3,000 lines in all
---

## Which PRs make the next train?
- `merge_train_order()` in `tools/merge_train.php`, which `--list` prints and `--build` without numbers merges. Docs-only PRs land alone; PRs each within 1,000 product lines (`working-mode.md`) form the train, longest-waiting first (`eligible_since`; equal waits smaller first, an unknown wait last), at most 12 (`MERGE_TRAIN_MAX_PRS`) and at most 3,000 product lines together (`MERGE_TRAIN_TOTAL_LIMIT`); the rest wait for a later train.
- A PR that does not fit waits at the head of the next train while a smaller one behind it rides.

## Why longest-waiting and not smaller first?
- With smaller first each new small PR went to the top, and the trains of 2026-11-07 afternoon took only the first one to three (#7919, #7921, #7924, #7925). #7880 (979 lines, ready and green since 13:55 UTC), #7890 (818, since 14:26) and #7897 (804, since 14:53) starved while PRs ready at 16:46 landed within half an hour, though nothing but the order blocked them.

## Why 3,000 product lines together?
- Design-template steps run 300 to 800 product lines, so trains held to 1,000 lines in all carried one or two PRs (2026-11-07: 164 PRs in 122 trains). A train's CI takes about the same time whatever it carries. The total went to 2,000 on 2026-11-07.
- 3,000 is a trial started 2026-11-25. At 2,000, in 24 hours up to it: 21 trains, 6 with a conflict settled by hand (about 0.3 a train), one real code clash, none caused by a train's size. If conflicts per train rise clearly, it goes back to 2,000.
- Keeping steps that touch the same plan file out of one train stays a suggestion, not a rule; settling such conflicts by hand is fine.

## When does a PR over the size limit ride?
- In the next train, at its head, one a train: the longest-waiting oversized PR (equal waits the smaller first); the small PRs fill the rest within 3,000 lines in all, and the other oversized PRs wait. One above 3,000 lines itself rides alone.

## Why not a train of its own?
- Until 2026-11-25 an oversized PR rode alone, so a red train was easy to blame, at the latest an hour after it became eligible (before #7889 only when no small PR waited, and on 2026-11-07 six of them were still waiting at 14:00).
- On 2026-11-25 four oversized PRs (#11617, #11635, #11642, #11643, 1,100 to 1,374 lines) each needed a full CI round of their own while about 40 PRs came in against 29 landed; the queue went over 20 with one coordinator. Since then an oversized PR rides with small ones, at most one a train.

## From when is a PR eligible?
- `merge_train_eligible_since()`, from the check runs of its head: a `ready` PR from its last run's finish (the label's own time is not read, so a label put on later counts from the green checks), a quiet one an hour after its first run's start. Tested in `tests/merge_train_test.php`.
