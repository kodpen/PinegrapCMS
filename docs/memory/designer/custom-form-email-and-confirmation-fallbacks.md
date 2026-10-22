---
name: custom-form-email-and-confirmation-fallbacks
description: Custom form submit fallbacks (empty plain text body -> field list without hidden fields for the submitter; missing or binned next page -> message) and what the legacy page screen refuses (#1209).
---

## What was the symptom?
- kodpen.com, 2026-10-21: a legacy subscription form (plain text, empty Body) put e-mails in the queue that failed six times with PHPMailer `empty_message`. The same form had Next Page with no page, so `get_page_name(0)` sent the visitor to `/` without a thank-you. Fixed in PR #1209.

## What does a submission do with an empty plain text body?
- `pg_cf_email_body_or_default($page_id, $body, $for_submitter)` (`includes/fn/custom_form.php`) runs before `get_variable_submitted_form_data_for_content()` at all six plain text sites: `custom_form.php`, `pg_send_custom_form_notifications()` in `includes/fn/mail.php`, `submit_order.php` (form sent with a product); each has a submitter and an administrator site, and only the submitter sites pass `true`.
- Empty after trim -> `label: ^^name^^` lines from `form_fields` (no `information` rows; empty label -> name, trailing colon dropped). No named field -> `Reference Code: ^^reference_code^^` + `Date Submitted: ^^submitted_date_and_time^^`. The stored body is not changed.
- The submitter's body leaves out `office_use_only` fields ("Hide This Field from Submitter"): the site fills them in for the office, and the legacy confirmation screen hides them too. The administrator's body keeps them. A form with only hidden fields gives the submitter the Reference Code / Date body.
- HTML mail with a missing page is not covered at send time; the screen refuses it instead.

## What does the legacy page screen refuse?
- `pg_cf_legacy_settings_errors($settings, $page_exists)` on `edit_page.php` and `add_page.php` (after the form name check, same redirect + `mark_error()` pattern, so the typed changes are lost like there):
  - E-mail Submitter / Administrator on + Plain Text + empty Body;
  - the same on + HTML (any format other than `plain_text`, as `custom_form.php` reads it) + no page, a deleted page or a page in the Recycle Bin;
  - Confirmation Type `page` + no, deleted or binned Next Page. An empty or missing type is the message and needs no page.
- On `edit_page.php` it runs only when the user may set the page's type (`$user_can_set_page_type`, the condition that guards saving the type properties). A basic user without `set_page_type_custom_form` sees no form fields and posts none; checking them refused every save of that page, even a title change.
- An empty stored `confirmation_type` renders the Message radio (it used to render Next Page), matching the submit rule.

## What does the visitor see when the next page is missing?
- `custom_form.php` resolves the alternative page first, then `pg_cf_confirmation_shows_message($type, $page_id)`: not `page`, id 0, a deleted page or a page in the Recycle Bin (`pg_recycle_page_binned()`) -> back to the form with `?<page_id>_confirmation=true`.
- Why the bin: deleting a page in the panel keeps its row and moves it into the private bin folder as `pgbin_<id>_...` (`pg_recycle_page()`), so counting the row sent the visitor to a page they cannot open.
- Both renderers accept that parameter on the same rule: `get_custom_form_screen_content.php` (gets `confirmation_page_id` from `get_page_content.php`) and `_render_system_widget_custom_form` in `includes/fn/widgets.php`.
- `pg_cf_confirmation_message_or_default()`: a message with no text (tags, `&nbsp;`) but no image/iframe/video shows `lang('Thank you. Your message has been received.')`, the text new forms are created with.

Related: docs/memory/designer/custom-form-options-and-rules.md