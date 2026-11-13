---
name: experiments-screen
description: The Experiments results screen (experiments.php, PX5 #6255): list, per-variant figures, lift, the z-test as a sentence, the daily-rate SVG, buttons posted to the sheet
module: experiments_results
---

## Where is it?
- `experiments.php` (root, `validate_area_access('designer')`): the Page Design Studio row (21) of the main menu after Translations, the panel search chip `Experiments` under the `design` gate, and "All experiments" in the toolbar of a page's sheet (`page_experiments.php`), whose experiment names link to `experiments.php?id=<n>`. Active row 21 for both screens.
- The list skips experiments of pages in a folder the user may not edit (`check_edit_access()`); a detail of one refuses with Access denied.

## What does one experiment show?
- Per variant (`pg_experiment_results_rows()`, pure): views, conversions (held inside the views), rate, change against the live page (the variant without a draft is the control) and "Publish the winner". Above the table one sentence per variant (`pg_experiment_verdict_sentence()`).
- Start, pause, resume, end and publish post to `page_experiments.php` with `return=results`; the sheet does the change (all its checks) and goes back to `experiments.php?id=`.
- When the page left the site (Recycle Bin, deleted), the screen drops Start, publishing a variant and the sheet link and says "The page is in the Recycle Bin." instead of the `pgbin_` name; pause, end and keeping A still post to the sheet, which accepts them without a live page.

## How is a variant judged?
- `pg_experiment_significance($views_a, $goals_a, $views_b, $goals_b)` (pure): rates, relative lift (null without a control rate), pooled two-proportion z, two-sided p (`pg_experiment_two_sided_p()`, Abramowitz–Stegun erf), significant below 0.05, and the views each variant still needs at these rates for 95 % confidence and 80 % power (0 when significant, null for equal rates or no views).
- `pg_experiment_verdict_kind()` → ahead / behind / more / even / none, judged by the views it carries (`views_a`, `views_b`, `goals_a`, `goals_b`): none only when neither version has views; more when one version has none or nobody converted yet (needed null; sentences "Not enough visits yet." / "No conversions counted yet."). Before, both cases fell to "No visits counted yet." or "same rate". The API's `verdict` keeps the same five values.
- Percentages go through `pg_experiment_percent()` (`{var:1}%` and `pg_format_number()`): `%37,5` in Turkish.

## How is the chart drawn?
- `pg_experiment_chart_range()`: start (or creation) to end (or now), at most the last 90 days. `pg_experiment_results_days()` sums `experiment_hourly` per day and variant in SQL; `pg_experiment_daily_series()` fills every day (calendar steps), percent with one decimal, null without views.
- `pg_experiment_chart_svg()`: an inline, `aria-hidden` SVG, one polyline per variant in `--pg-*` colours, no chart library; the same figures in a table inside a `<details>` ("Show the figures"). It draws as soon as any day has a rate, 0 % included (top at least 5 %); a lone point is a zero-length round-capped polyline, since the stretched SVG (`preserveAspectRatio="none"`) turned a circle into an ellipse.

## Why is the header "Change against the live page"?
- The key `Lift` already exists in the shards as an elevator (`Asansör`).

Related: docs/memory/designer/page-experiments-sheet.md, docs/memory/visitors/experiment-goals.md
