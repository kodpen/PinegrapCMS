---
name: mail-threading
description: Which conversation an incoming mail joins (ws_mail_find_conversation()): threading headers, only for a sender who took part, then the subject tag; why a stranger's reply opens its own (#9012)
---

## How is the conversation found?
- `ws_mail_find_conversation()` (includes/workspace/mail.php): In-Reply-To, then References newest first (at most 30), hashed and looked up in the box's items, ours or the customer's. Outgoing answers are items too (`mail-reply.md`), so our own Message-IDs are found by hash. Never by subject.
- The conversations those items sit in are tried newest mail first (at most 5). One lets the mail in only when its sender took part (`ws_mail_sender_took_part()`, pure): the customer, or an address in From, To, Cc or Bcc of one of its newest 200 mails, such as a colleague the customer copied or one an answer was copied to (Reply to all, `mail-reply.md`).
- Then a `[#T<id>]` subject tag, only when that conversation's `customer_email` is the sender. Else the mail opens a conversation of its own (`mail-ingest.md`).

## Who is no longer let in by the headers alone?
- A sender none of the conversation's mails names: someone the customer forwarded an answer to, or anyone who knows a Message-ID. Their mail opens its own conversation, and its subject still shows the `[#T<id>]` of the one it answered.
- Why: an answer from a conversation goes to its customer (`ws_mail_send_check()`), so a stranger's mail inside it had the team answer the wrong person.

## What did #9012 find?
- On dev, a conversation opened by a DMARC aggregate report held two later mails of an unrelated person; the person's first mail was a conversation of its own. Reply by e-mail on it would have gone to the report service.
- Only the threading headers can put another sender's mail into a conversation: the db helpers end the request on a failed INSERT, so a stale `mysqli_insert_id()` in `ws_mail_ingest()` is not a way in. The two mails' headers could not be read from the panel, so which Message-ID they named stays unknown. Mail already kept was not moved.

Tests: `tests/workspace_mail_ingest_test.php` (`ws_mail_sender_took_part()`, the lookup's order).
