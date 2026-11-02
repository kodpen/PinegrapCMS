---
name: search-and-audits-screen
description: Search and audits screen (search_audits.php): engine cards, state, progress and failure sources, the job switch, Run now, where it is linked
module: search_audits
---

## What does the screen show?
- One card per engine (`pg_search_audits_engines()` in `includes/fn/search_audits.php`): search index (`update_search_index`, only with the advanced site search), SEO scores (`seo_score_job`), HTML structure (`seo_analyze_job`), accessibility (`seo_a11y_job`). Managers and up. It replaces the index's popup window (owner, 2026-11-01, `workflow/decisions-made-product-18.md`).
- Last full run: `pg_cron_health_last_runs()` (the `_done` marker, `scheduled-job-resume.md`). State (`pg_search_audits_status()`, pure): running while the job's lock in `cron_runs` is held, else off when the job is not switched on (or Run Cron Jobs Automatically is off), due when last tick plus interval has come, scheduled before.
- Progress of an unfinished pass: index, the cursor's stage (`data/temp/search_index.json`); SEO scores, rows with `seo_analysis_current = 0`; structure, rows not current or checked before their edit; accessibility, pages up to the cursor in `data/temp/seo_a11y.json`. Pages, plus products and groups when the store is on.
- Failures: the index cursor's failure notes and skipped items; structure, the `render_failed` rows; index and accessibility also the newest Site Log lines (8 days) of their passes, found by the longest literal piece of the translated line (`pg_search_audits_log_needle()`), since the lines are stored translated.

## How do the switch and Run now work?
- The switch writes `config.job_dispatch` through `pg_search_audits_switch_list()` (pure: catalogue order, only dispatchable names, like `maintenance.save.php`). JSON requests (`action=card|switch`) check session, role and token themselves and refuse in JSON (`pg_search_audits_json_gate()`).
- Run now repeats short requests until the pass is done (at most 500). SEO, structure, accessibility: `search_audits_run.php`, anonymous render like `audit_run.php`, the job's lock taken (a second run answers 409 busy), `SEO_ANALYZE_BUTTON_BUDGET`, `pg_search_audits_tick()`. The first request of a Run now starts a new full pass when nothing is queued (the score and structure passes otherwise take only edited records). A finished pass calls `pg_cron_ran()`. Index: the screen fetches `update_search_index.php?continue=1` and reads the card again until the cursor file is gone.

## Where is it linked?
- Pages tools menu and the main menu's Pages panel (in place of Update Search Index), the panel search, the top of Site Settings › SEO.

Test: `tests/search_audits_test.php`.
