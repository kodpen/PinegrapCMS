---
name: mail-inbox-more-scopes-rules
description: Exact scope of IB11 and IB12 of plans/mail-inbox-more.md after the IB9 census: incoming mail rules and "I am away"; files, functions, texts, schema asked
---

## IB11: Incoming mail rules?
- Kept in `ws_mail_boxes.settings` JSON as `rules`, like `routes` (at most 20 a box): condition sender address, sender domain (with subdomains, as `ws_mail_block_domains()`) or words in the subject; action "Give it to" a person or "Close it". No label: conversations carry none. No schema.
- New module `includes/workspace/mail_rules.php`: `ws_mail_rules()` reads, `ws_mail_rule_input()` checks, `ws_mail_rule_match()` (pure) picks the first that fits.
- Called by `ws_mail_ingest()` for a mail that opens a conversation, after the conversation is written, before the notices and the automatic answer. A person no longer allowed to answer (`ws_mail_can_answer()`) is skipped; "Give it to" tells them (`mail_assigned`, actor 0); a closed conversation gets no automatic answer. Phone boxes have none (`ws_mail_phone_ingest()` untouched).
- Screen: "Incoming mail rules" on the Mailboxes card beside "Blocked senders" (`ws_mail_blocks_html()`), add and Delete through `mailboxes.save.php`, behind the Workspace settings right as the list is; a Site Log line per change. No API route.

## IB12: "I am away"?
- "My e-mail settings" drawer (`mySettings()`, actions `ws_mail_my_settings` / `_save`) gains "I am away": first day, last day, a text (at most 5,000 characters, as the automatic answer), an optional stand-in from the team members.
- `ws_mail_ingest()`, an ongoing conversation whose holder is away today (their time zone): the text once per sender in the range, through the box's route like `ws_mail_auto_reply()` (Auto-Submitted, never to a `fail` sender, never after the loop gate or hold), kept as an "Automatic answer" item (`user_id` 0); once per sender read from those items since the first day.
- Stand-in: `ws_mail_conversation_changes()` (hand, API) and IB11's rule give a conversation meant for an away person to the stand-in, when the stand-in may answer there.
- Calendar: saving writes a `ws_personal_plans` row (all day, `visibility` team, "Away") through `personal_plans.php`; clearing deletes it.

## Which schema does IB12 need? (asked of the architect)
- A, recommended: `ws_profiles.mail_away` TEXT NULL, JSON (first day, last day, text, stand-in, plan id), written as `mail_signature` is. Ingest reads one profile row.
- B: five columns on `ws_profiles` (two DATE, TEXT, stand-in, plan id).
- C: A or B plus a table of senders already answered, for an exact once per sender.
