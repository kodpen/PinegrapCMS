---
name: application-review-correction-link
description: A not eligible application entry's correction link (AR4, V4): sent from the submitted form's Application card, application_fix.php with the named fields, the re-check, until the programme closes
module: application_jurors
---

## How is it sent?
- The submitted form's "Application" card (`pg_application_entry_card()`), on a not eligible entry while the programme is open (status 1): "Send a correction link", then "Send the correction link again". A press, never automatic.
- `pg_application_fix_send()`: fields = those the eligibility reasons name, each once (`pg_application_fix_fields()`); a random 64-hex token, its sha256 in `application_entries.fix_token_hash`, the names in `fix_fields`. A new link replaces the old. Mailed to `get_submitter_email_address()`, with the field labels and the closing time; `audit_log` line `fix_link` on the entry.

## What does the page show?
- `application_fix.php?t=<token>`, in `waf_sensitive_scripts()`, no sign-in, a secure card titled with the form's name: the reasons, then only the named fields open (text, a pick list or radio as a select, a check box group as ticks, a file upload). "Only these answers can be changed".
- Not the form's own page, as the desk was first described (`application-review.md`): drawing the site form with all but some fields locked is a change to the custom form's renderer, left out of AR4.
- States (`pg_application_fix_link_state()`): `invalid` (404), `expired` (410: programme not open, or its `closes_at` passed), `done` (the entry is eligible or let in), `open`.

## What does saving do?
- `pg_application_fix_save()`: a choice outside the field's options is dropped (`pg_application_fix_input()`); the field's `form_data` rows are replaced; a file is placed as `custom_form.php` does (`pg_cf_place_upload()`, a `files` row in the field's upload folder). `forms.last_modified_timestamp` is touched.
- The entry is checked again (`pg_application_eligibility()`): eligible 1, or 2 with the new reasons; `audit_log` line `fix`. The link is not spent: a still failing entry is corrected again until the close.

Related: docs/memory/core/application-review-programme.md, docs/memory/core/application-review-jurors.md
