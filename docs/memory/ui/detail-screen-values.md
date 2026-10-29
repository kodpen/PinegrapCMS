---
name: detail-screen-values
description: Read-only values on details screens (Visit Details, submitted forms) as text not headings, escaped once, long links wrapping; ERP quarter option wording
---

## How is a read-only value printed under its caption?
- As `<div class="h5">` (or `h4`/`h6`) under the `form-text` caption, never an `<h4>`/`<h5>`/`<h6>` element: a screen reader's heading list filled with "Yes", "-", dates and IP addresses and lost the real section headings. Bootstrap 6 gives `.hN` the element's size, weight and margin, so the look is the same (#2957, `view_visitor.php`, `edit_submitted_form.php`, `add_submitted_form.php`).

## Why are Visit Details' search terms escaped only once?
- `get_search_results.php` stores the raw query and `h()` double-encodes, so the old `h(str_replace(..., h($terms)))` showed `R&amp;D` for a search for R&D. One `h()` around the joined terms (#2957).

## How do long links fit a phone?
- The referrer URL, host name and landing page links on Visit Details carry `text-break`; neither the panel CSS nor Bootstrap 6 wraps or clips `.card` content (#2957).

## How is the ERP quarter option worded?
- `erp_profit.php` and `erp_vat_report.php` print `lang('{var:1} / Q{var:2}')` with the year and quarter number (Turkish "2026 / 3. Çeyrek"); the option value stays `Y-Qn`, which the period parser reads (#2957).

Related: docs/memory/ui/report-screen-controls.md, docs/memory/ui/panel-accessible-names.md
