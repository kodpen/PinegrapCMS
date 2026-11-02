---
name: editor-a11y-measurements
description: Contrast and target size measured on the editor's canvas are kept with the accessibility report: seo_issue rows with source editor:<time>, replaced on each save, left alone by the nightly pass
module: a11y
---

## What is kept?
- On save, the page on the canvas (the active tab, not marked unchanged) carries `a11y_measured`: the `{rule, label}` of its canvas findings `low_contrast` and `target_size` (`_sdMeasuredA11y()` in `style_designer.src.js`, at most 1,000). Only a browser resolves those colours and sizes (`designer-audit-contrast.md`, `designer-audit-targets-entry.md`).
- `pg_designer_save_page()` calls `pg_a11y_store_measured()` (`includes/fn/a11y.php`): the registry's browser-only concepts map the editor rule to `a11y_contrast` / `a11y_target_size` with its a11y severity; one row per code, occurrences counted, the first label as `detail`, `source = 'editor:<save time>'`. No column of its own: owner decision 4 of the search-and-audits plan, kept without a schema change.

## When are they replaced or cleared?
- Every save that changed the page, or sent measurements, deletes the page's `a11y_` rows whose source starts `editor:` and writes the new ones. A save that changed the page without measuring it (another tab, the API, the AI editor, a canvas that could not be measured) leaves none: they described the page before it.
- A page the report does not cover (`pg_a11y_page_covered()`: private folder, archived, a type the passes do not render) gets none.
- The nightly pass (`pg_seo_store_issues(..., 'a11y')`) skips them; its end-of-pass delete of pages that left the report and `pg_seo_forget_findings()` remove them with the rest.

## How does the report show them?
- They count in the checklist like any finding. A finding row says "Measured in the editor, <date>" (`pg_a11y_measured_note()`) on `view_accessibility.php` and in the PDF, whose intro says contrast and target size are also measured in the editor.

Test: `tests/a11y_test.php` (`test_a11y_measured_*`).
