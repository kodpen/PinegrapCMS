---
name: auto-dialog-counts
description: Pop-up counts (auto_dialog_daily, 11.0.223, #9877): shown, closed, taken per pop-up and day; the day-signed beacon, who is not counted, the sweep, the 30-day figures
module: auto_dialog_counts
---

## What does the table hold?
- `auto_dialog_daily` (`upgrade_2026_11_0_auto_dialog_daily()`): `auto_dialog_id` INT UNSIGNED, `day` DATE, `shown`, `closed`, `taken` INT UNSIGNED; PRIMARY KEY (`auto_dialog_id`, `day`), KEY `idx_day` (`day`). Listed in `get_tables()`. Confirmed by the architect in agent channel message 5840 (owner's yes: poll 51).
- One `INSERT … ON DUPLICATE KEY UPDATE <event> = <event> + 1` per event (`pg_auto_dialog_count_upsert_sql()`), by `high-volume-counter-rules.md`. Days are PHP `date('Y-m-d')`. No visitor data in it.
- A deleted pop-up's rows go with it (`pg_auto_dialog_delete()` calls `pg_auto_dialog_count_delete()`).

## How is an event sent?
- `get_page_content.php` gives each printed row `sign` = `pg_auto_dialog_count_sign()`: HMAC-SHA256 of `'auto-dialog:' . id . '|' . day` over `ENCRYPTION_KEY`, as the page speed beacon. No table or no key: no signature, no beacon. A preview prints none. It passes `count_url` (page_view.php).
- `frontend.src.js` (and the hand-patched `frontend.min.js`): `show_auto_dialog()` calls `software.count_auto_dialog(row, 'shown')` when the dialog opens and `'closed'` when it closes; once per pop-up, event and page load (`software.auto_dialog_counted`); `sendBeacon`, else fetch keepalive; posts `ad`, `ae`, `as`.
- `taken` (a press on the marked action or a form sent inside) has no sender yet: an address pop-up knows shown and closed only; designed content (PU6) calls `software.count_auto_dialog(row, 'taken')`.

## Who is not counted?
- The rule is the one check of the anonymous counts: sent unless the visitor refused statistics (`platform/consent-anonymous-counts.md`). The server asks `pg_consent_statistics_not_refused()` since #9902. The browser's `software.statistics_allowed()` still asks for `a1` until CM2 of the click map moves it to the same rule; stricter meanwhile, no harm.
- `page_view.php` (a small branch before the other payloads): `pg_auto_dialog_count_rate_exceeded()` (30 a minute an address, waf bucket `popup`), `pg_auto_dialog_count_allowed()` (no crawler, `pg_site_search_user_countable()` for staff, `pg_consent_statistics_not_refused()`), `pg_auto_dialog_count_parse()` (id digits, one of three events, today's or yesterday's signature; else the post is dropped), `pg_auto_dialog_count()` (silent without the table or for an id with no pop-up).

## How long are rows kept?
- 400 days (`PG_AUTO_DIALOG_COUNT_DAYS`): `job.php` runs `pg_auto_dialog_count_sweep()` once a day (`data/temp/auto_dialog_count_sweep.json`), `DELETE … LIMIT 5000` within 5 seconds.

## Where are the figures?
- The last 30 days (`PG_AUTO_DIALOG_COUNT_PERIOD`, today included): `pg_auto_dialog_count_figures()` sums in one grouped query. The list (`view_auto_dialogs.php`) has Shown, Closed and Taken (30 days) columns; the edit screen's Summary has three rows (`data-pg-auto-dialog-count`). PU5 redraws both screens.

## Tested?
- `tests/auto_dialog_counts_test.php`. The step was run twice on MariaDB 10.11; the upsert, figures, sweep and delete against it. No browser run.
