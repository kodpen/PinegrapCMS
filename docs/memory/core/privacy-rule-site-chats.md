---
name: privacy-rule-site-chats
description: The Privacy centre's "Site chats" rule (off, 60 days) that replaced Chat's unread Chat Retention Period; what it picks and deletes, why the update deletes nothing
module: privacy_retention
---

## What does the site chats rule do?
- Owner (2026-11-21, poll 215, `workflow/decisions-made-product-96.md`): the old Communication › Chat field "Chat Retention Period" (`config.chat_retention_days`, read nowhere) left the screen, the save and `init.php` (#VP3PR); the column stays, unread. The update deletes no chat: the rule starts off and needs its dry run and yes.
- `chats` picks `chat_conversations` with `channel = 'site'` and `GREATEST(last_message_at, created_at)` before the period (panel team chats never); `pg_privacy_rule_one()` deletes the conversation, its messages and their attachment files (entries `files`, `chat_messages`, `chat_conversations`); no person skip. Inventory retention `site_chats` ("Rule: site chats").

Related: docs/memory/core/privacy-retention-rules.md, docs/memory/core/privacy-rules-card.md
