---
name: mail-area-shell
description: What incoming e-mail takes from the Workspace today (MA10a, #9077): the E-mails list, the conversation, New e-mail and the card against the Workspace's gate, shell, script bundle, menu and job passes
---

## Which gate stops it?
- `workspace_mail.php`: `ws_screen_gate()` (`ws_ready()`, `ws_enabled()` = `WORKSPACE_ENABLED`, `ws_viewer()['member']`), then `ws_mail_reads_ready()`. The guarded frame `workspace_mail_frame.php` asks `ws_enabled()` too.
- List rows, the pane, Reply, New e-mail, Forward, status and holder are `ws_mail_*` panel actions: `pg_panel_ws()` → `ws_handle_action()`, which refuses while the Workspace is off and for anybody outside the team.
- The job: IMAP reading (`ws_mail_fetch_run()`), the orphan pass (`ws_mail_send_orphans_run()`), `workspace_mail_job.php` and the health line all ask `ws_enabled()`.
- Setup: the Mailboxes card sits in Site Settings › Channels, mail and voice, whose gate `pg_settings_workspace_settings_allowed()` needs the Workspace on and its settings right. The API's mail routes are Workspace routes, merged only while it is on (`includes/workspace/api.php`).
- So with the Workspace off no mail is read in, shown, answered or set up today; what is stored stays.

## What does the screen take from the shell?
- Markup: `pg_page_shell()` classes `workspace workspace_mail` and `.ws-shell` / `.ws-shell-main` / `#ws-root`, drawn by the Workspace's styles.
- Script: `ws_screen_assets($viewer, 'mail')`: the `ws-config` JSON (`ws_screen_config()`: the team, the viewer, the strings), `workspace.min.js` holding `assets/js/workspace_mail.js`, the search palette (`workspace_palette.min.js`), the lightbox, the date fields and voice-to-text (the AI rewrite is the channels screen's only).
- Menu: the `mail` entry of `ws_nav_groups()` (the Workspace's side list) with its unread tile; `output.php` lights menu 23 (Workspace). Shown by `ws_mail_list_visible()`: a box or one of its routes writes where the person reads.

## And the conversation and New e-mail?
- Who reads: the members of the box's channel or room, through `ws_mail_list_channels()` and `ws_can_read_channel()`; there is no mailbox right of its own. Who answers: `ws_mail_can_answer()` (`mail-room.md`).
- Sending is the mail queue's (`ws_mail_send()`), with the box's hourly limit and Signature; attachments are site files in the channel's or room's folder, linked by `PATH` and their stored name.
- Notices are Workspace inbox lines (`ws_notify()`: `mail_assigned`, `mail_customer`, `mail_unsent`); the event is `workspace.mail.received`; the AI suggestion is a channel message under the card.

## And the card?
- A `ws_messages` row in the box's channel or room, drawn by `ws_mail_card_html()` (`mail-card.md`). The conversation's `message_id` is that card and its items hang on messages, so the card is the conversation's anchor and is written even where nobody opens the channel.
- What is proposed: [[mail-area-proposals]].
