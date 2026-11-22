---
name: mail-box-remove
description: Removing a mailbox (Site Settings › Mailboxes, MT1 #10900): the row, its settings, password, rules and own blocks go; every conversation stays; the check, posted name and confirm
---

## What does Remove delete?
- The `ws_mail_boxes` row, and so everything kept in its `settings` JSON: the sealed password, the incoming mail rules, the routes, the reading position. Also the box's own `ws_mail_blocks` rows (`box_id` = the box; a block for every box, `box_id` 0, stays).
- `ws_mail_box_remove_queries()` lists the SQL; `ws_mail_box_remove_write()` runs it (the blocks only when `ws_mail_blocks_ready()`) and writes a Site Log line "the mailbox … was removed".
- Reading stops at once: the fetch reads only rows that exist.

## What stays?
- Architect (2026-11-20): Remove deletes the box's settings and never a conversation. Built by #10900. `ws_mail_conversations`, `ws_mail_items`, their cards in `ws_messages`, a box's room (`ws_channels` kind mailbox), reads and reminders are never touched; `tests/settings_mailboxes_tidy_test.php` fails if the SQL names one of them.
- A conversation of a removed box keeps its `box_id`; a reply from it finds no box (`ws_mail_box()` is null), so nothing is sent through the removed box.

## Who may remove, and how is it posted?
- `ws_mail_box_remove_check()`: the Workspace settings right, a box that exists, never a WhatsApp or SMS box (kept by its own card).
- A submit button of the pane's settings form named `ws_mail_remove`, value the box's id; `mailboxes.save.php` runs it after the drawer's save, like Test connection.

## What does the confirm say?
- `ws_mail_box_remove_confirm()`: "Remove the mailbox …? Reading stops, the stored password is deleted and its incoming mail rules go. The conversations stay where they are." After it: "The mailbox was removed. Its conversations stay where they are."

## Where is the button?
- In the box row's "more" menu (`ws_mail_box_menu()`), the Design examples' Menus part (#10852): Turn off or Turn on, a divider, then Remove in danger. Its confirm is asked by a captured click listener in `ws_mail_settings_script()` (`data-ws-mail-confirm`), so it works in the settings dialog too. The drawer's foot has Remove mailbox too (`mail-box-drawer.md`).
- Turn off / Turn on posts `ws_mail_switch` = `<id>:off` or `<id>:on` (`ws_mail_box_switch_value()`), checked like Remove, with a Site Log line.

Related: docs/memory/ui/look-settings-mailboxes.md, docs/memory/workspace/mail-blocks.md, docs/memory/workspace/mail-rules.md
