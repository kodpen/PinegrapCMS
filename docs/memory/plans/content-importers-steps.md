---
name: content-importers-steps
description: Steps IM1-IM3 of plans/content-importers.md: the import framework and screen, the WordPress WXR reader, pages and media fetches; claim as [plan:content-importers] IM<n>
---

## Which run side by side?
- Wave 1: IM1 alone (question 1 first). Wave 2: IM2, IM3, IM4, IM5 at once (four readers and the pages path, different files). Wave 3: IM6 after IM2-IM5. Wave 4: IM7. IM4-IM7: `content-importers-steps-2.md`.
- S under 400 product lines, M about 1,000. Tests in `tests/import_<topic>_test.php` on the fixtures, memory under `platform/`, texts through `tr_add.php`.

## Which steps are there?
- IM1 (M) Framework and screen. `free` after question 1.
  - Migration sub-step for `import_items` (question 1) and `imports` (id, kind, file, status, counts, started, finished, user). New `includes/fn/importers.php`: the reader contract (`pg_import_<kind>_open($file)`, `_next($cursor)` giving one item of a kind, `_plan($item)` for the dry run), `pg_import_run($import_id, $budget)` in the general job with a cursor file `data/temp/import_<id>.json`, the writers map (record, page, file, comment, contact, product, redirect) each returning made / skipped / failed with a reason, the report, and `pg_import_undo($import_id)` over `import_items`. `import.php` (panel): upload, kind detected by content, dry run table, mapping rows (target form and its fields, target folder, product group), Start, progress by short requests, the report, Undo. Memory: `platform/import-framework.md`.

- IM2 (M) WordPress WXR reader. After IM1.
  - `includes/import/wordpress.php`: a streaming XML reader (`XMLReader`, never the whole file in memory) over `<item>` elements: post types, status (published, draft as a draft record), dates in the site zone, author, categories and tags, the content with shortcodes stripped or kept as text (a fixed list: gallery, caption, embed), `wp:postmeta` for the featured image, comments under the item; `pg_import_wp_item()` maps to a record of the chosen form (title, body through `pg_sanitize_rich_text()`, excerpt, date, picture), to a page (handed to IM3), or to an attachment (handed to IM3); authors per question 2. Tests on the fixture: counts per kind, a post with a featured image and three comments, a draft. Memory: `platform/import-wordpress.md`.

- IM3 (M) Pages and media. After IM1.
  - Pages: the HTML body through `pg_designer_import_single_html()` into the chosen folder as drafts (the designer's import rules apply: images, lazy sources, framework files); media: a fetch queue inside the import (`pg_curl_run()` with `sink`, size caps, the stored-name rule, the file's own folder), the record's picture and the body's `<img>` addresses rewritten to the stored file after the fetch; an attachment that fails keeps its original address and a note in the report. Tests: the rewrite on fixed HTML, the cap. Memory: `platform/import-media.md`.
