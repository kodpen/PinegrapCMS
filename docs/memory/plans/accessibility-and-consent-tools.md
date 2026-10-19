---
name: accessibility-and-consent-tools
description: Plan (approved 2026-10-19, not started): designer contrast rule, nightly WCAG 2.2 Level A report, accessibility statement template, consent banner with equal accept/reject and Consent Mode v2 defaults
---

## Why?
- The designer validator has 13 rules and no contrast check (`_validationRules`, `assets/js/style_designer.js:54058`); contrast math exists only in the palette builder (`includes/fn/design_themes.php:415`). Turkish Presidential Circular 2025/10 (RG 21 Jun 2025) requires e-commerce sites under Law 6563 to meet the Level A checklist plus WCAG 2.2 within two years (about June 2027); the EU Accessibility Act applies to e-commerce since 28 Jun 2025 (EN 301 549 v4 moves to WCAG 2.2 AA). The consent banner sends Consent Mode v2 `update` only (`assets/js/pg_consent.js:152-161`), no default-denied call; the KVKK cookie guide (2025 revision) asks for equal-prominence accept/reject and per-purpose consent.

## What is decided?
- Designer: contrast rule (text vs resolved background, 4.5:1 / 3:1 large text) added to `_validationRules`, using the palette builder's contrast function moved into a shared JS helper; target-size (24×24) and "redundant entry" hints as warnings.
- Site report: a nightly job `seo_a11y_job.php` in the dispatcher (heavy lane) walks published pages like `seo_structure.php` and stores findings per page in the existing SEO issue tables with `category='a11y'` (missing alt, heading order, landmarks, labels, contrast, link text, language attribute, form error association, target size). Shown on the SEO screen as an "Accessibility" tab and in `GET /seo/issues?category=a11y`.
- Accessibility statement: a designer page template (block "Accessibility statement") seeded with the required sections (scope, standard, known issues, contact, date); the checklist report can be exported as PDF for the Ministry's monitoring forms.
- Consent banner: equal-weight Accept / Reject / Preferences buttons, per-purpose toggles (necessary, analytics, marketing), no pre-ticked categories, cookie inventory table from the catalogue, `gtag('consent','default', denied…)` before any tag and `update` after choice; GA stays held until consent as today. Consent events feed `consent_log` (`privacy-data-pack-audit-log.md`).
- Legacy classic pages: the nightly report covers them read-only; nothing in their markup is changed automatically.

## What remains?
1. Contrast helper + designer rules + tests on the ratio function (S–M).
2. Nightly a11y job, storage, SEO screen tab, API filter (M).
3. Statement block + PDF export (S).
4. Consent banner changes in `includes/fn/consent.php` and `pg_consent.js`, settings for button texts (S–M).
5. Memory: `docs/memory/platform/accessibility-report.md` when shipped.

## Open questions?
- Level AA checks (full WCAG 2.2 AA) are a later phase; Level A plus the Turkish checklist first.

## Where to start?
- Step 1; read `seo_structure.php`, `includes/fn/consent.php` and `docs/memory/platform/structured-data-open-graph.md`.
