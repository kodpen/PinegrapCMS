---
name: custom-form-mails
description: The e-mails of a submitted custom form in one function (pg_cf_form_mails_send()), held back while a fee is owed and sent when the record is marked paid (FL5b)
module: form_mails
---

## Where are a submitted form's e-mails sent?
- Module `includes/fn/form_mails.php`. `pg_cf_form_mails_send($page_id, $form_id, $settings, $mail)` is the old mail block of `custom_form.php`: the sender's copy (one e-mail per recipient, signed links and the new account's set password link in the sender's own copy only) and the administrator's e-mail (site language, `pg_tr_suspend()`).
- `$settings` holds the `pg_cf_form_mails_columns()` values of `custom_form_pages` (`pg_cf_form_mails_settings($page_id)` reads them); `$mail` holds `submitter_email_address`, `watcher_email_address`, `administrator_email_addresses` (from the chosen options), `contact_id`, `key_code`, `fee_owed`.
- `pg_cf_form_mails_plan()` (pure) decides who gets them: nothing while `fee_owed`; the sender and a watcher when the sender copy is on; the administrator e-mail to the option addresses plus the To address (once), sent when it has a To or a Bcc.

## When does a record that owes a fee get them?
- At submit `custom_form.php` passes `fee_owed` = `$form_fee !== null`, so nothing goes. `pg_form_payment_mark_paid()` sends them for the record it marks (`pg_cf_form_mails_send_for_record()`), once, as `paid_at` is written once. It runs from `pg_order_complete()` (card or redirect payment, the webhook included) and from `pg_order_mark_paid()` (staff record an offline payment).
- The recipients are read back from the record: the sender's address by the submit's own order (`pg_cf_record_submitter_address()`: the field connected to the contact's e-mail, the record's user's e-mail, the only e-mail address field), the option administrators (`pg_cf_option_admin_addresses()`), the offer's key code (`key_codes.code` = the reference code).
- Not kept: a watcher named through the add watcher field gets no copy when paid later; the new account's set password link is given only when the paying request still holds the session that opened the account (the card paid at submit, the 3-D Secure return). A webhook or staff path draws the sender's HTML copy without the visitor's language.

## Tested?
- `tests/form_mails_test.php`: the plan (no fee, fee owed, Bcc alone, nobody), the option addresses, the sender's address, the wiring. `form_mail_links_test.php` and `auto_registration_set_password_test.php` read the module for the sender copy's order and secrecy. No mail sent in a browser run.

Related: docs/memory/designer/custom-form-payment.md, docs/memory/ui/form-mail-link-variables.md
