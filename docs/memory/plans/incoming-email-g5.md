---
name: incoming-email-g5
description: G5 of incoming e-mail: G5a (status, holder, auto-reply) is #2923; G5b (E-postalar tab, assigning others, first-response target, events, assignee notice) not started
---

## Which steps, and where do they stand?
- G5a: PR #2923, `workspace/mail-status.md`: status open/waiting/closed, "Take it" / "Let it go", the box's automatic answer.
- G5b (next, not started): the rest below. Cut it into PRs of about 1,000 product lines if it grows.

## What is left for G5b?
- The "E-postalar" tab: conversations of the boxes whose channels a reader may read (`ws_can_read_channel()`), filtered by status and holder, newest mail first, linking to the card.
- Assigning someone else: a member of the conversation's channel, in the card's controls (`workspace_mail.js`) and `ws_mail_conversation_changes()` (today only `me` / `none`).
- First-response target: a per-box setting in `settings` JSON (hours); overdue = `first_reply_at = 0` and `first_in_at` + target passed; shown on the card and in the tab.
- Workspace event "E-mail received" and the assignee's notice (design file step 6). A new `ws_event()` emitter or status needs its `workspace_flows_parity_table()` line, and a `workspace.*` webhook event needs `channel_id` (workspace README key rules).

## Where to start?
- `includes/workspace/mail_status.php`. No schema change is needed: G1 created `assignee_id`, `first_in_at`, `first_reply_at`, `status`.
- When G5 is done, move this file's lasting decisions to `workspace/mail-status.md` and delete it; G7 and G4 follow in `incoming-email-plan.md`.
