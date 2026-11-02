---
name: page-experiments-steps-2
description: Steps PX5-PX8 of plans/page-experiments.md: the results screen, audience conditions and their cache rule, API and events, close-out; claim as [plan:page-experiments] PX<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `page-experiments-steps.md`; PX6 runs beside PX2 and PX4.

- PX5 (M) Results screen. After PX3.
  - `experiments.php` (panel, under the Pages tools and the panel search): the list with status and dates; per experiment a table of views, conversions and rate per variant, lift against the control, a plain sentence from a two-proportion z-test ("B is ahead by 12 %, and the difference is unlikely to be chance" / "not enough visits yet: about N more"), a daily chart of rates from `experiment_hourly`; Publish the winner here too. `pg_experiment_significance()` pure and tested against known pairs. The Visitors overview (`plans/visitors-overview.md`) may later show the running ones as a card. Memory: `ui/experiments-screen.md`.

- PX6 (M) Audience conditions. `free`.
  - `pg_sw_cond_specs()` gains the `audience` namespace: `returning` (a first-party cookie set on the first visit, functional), `country` (from `includes/geo.php` as the language redirect reads it), `device` (phone, tablet, desktop by `sql_device_type()`'s rule), `utm_source`, `utm_campaign` (the request, then the visit row), `signed_in`, `member`, `group:<id>` (contact group membership, which `audiences.md` fills by rules), `experiment:<id>` (the bucket from PX1). The condition panel lists them with the operators that fit (`designer/widget-condition-panel.md`); the cache rule: a page whose tree holds a `country` or `device` condition is cached per value (the key grows), one with `signed_in`, `member` or `group` is served as a personal page (`pg_page_cache_personal()` reason `audience`), `returning` and `utm_*` make the page personal too. `check_bindings.php` learns the tokens. Tests: each token's value from fixed requests, the cache decision per tree. Memory: `designer/audience-conditions.md`.

- PX7 (S) API, MCP and events. After PX5.
  - Resource `experiments` (list, get with figures, start, pause, end) with the schema rows and the `experiments:read` / `:write` scopes (`api/api-endpoint-rules.md`); events `experiment.started` and `experiment.ended` through `pg_announce()`; MCP tools follow. Memory: `api/api-experiments.md`.

- PX8 (S) Close-out. After PX1-PX7.
  - PR body names `[NEW] Added A/B experiments on designed pages and audience conditions on elements`; decisions to `designer/` and `visitors/`; the three plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
