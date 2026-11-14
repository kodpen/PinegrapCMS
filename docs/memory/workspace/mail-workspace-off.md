---
name: mail-workspace-off
description: Incoming e-mail with the Workspace off (MA10c): the gate ws_mail_on(), who asks it (tab, actions, jobs, frame, API, bell), a new box kept in the mailbox itself, the test
module: workspace_mail_gate
---

## What is the gate?
- `ws_mail_on()` in `includes/fn/workspace_mail_gate.php`, loaded everywhere: true once `ws_mail_boxes`, `ws_mail_conversations` and `ws_mail_items` are there (schema cache), never `WORKSPACE_ENABLED`. `ws_mail_ready()` returns it. Owner (2026-11-13, #9203): mail works where the Workspace is off; no switch of its own (`workflow/decisions-made-workspace-22.md`).
- `ws_mail_in_use()`: one read, a box switched on. The general job loads the Workspace's files for mail on a Workspace-off site only then.
- Inside the module `ws_enabled()` still tells the two cases apart (a channel opens, an AI suggestion is written); `ws_ready()` is still asked, since mail reads the team and rooms from the Workspace's tables.

## Who asks it?
- The tab: `pg_messages_mail_visible()` (menu row and strip) and `messages_mail.php` through `ws_screen_gate($user, false, true)`.
- The panel actions: `ws_action_switch_allows(…, $mail_on)` lets `ws_mail_actions_without_workspace()` through: every `ws_mail_*` case, `ws_whatsapp_reply_form`/`_reply`, `ws_bootstrap` (the holder picker's team) and `ws_tick` (with the Workspace off it runs the flows' pass only while `pg_flows_enabled()`).
- The jobs: `job.php`'s IMAP pass and orphans pass, `workspace_mail_job.php`, the HTML fill and the .eml sweep of `workspace_recurring_job.php`.
- `workspace_mail_frame.php` (a mail's HTML in the pane).
- The external API and MCP: `api_modules()` module `mail` (`api_modules_mail_alone()`), file `includes/workspace/api_mail_alone.php`, merges the routes whose id starts `workspace.mail_` or `workspace.mail.`, the `WorkspaceMail*` objects and `workspace.mail.received`, under `workspace:read`/`write`, as the flows module does (`automation-flows-api.md`). With flows merged too, the permission row reads "Automation flows and e-mail".
- The bell: `pg_notification_off_line()` lets through `pg_notification_mail_kinds()` (`mail_assigned`, `mail_customer`, `mail_unsent`) while mail is on, beside the flows' kinds (`automation-flows-bell.md`); described and marked read as theirs.

## What changes on the screens with the Workspace off?
- The pane shows no "Open in channel" and no `#channel` for any conversation (`ws_mail_pane_html(…, $channels)`); an inbox line about a mail leads to its conversation in Incoming e-mail (`ws_mail_notice_url(…, $channels)`).
- Mailboxes (Site Settings): a new box, or one already kept in no channel, is kept in "The mailbox itself" with no choice (`ws_mail_box_rooms_only()`): a hidden `kept_in`, the line "With the Workspace off a mailbox keeps its mail itself…", the People and Departments lists; no channel, no Routing, no Pinegrap AI switch. The save forces `kept_in` to mailbox and keeps the routes the box had.
- A box kept in a channel goes on writing its cards and keeps its form; its channel's members read it in the area. The card's list names its channel without a link.
- No AI suggestion is queued for a new conversation (it is a message under the card).

## What waits for the Workspace?
- Device banners (`pg_push_queue_still_relevant()`), as for flows. The WhatsApp connection card's Channel step (`ws_mail_phone_card_viewer()`).

## Which test guards it?
- `tests/workspace_mail_gate_test.php`: every `case 'ws_mail_*'` and every action `workspace_mail.js` sends is in the list; the entries ask the gate; every `/workspace/mail…` route is merged; every `ws_notify()` kind of a `mail*.php` is a mail kind of the bell; the box kept in the mailbox itself. A new mail action, route or notice kind fails it until it is covered.

## What stays unverified?
- No database or browser ran the Workspace-off paths in the cloud session; the tests check the pure parts and the wiring.
