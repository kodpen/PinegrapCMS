---
name: page-experiments-steps
description: Steps PX1-PX4 of plans/page-experiments.md: the experiment model and bucket, serving a variant with the cache, goals into the rollup, the designer sheet; claim as [plan:page-experiments] PX<n>
---

## Which run side by side?
- Wave 1: PX1 alone (questions 1-2 first). Wave 2: PX2, PX4 and PX6 at once (serving, the designer sheet, audience conditions: different files). Wave 3: PX3 after PX2, PX5 after PX3, PX7 after PX5. Wave 4: PX8. PX5-PX8: `page-experiments-steps-2.md`.
- S under 400 product lines, M about 1,000. Tests in `tests/experiments_<topic>_test.php` (pure functions, red first), memory under `designer/` and `visitors/`, texts through `tr_add.php`, `php tools/build_js.php` after a bundled script, `node --check` on `style_designer.src.js`.

## Which steps are there?
- Done: PX1 #6141, PX2 #6188, PX3 #6230, PX4 #6184.
