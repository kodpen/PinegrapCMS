---
name: seo-score-live-check-parity
description: pg_seo_compose() must get all four groups wherever the same SEO score is computed (designer seo_check vs pg_seo_recalculate); which inputs come from where.
---

## Why must two places computing the same number feed the same groups?
- `pg_seo_compose($meta, $structure, $link, $speed)`: parameters are optional and a missing argument does NOT error, it silently selects a different weight table.
- `api.php designer/seo_check` called it with two arguments (60/40) while `pg_seo_recalculate()` used four: designer showed 48, pages list 46.

## What does the live check feed now?
- All four. Meta and structure come from the canvas; link (`page.seo_link_score`) and speed (`pg_seo_evaluate_speed`) are read from the SAVED row, since both are measured from saved markup and incoming traffic and cannot be known before saving. The panel writes all four groups.
