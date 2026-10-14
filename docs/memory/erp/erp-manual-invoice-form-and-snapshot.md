---
name: erp-manual-invoice-form-and-snapshot
description: Manual invoice form mechanics (lines[n][field] arrays, template row, JS vs PHP totals), the account snapshot columns (4.51) and extra document data.
---

## How do the invoice form fields work?
- Line fields are the array `lines[n][field]`. `liveform::add_fields_to_session()` stores an array value as-is and `get_field_value('lines')` returns the array (inner values are not trimmed; PHP trims on its side).
- Line inputs are still printed with `output_field()`, with the value passed explicitly via `value` (the field name is not in the session, so the session value does not apply).
- The `<template>` row is printed with an `__INDEX__` placeholder and cloned by JS.
- Product choice is a shortcut, not a lock: `product_id` sits in a hidden field and drops to 0 when the product name is edited.
- JS totals mimic `erp_kurus` / `erp_line_total` / `erp_apply_rate` exactly (whole kuruş, `Math.round`); PHP recomputes on every save; a mismatch between the two is a bug.

## What is the account snapshot on an invoice? (4.51)
- Columns `erp_invoices.account_title / account_tax_number / account_tax_office / account_address / account_city / account_country_code / account_email`, copied from the account card by `erp_invoice_snapshot_account()` (`accounts.php`) at issue (also copied on draft save, re-copied at issue). The order bridge calls the same helper.
- `document.php`, `edit_erp_invoice.php` and `erp_invoices.php` read the copy FIRST and fall back to the live card when `account_title` is empty (backward compatibility; the migration backfills older issued invoices).
- Postal code and district fold into the address line (`address, postal_code district`).
- Join aliases are now `live_*`: writing `a.title AS account_title` shadows the column; do not do it in new queries.

## What was added to document data?
- Template unchanged, placeholders only: line `unit` (UN/ECE unit code, default `C62`); header `is_purchase`, `supplier_invoice_no`, `supplier_invoice_date` (on a purchase invoice the supplier's number; our own number still comes from the `purchase_invoice` series).

Related: docs/memory/erp/erp-manual-invoice-draft-issue.md
