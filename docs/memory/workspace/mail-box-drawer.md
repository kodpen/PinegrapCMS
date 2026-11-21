---
name: mail-box-drawer
description: A mailbox's drawer in Site Settings › Mailboxes since #10934: head with the state pill, tabs General / Reading / Answers / Routing, the Advanced fold, routes as rows, the foot with Remove mailbox
---

## How is a box's drawer laid out?
- Head: name, then address and the state pill (`ws_mail_box_state()`). Tabs General, Reading, Answers, Routing in the band under it: `ws_mail_box_tabs()`, drawn through `pg_settings_drawer()`'s `$line` and `$bar`. A box kept in the mailbox itself has no Routing (`ws_mail_box_tab_labels()`).
- General: name, address, Kept in (segments), channel or readers, On, Support box, signature. Answers: Send through, the SMTP server only for the mailbox's own server, sent copy and folder, Read the Sent folder, first answer time and automatic answer (support box), Pinegrap AI suggests.
- Foot (`ws_mail_box_drawer_foot()`): Remove mailbox (danger, MT1's confirm), Cancel quiet, Save filled. On Workspace Settings the form keeps its own Save under the tabs.

## What does Reading show?
- Mail service with the chosen service's one line, then only its sign-in fields (`ws_mail_box_signin_way()`: none, account or password; the card's script works it out again on each change).
- Server and port fold under "Advanced" with the checking server and Reads bounces, unless the service is Another server or a box has a server and no service (`ws_mail_box_server_own()`); the script moves the one copy between the two places. Test connection is a quiet button at the end (a kept box with a server).
- Gmail keeps "Sign in with" and the button of "Set up the Google application", Microsoft that of "Set up the Microsoft application"; the steps are in those drawers (`mail-box-start-setup.md`). Add a mailbox opens on its starting points first.

## How are routes edited?
- One row a kept route (address or +tag, channel, Remove), no empty rows; "Add a route" copies the `<template>` row, never past `WS_MAIL_ROUTES_MAX`. Removing every row clears the routes on Save.

## What does not change?
- The posted names and ids: every tab's fields post whichever tab is open. `tests/workspace_mail_drawer_test.php` pins them, the tabs, the folds, the foot and one line of help a field.
- A field the save refused (`pgSettingsRestore()` marks it) or the browser found invalid opens its tab first.

## What reads oddly?
- In Turkish the tab "Reading" reads "Okuyorum", the state pill's word: one English key, one translation.

Related: docs/memory/workspace/workspace-mail-settings-dialog.md, docs/memory/plans/mailboxes-tidy.md
