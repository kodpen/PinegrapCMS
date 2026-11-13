---
name: workspace-mail-settings-dialog
description: The Mailboxes card in Site Settings (Channels, mail and voice) and on Workspace Settings: drawers, ws_mail names, the shared check and write, the .eml form of its own, the Connect form
---

## Where is the Mailboxes card?
- PR #8899 (SW6b of the Workspace settings move, [[workspace-settings-overview]]): in Channels, mail and voice, section `pgset-ws-mail`, after Channel templates. It was the category's last link, so the "On the Workspace Settings screen" card and `ws_settings_screen_links_card()` are gone. The old screen's address redirects here (PR #8931).
- `pg_settings_legacy_anchors()` maps `ws-mail` to the card.

## What opens in a drawer?
- Each box's line keeps its channel ("Kept in the mailbox itself", a link to its list, for a box kept in no channel), last e-mail and last check; Test connection is a named submit button `ws_mail_test` (value the box's id), run after the pane's save. Edit opens `pgset-ws-mail-<id>`, Add a mailbox `pgset-ws-mail-0` (`ws_settings_mail_drawer()`).
- A box's drawer holds its form and, after it, "Bring in an .eml file": a form of its own (`ws_mail_eml_form()`), since a file cannot travel with the pane's Ajax save. It posts to the category's page (`settings_workspace_channels.php`) with the button `ws_mail_eml` and the file `ws_mail_eml_file`; the browser then shows that page with the result.
- "Connect a Microsoft account for mailboxes" is a button of the hidden Connect form `pgset_mail_connect` (`ui/settings-modal-card-extras.md`), returning to the category's page.

## Which names does the dialog post?
- `ws_mail[<id>][<field>]`, routes `ws_mail[<id>][route_match][]` (`lists` entry `ws_mail` 3). The form and its helpers in mail_route.php, mail_status.php and mail_triage.php keep their old names; `ws_mail_box_dialog_names()` renames them and drops `required` in the dialog. The script finds a field by either name and leaves the connected account unrequired there.
- `ws_mail_settings_dialog_input()` gives the old names back, with `box_id`.

## How do the two screens share the save?
- `ws_mail_box_save_check($viewer, $box, $post)` does every check and writes nothing; a refusal names its field (`ws_mail_box_input()` gives `field` too). `ws_mail_box_save_write()` stores and logs. `ws_mail_settings_post($viewer, $action, $liveform, $input, $file, $refuse)` runs both for the old screen, and Test connection and the .eml for both.
- The save module checks the box before anything of the pane is written and marks `ws_mail[<id>][<field>]`; after the save a refusal is a warning.

Tests: `tests/settings_workspace_channels_test.php`.
