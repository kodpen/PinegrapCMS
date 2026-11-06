---
name: chat-push-recipient
description: Who a chat message wakes on their devices; a member who started a site conversation is woken when staff answer
---

## Who does a chat message wake?
- `pg_chat_push_recipient()` in `chat.php`, called by `pg_chat_push_peer()`: a panel conversation wakes the other side. A site conversation wakes the operator (`target_user_id`, else `CHAT_OPERATOR_USER_ID`) when its starter writes, and the starter when anyone on the staff side writes (the operator or a role-0 administrator). An anonymous visitor (`initiator_user_id = 0`) has no account and wakes nobody; nobody is woken by their own message.

## What did it fix?
- Before, a site conversation only ever woke the operator, so a signed-in member who started one (member mode, `pg_chat_site_member_enabled()`) never got a banner for the answer, and an administrator's answer queued a push to the operator that the queue then dropped as read.

## Tests?
- `tests/chat_push_recipient_test.php`.
