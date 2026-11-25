---
name: designer-publish-weight-seo-check
description: The weight seo_check measures for the Publish check (PC5): when it is asked, the files whose sizes it reads, the design's own stylesheet and the other pages' names, the answer, its time
module: publish_weight
---

## What does `seo_check` answer?
- `weight` = `pg_publish_weight_report()`: `bytes`, `budget`, `grades`, `findings`, `unused` (selector, media) and `sizes` (node => natural [width, height] of a picture without width and height, what "Fix" writes). Null when not asked or the page is not saved, false when the draft did not render.
- Files: the software folder's own (`PATH . SOFTWARE_DIRECTORY . '/'` to `PG_FUNCTIONS_DIR`) and the uploaded files (`PATH` to `FILE_DIRECTORY_PATH`); a file in the object store or on another host is unknown.
- The stylesheet judged is the design's own, `<style id="pg-user-styles">` of the render (`pg_publish_weight_own_css()`). Used names: the render's, plus every word of the JSON of the design's other pages' trees and of all shared components (`pg_publish_weight_tree_names()`), so it errs towards used.
- `PgPublishFindings.read()` takes `sources.weight` as the findings (the measure's ids kept) or `false` (one failed finding).

## How fast is it?
- With the other pages' names (PC5): 383 KB page, 188 pictures, 255 KB own stylesheet of 6,000 rules, 30 other pages of 1,539 nodes: report 105 ms median (15 runs, PHP 8.3), of which the 30 trees' names 80 ms.

Test: `tests/publish_weight_test.php`.
