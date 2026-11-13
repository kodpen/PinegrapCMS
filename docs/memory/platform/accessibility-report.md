---
name: accessibility-report
description: Accessibility report (seo_a11y_job.php, includes/fn/a11y.php, view_accessibility.php): server-side WCAG 2.2 checks, a11y_ codes in seo_issue, the resumable pass, screen, PDF, API filter
module: a11y
---

## What does the report check?
- `pg_a11y_rules()` lists every check with its severity, WCAG 2.2 criterion and level (Level A, plus contrast 1.4.3 and target size 2.5.8 of AA); `pg_a11y_rule_<code without a11y_>()` is its function over the parsed page, one example per occurrence. `alt=""` is decoration; a placeholder is no label.
- Generic link text: name and list both pass `fold_keyword_for_duplicates()` (I, İ, ı as i): TIKLA counts (#8621).
- Contrast and target size only where written inline (both colours, `_pg_theme_contrast()`; width and height in px); an unsure colour skips the text. The designer measures both on the canvas (`designer/designer-audit-contrast.md`).
- Title, lang and main run only when the output holds `<html`: a page may answer JSON or a fragment (`visitors/seo-structure-non-html-output.md`).
- Hidden markup (`hidden`, `aria-hidden`, inline `display:none`) is left out.

## Where are the findings kept?
- In `seo_issue`, `entity_type = 'page'`, codes starting `a11y_`; no column or table of its own. `pg_seo_store_issues(..., 'a11y')` replaces only those rows, never those measured in the editor (`designer/editor-a11y-measurements.md`); other scopes never do.
- Not SEO findings: `pg_seo_load_issues()` (category `seo`), `seo.php`'s flag map and `GET /seo/issues` leave them out; category `a11y` reads them.
- A deleted page loses them with its other `seo_issue` rows; a page the structure pass finds unrenderable too (`pg_seo_forget_findings()`).

## How does the pass run?
- `seo_a11y_job.php`, dispatcher job `seo_a11y_job` (heavy lane, daily), opt-in like every dispatched job. Pages: `pg_render_pass_scope('a11y')` (`background-render.md`); classic pages are read, never changed. Rendered by `pg_seo_render_page()`.
- A run stops after `SEO_ANALYZE_TIME_BUDGET` (120 s) or the time `job.php` has left; keeps its place in `data/temp/seo_a11y.json` after every page; a lost file restarts the pass. Only a finished pass records its run and `seo_a11y_job_done` ("Last full check"); an unfinished one is due again in a minute (`scheduled-job-resume.md`). At the end the findings of pages no longer in the report are deleted.
- A page whose render fails keeps its earlier findings; the run logs how many failed (job and Check Pages Now alike), the button's notice names them.
- The state file also adds up the pass's checked and failed pages; a finished pass keeps them in `config_text` `a11y_last_pass` (JSON, no schema). The PDF and the statement read it through `pg_a11y_pass_record()`: "M of N published pages checked" when some could not be rendered, never the whole scope as checked (#5853). With no record yet the scope stands in.

## Where is the report shown?
- `view_accessibility.php` (managers and up), linked from the main menu's Pages panel (beside the 404 Log), the Pages list's SEO buttons and the panel search. A checklist row per check in criterion number order (`pg_a11y_rules_by_criterion()`, the filter and the PDF too, #5857; the registry keeps its own order), the findings worst first (1,000 rows, filter by check; a cut list reads "first N of M" under the table and in the PDF, #5858), Check Pages Now (`audit_run.php`: the same pass, anonymous, `SEO_ANALYZE_BUTTON_BUDGET`, resumes at the job's place), and the PDF.
- Download PDF: `pg_a11y_report_html()` (checklist then up to 2,000 findings) rendered by `pg_privacy_export_pdf()` (dompdf). Screen and PDF say an automatic check covers only part of each criterion.
- Until a full pass has finished (`pg_a11y_result_state()`), a check with no findings reads "Not checked yet" on the screen and in the PDF, never "No problem found" (#2041).
- The checklist's check column and the filter say "Automatic check" ("Denetim"), not "Check", whose Turkish is the button verb "Denetle" (#5860).

Test: `tests/a11y_test.php`.
