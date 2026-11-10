---
name: mail-failure-reason
description: Screens that send an e-mail at once show the mail server's reason on failure through pg_mail_failure_sentence(), not a pointer at the log
module: mail_queue
---

## What does a screen say when email() returns false?
- `pg_mail_failure_sentence($fallback, $test = false)` (`includes/fn/mail_queue.php`): "The e-mail could not be sent: {reason}" (or "The test e-mail could not be sent: {reason}") from `pg_mail_last_error()`, else `$fallback`. Raw text: escape with `h()` where printed.
- Call it right after the failed `email()`: every `email()` call resets the reason. The reason is PHPMailer's ErrorInfo, in Turkish when the panel language is tr.
- Users: SMTP Settings test, ERP invoice / quote / purchase order / reconciliation letter / accountant pack e-mails. `erp_mail_settle()` stores the same sentence in `erp_document_mails.error` (cut at 255), shown in the mail history.
- Users (returns): the return screen (`view_return.php`) after Approve, Reject, Create Return Label and Refund, and Add Return with "tell the customer". `pg_return_staff_move()`, `pg_return_create_label()`, `pg_return_staff_record()` and `pg_return_refund()` clear `pg_mail_last_error('')` before their sender (a sender can return false before `email()`, leaving an older reason) and return it as `mail_error`; `pg_return_mail_failure_text($sentence, $reason)` appends "The mail server said: {reason}"; `pg_return_staff_notice()` uses it. `mailed` stays a bool (bulk approval counts it); the storefront and the API never show the reason.
- These return mails are `'queue' => true`: while the general mail job runs, `email()` queues them and returns true, so the reason shows only when the mail was sent at once and failed.

## Why not "the details are in the activity log"?
- The ERP screens open to role-3 users with ERP rights; the Site Log needs manager, so the pointer led to a screen they could not open. Someone testing SMTP settings had to leave the screen to read "Could not authenticate".

## Which field is marked?
- `erp_mail_invoice_send()` and `erp_reconciliation_send()` return `field`: the To box (`mail_to` / `to`) only for an address refusal, `_error` for everything else (mail server, settings, document). Before, a server failure was marked on To, which read as a wrong address.

## Tests
- `tests/mail_failure_reason_test.php`: helper sentence and fallback, source scan of the senders and the field marking, the return text and notice, and a scan that the return functions clear and carry the reason.
