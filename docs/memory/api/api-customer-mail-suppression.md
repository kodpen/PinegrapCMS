---
name: api-customer-mail-suppression
description: email_suppressed on the Customer answer (the suppressed badge of the contact screens) and POST /customers/{id}/allow-mail, the contact card's "Allow again" with dry_run
---

## What does email_suppressed say?
- Whether a `mail_bounces` row with `suppressed = 1` stops mail to the contact's stored address (hard bounce, complaint, manual block or the soft limit): the condition `pg_mail_bounce_suppressed_sql('contacts.email_address')` that `view_contacts.php` and the badge of `edit_contact.php` use.
- Every Customer read (list, get, create, update answers) selects it through `api_customer_suppressed_sql()`, which gives `0` before the 2026.11.0 table exists, so an old site answers false, not a failed query.

## What does POST /customers/{id}/allow-mail do?
- The "Allow again" button: `pg_mail_bounce_allow_contact()`, which reads the address from the contact (never sent) and clears through `pg_mail_bounce_clear()`, writing the activity line. Scope `customers:write` (the screen's contacts access), row rule through `api_customer_reachable_or_fail()` (the screen's `validate_contact_access()`).
- `dry_run`: the stop sits after the refusal. 409 `email_not_suppressed` when no block stands or the customer has no address (the screen does nothing silently; the API says so, so a rehearsal tells whether the real call lifts anything). A race lost between the check and the clear answers the same 409.
- Answers the Customer, `email_suppressed` false. No webhook: the screen announces nothing either.
