---
name: copy-buttons
description: Hand-written panel Copy buttons: label kept, icon swapped, polite status beside the button, clipboard API only in a secure context, execCommand decides; which screens follow it
---

## What does a hand-written Copy button do?
- The button keeps its text (or its `aria-label` and tooltip on the Workspace Claude card); only the icon changes for about 1.5 s. Replacing the label or `innerHTML` leaves the focused button nameless.
- A `<span class="visually-hidden" role="status" aria-live="polite" data-copied="Copied" data-refused="The browser did not let the text be copied. Select it and copy it from here.">` sits in the same wrapper as the button. A modal `<dialog>` or `.pg-open-sheet` makes the rest of the page inert, so a region outside it is never heard.
- `navigator.clipboard.writeText()` only when `navigator.clipboard && window.isSecureContext` (plain http has no clipboard API); `.then(done, fallback)`, never a swallowed `.catch(function () {})`.
- `fallback` selects the text (`field.focus(); field.select()`, or `getSelection().selectAllChildren()` for a `<code>` or a `<div>`), runs `copied = document.execCommand("copy")` and calls `done` only when that is true; otherwise the status says the refusal text and the text stays selected.
- Both strings already exist in the Turkish shards; no new key.

## Which screens follow it?
- ERP accountant link (`erp_accountant.php`), Workspace Settings › Claude in channels and its one-time secret sheet (`$copy` in `includes/workspace/claude.php`, also used by the setup guide), the Chrome browser pages on Site Settings › Languages and Translations (`pg_tr_browser_pages_html()`, one delegated listener bound once per page because the list also arrives over Ajax), Site Log error rows (`view_log.php` handler, status printed by `pg_site_log_list_description()` in `includes/fn/site_log_list.php`), Connections' Redirect address, the Workspace calendar feed address.
- API applications (`api_settings.php`): every button comes from `api_settings_copy_button($id, $caption_id, $class)`, which prints the button and its status inside the `.api-secret-box` (the drawer is a modal `<dialog>`, the one-time sheet inerts the page). The button is named `aria-labelledby="<id>_copy <caption id>"` ("Copy Key", "Copy Secret"); captions are the `<label>` or the client `<summary>` with an `_label` id. The source is a `<code>`, so the fallback uses `selectAllChildren()`.
- Two-step setup (`mfa.php`): status `#mfa_copy_status` right after the input group (inside it, it would take the button's rounded end).
- Already correct before: File Manager, Workspace editor, form item view, designer home.

## What fixed what?
- Before: over http Site Log returned silently; the Claude card and calendar feed said Copied after a failed `execCommand`; the Chrome pages flipped the icon before the write settled and never back; Connections said nothing; the calendar feed replaced its label for good; the accountant link ignored a refusal; API applications threw over http, replaced the focused button with a bare icon and gave keyboard users no way to copy the one-time secret; two-step setup said nothing.

Test: `tests/copy_buttons_test.php` (source pins of each handler and status region), `tests/erp_marks_names_test.php` (accountant status span).
