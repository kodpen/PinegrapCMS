---
name: dashboard-v2-pipeline-card
description: New dashboard pipeline card (cards/pipeline.php, renderer in pg_dash_cards_work.js): the line per preset, the eight spans, where each step reads its data, gates, flags and links
---

## Where is it?
- `includes/dashboard/v2/cards/pipeline.php`; renderer `pgDash.renderers.pipeline` in `assets/js/pg_dash_cards_work.js` (beside Next up, so the bundle list was not touched); styles in the "Work cards: Pipeline" section of `pg_dash.src.css`. Tests: `tests/dashboard_v2_pipeline_test.php`.

## How does it know the preset?
- It reads the person's stored layout (`pg_dashboard_v2_row()`) and its `preset`; Simple, an unknown preset or no row take the first default (Ecommerce with the shop on, else Corporate). The answer carries `title` and `sub`; the renderer writes the title into the card head, so the gallery and edit mode keep "Pipeline". A preset picked in edit mode shows its line after the save.

## How is the period cut?
- `pg_dashboard_v2_pipeline_window()`: from the first day's midnight (today, 7 or 30 days stepped by `pg_dashboard_days_back()`) to now, cut in eight equal spans; one SQL query per step sums the spans with `CASE` on the raw time column (index kept; the last span has no upper edge). The compared period (visits only) ends at the start, or is a year back; compare 2 has none.
- Visits and page views come from `visitor_stats_hourly` (`pg_visitor_stats_range()`), an hour counted in the span its start falls in; never raw `visitors` rows.

## Which steps, gates and flags?
- Corporate: visits (visitors gate; note the change), forms (forms gate; list on the period), new contacts (staff: a role-3 person sees only some groups; list on the period), meetings = Workspace tasks the person created or is assigned to (flag: overdue).
- Blog: visits, page views (note pages per visit), comments on live pages (flag: waiting for moderation), subscribers (`opt_in`, list `filter=my_subscribers` on the period).
- Ecommerce: visits, carts = every order started (note: still `incomplete`), orders (`status != 'incomplete'`, note average total, flag: unshipped online orders as the greeting counts them), delivered = `shipping_tracking_numbers.delivered_at` (flag: refund requests).
- Agency: in work = pages edited, prototypes = `prototype_links` created (flag: opened in the period; link to the viewer of the design shared last, `prototype.php` needs a style), approvals asked of the person (flag: still waiting on them), going live = `page.publish_at` in the coming seven days.
- A step the person may not see, or whose table or column is missing (`pg_schema_has()`), is left out; no steps at all shows the empty line. Flags are text with their tone in the words, never colour alone.
