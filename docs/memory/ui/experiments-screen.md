---
name: experiments-screen
description: The Experiments results screen (experiments.php, PX5): figures per variant from experiment_hourly, lift, the two-proportion z-test as a sentence, daily rates; the per-IP limit on click goals
module: experiments_results
---

## How is a variant judged?
- `pg_experiment_significance($views_a, $goals_a, $views_b, $goals_b)` (pure): rates, relative lift (null without a control rate), pooled two-proportion z, two-sided p (`pg_experiment_two_sided_p()`, Abramowitz–Stegun erf), significant below 0.05, and the views each variant still needs at these rates for 95 % confidence and 80 % power (0 when significant, null for equal rates or no views). Goals are held inside 0 and the views.
- `pg_experiment_verdict_kind()` → ahead / behind / more / even / none; `pg_experiment_verdict_sentence()` gives the translated sentence.
- `pg_experiment_daily_series()` sums hourly rows per day and variant over every day of the range (calendar steps, at most 400), percent with one decimal, null on a day without views.

Related: docs/memory/designer/page-experiments-sheet.md, docs/memory/visitors/experiment-goals.md
