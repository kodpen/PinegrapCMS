---
name: erp-account-save-partial-writes
description: Write-only-what-was-sent rules for erp_account_save: key-exists writes, API PATCH full-data pattern, CSV optional-column rule, contact_id nullable.
---

## Why does `erp_account_save()` write some columns only if the key exists?
- `contact_id` (and `overdue_notify_customer`) are written only when `array_key_exists`. Lesson: writing a column as "0 if absent" turns every form that does not send that column into a resetter (the edit form that omitted the key reset the link).

## How does an API PATCH update an account?
- `erp_account_save()` writes every column it was not given as its default, so the `PATCH` handler first builds the full `$data` from the existing row (`erp_api_account_save_data()`), then overlays the incoming fields. The same pattern applies to any other partial-update endpoint.
- `contact_id` is `nullable` + `min 0`: null/'' → 0 → the link is dissolved (`erp_account_link_contact($id, 0)`).
- IIS: WebDAV answers 405 on PATCH; the API rule is `also_accepts`, i.e. POST on the same path with `X-HTTP-Method-Override`. When writing an endpoint use `'method' => 'POST', 'also_accepts' => array('PATCH')`; when testing, send the override header.

## What is the optional-column rule for CSV import?
- Do NOT put the key in the `$account` defaults; write it only when its `case` matches. Then the "write if key exists" rule of `erp_account_save()` protects a column absent from the file (a new card keeps the DB default). `overdue_notify_customer` works this way.

Related: docs/memory/erp/erp-account-contact-link.md
