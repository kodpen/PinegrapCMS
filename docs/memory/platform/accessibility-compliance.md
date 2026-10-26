---
name: accessibility-compliance
description: Why Pinegrap has accessibility and consent tools (Circular 2025/10, EU Accessibility Act, KVKK cookie guide), which piece covers what, and what is left for later
---

## Why are these tools there?
- Turkish Presidential Circular 2025/10 (RG 21 Jun 2025) asks e-commerce sites under Law 6563 to meet the Level A checklist plus WCAG 2.2 within two years (about June 2027). The EU Accessibility Act applies to e-commerce since 28 Jun 2025 (EN 301 549 v4 moves to WCAG 2.2 AA).
- The KVKK cookie guide (2025 revision) asks for equal-prominence accept/reject and per-purpose consent.

## Which piece covers what?
- Designer audit (PR #628, PR #642): text contrast against the resolved background with `assets/js/pg_contrast.js`, the browser twin of `_pg_theme_contrast()`; target size; redundant entry. Measured on the canvas, not as tree rules (docs/memory/designer/designer-audit-contrast.md, designer-audit-targets-entry.md).
- Site report (PR #1748, PR #1760): the nightly `seo_a11y_job.php`, findings in `seo_issue` with `a11y_` codes, `view_accessibility.php` with its PDF for the Ministry's monitoring forms, `GET /seo/issues?category=a11y` (accessibility-report.md). The report has its own screen rather than an SEO tab: there is no separate SEO screen to hold one.
- Accessibility statement (PR #688): palette Blocks › Legal › "Legal: Accessibility Statement" (`legal-accessibility-statement`), six sections: scope, standard, known issues, contact, date.
- Cookie notice (PR #619): cookie-consent-notice.md. Its wording is the translation system's (`pg_consent_ui_keys()`), with no settings of its own.
- Classic pages: the nightly report reads them; nothing in their markup is changed automatically.

## What is left for later?
- Full WCAG 2.2 AA checks are a later phase; Level A plus the Turkish checklist came first (contrast and target size are the AA checks already in).
- The Turkish checklist's own items beyond WCAG Level A are not mapped: the report and its PDF go by WCAG criterion. If the PDF has to follow the checklist's numbering, ask the owner for the checklist text.
