---
name: application-review-rules
description: Pure rules of application review: eligibility codes, weighted sums, leniency correction, spread flag, balanced assignment, shortlist tie rule
module: application_review
---

## Where are the rules?
- `includes/fn/application_review.php`, no database, tested in `tests/application_review_test.php` (#10809). The store, screens and API of plan `application-review` call it.

## How is eligibility checked?
- `pg_application_eligibility($rules, $answers)`: rule types `file` (a non-empty answer), `range` (`min`/`max`, both ends included), `choice` (`values`; a multi-choice answer passes when one value is listed). An unknown type is skipped.
- Reasons come back as codes, not text: `missing`, `not_number`, `below`, `above`, `not_listed`, with the rule index and field. The screen translates them.

## How is a review scored?
- `pg_application_criteria_valid()`: unique ids, whole weights of at least 1, adding up to 100.
- `pg_application_weighted_sum()`: whole scores 1 to 10, sum of score × weight over 100; null while a criterion is unscored or out of range. Scores and means are rounded to 4 decimals (`PG_APPLICATION_SCORE_DECIMALS`), so float noise never splits a tie.

## How does the leniency correction work?
- `pg_application_leniency_correct($reviews)`: each reviewer's scores become z-scores against their own mean and population deviation, mapped onto the mean and deviation of all reviews passed in, clamped to 1 to 10.
- Only from 5 scores of that reviewer (`PG_APPLICATION_LENIENCY_MIN_SCORES`), and not for a reviewer who gave every entry one score: then `corrected` carries the raw score and `corrected_applied` is false.

## When is an entry flagged "needs consensus"?
- `pg_application_needs_consensus($scores, $bound)`: highest minus lowest above the programme's bound (strictly), with two scores or more; no bound, no flag.

## How are reviewers given out?
- `pg_application_assign($entries, $pool, $n, $existing, $conflicts, $random)`: fewest assigned first, ties at random (pool order with `$random` false), never one reviewer twice on an entry or one with a conflict on it.
- `$start_load` (reviewer => count) adds load given in another category's call, so a reviewer in several pools is not served first in each (AR3a).
- A re-run keeps `$existing` reviewers (their load counts) and only tops up; a kept reviewer who declared a conflict since is dropped and replaced. An entry the pool cannot fill is listed in `short` with how many it lacks (a pool of one, everyone in conflict).

## How are ranking and the shortlist cut?
- `pg_application_rank()`: highest first, equal scores by entry id, unscored entries left out; `pg_application_rank_by_category()` per category.
- `pg_application_shortlist($scores, 'cutoff'|'top', $value)`: a cut-off takes every entry at or above it; a top N takes every entry tied with the Nth place too, so a tie at the cut-off is never split by entry order.

Related: docs/memory/plans/application-review.md
