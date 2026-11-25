---
name: bug-hunt-areas-mail-api-reading
description: Bug hunt of Workspace e-mail read and drafted over the API and MCP (MT1-MT6, #11367): the three faults fixed, what was read clean, what was left as it is
---

## What was the area?
- Given by the architect: the reading and drafts side of Workspace e-mail over the API and MCP. Read: `includes/workspace/api_mail.php` (list, one conversation, one mail, read mark), `api_mail_drafts.php`, `mail_drafts.php`, with `mail_list.php`, `mail_reads.php`, `mail_pane.php`, `mail_drafts_screen.php`, `mail_later.php` and `mail_compose.php` where they are shared. The sending side was read before (`bug-hunt-mail-sending.md`).

## What did it fix? (#11367 lists each with file and line)
- A draft changed over the API with new `text` and no `html` kept its old HTML, and Send (the API's and the Drafts view's) mailed the old words: `ws_api_mail_draft_update_fields()` drops it, as Save draft does.
- A mail held at its time (Send later) became a draft keeping its files on deleted card lines; deleting or sending the draft left those files on disk for good, reached by nothing: `ws_mail_draft_delete()` removes the ones no other line or draft holds.
- `workspace.mail_drafts.create` for a reply kept a `to` or `subject` sent with it, though the route says they are not kept and the mail goes to the customer: `ws_mail_draft_reply_fields()`.

## What was read clean?
- The list's filters and cursor against the screen's (`ws_mail_list_where()`, order and cursor on the newest mail, unread with the reminder's wake); a channel the caller may not read (list, conversation, mail, draft, file: 404 or empty); a mail's HTML and files (`include_html`, the file lines not deleted).
- The read mark (a key refused before anything, the newest mail without drafts); another person's reply draft (read by channel, changed by who may answer, files checked again for the sender at Send); every `dry_run` stop after its checks, nothing written above it.

## What was left as it is?
- The API reads a plain box's status and holder columns as data: decided (`workspace/mail-support-box.md`).
- A delivery failure is found by `in_reply_to` without the pane's `user_id > 0`; only a mail whose ingest stopped half-way could differ, so not changed.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workspace/mail-api.md, docs/memory/workspace/mail-drafts.md
