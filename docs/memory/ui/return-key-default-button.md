---
name: return-key-default-button
description: Return in a drawer form or a Site Settings category saves through a hidden first submit button; Enter in the guided tour leaves a focused Skip or Back button alone
---

## Which button does Return press in a form with action buttons?
- The browser presses the form's first submit button, shown or not: a `d-none` button still counts as the default one.
- A form whose action buttons must stay inside it (a drawer with Rotate, Delete and Save) starts with `<button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>`, nameless, so Return posts only the hidden action field (`update`) and saves. Same pattern: `includes/settings/modal.php`, `includes/settings/screen.php`.
- Moving only the dangerous button down is not enough: the next action button (Yes, delete) becomes the default.
- A button that does its own job can also go on a form of its own (`panel-save-shortcut.md`, "And Return in a text field?").

## Which drawers carry it?
- `api_settings.php` `#api_drawer`: before PR #3190 Return in Name, Description or Requests per minute pressed Issue a new secret (`api_action=rotate`, which beats the hidden `update` because PHP keeps the last value). A server key was rotated with the old secret's 24-hour run-out, and the edits were never saved; for other app kinds the edit was simply lost.
- `marketplace_settings.php` `#mp_drawer`: Return pressed Remove marketplace (`mp_action=delete`, behind a `confirm()`).
- E-mail sending (`includes/settings/mail_sending.php`, formerly SMTP Settings): its cards bring no submit button of their own before their action buttons; in the dialog Return saves, and the page path's form starts with the hidden Save of `includes/settings/screen.php`. Before PR #3499 Return on the old screen pressed Send Test (sent with the stored settings) or Generate DKIM Key (the pair was replaced and the TXT record in DNS stopped matching).
- Since PR #3886 each action runs the save first (`pg_mail_settings_save()`, `includes/fn/mail_settings.php`) and stops when config.php cannot be read or written.
- Save and send a test sets `$_SESSION['smtp_settings_send_test']` and the pane read after the save sends (the posting request's SMTP constants still hold the old values); Save and test checks the selector and domain just saved, not `DKIM_SELECTOR`/`DKIM_DOMAIN`; Save and generate a DKIM key saves the selector and domain its TXT record names (`ui/settings-mail-sending.md`).
- Before, Send Test and Test DKIM used the stored settings and the redirect dropped what was typed, and Generate DKIM Key never saved its pair, so the mailer kept signing as `mail._domainkey.example.com`. Pinned by `tests/mail_settings_save_test.php`.
- `tests/return_key_buttons_test.php` pins all three.

## What does Enter do in the guided tour?
- `assets/js/pg_tour.js` `onKey` listens on window in the capture phase: Escape ends, ArrowRight and Enter go to the next step, ArrowLeft back.
- Enter on a focused bubble button other than Next returns early, so Skip and Back run their own click. Before PR #3190 Enter on them went to the next step (Space worked).
