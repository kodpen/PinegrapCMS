---
name: legacy-layouts-census
description: Census of the page types' custom layouts for their move to Bootstrap 5.3.8 (LL1, #8968): per type the widget twin, layouts that differ from the template, Bootstrap 3 names
---

## What was counted?
- Both starting sites (`data/backups/*/sql.sql`, `*/layouts/<id>.php`, 88 + 89 files) and `includes/templates/<type>.php`, read with LB2's `pg_lbs_html()` (`includes/fn/legacy_bootstrap.php`). Counts are uses over both sites. Where the output joins and the cost: [[legacy-layouts-output-join]], [[legacy-layouts-map-cost]].

## How many layouts differ from their template?
- All of them: every modified file differs (white space aside), sharing 40-91% of its lines with today's template. They are the theme's own versions, not old copies, so converting the templates alone changes no starting-site page.
- 177 files hold 106 distinct texts (both sites share most). Four files belong to `standard` pages and are never drawn.
- The templates print Bootstrap 3 too (express order 148 renamed uses, cart 73, order preview 65, view order and order receipt 41 each).

## Which types, twins and names? (pages with a custom layout / distinct texts; renamed uses; by hand)
- Widget twin per `pg_layout_widget_kinds()` for all but two: `custom form` (its layout turns into design elements) and `order receipt` (none).
- form list view 52/3: 312 (form-group, ti-close, ti-search), by hand 156 (`input-group-btn` 104, `pager` 52).
- custom form 26/21: 248 (form-group 144, checkbox 40, glyphicon-ok 20, btn-default 12).
- order form 20/11: 584 (form-group 240, text-right 40, visible-xs-inline 40, input-group-addon 40).
- photo gallery 12/11: 16. catalog 10/9: 64, by hand 24 (`input-group-btn`, push/pull).
- catalog detail 6/6: 78, by hand 14. order receipt 6/5: 236 (text-right 188), by hand 42 (`dl-horizontal`).
- express order 5/5: 807 (form-group 338, text-right 170, radio 37, checkbox 36), by hand 10 (`form-inline`).
- billing information 4/3: 120. order preview 4/3: 249, by hand 36. shopping cart 3/3: 292 (help-block 21, input-group-addon 18).
- Two pages each, 6-44 uses: change password, set password, forgot password, email preferences, login, membership entrance, registration entrance, my account, my account profile, update address book, search results, shipping address and arrival, shipping method.
- No custom layout on a starting page: form item view (40 pages), view order (2). Their markup is the DB layout and PHP ([[legacy-layouts-output-join]]).

## Which names lead over all layouts?
- Renamed: form-group 1205, text-right 620, ti-angle-down 270, visible-xs-inline 184, ti-close 140, checkbox 123, glyphicon(-ok) 194, input-group-addon 88, help-block 80, col-xs-6 65, radio 59, sr-only 34.
- By hand: input-group-btn 136, dl-horizontal 72, pager 52, form-inline 16, push/pull 8.
- No `data-toggle` family in any layout; 8 files carry a `<script>`, none selects a Bootstrap 3 class, and no site script outside the layouts does either.
