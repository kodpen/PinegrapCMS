---
name: email-campaign-shared-form
description: The e-mail campaign create and edit screens share includes/forms/email_campaign_form.php: its helpers, the read-only flag, what stays in the screens
---

## Where is the campaign form?
- `includes/forms/email_campaign_form.php` (gate `PG_FUNCTIONS_DIR`), loaded by `add_email_campaign.php` and `edit_email_campaign.php` with `require_once` after `init.php`. Done in #2925.
- No helper reads the database; `tests/email_campaign_form_test.php` renders them.

## Which helpers does it hold?
- `pg_email_campaign_card($title, $body)`: the full-width card every part uses (the screens also use it for create's contact-group card).
- `pg_email_campaign_message_card($subject_row, $format_row, $extra)`: the 'E-Mail Message' card; edit passes its body as `$extra`.
- `pg_email_campaign_subject_row($subject, $readonly)`, `pg_email_campaign_format_row($content, $fixed)`: create passes its format radios with the plain-text body and the page select with preview; edit passes the stored format name with `$fixed = true`.
- `pg_email_campaign_from_card($values, $readonly)`: from name, from address, reply-to. Create prefills `ORGANIZATION_NAME` and the user's address.
- `pg_email_campaign_schedule_card($start_time, $readonly, $enabled)`: `''` unless `email_campaign_job_enabled()` (or `$enabled`); a zero date is blank; read-only and blank prints "Immediately" (before #2925 that branch never ran). Prints `get_date_time_picker_format() . pg_fields_script()`, no timepicker call (`bs6_timepicker_addon_test.php`).
- `pg_email_campaign_purpose_card($purpose, $readonly)`: CAN-SPAM commercial/transactional; anything but `commercial` checks transactional.

## How are its headings named?
- Format choices and Purpose radios sit in `<fieldset><legend class="form-label fs-md">`; a fixed format and a read-only purpose print a `<div class="form-label">` heading, never a `<label>` without a field. Pinned by `tests/email_campaign_screens_names_test.php` with the screens' names (contact group radios, extra address, Status, BCC, body, `{var:1}%` progress).

## When is the form read-only?
- Edit sets `$readonly = ($status == 'complete')` and passes it to every helper: values print as `<p>`, no form, no Save.

## What stays in the screens?
- Create: the page_id single-value guard, the page and contact-group queries, the contact-group table, the entered address, Create, the whole save.
- Edit: the not-found guard, the role-3 owner check, status select, progress, profile and order boxes, body textarea or preview iframe, the to rows (manual/automatic, BCC on automatic), Save, the UPDATE and the `send_to` redirect.
- Every query stays: `db_q_campaigns_visitor_report_test.php` counts add 13, edit 7.
