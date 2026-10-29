---
name: panel-load-failures
description: Dashboard cards and the SEO detail panel when a load fails: the card retry line, the 401/403 fragment of get_seo_analysis.php, response.ok && !response.redirected in the fetch handlers
---

## What does a dashboard card show when its request fails?
- `pg_widget_failed(id)` in `welcome.php`: the skeleton (`.card-body-placeholder`) becomes a `.card-body.pg-widget-failed` with 'Something went wrong. Please try again.' and a 'Try again' button that puts the skeleton back and calls `pg_widgets_request([id])`.
- Called from the `error` callback of `pg_widgets_one_by_one()`, from `pg_widget_apply()` on a refused answer (status not success, e.g. a widget's 'Access denied'), and for a card missing from a successful batch.
- A card already drawn keeps its last content: only a card still holding the skeleton is changed. Before this, a failed card outside the refresh list pulsed until the page was reloaded.

## How does get_seo_analysis.php refuse?
- With a status and a short translated fragment ('Your session has expired. Please sign in again.'): 401 when there is no session (checked before `validate_user()`, which would redirect to the sign-in page that fetch follows as a 200), 403 when the GET token fails `hash_equals()` (it no longer calls `validate_token_field()`, whose `output_error()` drew the site's designed error page with its Bootstrap 5.3.8 stylesheets into the panel). Same rule as `core/json-endpoint-rules.md`.

## What do the callers accept?
- Only `response.ok && !response.redirected`: both fetch handlers in `seo.php` (`pg_seo_render_run_script()` and the drawer of `pg_seo_render_detail_offcanvas()`) and the run-button copy in `view_folders.php`. Otherwise they show a translated `.alert` (session expired for 401/403/redirect, else a generic line; the run buttons get `.pg-seo-run-failed` under them and are enabled again).
- The File Manager preview `$.ajax` reaches its `.fail` branch on 401/403.
- Pinned by source in `tests/panel_load_failures_test.php`.

Related: docs/memory/ui/dashboard-widget-files.md, docs/memory/core/json-endpoint-rules.md, docs/memory/ui/button-busy-state.md
