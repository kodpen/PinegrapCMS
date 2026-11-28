---
name: decisions-made-schema-54
description: Decisions made, schema part 54 (2026-11-27 on): the help centre's priority, source and kb_votes; the markets' tables and an order's charged currency; one table for the accounting sync
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-53.md`.

## What does the help centre add to the database? (owner, 2026-11-27, agent channel poll 252, message 14929)
- Asked after the count, with what the architect had settled told first: a support request is no thing of its own but the mail conversation of a support box (its state, holder, first-answer target and rules are there); a form, the chat window and My Account open one; articles stay a collection. All of that needs no addition.
- Owner: "Hepsi". Offered beside it and not chosen: only the request's columns; only the priority; nothing.
- So, each by a step of the open version with the idempotent helpers:
  - `ws_mail_conversations.priority` TINYINT (Low, Normal, High, Urgent), on the row because every list, filter, count and the API read it.
  - `ws_mail_conversations.source` VARCHAR(10) (mail, whatsapp, sms, form, chat, account) and `source_id` INT (the `forms` or `chat_conversations` row): the list's label and the link back.
  - The table `kb_votes` (id, form_id, record_id, the answer yes or no, created_at) for "Was this helpful?": no address and no IP; a cookie stops a second vote.
- His yes covers these and nothing else: the resolve-by time stays computed, and a clock that stops while waiting would be a further column, not asked.
- Built by HC7 (priority and source, `workspace/mail-priority-source.md`) and HC8 (`kb_votes`, `designer/kb-votes-helpful.md`).
- Running sites: a conversation stored before reads as Normal with the source mail; nothing is rewritten.

## What do markets add to the database? (owner, 2026-11-27, agent channel poll 251, message 14926)
- Asked after the count: every amount is in the site's base currency today and another currency is display only; marketplace orders are stored in their own currency, yet the dashboard and the reports add them up as base. The behaviour was told as settled by the architect (`plans/markets-price-lists.md`); the additions were his to give.
- Owner: "Hepsi". Offered beside it and not chosen: markets without fixed prices; only the order's currency; nothing for now.
- So, each by a step of the open version with the idempotent helpers:
  - `orders.charged_currency` CHAR(3) and `orders.charged_rate` DECIMAL(18,8) (base units for one charged unit on the order's day): MK2. An order is kept in the currency it is charged in, so no second total is stored.
  - The tables `markets` (name, currency, rounding, on or off, order) and `market_countries` (a country in one market, as `zones_countries_xref`), and `orders.market_id`: MK4.
  - The table `market_prices` (a product's fixed price in a market): MK9.
- His yes covers these and nothing else; a column a step finds it needs beyond them is asked.
- Running sites: with no market made nothing changes; an order stored before reads as charged in base at rate 1, and no stored total is rewritten.

## Where are the accounting sync's queue and pairing kept? (owner, 2026-11-27, agent channel poll 250, message 14911)
- Asked after the count of the QuickBooks and Xero sync, the rest told as settled by the architect (`api/accounting-sync-rules.md`).
- Owner: "Tek yeni tablo". Not chosen: columns on the records; not built for now.
- So: one table, `accounting_sync`, by a step of the open version (AS3): service, company, kind (customer, item, tax, sale, payment, refund), local table and id, remote id, sync token, status (waiting, sent, failed), attempts, next try, last error, times; unique on service, company, kind, local table and id. A row is both the queue and the pairing, as `ad_conversion_events` is (`decisions-made-schema-35.md`).
- No column is added to `orders`, `products`, `erp_invoices` or the customer for either service.
