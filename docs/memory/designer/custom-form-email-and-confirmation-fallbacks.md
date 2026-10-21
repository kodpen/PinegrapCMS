---
name: custom-form-email-and-confirmation-fallbacks
description: Custom form e-mail body and confirmation fallbacks at submit time, and what the legacy page screen refuses to save (empty plain text body, HTML or Next Page without a page).
---

## What was the symptom?
- kodpen.com, 2026-10-21: a legacy subscription form (plain text, empty Body) put e-mails in the queue that failed six times with PHPMailer `empty_message`. The same form had Next Page with no page, so `get_page_name(0)` sent the visitor to `/` without a thank-you. Fixed in PR #PRNUM.

## What does a submission do with an empty plain text body?
- `pg_cf_email_body_or_default($page_id, $body)` (`includes/fn/custom_form.php`) runs before `get_variable_submitted_form_data_for_content()` at all six plain text sites: `custom_form.php` (submitter, administrator), `pg_send_custom_form_notifications()` in `includes/fn/mail.php` (both), `submit_order.php` (form sent with a product, both).
- Empty after trim -> `label: ^^name^^` lines from `form_fields` (no `information` rows; empty label -> name, trailing colon dropped). No named field -> `Reference Code: ^^reference_code^^` + `Date Submitted: ^^submitted_date_and_time^^`. The stored body is not changed.
- HTML mail with a missing page is not covered at send time; the screen refuses it instead.

## What does the legacy page screen refuse?
- `pg_cf_legacy_settings_errors($settings, $page_exists)` on `edit_page.php` and `add_page.php` (after the form name check, same redirect + `mark_error()` pattern, so the typed changes are lost like there):
  - E-mail Submitter / Administrator on + Plain Text + empty Body;
  - the same on + HTML (any format other than `plain_text`, as `custom_form.php` reads it) + no or missing page;
  - Confirmation Type not `message` + no or missing Next Page.
- An empty stored `confirmation_type` now renders the Message radio (it used to render Next Page), matching the submit fallback.

## What does the visitor see when the next page is missing?
- `custom_form.php` resolves the alternative page first, then `pg_cf_confirmation_shows_message($type, $page_id)`: not `page`, id 0 or a deleted page -> back to the form with `?<page_id>_confirmation=true`.
- Both renderers accept that parameter on the same rule: `get_custom_form_screen_content.php` (gets `confirmation_page_id` from `get_page_content.php`) and `_render_system_widget_custom_form` in `includes/fn/widgets.php`.
- `pg_cf_confirmation_message_or_default()`: a message with no text (tags, `&nbsp;`) but no image/iframe/video shows `lang('Thank you. Your message has been received.')`, the text new forms are created with.

Related: docs/memory/designer/custom-form-options-and-rules.md
