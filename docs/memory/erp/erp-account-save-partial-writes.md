---
name: erp-account-save-partial-writes
description: Which columns erp_account_save() writes only when given and which it always writes, the full-row rule for every partial update (API PATCH, account sync), the CSV optional-column rule.
---

## Which columns does `erp_account_save()` write only when the key is given?
- `contact_id`, `overdue_notify_customer`, `state` (4.71), `country_code` on an update (a new account takes the store's), `invoice_email` / `invoice_mail` (4.97), `credit_limit` (4.98): written only when `array_key_exists`, so a caller that does not name them keeps the stored value.
- Lesson: writing "0 if absent" turns every form that does not send the column into a resetter (the edit form that omitted `contact_id` cut the account's contact link).

## Which columns does it always write?
- Everything else: title, kind, is_person, tax_number, tax_office, email, phone, address, district, city, postcode, currency, status, notes, payment_days, overdue_notify_days. On an update an absent one falls to its default.
- So any partial update builds the full row from the stored account first and lays the new fields over it: `erp_api_account_save_data()` for the API `PATCH`, `erp_edoc_account_save_data()` for the e-document account sync. The same pattern applies to any new partial-update door.

## How does the API PATCH handle the contact link?
- `contact_id` is `nullable` + `min 0`: null/'' → 0 → the link is dissolved (`erp_account_link_contact($id, 0)`).
- IIS: WebDAV answers 405 on PATCH; the API rule is `also_accepts`, i.e. POST on the same path with `X-HTTP-Method-Override`. When writing an endpoint use `'method' => 'POST', 'also_accepts' => array('PATCH')`; when testing, send the override header.

## What is the optional-column rule for CSV import?
- Do NOT put the key in the `$account` defaults; write it only when its `case` matches. Then the key-exists rule protects a column absent from the file (a new card keeps the DB default). `overdue_notify_customer` works this way.

Related: docs/memory/erp/erp-account-contact-link.md
