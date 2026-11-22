---
name: application-review-ranking
description: A programme's Entries tab (AR5): combined scores of the current round, the consensus flag and Settle, ranking per category, the shortlist cut, "Start round N"
module: application_ranking
---

## Where is it?
- The programme's screen, tab "Entries" (`edit_application_program.php?id=…&view=entries&category=…`), same gate as Set-up. Code: `includes/fn/application_ranking.php`, loaded with `require_once` by the screen and by `application_my_reviews.php`; pure parts tested in `tests/application_ranking_test.php`.

## How are the scores combined?
- `pg_application_combine()`: the sent reviews (status 2) of the current round; each entry's mean of the weighted totals and its corrected mean (`pg_application_leniency_correct()` over all of the round's sent reviews, from a reviewer's fifth). Both are shown; only the round's "Rank by" decides which ranks.
- The spread and the "Needs consensus" flag are taken on the score that ranks, so with the corrected mean a strict and a generous reviewer who agree on the order do not flag an entry.
- `pg_application_scores_refresh()` writes `score_raw`, `score_adjusted`, `spread` (times 100) and `consensus` where they changed: on every sent review (`pg_application_review_save()`) and when the Entries tab opens. A settled entry (2) stays settled; a flag that no longer holds goes back to 0.

## Where are a round's ranking settings?
- In the round's item of the `rounds` JSON, no column: `spread` (the bound, 0 to 9 or null for never), `rank_by` (`raw` or `corrected`), set in Set-up's round card as "Needs consensus above" and "Rank by"; `cut` (`{mode: cutoff|top, value}`), set on the Entries tab. A Set-up save keeps each round's `cut` by its index (`pg_application_rounds_keep_cut()`).

## How is the list drawn?
- A plain table, not the list table driver: the rank order is the list's meaning and the cut-off is a row across it (`ui/list-and-table-screens.md`). Categories are a segmented choice of links, in name order; no tabs without categories.
- Rank, entry (reference code, linked), "N of M" sent reviews, mean, corrected mean, spread, pills "Not eligible", "Needs consensus", "Settled", "Shortlisted"; row buttons Open, "Let it in" (not eligible) and Settle (needs consensus), each opening a note drawer (`data-pg-ap-note` in `application_program.js`).
- Only eligible or let in entries with a sent review are ranked; the others follow without a rank.

## How does the shortlist cut work?
- "Apply the cut": a cut-off score (1 to 10) or a top N per category; a tie at the cut is never split (`pg_application_shortlist()`). The cut is kept on the round, `shortlisted` set to 1 for the entries it takes and 0 for the round's other undecided entries; an `audit_log` line `shortlist` on the programme.
- The screen redraws the cut line from fresh scores and says so when the stored shortlist no longer matches: the cut is applied again by a press, never by itself.

## What does Settle do?
- Needs a note (1000 chars): `consensus` 2, the scores stay as sent; the note is only in the entry's `audit_log` line `consensus`.

## How does the next round start?
- The head's button "Start round N" shows on a closed programme with a next round set up, disabled with no shortlist (`pg_application_next_round_ready()`). It moves the shortlisted entries to the next round with scores, flags and `shortlisted` cleared, raises the programme's `round`, and writes `next_round` on the programme. The others stay in the round they ended in; reviews keep their round. Then "Give out entries" on Reviewers gives them to the round's pools.

Related: docs/memory/core/application-review-rules.md, docs/memory/core/application-review-reviewers.md, docs/memory/core/application-review-my-reviews.md, docs/memory/plans/application-review-screens-review.md
