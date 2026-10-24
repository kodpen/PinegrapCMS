---
name: form-mail-link-variables
description: ^^email_preferences_url^^ and ^^confirm_subscription_url^^ in form e-mails: who gets a link, where and in which order they are filled in, why they are not standard fields (PR #1385)
---

## What do the two variables print?
- `^^email_preferences_url^^`: the signed `email_preferences.php` address for the address the e-mail goes to (`pg_email_preferences_url()` over the pure `pg_email_preferences_url_build()`, `includes/fn/mail.php`). '' for an empty address or one without "@" (no stricter check, so every campaign recipient keeps its `List-Unsubscribe` header) and without `ENCRYPTION_KEY`.
- `^^confirm_subscription_url^^`: `pg_cf_confirm_subscription_url()`, the link `pg_fs_hold_submission()` keeps in `pg_fs_pending_url()` when a double opt-in form held the group back (`core/form-double-opt-in.md`), only for the contact's own address, normalised (`pg_cf_confirm_subscription_url_for()`); '' otherwise, a resend included. It has its own HMAC prefix and a 7-day expiry, unlike the preferences signature.
- Write them bare or alone in a `[[...||...]]` block: `[[Manage your e-mails: ^^email_preferences_url^^]]`. Not inside `{if ...}` and not in the same block as `^^reference_code^^`: the form item view fills them before those are read, so `{if ^^email_preferences_url^^ == ''}` loses its field.

## Who gets a link?
- Only the e-mail to the person who sent the form: the submitter copy in `custom_form.php`, the resend in `pg_send_custom_form_notifications()` and the billing address copy of a product submit form (`submit_order.php`, never a confirmation link: no contact).
- A watcher's copy, the administrator's e-mail (also drawn under `pg_tr_suspend()`) and the confirmation page get ''. A signed preferences link lets its holder change that address's subscriptions.
- A message with a link is sent `'secret' => true` (`pg_cf_mail_links_in()`): its body stays out of the failure log and the delivery-failed notice.

## In which order is an e-mail filled, and why?
- `[[...]]` blocks must be evaluated only on text with no submitted value in it. Otherwise a visitor writes `[[^^email_preferences_url^^||` in one field and `]]` in another, and everything between them (other fields) disappears from the administrator's e-mail.
- Subject and plain text body: `pg_cf_fill_mail_links()` on the template first, then `get_variable_submitted_form_data_for_content()`. `$submitter_email_subject` stays the template for the next recipient.
- HTML: `get_form_item_view.php` fills the layout (blocks included) before any value goes in. After `get_page_content()` the body only gets `pg_cf_fill_mail_link_tokens()`: bare tokens, escaped, the Link dialog's `http://` taken off, no block evaluated.
- Tests `tests/form_mail_links_test.php` check this order in all three senders and that `pg_cf_fill_mail_links($body` never appears.

## How is it wired?
- `pg_cf_mail_links_context(array(email_address, contact_id, custom_form_page_id, form_id))` is set just before the sender's copy is drawn and cleared (`false`) after `email()`. `pg_cf_mail_link_values()` reads it through the pure `pg_cf_mail_link_values_build($context, $suspended, $preferences_url, $confirm_url)`.
- `pg_mail_merge_address_hrefs()` (`includes/fn/mail.php`) is shared with the review link PR #1316; when both are open, merge one and rebase the other so only one definition stays.

## Why are they not standard fields?
- `get_standard_fields_for_view()` is also read by list views, the search index, sitemap and the designer; a signed link there would leak. The edit screen lists them in their own "E-mail links" group (`pg_cf_variables_help_build()` key `mail`).

## What is not done?
- The designer's form item view widget (`_render_system_widget_form_item_view()`) does not go through `get_form_item_view()`: on a designer confirmation page the token prints as written; in a designer e-mail only the bare token is filled. No `__email_preferences_url` binding yet.
- A link whose `^^` the editor stored percent-encoded (`%5E%5E`) is not filled.
