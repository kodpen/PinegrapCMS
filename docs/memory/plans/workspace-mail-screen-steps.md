---
name: workspace-mail-screen-steps
description: Steps WM1-WM9 of plans/workspace-mail-screen.md: queue outcome hook, schema, send path, the screen with views and pane, New e-mail, Forward as e-mail, the answer on the same path, API, close-out
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each step with its tests and its memory topic; the schema step rerun twice on a dev DB.
- Claim as `[plan:workspace-mail-screen] <step>`. A step is free once the steps it names have landed.

- WM1 (S, core) Done #8607. The queue's outcome hook. `includes/fn/mail_queue.php`: `pg_mail_outcome_handlers()`, the `on_outcome` and reference properties, the call after sent, given up and blocked, `keep_mime` honoured for such a row; type `workspace` in `pg_mail_queue_list_types()`. Topic `core/mail-queue-outcome.md`.
- WM2 (S, schema) Done: #8634, label 11.0.185 (`workspace/mail-tables-sending.md`). Approved as planned (owner 2026-11-11, #8604). The step of `workspace-mail-screen-sending.md`: four `ws_mail_items` columns and `ws_mail_reads`, with the idempotent helpers. The PR body names the `[SCHEME]` item. Topic: `workspace/mail-tables.md`.
- WM3 (M) The send path. After WM1 and WM2. `includes/workspace/mail_send.php`: `ws_mail_send_boxes()` (what a person may send from), `ws_mail_send_check()`, `ws_mail_send()`, `ws_mail_send_outcome()`, Send again, the limits, files by path, the card of a mail that went out. No screen. A test: an answer from any recipient finds the conversation (`ws_mail_find_conversation()`: the subject tag matches only the customer's address, the references anyone's). Topic `workspace/mail-send.md`.
- WM4 (M) The screen. In progress. Views, mailbox filter, search, Load more, the reading pane, unread marks with the menu count and Mark all as read (`mail_list.php`, `workspace_mail.js`). Reply still opens today's drawer. Topics `workspace/mail-list.md`, `workspace/mail-screen-pane.md`.
- WM5 (M) New e-mail. After WM3 and WM4. The form (From, To, Cc, Bcc, Subject, text, files, the channel it is kept in), its panel action, the unsent form kept in the browser, the button on the screen and in a mailbox channel's menu; a mailbox's Signature (plain text in `settings`, no schema) put under the text. Topic `workspace/mail-compose.md`.
- WM6 (M) Forward as e-mail. After WM5. The button on the chosen-messages bar and in the message menu, a mail's Forward on the card and in the pane, the filled form, the line under the source. Topic `workspace/mail-forward.md`.
- WM7 (M) The answer on the same path. After WM5. Reply by e-mail goes through `ws_mail_send()` (queue, state, Not sent), gains Cc, Bcc, files and Reply to all (the last incoming mail's To and Cc without the box's own addresses); `ws_mail_reply_send()` keeps its contract for the API. Topic: `workspace/mail-reply.md`.
- WM8 (S) API. After WM4 and WM5. `POST /workspace/mail/messages` (a new mail; person-only, `'mcp' => false`), the list's `view`, `box_id`, `q` and `unread`, the items' `to`, `cc` and `send_state`, a read route; `check_api_schema.php`. Topic: `workspace/mail-api.md`.
- WM9 (S) Close-out. `workspace/mail-overview.md` (the `mail-*` cluster has more than ten files and no overview), the lasting rules into `workspace/mail-decisions.md`, the PR body names the `[NEW]` changelog line, the three plan files deleted.

## What is left for later?
- Writing in HTML (owner: later) and showing a mail's HTML: both wait for the software's own editor and a safe HTML view.
- Recipient suggestions from contacts and earlier conversations; drafts kept on the server; sending at a chosen time.
- Reading a box's Sent folder, so mail sent from another mail program shows in Sent.
- A draft an assistant offers for a new mail (today only the triage's reply draft, `workspace/mail-triage.md`).

## Which questions are open?
- None: the schema of WM2 is answered (#8604, `workflow/decisions-made-schema-15.md`). A step that meets one asks it as an `architect` issue before it builds.
