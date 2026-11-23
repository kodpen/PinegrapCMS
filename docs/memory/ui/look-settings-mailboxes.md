---
name: look-settings-mailboxes
description: Site Settings › E-mail › Mailboxes in look A: the folded rows, a box row with its state pill and menu, Accounts for mailboxes, button ranks, the drawer's parts
---

## How is the Mailboxes tab laid out?
- Owner (2026-11-20): the tab was "çorba gibi" and had no way to remove a box; tidied by MT1 (#10900), MT2 (#10934), MT3 (#10950). It follows `ui/screen-composition-rules.md`, parts copied from the Design examples screen.
- Folded rows (`pg_settings_fold()`, `ws_mail_settings_rows()`, `ui/settings-fold-rows.md`), in this order: Mailboxes (open unless another row needs a look), Accounts for mailboxes, Incoming mail rules, Blocked senders, Bring in an .eml file.
- No purpose line of its own since EM1 (#10983): the E-mail category's tabs set Mailboxes beside E-mail sending (`ui/settings-email-tabs.md`, the whole category: `ui/settings-mail.md`).

## What does a box's row show?
- A list row (`ws_mail_settings_box_row()`): `pg-icon-tile` with `bi-inbox`, the name, the address, where it is kept and the last check (with the failed check's first sentence), the state pill, then Test connection (a box read from a server and not off) and Edit as `pg-button pg-button-quiet pg-button-sm`, last the "More actions" menu.
- The pill (`ws_mail_box_state()`): Reading (teal), Off (gray), Sign-in needed (amber: refused or missing password or account), Error (rose). A box with no server has no pill; its line says "Not read from a server".
- The Mailboxes row's own line and pill (`ws_mail_settings_boxes_face()`): how many boxes; Error, then Sign-in needed, opens the row; else Reading, else Off when all are off.
- The menu (`ws_mail_box_menu()`): Turn off or Turn on, a divider, Remove in danger (`workspace/mail-box-remove.md`).
- "Add a mailbox": quiet small button in the Mailboxes row's header; since EM5 (#11175) it opens the three-step sheet Choose, Sign in, Details (`workspace/mail-add-sheet.md`). An empty list shows "No mailbox yet." in the Design examples' empty state.

## What does Accounts for mailboxes show?
- Google and Microsoft as connect rows (`ws_mail_account_row()`, the Design examples' connect row): the state in Connections' words (Connected, Connected for another purpose, Not connected) and Manage or Connect, a link to the provider's page of Connections (`ui/connections-mailboxes-accounts.md`). Below manager: no link, the line says who connects it.
- A new Gmail or Microsoft box needs no trip there: Add a mailbox signs in on its Sign in step; a sending account is offered to a box with "Allow reading mail" (`api/mail-one-account.md`).

## How is a box's drawer drawn?
- Parts and fields: `workspace/mail-box-drawer.md`. Tabs are `pg-seg` segments in the band under the head; the foot: Remove mailbox `pg-button-danger`, Cancel quiet, Save the one `pg-button-fill`, no icon.
- Fields: `pg-field-label`, `pg-input`, one `pg-field-help` line a field, `input.pg-switch` (On, Support box, Automatic answer, Pinegrap AI suggests), `input.pg-checkbox` each beside its label. Server, port, checking server and bounces fold under "Advanced" for a known service.
- The helpers drawn only in this form share the look: `mail_room.php`, `mail_route.php`, `mail_status.php`, `mail_triage.php`. `tools/check_look_baseline.json` holds no old button of `mail_settings.php`.

## What is not done?
- Board C of the drawing (`ui/email-settings-drawing/`): the empty state's tile, sentence, "Add a mailbox" fill and meta line are not drawn, and the other rows show before a box exists. None of EM1 to EM6 built it.
- The Workspace Settings branch of `ws_mail_settings_card()` (no `$form`) has no caller left; its buttons share the look, its markup does not.

Related: docs/memory/ui/look-settings-screens.md, docs/memory/workspace/workspace-mail-settings-dialog.md, docs/memory/workspace/mail-box-drawer.md
