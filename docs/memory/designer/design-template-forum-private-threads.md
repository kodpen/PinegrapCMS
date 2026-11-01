---
name: design-template-forum-private-threads
description: Forum template 1.3.0: the conversation, guide-on-the-desk and report pages draw their messages and notes with two-sided Comments widgets instead of the fixed comments block
---

## Which widgets replaced the fixed block?
- `conversation_replies` (conversation), `doc_messages` (doc_submission) and `report_notes` (staff_report), each right under its record view, in the page's `.pf-replies` box. Decision: owner, 2026-11-01 (`workflow/decisions-made-product-16.md`): every thread, public and private, is a Comments widget.
- One `$two_sided` config: order oldest, featured none, layout flat, max_depth 1, per_page 0 (all on one page).
- `$reply_thread()` with `reply` and `watch` off: no Reply, Share, quote, featured mark, Replying to box, follow box or watcher card. The sender is e-mailed every answer and the moderators every message by the page's comment settings; report notes go to nobody. The moderators keep Edit and Stop or Allow new.

## Which words and settings?
- Conversation: Write a reply, Your reply, Send reply; the topic's signed-with-profile message, closed "This conversation is closed."
- Desk: Write a message, Your message, Send message, Stop or Allow new messages; closed "This guide takes no new messages."
- Report: Add a note, Your note, Add note, Stop or Allow new comments; message "Notes are for the moderators only…", closed "This report takes no new notes."
- Labels unchanged (`Message`, `Note`); attachments and select_name off.

## Who sees them?
- The record view's access decides: the conversation and the desk are `submitter_only` views on registration pages; the report page is in the private (moderators') folder. A thread under a record the visitor may not read is hidden and a post is refused ([[comments-widget-contract]]).

## What happens to older sites?
- Nothing: rows are never reused; a 1.2.x site keeps the block on these three pages. Make designable there gives the editor's starter, not this layout.

## How is it checked?
- `test_forum_comments` (every page with comments places one Comments widget right under its record, no block anywhere), `test_forum_two_sided_threads` (options, no public-thread parts, pure draws for a member, a signed-out visitor and a moderator), `test_forum_widget_flags_fit_their_mode`.
