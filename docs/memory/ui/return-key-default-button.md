---
name: return-key-default-button
description: Return in a drawer form or SMTP Settings saves through a hidden first submit button; Enter in the guided tour leaves a focused Skip or Back button alone
---

## Which button does Return press in a form with action buttons?
- The browser presses the form's first submit button, shown or not: a `d-none` button still counts as the default one.
- A form whose action buttons must stay inside it (a drawer with Rotate, Delete and Save) starts with `<button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>`, nameless, so Return posts only the hidden action field (`update`) and saves. Same pattern: `includes/settings/modal.php`, `includes/settings/screen.php`.
- Moving only the dangerous button down is not enough: the next action button (Yes, delete) becomes the default.
- A button that does its own job can also go on a form of its own (`panel-save-shortcut.md`, "And Return in a text field?").

## Which drawers carry it?
- `api_settings.php` `#api_drawer`: before PR #3190 Return in Name, Description or Requests per minute pressed Issue a new secret (`api_action=rotate`, which beats the hidden `update` because PHP keeps the last value). A server key was rotated with the old secret's 24-hour run-out, and the edits were never saved; for other app kinds the edit was simply lost.
- `marketplace_settings.php` `#mp_drawer`: Return pressed Remove marketplace (`mp_action=delete`, behind a `confirm()`).
- `smtp_settings.php` (whole form, not a drawer): the hidden first button carries `name="submit_save" value="Save"` (no id, the visible Save keeps it), because the save branch runs only on `submit_save === 'Save'`. Before PR #3499 Return in any box pressed Send Test when SMTP was ready (sent with the stored settings, dropped what was typed) or, when not ready, Generate DKIM Key: the prefilled selector and domain passed its check, the key pair was replaced and the TXT record already in DNS stopped matching. Generate DKIM Key stays in the form, since it posts the selector and domain boxes. Ctrl+S now presses the hidden button (first `submit_save`), same post.
- `tests/return_key_buttons_test.php` pins all three.

## What does Enter do in the guided tour?
- `assets/js/pg_tour.js` `onKey` listens on window in the capture phase: Escape ends, ArrowRight and Enter go to the next step, ArrowLeft back.
- Enter on a focused bubble button other than Next returns early, so Skip and Back run their own click. Before PR #3190 Enter on them went to the next step (Space worked).
