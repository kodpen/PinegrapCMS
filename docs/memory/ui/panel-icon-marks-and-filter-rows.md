---
name: panel-icon-marks-and-filter-rows
description: Ligature status marks with a hidden text, filter-link rows as nav not tablist, list-cell text visible instead of a title, payment method shown through pg_payment_method_label() on return screens
---

## How is an icon-only status mark named?
- A Material Icons ligature (`task_alt` in the recurring commission profile list, `pg_commission_profile_list_row()`) is `aria-hidden="true"` and followed by `<span class="visually-hidden">` with `lang('Yes')`; without it a screen reader reads "task_alt". The Enabled column of My Campaign Profiles does the same. A ligature inside a labelled button (`save`) is `aria-hidden` too.

## How is a row of filter links marked up?
- As `<nav aria-label="Filters">` with plain links carrying `aria-current`, like `.mpp-modes`. `role="tablist"` with no `role="tab"` children is announced as an empty tab list (the three `.mpp-tabs` rows of `marketplace_products.php`). No script or style reads the role.

## Where does text a list cell needs go?
- Into the cell as a visible `small fg-secondary` line (the item names of a return request on the Returns list, like the e-mail under the customer). A `title` turns into a hover popover (`pgBindTitlePopovers()`) on a cell that takes no focus, so keyboard and touch users never see it.

## How do the return screens show the payment method?
- `add_return.php` and `view_return.php` print `pg_payment_method_label($order['payment_method'])`, as `view_order.php` does; the stored value is the English name ("Credit/Debit Card").

## Where does Edit Recurring Commission Profile go back to?
- Cancel, the breadcrumb and the save redirect all lead to `view_recurring_commission_profiles.php`, the only screen that opens it.

Tests: `tests/returns_commission_names_test.php`; the three e-mail campaign lists (row boxes, `'{var:1}%'` progress, ligatures, landmarks) in `tests/email_campaign_list_names_test.php`.
