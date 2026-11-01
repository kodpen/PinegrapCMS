---
name: sms-marketing-kind
description: The marketing SMS kind (PR #3820, #3570): sender identity and type label settings, appended to marketing texts only, the consent rule pg_sms_send() and pg_sms_enqueue() apply
---

## Where are the identity and the label set?
- Settings › Communication › SMS Messages, "Marketing Messages": trade name, MERSİS number (16 digits, spaces/dots/dashes dropped), type label. Stored in `connect_apps` `sms_templates` under `options.marketing` beside `options.events`: no schema. `pg_sms_marketing_settings()` reads, `pg_sms_marketing_settings_decide()` (pure) decides, `pg_sms_message_settings_check()` / `_save()` merge it with the templates.
- An empty label (or the built-in one sent back unchanged) is the built-in `pg_sms_marketing_label_default()` in the site's language ("Tanıtım, kampanya ve bilgilendirme mesajıdır.").

## What does a marketing text look like?
- `pg_sms_marketing_text()`: text, `\n`, trade name + "MERSİS No: …", `\n`, label. Applied in `pg_sms_check()` for the commercial kind only, before the 1000-character limit; never to order, shipping, otp, reminder, stock or test. A queued body is sent as it was queued: the queue run passes `$queued` to `pg_sms_send()` / `pg_sms_check()`, which then appends nothing, so an identity or label changed between queueing and sending no longer gives a second footer (#4674). `pg_sms_marketing_text()` also leaves a text already ending with the same footer alone.
- No trade name and no MERSİS number: `pg_sms_check()` refuses with `pg_sms_marketing_identity_missing()`, before the number is read.

## Who may get one?
- `pg_sms_marketing_permission($number)` (database), in `pg_sms_enqueue()` and again in `pg_sms_send()`, so a refusal given after queueing stops the queued message. Contacts of the number: `contacts.mobile_phone` matched through `pg_sms_number_variants()` / `pg_sms_number_sql()` (also used by the İYS pull), plus contacts whose latest order's billing phone is the number and who answered at a checkout (as `pg_iys_push_number()`).
- `pg_sms_marketing_refusal()` (pure): any contact's latest `sms:marketing` row `refused` → refused; any `iys_sms_status` `refused` → refused; no `granted` row → not consented. İYS's approval alone is no consent (#2430: İYS never widens or narrows beyond the site's answers, only its refusal stops).
- The drivers get `$commercial` true for this kind: Netgsm `iysfilter` 11, İleti Merkezi `iys` 1 with list BIREYSEL; Twilio ignores it.

## What is not done?
- Nothing queues a marketing SMS yet: step 10 (SMS campaign) is the sender, so running sites change nothing. Not sent through a live provider.
- The lookup scans contacts and orders with `REPLACE()` per message; a campaign of many numbers may want the contact id passed in (step 10).

## Tests?
- `tests/sms_marketing_test.php`; `tests/sms_test.php` (the kind refused without identity).

Related: docs/memory/platform/sms-provider-layer.md, docs/memory/core/sms-consent-box.md, docs/memory/platform/iys-sms-channel.md, docs/memory/platform/sms-and-iys-rules.md
