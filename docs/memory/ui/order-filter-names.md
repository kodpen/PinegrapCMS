---
name: order-filter-names
description: Advanced Filters panel: printed after the toolbar on every list; on Orders, filter_ ids named by labels, Date Type name, no address autofill, phone and e-mail keyboards
---

## Why did the filter labels name nothing?
- About 50 `<label class="form-label">` had no `for` and their fields no `id`. `pgInjectAriaAndRequiredMarkers()` (`backend.src.js`) ties a for-less label only to a next field that already has an id, so a screen reader read each filter as a bare "edit text" and a click on a label focused nothing.
- Each field now has `id="filter_<name>"` (prefix so nothing clashes with other ids on the list screen) and its label `for` points at it.

## How is the Order Date / Ship Date select named?
- It has no visible label (it sits under the Date Range heading): translated `aria-label` "Date Type".

## Why `autocomplete="pg-no-autofill"` on the person and address boxes?
- Names like `billing_first_name`, `shipping_zip_code`, `billing_email_address` made Chrome offer the admin's own saved address; picking it filled the whole section. `off` is ignored by Chrome (`settings-filter-autofill.md`). Billing and shipping person, address, phone, fax and e-mail boxes and `ship_to_name` carry the token.

## Which keyboards?
- Phone and fax: `inputmode="tel"`; e-mail: `inputmode="email"`. They stay `type="text"` so a partial value still filters.
- Contacts and the Submitted Forms Referring URL: `contact-filter-keyboards.md`.

## Where is the panel printed?
- After the toolbar (`#button_bar`) and before the stats and the list, on Orders, Contacts, Submitted Forms, Commissions and Site Log (Commissions and Site Log: after the row holding the toolbar and the toggle). It was first in `<main>` (Site Log: before it, via `pg_page_shell()` `pre_main_html`), so with the panel open, Tab reached its X and every filter box before the toggle that opened it, while at 1280 px and wider it is drawn as a sticky right column. Test: `tests/advanced_filters_order_test.php`.

## Are the panel's icons read aloud?
- No. The header icon before "Filters" and the Update button's icon are `aria-hidden="true"` on all five screens: Orders draws `bi-funnel` and `bi-arrow-repeat`; Contacts, Submitted Forms, Commissions and Site Log draw Material `filter_list` and `sync`. Before, a screen reader read "filter_list Filters" and "sync Update". Test: `test_advanced_filters_order_panel_icons_silent` in `tests/advanced_filters_order_test.php`.

## What else was finished?
- The Start/End date selects (`tests/list_date_filters_test.php`) and the Orders list rows (`tests/list_row_names_test.php`).

Test: `tests/order_filter_names_test.php`.
