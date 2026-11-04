---
name: experiment-goals
description: Page experiment goals (PX3, #6230): form, order, click, page; counted for the variant the pg_exp cookie holds, once a day per experiment by a mark in the cookie, into experiment_hourly.goals
module: experiments
---

## Why a rollup and no visitor rows?
- Views and goals follow `high-volume-counter-rules.md`: one aggregated `experiment_hourly` row per hour and variant, never a row per visit; the visit's variant lives only in `pg_exp`.

## How is a goal counted?
- `pg_experiment_goal($kind, $event)`: the visitor's buckets (`pg_exp`), the running experiments among them whose `goal_kind` is the kind (`pg_experiment_running_for_goals()`, one query plus variants), then `pg_experiment_goal_lines()` (pure): one line per experiment the event matches, for the variant the bucket names, unless counted today. Each line is one `INSERT … ON DUPLICATE KEY UPDATE goals = goals + 1` on the hour's row (`pg_experiment_count_goal()`, same key as the views).
- Nothing without a bucket, for crawlers and scripts, or for signed-in staff; nothing when headers are already out (the mark could not be sent, so a goal never counts twice).
- The cookie is the visitor's own and unsigned, so a script can rewrite the day mark: when a goal would count, `pg_experiment_goal_rate_exceeded()` holds an address to `PG_EXPERIMENT_GOAL_RATE_LIMIT` (10) goals a minute on the firewall's counter (scope `exp_goal`), as the page speed beacon does (#6255).

## How is "once a day" kept without a table?
- The day of the last count (days since 1970-01-01, `pg_experiment_day_number()` of the site's date) rides on the cookie's pair: `<id>:<key>:<day>`. `pg_experiment_bucket_parse()` reads the bucket with or without it; `pg_experiment_goal_seen_parse()` reads the marks. `pg_experiment_bucket_write()` keeps the request's marks unless given new ones, so the router's draw and the resend never drop them.

## Which event is each kind's goal?
- `pg_experiment_goal_matches()` (pure):
  - form: `custom_form.php` after a submission, outside the tracking switch; the form's page is `goal_target`, or without one the experiment's page or one of its variant drafts.
  - order: `pg_order_complete()` after the visit step; the buyer's request carries the cookie, a payment service's message has none and counts nothing.
  - click: an element (or one inside it) marked `data-pg-goal="<name>"`, set in the designer's Attributes panel; `goal_target` is the name, empty for any marked element. `get_page.php` puts `pg_experiment_click_inject()`'s script into the served experiment page before the page cache, so the copy has it; it posts `xg` (experiment id) and `xn` (name) to `page_view.php`, once per name and page load. The variant comes from the cookie, so the post carries nothing to sign.
  - page: the page `goal_target` names, viewed with a bucket: `get_page.php` on a GET (not edit, Preview or an e-mail body), and `page_view.php` for a cached copy with a beacon.

## What is not counted?
- A goal page answered from the page cache without a beacon (visitor tracking off and no experiment of its own): the router does not count goals.
- A visitor without JavaScript clicking a click goal.

Related: docs/memory/designer/page-experiments-model.md, docs/memory/designer/page-experiments-serving.md, docs/memory/visitors/page-view-beacon.md
