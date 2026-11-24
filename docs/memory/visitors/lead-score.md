---
name: lead-score
description: The visitor journey's scores (LJ3, LJ3b): config_text lead_score, behaviour events, the fit score, interests by page folder or product category, the job's pass, the Interest card
module: lead_score
---

## Where are the settings kept?
- config_text `lead_score` (JSON), read by `pg_lead_settings()` through `pg_lead_score_settings()`: `threshold` 1 to 100 (default 60), `weights` (over `pg_lead_score_weights()`), `pricing_pages` and `contact_pages` (page ids), `half_life` 1 to 365 days (30), `keep_months` 1 to 36 (13).
- `keep_months` is used by the tie-back (`pg_lead_tie()`) and the keep period trim (`pg_lead_trim_run()`) through `pg_lead_keep_months()`.
- Set on Site Settings › Visitors and privacy, row "Interest score": `visitors/lead-score-settings.md`.

## Which acts give interest points?
- `pg_lead_score_events()` from `pg_lead_journey_read()`'s rows (forms all, orders not read): a visit that opened a pricing or contact page (once a visit each), a visit within 7 days of the one before (`return_7d`), a form, a booking, a campaign e-mail opened, a download. An order gives no interest point.
- Pricing and contact pages: the chosen page ids when any is chosen for that kind; otherwise a page named `pricing`, `prices`, `plans`, `fiyat`, `fiyatlar`, `fiyat-listesi`, `ucretler`, `paketler` / `contact`, `contact-us`, `iletisim`, `bize-ulasin` (`pg_lead_score_page_kind()`).

## How is the fit score made?
- `pg_lead_fit_score()`: e-mail on a company domain 40 (not `pg_lead_free_mail_domain()`'s list: gmail, hotmail, outlook, yahoo, yandex, icloud, mynet, proton, gmx and others), company filled 25, title filled 20, in any contact group 15; capped 100, top three reasons.

## What are interests?
- `pg_lead_interest_topics()`: the folder of each page seen (not the site's root folder), weighted by its seconds (at least 10 a page) and faded by the half-life from the visit's start; top five with id, kind (`folder` or `category`), name, weight.
- On a catalogue page the product's groups replace the folder (a product in several groups shares its seconds evenly); a product group page counts that group. Never the catalogue's top group (`parent_id` 0). An item gone from the catalogue falls back to the folder (`pg_lead_interest_categories()`). The item comes from the visit's page row (`visitors/lead-journey-store.md`).

## How does the pass run?
- `job.php` calls `pg_lead_score_run()` every tick; it works only every 600 s (`PG_LEAD_SCORE_EVERY`) unless a pass is unfinished, within 10 s of the tick (`pg_cron_time_left()`), 200 contacts a run.
- Pass record config_text `lead_score_pass` `{since, started}`, claimed with `pg_config_text_claim()` so overlapping runs do not both go. Candidates: a visit started since `since` − 1 day (a visit still going), a journey row since `since`, a score with interest over 0 not worked out for a day (fading); minus those already scored in this pass (`scored_at` ≥ `started`).
- `pg_lead_score_contact()`: a contact with panel work, no visit any more or no contacts row loses its row. Writes `interest`, `fit`, `reasons` `{interest, fit}`, `topics`, `points` `{at, kinds}`, `hot` (interest ≥ threshold), `hot_at` set when it turns hot, `last_visit_at`, `touched_at` (newest journey row), `scored_at`. `returned_at` and the events are LJ4.

## Where is it shown?
- Contact screen, second column under the picture: card "Interest" (`pg_lead_score_contact_card()`), only once scored: Interest and Fit figures of 100, a "Hot" pill, "Why" (top three of both reasons), "Interested in" pills.

- Contacts list: the "Score" column (`visitors/lead-score-settings.md`).

Tests: `tests/lead_score_test.php`.
Related: docs/memory/visitors/lead-score-settings.md, docs/memory/visitors/lead-journey-rules.md, docs/memory/visitors/lead-journey-view.md
