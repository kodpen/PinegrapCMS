---
name: order-filter-names
description: Orders list Advanced Filters (view_orders.php): filter_ ids named by their labels, the Date Type select name, no address autofill, phone and e-mail keyboards
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

## What was left?
- The Start/End date selects (datetime picker plan), the `filter_list` header icon and the list rows.

Test: `tests/order_filter_names_test.php`.
