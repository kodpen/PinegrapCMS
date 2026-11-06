---
name: detail-screen-values
description: Read-only values on details screens (Visit Details, submitted forms) as text not headings, escaped once, long links wrapping; Visit Details' wide layout; ERP quarter option wording
---

## How is a read-only value printed under its caption?
- As `<div class="h5">` (or `h4`/`h6`) under the `form-text` caption, never an `<h4>`/`<h5>`/`<h6>` element: a screen reader's heading list filled with "Yes", "-", dates and IP addresses and lost the real section headings. Bootstrap 6 gives `.hN` the element's size, weight and margin, so the look is the same (#2957, `view_visitor.php`, `edit_submitted_form.php`, `add_submitted_form.php`).

## Why are Visit Details' search terms escaped only once?
- `get_search_results.php` stores the raw query and `h()` double-encodes, so the old `h(str_replace(..., h($terms)))` showed `R&amp;D` for a search for R&D. One `h()` around the joined terms (#2957).

## How do long links fit a phone?
- The referrer URL, host name and landing page links on Visit Details carry `text-break`; neither the panel CSS nor Bootstrap 6 wraps or clips `.card` content (#2957).

## How is Visit Details laid out?
- `view_visitor.php` follows the wide layout (`edit-screen-wide-layout.md`), no save bar (nothing to save): the main column holds Referral Information, UTM (only with a UTM source) and Actions performed during Visit as value tiles (`$output_tile`, `col-12 md:col-6 xl:col-4`; Page Views and Order Total first, as `h4`); the sticky column holds a Summary (visitor number, start and end time, IP address, currency) and the location as `<dl>` rows (`$output_fact`). Five full-width cards in one column spent a wide screen on three short rows (B11). Pinned by `tests/visit_details_test.php`.

## How is the ERP quarter option worded?
- `erp_profit.php` and `erp_vat_report.php` print `lang('{var:1} / Q{var:2}')` with the year and quarter number (Turkish "2026 / 3. Çeyrek"); the option value stays `Y-Qn`, which the period parser reads (#2957).

Related: docs/memory/ui/report-screen-controls.md, docs/memory/ui/panel-accessible-names.md
