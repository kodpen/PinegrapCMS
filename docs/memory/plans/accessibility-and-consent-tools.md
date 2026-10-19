---
name: accessibility-and-consent-tools
description: Plan (approved 2026-10-19; designer checks done, #628 #642): nightly WCAG 2.2 Level A report, accessibility statement, consent banner (equal accept/reject, Consent Mode v2 defaults)
---

## Why?
- The designer audit had no contrast, target size or repeated-field check; it now measures them on the canvas (step 1). Turkish Presidential Circular 2025/10 (RG 21 Jun 2025) requires e-commerce sites under Law 6563 to meet the Level A checklist plus WCAG 2.2 within two years (about June 2027); the EU Accessibility Act applies to e-commerce since 28 Jun 2025 (EN 301 549 v4 moves to WCAG 2.2 AA). The consent banner sends Consent Mode v2 `update` only (`assets/js/pg_consent.js:152-161`), no default-denied call; the KVKK cookie guide (2025 revision) asks for equal-prominence accept/reject and per-purpose consent.

## What is decided?
- Designer (built): text contrast against the resolved background (4.5:1, 3:1 for large text) with `assets/js/pg_contrast.js`, the browser twin of the palette builder's `_pg_theme_contrast()`; target size (24×24 with the spacing exception) and redundant entry hints. Measured on the canvas beside `_validationRules`, not as tree rules: docs/memory/designer/designer-audit-contrast.md, designer-audit-targets-entry.md.
- Site report: a nightly job `seo_a11y_job.php` in the dispatcher (heavy lane) walks published pages like `seo_structure.php` and stores findings per page in the existing SEO issue tables with `category='a11y'` (missing alt, heading order, landmarks, labels, contrast, link text, language attribute, form error association, target size). Shown on the SEO screen as an "Accessibility" tab and in `GET /seo/issues?category=a11y`.
- Accessibility statement: a designer page template (block "Accessibility statement") seeded with the required sections (scope, standard, known issues, contact, date); the checklist report can be exported as PDF for the Ministry's monitoring forms.
- Consent banner: equal-weight Accept / Reject / Preferences buttons, per-purpose toggles (necessary, analytics, marketing), no pre-ticked categories, cookie inventory table from the catalogue, `gtag('consent','default', denied…)` before any tag and `update` after choice; GA stays held until consent as today. Consent events feed `consent_log` (`privacy-data-pack-audit-log.md`).
- Legacy classic pages: the nightly report covers them read-only; nothing in their markup is changed automatically.

## What remains?
1. Done (PR #628, PR #642): contrast helper with the PHP-JS test, designer contrast, target size and redundant entry checks. The nightly report (2) reuses `_pg_theme_contrast()`. Left: drop `_SD_ENTRY_SOFTWARE_TWICE` (the `email_verify` exemption) once PR #668 (the address asked once) is in.
2. Nightly a11y job, storage, SEO screen tab, API filter (M).
3. Statement block + PDF export (S).
4. Consent banner changes in `includes/fn/consent.php` and `pg_consent.js`, settings for button texts (S–M).
5. Memory: `docs/memory/platform/accessibility-report.md` when shipped.

## Open questions?
- Level AA checks (full WCAG 2.2 AA) are a later phase; Level A plus the Turkish checklist first.

## Where to start?
- Step 2; read `seo_structure.php`, `includes/fn/consent.php` and `docs/memory/platform/structured-data-open-graph.md`. The report cannot run the browser checks: on the server, contrast is computable only from inline styles and known classes.
