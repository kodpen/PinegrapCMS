---
name: single-email-address-boxes
description: Panel boxes that hold one e-mail address refuse a broken one before the save writes anything, through pg_email_field_errors()
module: mail
---

## Which boxes are checked?
- Site Settings > Website: Support E-mail Address (`email_address`, the From of system mail).
- Security: Registration and Membership E-mail Addresses.
- Ecommerce: order e-mail (`ecommerce_email_address`, also on ERP documents), reward program BCC, affiliate e-mail.
- Create/Edit Page custom form: submitter From, administrator To and BCC, only while that e-mail is switched on (`pg_cf_legacy_settings_errors()`), so a hidden box never refuses a save. The designer page pane shares the check through `pg_page_type_custom_form_errors()`.
- Edit Page: comments moderator To, only while comments are on.
- The Alerts recipients (a list) have their own check, `pg_ops_alert_recipients_invalid()`.

## How does the check work?
- `pg_email_field_errors($values)` (`includes/fn/mail.php`): field => typed text in, field => "Not an e-mail address: …" out, for a value `validate_email_address()` refuses after trim. Blank and not posted (`null`) pass; a list or an array is refused: `email()` hands the string to PHPMailer's `addAddress()`/`addBCC()` as one address.
- Settings save modules mark each error and `return` on `check_form_errors()` before their `UPDATE` (`settings-invalid-vs-error.md`); the page screens use `pg_page_form_refuse()` (`page-form-refused-save.md`).
- Stored values are not rewritten; a stored broken address is refused at the next save of its card.

## Why?
- The boxes were written unchecked while the same screens refused a bad Alerts recipient: "info@sitecom" was stored, the dialog said saved, and mail from or to it failed.
- Test: `tests/settings_email_addresses_test.php`.
