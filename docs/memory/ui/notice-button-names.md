---
name: notice-button-names
description: A notice, hint or dialog names the control it means by its label, never "OK", "the button", a colour or a direction
---

## How does a text point at a control?
- By the control's own label, as the screen draws it: "click Refresh SEO Scores again", "press \"Run the steps again\"", "Use Add condition". Not "click OK" (pgConfirm's buttons read Delete/Cancel or what `confirmText` says), not "the button below", not "if it is red", not "on the right" or "on the left": columns stack below 768px and a colour or a side says nothing to a screen reader.
- A status is named by its word: "the Files check on System Status shows Problem", not "is red".
- When the label changes with the screen's scope, compute it once and pass it as `{var:1}` (wrapped in `h()`): `translations.php` sets `$update_button_label` (Update this page / Update these texts / Update translations) and its empty-list text, manual-engine note and Clear translations dialog name it.
- Only a native `confirm()` really has an OK button (`view_contacts.php`, `view_submitted_forms.php`); those texts may say OK.

## Which screens were fixed, and how is it pinned?
- Site Log, Pages, Storage Engine, Repair Software, the offer editor, the e-invoice inbox and Translations; PR #3263; `tests/notice_button_names_test.php` scans their sources for the old wording and the named buttons.
- Then Sessions ("Stop on its row", "from Notifications in their own panel"), API Documentation ("Choose an application under Try as first"; the label got `for="doc_app"` so the select has a name) and the offer editor's refused save ("Some entries cannot be saved; the reason is written under each one.": it also comes for offer fields, not only tinted rows), in the same test. The frame tour and the dashboard tip name Back, Menu view (Ctrl+D), Show menu / Hide menu, Interface tour in My Menu, Sections (the settings list under 576px) and Light under Appearance, not "the arrow at the top", "this button", "your own menu" or "the user menu"; `tests/tour_control_names_test.php`.
