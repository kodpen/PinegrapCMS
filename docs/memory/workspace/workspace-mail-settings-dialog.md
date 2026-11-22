---
name: workspace-mail-settings-dialog
description: Site Settings › E-mail › Mailboxes (gate mail): what opens in drawers, the posted ws_mail names, the shared check and write, the .eml row's form of its own, a port never drawn as 0
---

## Where is the Mailboxes card?
- Since EM1: the Mailboxes tab of the E-mail category (`ui/settings-email-tabs.md`), drawn by `includes/settings/mailboxes.php`, saved by `.save.php` through `email.save.php`; `settings_mailboxes.php` opens the tab. Section `pgset-ws-mail`.
- Its gate `mail` (`pg_settings_mail_settings_allowed()`): the Workspace's tables and its settings right (`ws_rights()`), whatever the switch, so the Workspace's switch never hides it (owner: "ayarları da ayarlara taşınsın"). The tab alone reads no `prep.php` (`pg_settings_category_reads_prep()`).
- `pg_settings_legacy_anchors()` maps `ws-mail` to `email/pgset-ws-mail`.

## What opens in a drawer?
- The tab is folded rows (`ui/look-settings-mailboxes.md`). A box's row: Test connection, a named submit button `ws_mail_test` (value the box's id), run after the pane's save; Edit opens `pgset-ws-mail-<id>`; its menu posts `ws_mail_switch` and `ws_mail_remove` (`mail-box-remove.md`). Add a mailbox opens `pgset-ws-mail-0` on its starting points (`ws_settings_mail_drawer()`, `mail-box-start-setup.md`).
- The drawer's tabs, folds, routes and foot: `mail-box-drawer.md`.
- "Bring in an .eml file" is the pane's last row since MT1 (`ws_mail_eml_pane()`). A file cannot travel with the pane's Ajax save, so its fields name a form of their own printed outside the settings form (`ws_mail_eml_pane_form()`, id `pgset-ws-mail-eml-form`), posting to `settings_mailboxes.php`: `ws_mail_eml` (the box's id) and the file `ws_mail_eml_file`; the browser then shows that page with the result.
- No Connect button since CX11e (#10720): "Accounts for mailboxes" leads to Google's or Microsoft's page (`ui/connections-mailboxes-accounts.md`).
- The category writes connect.php's message (`pg_connect_flash_take()`) on the page, a refusal as an error (since #10431).

## Which names does the dialog post?
- Field names, ids and what a save stores never changed with the look (MT1 to MT3): the save module and the settings search read them, `tests/workspace_mail_drawer_test.php` pins the posted names.
- `ws_mail[<id>][<field>]`, routes `ws_mail[<id>][route_match][]` (`lists` entry `ws_mail` 3). The form and its helpers in mail_route.php, mail_status.php and mail_triage.php keep their old names; `ws_mail_box_dialog_names()` renames them and drops `required` in the dialog. The script finds a field by either name and leaves the connected account unrequired there.
- `ws_mail_settings_dialog_input()` gives the old names back, with `box_id`.

## How do the two screens share the save?
- `ws_mail_box_save_check($viewer, $box, $post)` does every check and writes nothing; a refusal names its field (`ws_mail_box_input()` gives `field` too). `ws_mail_box_save_write()` stores and logs. `ws_mail_settings_post($viewer, $action, $liveform, $input, $file, $refuse)` runs both for the old screen, and Test connection and the .eml for both.
- The save module checks the box before anything of the pane is written and marks `ws_mail[<id>][<field>]`; after the save a refusal is a warning.
- On the category's page a refused box save comes back with its drawer open again, the field marked with its sentence and focused (`pgSettingsRestore()`, `ui/settings-drawer-card-form.md`, #9006).

## Why is a port never drawn as 0?
- A box without a sending server stores `smtp_port` 0 (`ws_mail_box_input()`). The Port fields carry `min="1"`, and the drawer's Save submits the whole page form, so a drawn 0, even hidden, made the browser refuse every later save of that box (#9023). `ws_mail_box_port_shown()` draws the stored port, else the chosen service's (993 IMAP, 587 SMTP without one); the server still ignores the port while the SMTP server is empty.

Tests: `tests/settings_workspace_channels_test.php`, `tests/workspace_mail_drawer_test.php`, `tests/settings_mailboxes_tidy_test.php`.
