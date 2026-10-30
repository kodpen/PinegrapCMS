---
name: mail-transport-failure
description: PHP mail() failing with no SMTP server set is permanent, stores the product sentence, sends no sender notice, and the mail queue screen warns with a link to SMTP Settings
module: mail, mail_queue
---

## What was the symptom?
- Issue #3582: on a server without a working PHP `mail()` and no SMTP server (IIS, no local mail server) every queued message showed "Örnek e-posta fonksiyonu oluşturulamadı." (PHPMailer's `instantiate`, wrongly translated in `includes/phpmailer/language/phpmailer.lang-tr.php`), was tried six times, and nothing said what to do.

## How is the failure recognised?
- By structure, never by PHPMailer's text. `pg_mail_send_built($mail, &$transport_failed)` (`includes/fn/mail.php`) replaces `$mail->send()` with `preSend()` then `postSend()`. A false or an exception from `postSend()` while `$mail->Mailer === 'mail'` (no SMTP server, no connected account) is the transport failure. A `preSend()` failure and every SMTP or connected-account failure keep the old path (retried).
- The `!function_exists('mail')` branch of `email()` (its own sentence, "…the PHP mail() function is disabled.") is permanent too.

## What is recorded?
- `pg_mail_failure_outcome($error, $permanent, $transport_failed)` (`includes/fn/mail_queue.php`): for the transport failure the error is `pg_mail_transport_failure_sentence()` ("The server cannot send e-mail with PHP mail(). Set up an SMTP server in SMTP Settings."), permanent, and `notify` false: no "Email delivery failed" notice, it would go through the same broken `mail()`. Other permanent failures keep their notice.
- The Site Log line and `pg_mail_last_error()` carry that sentence. PHPMailer's files are not edited.

## What does the queue and the panel do?
- Permanent: given up at attempt 1 (`mail-queue-permanent-errors.md`), one bell row per message, or the summary row for more than 3 in one run, as for any permanent failure.
- Ops `mail_failures` counts rows with attempts > 0 that are queued or failed, once per row: a row given up at once counts exactly as a retrying one did, so the alert fires at the same volume; its text is now the sentence.
- `mail_queue.php` shows one warning above the counters, with a link to `smtp_settings.php`, while the newest tried row (sent, or attempts > 0) is failed with one of the two sentences (`pg_mail_transport_warning_shown()`, English source or current translation). A message sent after SMTP is set up ends it. No schema change.

## How was it verified?
- `tests/mail_transport_test.php` (fake PHPMailer whose `preSend()`/`postSend()` answer as told). Not run on a live server without `mail()`.
