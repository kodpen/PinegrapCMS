---
name: accessibility-statement
description: Accessibility statement (system widget accessibility_statement, view_accessibility.php?statement=1): settings card, conformance verdict from the nightly report, known limitations, W3C structure
module: widgets_accessibility
---

## What is it?
- The statement the EU Accessibility Act and EN 301 549 ask for, in the W3C statement structure: commitment and scope, conformance status (WCAG 2.2 AA, EN 301 549), known limitations, feedback and contact, assessment approach, enforcement procedure, date. Code: `includes/fn/widgets_accessibility.php`.
- Two ways out: the system widget kind `accessibility_statement` ("Accessibility Statement", `bi-universal-access`; JS starter only, no Messages block, no edit chip) and a printable page `view_accessibility.php?statement=1` (managers and up) for a site without such a page. The report's toolbar has "Create the statement page" (opens the settings card, whose text says to place the widget) and "Print the statement".
- The older palette block `legal-accessibility-statement` (Blocks › Legal) stays: fixed text the operator writes, nothing from the report.

## Where are the texts kept?
- Site Settings › Visitors and privacy › Accessibility Statement (`pgset-a11y-statement`, `includes/settings/privacy.php`, saved in `privacy.save.php` when the card's hidden `a11y_statement_card` is posted): organisation, scope, contact, feedback, enforcement, date. One `config_text` row `a11y_statement` as JSON, so no schema; all empty deletes the row. `pg_a11y_statement_clean()` trims, cuts each field at `pg_a11y_statement_fields()` (characters) and drops a date that is not a real Y-m-d.
- Empty organisation or scope: the site name (`HOSTNAME_SETTING`); empty date: today; empty contact, feedback or enforcement: that part is left out.

## How is the conformance decided?
- `pg_a11y_conformance()` (pure) over the report's stored findings (`pg_a11y_report_summary()`, codes the rule registry still names): `unchecked` until a full pass has finished (no claim, never "full" for a site never checked, as #2041 for the report), `full` with no finding, `none` when error findings stand on at least half the pages checked (`pg_a11y_pages()`), else `partial`. The automatic check covers only part of each criterion; the statement says so under "Assessment approach".
- Known limitations: `pg_a11y_statement_limits()` one row per check with findings, errors first, then by page count; label, criterion with name, level, pages.

## Tokens and flags?
- Static: `__a11y_organisation`, `__a11y_scope`, `__a11y_standard`, `__a11y_conformance` (the claim in a sentence), `__a11y_contact`, `__a11y_feedback`, `__a11y_enforcement`, `__a11y_date`, `__a11y_last_check`, `__a11y_pages`, `__limit_total`. Row (loop_area): `__limit_label`, `__limit_criterion`, `__limit_level`, `__limit_pages`. All escaped.
- Flags (`pg_a11y_statement_flags()`): `a11y_assessed`, `has_limitations`, `no_limitations`, `has_contact`, `has_feedback`, `has_enforcement`, `has_last_check`.
- The JS palette is `SW_ACCESSIBILITY_STATEMENT_TOKEN_GROUPS`: `check_bindings.php` reads only palettes named `SW_[A-Z_]*TOKEN…`, so a digit in the name (`A11Y`) hides it.

## What is not verified?
- `tests/a11y_statement_test.php` covers the verdict on fixed finding sets, the limitation order, the settings clean-up and escaping in the print view. The widget on a live page and the settings card save need a dev site.
