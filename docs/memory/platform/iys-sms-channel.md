---
name: iys-sms-channel
description: İYS MESAJ channel (PR #3806): contacts.iys_sms_status (11.0.136), the SMS consent rows pushed for a mobile number, İYS's SMS changes pulled and matched to the contacts' numbers
---

## What does the column hold?
- `contacts.iys_sms_status` VARCHAR(16) '' / approved / refused (11.0.136, #3570): what İYS last said about the contact's mobile number on the MESAJ channel, as `iys_email_status` (11.0.39) for the address. Nothing reads it to narrow sending (#2430: sending is every opted-in number İYS has not refused, and a refusal stops it through the consent log).

## When does the job handle SMS?
- In the same `pg_iys_run()` as e-mail, behind the same gate (complete account, e-mail switch on), once `pg_schema_has('contacts', 'iys_sms_status')`. There is no separate SMS switch: the card labels the one switch "E-mail and SMS" (`iys-settings-card.md`). One shared push cursor and one changes cursor serve both channels.
- Dry run as for e-mail: counts the rows ("consent answer(s) would be pushed"), calls nothing, leaves the cursor.

## Which number does a row go to?
- `consent_log` rows `sms`/`marketing`, source not `iys`. `pg_iys_push_number()`: a `checkout` answer goes with the billing phone of the contact's latest order (the box asked about it) while that is a mobile; else the contact's `mobile_phone`.
- `pg_iys_sms_recipient()`: Turkish mobiles only, as `+905xxxxxxxxx` (`pg_sms_number()`); a landline, a number abroad or none is `skipped`. `pg_iys_sms_consent_body()` = the e-mail body with `type` MESAJ.
- A 2xx sets `iys_sms_status` on the row's own contact only; other contacts with the number get it from the pull.

## How is a pulled change applied?
- `pg_iys_sms_changes_parse()` reads the MESAJ items (ONAY/RET, Turkish mobile) of the page `pg_iys_changes_parse()` reads; the e-mail reader is unchanged.
- `pg_iys_apply_sms_changes()`: one query per page finds the contacts whose `mobile_phone`, without spaces, dashes, brackets, dots and slashes, is one of `pg_iys_number_variants()`; each is confirmed with `pg_sms_number()`. A number stored with other separators (a no-break space) is missed.
- RET: a contact whose last `sms:marketing` row is `granted` gets a refused row (source `iys`, never pushed back), so sending stops (`pg_iys_sms_refusal_needed()`); then `iys_sms_status` for all of them. ONAY only sets the status.

## What is not verified?
- No İYS account and no database: the MESAJ field names and the `+90` recipient form come from İYS's published documents; the step was not run twice on a dev DB.

Related: docs/memory/platform/iys-client.md, docs/memory/core/sms-consent-box.md, docs/memory/platform/sms-and-iys-rules.md
