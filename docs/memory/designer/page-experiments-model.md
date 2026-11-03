---
name: page-experiments-model
description: Page experiments model: the three tables (11.0.153), statuses and moves, the weighted draw, the pg_exp bucket cookie, which requests never join
module: experiments
---

## Which tables hold an experiment?
- `experiments` (page, name, status, goal, dates, winner; `idx_page (page_id, status)`), `experiment_variants` (variant A is the live page, `draft_page_id` 0; others are drafts; `variant_key` VARCHAR(8), `weight`; unique per experiment), `experiment_hourly` (sha1 of `day|hour|experiment|variant`, views and goals, one `INSERT … ON DUPLICATE KEY UPDATE` per event). Step `upgrade_2026_11_0_page_experiments()`, 11.0.153; shape decided in #5038.

## Which status moves are allowed?
- `pg_experiment_may_move()`: draft→running; running→paused|ended; paused→running|ended; ended is final. A draft is deleted, not ended.

## How is a variant drawn?
- `pg_experiment_draw($weights, $random)` (pure): each key holds a share of [0, 1) as large as its weight, in the order given; zero, negative or non-numeric weights never win; random is clamped; '' when nothing is positive. `pg_experiment_random()` gives the random.
- `pg_experiment_for_page($page_id)`: the running experiment with at least two variants (the oldest if data holds more), cached per request; null before the upgrade created the tables (`pg_schema_has()`).

## What is in the pg_exp cookie?
- `<experiment id>:<key>` pairs joined by dots, sorted by id, at most 20 (oldest ids drop), keys `[a-z0-9]{1,8}`. 90 days, path `/`, HttpOnly, `SameSite=Lax`. `pg_experiment_bucket_read()` / `_write()`; write also updates `$_COOKIE`.
- Listed in `pg_consent_catalogue()` under necessary (strictly functional, no consent).
- `pg_page_cache_set_cookie_names()` leaves it out, so setting it keeps a page cacheable. The cacheable path still runs `header_remove('Set-Cookie')`, then sends a bucket written in the request again (`page-experiments-serving.md`).

## Which requests never join?
- `pg_experiment_request_excluded()` (pure, first reason wins): `bot` (`pg_request_is_automated()`, the firewall's verdict), `staff` (signed in, role below 3), `designer` (get_page_content mode `edit`), `preview` (`pg_request_is_panel_preview()`), `prototype` (`PG_PROTOTYPE_ENTRY`). Facts from `pg_experiment_request_context($mode)`. They always get the live page.

Related: docs/memory/plans/page-experiments.md, docs/memory/visitors/high-volume-counter-rules.md
