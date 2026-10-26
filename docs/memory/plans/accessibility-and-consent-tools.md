---
name: accessibility-and-consent-tools
description: Plan (approved 2026-10-19; designer checks #628 #642, statement block #688, consent notice #619, report #1748 and its screen done): menu entry and the designer's email_verify exemption left
---

## Why?
- The designer audit had no contrast, target size or repeated-field check; it now measures them on the canvas (step 1). Turkish Presidential Circular 2025/10 (RG 21 Jun 2025) requires e-commerce sites under Law 6563 to meet the Level A checklist plus WCAG 2.2 within two years (about June 2027); the EU Accessibility Act applies to e-commerce since 28 Jun 2025 (EN 301 549 v4 moves to WCAG 2.2 AA). The KVKK cookie guide (2025 revision) asks for equal-prominence accept/reject and per-purpose consent; the consent notice now meets it (step 4).

## What is decided?
- Designer (built): text contrast against the resolved background (4.5:1, 3:1 for large text) with `assets/js/pg_contrast.js`, the browser twin of the palette builder's `_pg_theme_contrast()`; target size (24×24 with the spacing exception) and redundant entry hints. Measured on the canvas beside `_validationRules`, not as tree rules: docs/memory/designer/designer-audit-contrast.md, designer-audit-targets-entry.md.
- Site report: a nightly job `seo_a11y_job.php` in the dispatcher (heavy lane) walks published pages like `seo_structure.php` and stores findings per page in the existing SEO issue tables with `category='a11y'` (missing alt, heading order, landmarks, labels, contrast, link text, language attribute, form error association, target size). Shown on its own screen (`view_accessibility.php`, from the Pages list beside the SEO buttons: there is no separate SEO screen to hold a tab) and in `GET /seo/issues?category=a11y`.
- Accessibility statement: a designer page template (block "Accessibility statement") seeded with the required sections (scope, standard, known issues, contact, date); the checklist report can be exported as PDF for the Ministry's monitoring forms.
- Consent notice (built, docs/memory/platform/cookie-consent-notice.md): Settings / Reject all / Accept all alike on the first view, per-purpose switches with nothing pre-ticked, the cookie list per category, Consent Mode v2 default at the top of `<head>` and an update before held scripts start; GA stays held until consent. Consent events feed `consent_log` (`privacy-data-pack-audit-log.md`). Its wording, the buttons included, is the translation system's (`pg_consent_ui_keys()`); no settings of its own.
- Legacy classic pages: the nightly report covers them read-only; nothing in their markup is changed automatically.

## What remains?
1. Done (PR #628, PR #642): contrast helper with the PHP-JS test, designer contrast, target size and redundant entry checks. The nightly report (2) reuses `_pg_theme_contrast()`. Left: drop `_SD_ENTRY_SOFTWARE_TWICE` (the `email_verify` exemption); PR #668 (the address asked once) is in, so nothing requires the second field any more.
2. Nightly a11y report: done (PR #1748 checks, job, storage, API filter; PR #1760 screen `view_accessibility.php` and PDF; `platform/accessibility-report.md`). Left: a Pages menu entry in `includes/fn/output.php` (held by another PR when built).
3. Statement block: done (PR #688; palette Blocks › Legal › "Legal: Accessibility Statement", `legal-accessibility-statement`, six sections).
4. Consent notice: done (PR #619).

## Open questions?
- Level AA checks (full WCAG 2.2 AA) are a later phase; Level A plus the Turkish checklist first.
- The Turkish checklist's own items beyond WCAG Level A are not mapped yet: 2a reports by WCAG criterion. The report goes by WCAG criterion; ask the owner for the checklist text if the PDF has to follow its numbering.

## Where to start?
- The left items of steps 1 and 2; `docs/memory/platform/accessibility-report.md`.
