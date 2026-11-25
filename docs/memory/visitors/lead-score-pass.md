---
name: lead-score-pass
description: The interest score's scoring pass (LJ3): when it runs, which contacts it works out (keep period rows included), what it writes, what marks a row for work (a contact merge, a settings change)
module: lead_score
---

## How does the pass run?
- `job.php` calls `pg_lead_score_run()` every tick; it works only every 600 s (`PG_LEAD_SCORE_EVERY`) unless a pass is unfinished, within 10 s of the tick (`pg_cron_time_left()`), 200 contacts a run.
- Pass record config_text `lead_score_pass` `{since, started}`, claimed with `pg_config_text_claim()` so overlapping runs do not both go.
- Candidates (`pg_lead_score_candidates_sql()`): a visit started since `since` − 1 day (a visit still going), a journey row since `since`, a score with interest over 0 not worked out for a day (fading), a score whose `last_visit_at` is before the keep start not worked out for a day (any interest: its visits are trimmed, so the row goes), a row marked for work (`scored_at` 0); minus those already scored in this pass (`scored_at` ≥ `started`).
- Before the bug hunt (2026-11-24) a row at interest 0 was never worked out again and outlived its trimmed visits for good, still matching "Interested in a folder".
- `pg_lead_score_contact()`: a contact with panel work, no visit any more or no contacts row loses its row. Writes `interest`, `fit`, `reasons` `{interest, fit}`, `topics`, `points` `{at, kinds}`, `hot` (interest ≥ threshold), `hot_at` set when it turns hot, `last_visit_at`, `touched_at` (newest journey row), `scored_at`. `returned_at` and the events: `visitors/lead-tell.md`.

## What marks a row for work (`scored_at` 0)?
- A contact merge (`pg_lead_score_merge()`, from `pg_contact_holders_move()`): the strongest row (hot first, then the highest interest, the survivor's own on a tie) moves to the contact that stays, keeps hot when any row was (no second `customer.hot`), the newest `last_visit_at` and the last `returned_at`; the other rows go. Before (bug hunt 2026-11-24) every row was deleted and the survivor waited for its next visit, out of Hot Contacts.
- Saving settings that change a score (`pg_lead_score_settings_rework()`: threshold, an act's points, pricing or contact pages, half-life; not the keep period) marks every row (`pg_lead_score_rework_all()`), so the hot flags follow a new threshold at the next pass, not up to a day later.

Tests: `tests/lead_score_pass_test.php`, `tests/lead_score_test.php`.
Related: docs/memory/visitors/lead-score.md, docs/memory/visitors/lead-tell.md
