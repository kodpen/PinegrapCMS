---
name: mail-box-start-setup
description: Site Settings › Mailboxes since MT3: Add a mailbox's four starting points, and the Google and Microsoft set-up drawers opened from a box's Reading tab
---

## How does Add a mailbox start?
- The drawer `pgset-ws-mail-0` opens on four starting points, the Design examples' starting-point cards (`.pg-start`, `form-field-card` radios): Gmail or Google Workspace, Microsoft 365 or Outlook, Another mail server, No server (.eml files). `ws_mail_start_points()` lists them with the mail service each sets (`gmail`, `microsoft`, `other`, ''); `ws_mail_start_html()` draws them.
- While no card is chosen the band under the head says "How is the mailbox read?" and the tabs and the form wait hidden (`data-ws-mail-start-*`). A card chosen with the pointer, the space bar or Enter (`leaveStart()` in `ws_mail_settings_script()`) sets Mail service, shows the tabs and the form and opens Reading on the service's fields, the service focused. The arrow keys only move the choice.
- The radios are named `ws_mail_start`; the save never reads it. A refused save of a new box shows the form at once (the marked field's `showField()`).

## Where are the steps done at Google or Microsoft?
- In their own drawers, printed once by the category (`ws_settings_mail_setup_drawer()`, ids `pgset-ws-mail-google-setup` / `pgset-ws-mail-microsoft-setup`), opened by one quiet button in a box's Reading tab (`ws_mail_setup_button()`): under "Sign in with" for Gmail, alone for Microsoft.
- Google, five numbered steps (`ws_mail_setup_steps()`, `.pg-stepper-cards`): the OAuth client in Google Cloud Console, both redirect addresses with Copy, the Gmail API, the mailbox's account on Google's page in Connections (its state, `ws_mail_connection_html()` in a new tab), what to choose in the box. Then Google's verification warning and, folded, "Use an app password instead" (2-Step Verification, App passwords, the box's fields) with its notes.
- Microsoft, four steps: App registrations in the Microsoft Entra admin center, both redirect addresses as Web redirect URIs, the mailbox's account on Microsoft's page, what to choose in the box.
- The box's form keeps only "Sign in with" (`google_way`, not stored, `ws_mail_google_signin()`); no paragraph or list of steps.

## How does a drawer open over a box's drawer?
- In the settings dialog the button is `data-pgdrawer="open"` (`ws_settings_mail_drawer()` renames it) and the dialog's drawers stack: `ui/settings-modal-drawer.md`.
- On `settings_mailboxes.php` Bootstrap closes the box's drawer when the set-up drawer opens; the card's script opens the box's drawer again, as it was, once the set-up drawer is hidden, its button focused.

## What was seen?
- Sandbox at 1440 and 390 px, page and dialog (#10950): cards two a row from 768 px, one a row on a phone; Gmail and Microsoft open Reading with their button; set-up drawers without horizontal scroll; closing one brings the box's drawer back.
- In Turkish the steps say "Okuma sekmesinde" while the tab reads "Okuyorum" (`mail-box-drawer.md`).

Tests: `tests/workspace_mail_google_steps_test.php`, `tests/workspace_mail_drawer_test.php`.

Related: docs/memory/workspace/mail-box-drawer.md, docs/memory/workspace/mail-google.md, docs/memory/ui/look-settings-mailboxes.md
