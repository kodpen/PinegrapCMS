---
name: email-campaign-create-refusals
description: New campaign (add_email_campaign.php) refuses on its own form: the refusals listed above it, what was written kept (#10086, PR #10096)
---

## How does New campaign show a refusal?
- On the form itself: the screen draws the form again in the same request with what was posted (subject, body kind, plain text, pasted HTML, page, contact group choices, extra address, sender, send time, purpose) and lists the refusals above it with `pg_email_campaign_refusals_alert()` (`includes/forms/email_campaign_form.php`, a `role="alert"` box). The pattern is the SMS campaign screen's (`edit_sms_campaign.php`).
- Refused on the form: subject, sender, reply-to, format, purpose; an empty pasted HTML; no page or a page id with no page ("Please select a page for the body of the campaign."); a page the user may not view (still logged); an invalid "Also send message to" address; no recipient.
- The field, body, page and address checks are shown together; the recipients are picked only when the rest is right. Nothing is stored before every check passes.
- Still the bare error page: the token check and "Query failed.".

## Why not liveform `mark_error()` with a redirect?
- The form's fields are plain markup, not liveform fields, so a marked error would show nowhere at the field; a redirect would also carry the whole posted body (a pasted HTML can be large) through the session.

## What did it fix?
- Before #10096 every refusal called `output_error()`: a bare page whose only button, "Back to the control panel", lost the written campaign (#10086).
- Edit Campaign (`edit_email_campaign.php`) still refuses with `output_error(pg_email_campaign_field_errors_html(…))`.

Tests: `tests/email_campaign_form_test.php` (`test_email_campaign_refusals_alert`, `test_email_campaign_create_refuses_on_the_form`).
