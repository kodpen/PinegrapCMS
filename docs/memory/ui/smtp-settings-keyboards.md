---
name: smtp-settings-keyboards
description: Phone keyboards of the SMTP Settings boxes: URL keyboard on hosts and the DKIM domain, no auto-capitalisation on user names and the DKIM selector
---

## Which keyboard does each SMTP Settings box open?
- `smtp_settings.php`: `system_smtp_hostname`, `campaign_smtp_hostname` and `dkim_domain` pass `'inputmode'=>'url','spellcheck'=>'false','autocapitalize'=>'off'`.
- `system_smtp_username`, `campaign_smtp_username` and `dkim_selector` pass `'spellcheck'=>'false','autocapitalize'=>'off'` with no inputmode; the user names keep `autocomplete=new-password` (`liveform-field-names.md`).
- Ports and Max. are `type=number`; the password boxes are unchanged.
- Pinned by `tests/smtp_settings_keyboards_test.php`.

## Why stay type=text?
- A host box takes `host:port` or a bare host name, and the DKIM domain and selector are bare names; `type=url` would demand a scheme. `inputmode` changes only the keyboard.

## What broke before?
- iOS and Gboard capitalised the first letter. The save stores values as typed (control characters stripped, trimmed, never lowercased) and `includes/fn/mail.php` hands the user name to PHPMailer unchanged, so SendGrid's literal `apikey` arrived as `Apikey` and the login failed. Hosts and DKIM names are case-insensitive but were stored and shown capitalised.

## Does spellcheck=false stop iOS autocorrect?
- No; that is the `autocorrect` attribute, which liveform does not pass. `autocapitalize="off"` is what keeps the first letter small.
