---
name: accessibility-report
description: Accessibility report (seo_a11y_job.php, includes/fn/a11y.php, view_accessibility.php): server-side WCAG 2.2 checks, a11y_ codes in seo_issue, the resumable pass, screen, PDF, API filter
---

## What does the report check?
- `pg_a11y_rules()` lists every check with its severity, WCAG 2.2 criterion and level; `pg_a11y_rule_<code without a11y_>()` is its one function over the parsed page, returning one example per occurrence. Level A: page title (2.4.2), `<html lang>` as a language tag (3.1.1), a main landmark (2.4.1), heading order (1.3.1), image alt (1.1.1, `alt=""` is decoration), control labels (a placeholder is none), label/aria idrefs that name no id, `aria-invalid` without a described error (3.3.1), link names and generic link text (2.4.4, the SEO word list), button and frame names (4.1.2), autoplaying sound (1.4.2). AA: contrast (1.4.3) and target size (2.5.8).
- Contrast and target size are measured only where the markup says it inline: both colours on the text or its ancestors (`_pg_theme_contrast()`, 4.5:1, 3:1 for 24px or bold 18.66px), width and height in px. A colour the check cannot be sure of (variable, gradient, image, transparency) skips the text. The designer measures both on the canvas (`designer/designer-audit-contrast.md`).
- Title, lang and main run only when the output holds `<html`: a page may answer JSON or a fragment (`visitors/seo-structure-non-html-output.md`).
- Hidden markup (`hidden`, `aria-hidden="true"`, inline `display:none`) is left out.

## Where are the findings kept?
- In `seo_issue`, `entity_type = 'page'`, codes starting `a11y_`; no column or table of its own. `pg_seo_store_issues(..., 'a11y')` replaces only those rows and the other scopes never touch them.
- They are not SEO findings: `pg_seo_load_issues()` (category `seo` by default), the flag map in `seo.php` and `GET /seo/issues` leave them out. `pg_seo_load_issues($type, $id, 'a11y')` and `GET /seo/issues?category=a11y` read them.
- A deleted page loses them with its other `seo_issue` rows; a page the structure pass finds unrenderable too (`pg_seo_forget_findings()`).

## How does the pass run?
- `seo_a11y_job.php`, dispatcher job `seo_a11y_job` (heavy lane, daily), opt-in like every dispatched job. Pages: public folder, not archived, a type `pg_seo_renderable_page_types()` lists; classic pages are read, never changed. Rendered by `pg_seo_render_page()`, the structure pass's render.
- A run stops after `SEO_ANALYZE_TIME_BUDGET` (120 s) and keeps its place in `data/temp/seo_a11y.json` (last page id); a lost file restarts the pass. The run is recorded (`pg_cron_ran()`) only when the pass reaches the end, so the dispatcher hands an unfinished pass out on the next tick. At the end the findings of pages no longer in the report are deleted.
- A page whose render fails keeps its earlier findings; the run logs how many failed.

## Where is the report shown?
- `view_accessibility.php` (managers and up), linked from the main menu's Pages panel (managers and up, beside the 404 Log), the Pages list's SEO buttons and the panel search. Legal background and the related tools: accessibility-compliance.md. A checklist row per check (criterion, level, pages and times found), the findings worst first (1,000 rows, filter by check), Check Pages Now (the same pass, `SEO_ANALYZE_BUTTON_BUDGET`, carries on from the job's place), and the PDF.
- Download PDF: `pg_a11y_report_html()` (checklist then up to 2,000 findings) rendered by `pg_privacy_export_pdf()`, the shared dompdf call. Both the screen and the PDF say an automatic check covers only part of each criterion.
- Not on the SEO detail panel or in the page's SEO block: those read `pg_seo_load_issues()` with category `seo`.

Test: `tests/a11y_test.php`.
