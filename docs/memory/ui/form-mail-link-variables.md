---
name: form-mail-link-variables
description: ^^email_preferences_url^^ and ^^confirm_subscription_url^^ in form e-mails: who gets a link, where and in which order they are filled in, why they are not standard fields (PR #1385)
---

## What do the two variables print?
- `^^email_preferences_url^^`: the signed `email_preferences.php` address for the recipient (`pg_email_preferences_url()` over the pure `pg_email_preferences_url_build()`, `includes/fn/mail.php`). '' for an empty address or one without "@" (no stricter check: every campaign recipient keeps `List-Unsubscribe`) or without `ENCRYPTION_KEY`.
- `^^confirm_subscription_url^^`: `pg_cf_confirm_subscription_url()`, the link `pg_fs_hold_submission()` keeps in `pg_fs_pending_url()` when a double opt-in form held the group (`core/form-double-opt-in.md`), only for the contact's own address, normalised (`pg_cf_confirm_subscription_url_for()`); '' otherwise, a resend included. Own HMAC prefix, 7-day expiry.
- HTML: `%5E%5Ename%5E%5E` the editor stored inside a tag (any hex case) is filled like `^^name^^` (`pg_cf_mail_link_decode_tokens()`, #8592); the recipient got the encoded token before. Text between tags and plain text are typed, so `%5E%5E` there stays.
- Write them bare or alone in a `[[...||...]]` block: `[[Manage your e-mails: ^^email_preferences_url^^]]`. Not inside `{if ...}` and not in the same block as `^^reference_code^^`: the form item view fills them before those are read, so `{if ^^email_preferences_url^^ == ''}` loses its field.

## Who gets a link?
- Only the e-mail to the person who sent the form: the submitter copy in `pg_cf_form_mails_send()` (`includes/fn/form_mails.php`), the resend in `pg_send_custom_form_notifications()`, a product submit form's billing address copy (`submit_order.php`, no confirmation link: no contact).
- A watcher's copy, the administrator's e-mail (also drawn under `pg_tr_suspend()`) and the confirmation page get ''. A signed preferences link lets its holder change that address's subscriptions.
- A message with a link is sent `'secret' => true` (`pg_cf_mail_links_in()`): its body stays out of the failure log and the delivery-failed notice.

## In which order is an e-mail filled, and why?
- `[[...]]` blocks must be evaluated only on text with no submitted value in it. Else a visitor's `[[^^email_preferences_url^^||` in one field and `]]` in another hide the fields between them in the administrator's e-mail.
- Subject and plain text body: `pg_cf_fill_mail_links()` on the template first, then `get_variable_submitted_form_data_for_content()`. `$submitter_email_subject` stays the template for the next recipient.
- HTML: `get_form_item_view.php` fills the layout (blocks included) before any value goes in. After `get_page_content()` the body only gets `pg_cf_fill_mail_link_tokens()`: bare tokens, escaped, the Link dialog's `http://` taken off, no block evaluated.
- Tests `tests/form_mail_links_test.php` check this order in all three senders and that `pg_cf_fill_mail_links($body` never appears.

## How is it wired?
- `pg_cf_mail_links_context(array(email_address, contact_id, custom_form_page_id, form_id))` is set just before the sender's copy is drawn and cleared (`false`) after `email()`. `pg_cf_mail_link_values()` reads it through the pure `pg_cf_mail_link_values_build($context, $suspended, $preferences_url, $confirm_url)`.
- `pg_mail_merge_address_hrefs()` (`includes/fn/mail.php`) also serves the review request e-mail (`^^review_url^^`, PR #1316).

## Why are they not standard fields?
- `get_standard_fields_for_view()` is also read by list views, the search index, sitemap and the designer; a signed link there would leak. The edit screen's "E-mail links" group: `pg_cf_variables_help_build()` key `mail`.

## What is not done?
- The designer's form item view widget (`_render_system_widget_form_item_view()`) does not go through `get_form_item_view()`: on a designer confirmation page the token prints as written; in a designer e-mail only the bare token is filled. No `__email_preferences_url` binding yet.
