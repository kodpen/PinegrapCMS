---
name: workspace-mail-screen-sending
description: How the Workspace mail plan sends: one send path through the mail queue with an outcome hook, the item's send state, the schema step, limits, files, what a forward carries
---

## How does a mail leave?
- Architect (2026-11-10, under the owner's delegation, for this file). One function, `ws_mail_send()` in `includes/workspace/mail_send.php`, for a new mail, a forward and (WM7) the answer. It checks (`ws_mail_send_check()`), writes the conversation, its card and the out item with state `queued`, then hands the mail over: `pg_mail_enqueue()` while `pg_mail_job_alive()`, else `email()` at once.
- The mail is built as the answer's is (`workspace/mail-reply.md`): From the box, the box's route (`ws_mail_reply_route()`: its own SMTP, the site's server, the connected account), a Message-ID of the conversation, the subject tag and the loop header, so an answer joins the conversation. Type `workspace` (a label on the Mail queue screen), `secret` (the text stays out of failure notices), `notify_sender` off: the writer hears in the Workspace.
- Files go by `path`, read at send time, so the queued row stays small.

## How does the Workspace learn the outcome?
- The queue tells its callers nothing today: an outcome stays in `mail_outbox`. WM1 adds an outcome hook: a row may name a handler (`on_outcome`) and a reference; `pg_mail_outcome_handlers()` maps the name to a function and its file, and a row never carries a function name. After sent, given up or blocked the queue calls it with the row id, the outcome, the reference, the error and, when asked (`keep_mime`), the sent text.
- `ws_mail_send_outcome()` sets the item: `sent` (time, the Sent-folder copy through `ws_mail_reply_copy()`, `mail-sent-and-bounces.md`) or `failed` with the reason. A failed mail shows Not sent on the item, tells the writer (inbox kind `mail_unsent`) and offers Send again. While the queue retries, the item reads Waiting to be sent.
- A bounce later is today's "Not delivered" line.

## What does the schema step add?
- One step of the open version, decided here so the step asks nobody: `ws_mail_items.bcc_list` (text), `send_state` (empty for mail that came in and for earlier answers, else `queued`, `sent`, `failed`), `send_error`, `outbox_id` (the queue row while it waits); table `ws_mail_reads` (`conversation_id`, `user_id`, `item_id`; primary key the first two).
- A `ws_mail_reads` row with conversation 0 is the person's floor: everything up to that item is read. It is written at the newest item when the person's count is first asked for, and by Mark all as read, so nobody starts with a backlog.
- Running sites: additions only, nothing rewritten; an earlier out item reads as sent.
- Why a table: the only read mark is one position per channel (`ws_channel_members.last_read_id`); opening one mail would mark the whole channel read.

## Which limits hold?
- To, Cc and Bcc together at most 20 addresses, each checked. One blocked by the bounce list (`pg_mail_address_blocked()`, `core/mail-bounces.md`) is refused by name before anything is queued, since the queue gates only To.
- At most 30 mails an hour a person (`WS_MAIL_SEND_HOURLY`); a newsletter is the campaigns' work.
- Text 1 to 50,000 characters (`WS_MAIL_REPLY_MAX`), a subject required. Files: the Workspace's upload rules (`ws_upload_refused()`, `ws_upload_inspect()`), at most 10 and 20 MB together, kept as the out item's files.

## What does a forward carry?
- Channel messages (`ws_forward_pick()`: one channel the person reads, up to 20, not deleted): under the person's own words each message as name, time and plain text (`ws_plain_text()`), its file attached; a file over the limits is named in the form and left out.
- A mail: the original's From, date, subject, To and text quoted, its files attached. It opens a conversation of its own, because a conversation answers one customer; a line under the source says who forwarded it to whom and links the new card.
- A new conversation's customer is the first To; its status Waiting, its holder the writer.
