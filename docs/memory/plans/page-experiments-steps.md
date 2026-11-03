---
name: page-experiments-steps
description: Steps PX1-PX4 of plans/page-experiments.md: the experiment model and bucket, serving a variant with the cache, goals into the rollup, the designer sheet; claim as [plan:page-experiments] PX<n>
---

## Which run side by side?
- Wave 1: PX1 alone (questions 1-2 first). Wave 2: PX2, PX4 and PX6 at once (serving, the designer sheet, audience conditions: different files). Wave 3: PX3 after PX2, PX5 after PX3, PX7 after PX5. Wave 4: PX8. PX5-PX8: `page-experiments-steps-2.md`.
- S under 400 product lines, M about 1,000. Tests in `tests/experiments_<topic>_test.php` (pure functions, red first), memory under `designer/` and `visitors/`, texts through `tr_add.php`, `php tools/build_js.php` after a bundled script, `node --check` on `style_designer.src.js`.

## Which steps are there?
- PX1 (M) Model and bucket. `free` after questions 1-2.
  - Migration sub-step for the three tables (`install_create_table()`, in `get_tables()`, rerun twice). New `includes/fn/experiments.php`: statuses (draft, running, paused, ended) and transitions, `pg_experiment_for_page($page_id)` (one running experiment per page, cached per request), `pg_experiment_draw($weights, $random)` (pure), `pg_experiment_bucket_read()` and `_write()` on the `pg_exp` cookie (`<experiment id>:<key>` pairs, 90 days, `SameSite=Lax`, the cookie's name in `pg_page_cache_set_cookie_names()` and in `pg_consent_catalogue()` as functional), `pg_experiment_request_excluded()` (bots by the firewall's verdict, staff sessions, the designer, Preview, prototype play). Tests: the draw over fixed randoms and weights, the cookie round trip, the exclusions. Memory: `designer/page-experiments-model.md`.

- PX2 (M) Serving and the cache. After PX1.
  - Where the router loads a designed page's tree for rendering: a running experiment draws or reads the bucket and swaps in the variant draft's tree (body only; title, meta, structured data, canonical and address from the live page; the draft's own address never answers for visitors while it is a variant); `pg_page_cache_decide()` adds the variant key to the cache key and the stored copy's beacon carries it; `page_view.php` and the server-side count write views into `experiment_hourly`; cache invalidation (`platform/page-cache-invalidation.md`) drops every variant copy when the live page, the draft or the experiment changes. Tests: the tree pick per bucket, the cache key, the exclusion paths. Memory: `designer/page-experiments-serving.md`.

- PX3 (M) Goals. After PX2.
  - Goal kinds: form sent (`custom_form.php` on a page of the experiment, or any page), order completed (`pg_order_complete()` context: the bucket cookie read at completion), element click (a `data-pg-goal` attribute the designer sets on a button or link, sent by the page-view beacon with the variant), page reached (the goal page's view with the bucket present). Each counts once per bucket cookie and experiment per day (a small `experiment_goal_seen` key in the cookie, no table). Tests: the once rule, each kind's count line. Memory: `visitors/experiment-goals.md`.

- PX4 (M) The designer's Experiments sheet. After PX1.
  - In the page's tools (the designer's page settings and `view_pages.php` tools menu): create an experiment (name, goal, pick or create the variant draft as a copy of the live page, weights), start, pause, end, "Publish the winner" (the variant's tree through `pg_designer_save_page()` with the lock and revision rules, then the experiment ends and the draft returns to a plain draft); a status chip on the page row and in the designer's top bar; a variant draft shows "variant of <page>" and refuses Publish and Bin while running. Memory: `designer/page-experiments-sheet.md`.
