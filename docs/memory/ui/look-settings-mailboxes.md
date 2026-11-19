---
name: look-settings-mailboxes
description: Site Settings › Mailboxes in look A (S5d, #10431): purpose line and link to E-mail sending, box rows as list rows, button ranks, the box form's fields and switches
---

## What does the Mailboxes card look like?
- Since S5d (#10431). First line (`pg-card-sub`): what the category is for, incoming mail only, then "For sending: E-mail sending", a link to `pg_settings_url('mail_sending')`. E-mail sending gets the mirror line ("Gelen e-posta için: Posta kutuları") in its own PR.
- Each box is a `pg-list-row` with a `pg-icon-tile pg-tone-team` (`bi-inbox`): the name as title; address, place and last e-mail on one text line; the last check on the next; Test connection and Edit as `pg-button pg-button-quiet pg-button-sm` under them. An off box stays at `opacity-50`.
- Add a mailbox in the card header, Connect a Microsoft account for mailboxes and Bring in (the .eml form): quiet small buttons. The drawer's Save is the one `pg-button pg-button-fill`, no icon.
- Blocked senders and Incoming mail rules were already list rows (`workspace/mail-blocks.md`, `workspace/mail-rules.md`).

## What moved in the box form?
- `form-label` → `pg-field-label`, `form-control form-control-sm` → `pg-input` (selects and the signature textarea too), `form-text` → `pg-field-help`, the switches (On, Support box, Automatic answer, Pinegrap AI suggests) are `input.pg-switch`, the check boxes (bounces, sent copy, Sent folder, the room's people and departments) `input.pg-checkbox`, each beside its label in a flex row. Section titles (Reading the mailbox, Sending answers, Routing, Automatic answer) are `h3.pg-group-label`.
- The helpers of other files drawn only in this form moved with it: `mail_room.php`, `mail_route.php`, `mail_status.php`, `mail_triage.php`. Field names, ids, what a save stores and the texts stayed.
- The Gmail steps between the service and the user name: `workspace/mail-google-mailbox.md`.
- `tools/check_look_baseline.json`: `mail_settings.php` 11 → 0 old buttons.

## What is not done?
- The Workspace Settings branch of `ws_mail_settings_card()` (no `$form`) has no caller left; its buttons moved too, its markup did not.

Related: docs/memory/ui/look-settings-screens.md, docs/memory/workspace/workspace-mail-settings-dialog.md
