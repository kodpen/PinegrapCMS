---
name: mail-routing
description: Routing incoming Workspace e-mail (G6, mail_route.php): box routes by address or +tag to a channel, recipients read, the known customer's line in their channels
---

## Where does a new conversation go?
- `ws_mail_ingest()`: an answer stays in its conversation's channel; a new conversation takes `ws_mail_route_channel($box, $parsed)`: the first of the box's routes whose address the mail was written to, while that channel exists and is not archived, else the box's channel (missing or archived box channel is still `refused` / `no_channel`).
- Routes live in `ws_mail_boxes.settings` as `routes` (list of `match`, `channel_id`; at most `WS_MAIL_ROUTES_MAX` = 10), no column. `ws_mail_routes()` reads and cleans them.
- `match` (`ws_mail_route_match()`): a whole address (an alias the provider delivers into the same box), or `+tag` (`[a-z0-9][a-z0-9._-]{0,63}`), which stands for the box's own address with that tag: `+billing` on `support@shop.test` is `support+billing@shop.test` only, never another address's tag.

## Which addresses count as recipients?
- `ws_mail_recipients()`: To, Cc and the receiving server's `Delivered-To`, `X-Original-To`, `Envelope-To` (a tag or alias often appears only there for a Bcc or a forward), lower case, each once. Bcc is never in a received mail.

## How are routes set?
- Box form "Routing" (`ws_mail_route_fields()`, before the server fields): the kept routes and three empty rows (`route_match[]`, `route_channel[]`). `ws_mail_routes_input()`: an empty row is skipped; refused are a bad address or tag, no channel, a channel the editor may not choose (`ws_mail_box_channels()`) unless a route already had it, the same match twice, more than 10.

## What does a known customer's channel get?
- Only for a new conversation whose sender is tied to a record, so only a DMARC `pass` from the box's server (`mail-ingest.md`): `ws_mail_customer_lines()` writes a system line (`ws_message_system()`) into `ws_customer_channels('contact', contact_id)`, else `('user_account', user_id)`: open or private channels only (never a guest room or a discussion), not the conversation's own channel, at most `WS_MAIL_CUSTOMER_LINES_MAX` = 3, most recently used first.
- Text `ws_mail_customer_line_text()`: "An e-mail from {name} came into <#channel:ID>: {subject}" (owner answer 4, #2444). The channel is a tag, drawn per reader by what they may see; `<@…>` / `<#…>` a sender typed in the name or subject lose their brackets, so they name and notify nobody. Answers to the conversation add no line.

## Tests?
- `tests/workspace_mail_route_test.php` (pure parts and wiring). The channel writes were not run against a database in the cloud session (PR #2904).
