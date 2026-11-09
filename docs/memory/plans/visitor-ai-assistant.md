---
name: visitor-ai-assistant
description: Plan (approved 2026-10-19, watch list): visitor-facing AI assistant in the site bubble answering from the site's own content with hand-over to Live Chat; AI disclosure; Premium licence
---

## What did the owner decide?
- 2026-10-30 (#3313): the plan stays a weak plan for now and is evaluated later; no start in 2026.11.0. The design below is settled for the day it starts.

## Why?
- Visitors get a human Live Chat (`chat.php`), a browser-side summariser and "ask an AI" links on records; no assistant answers from the site's content. Turkish SaaS competitors ship chat assistants (ikas, T-Soft) and use them as a 2026 headline. The EU AI Act transparency duties (from 2 Aug 2026) require telling people they talk to an AI.

## What is decided? (architect #3313 on the 2026-10-19 plan)
- Grounding, not a free chat: retrieval over the FULLTEXT `search_items` index (pages, records, products), product price, stock and variants, shipping methods, published FAQ/support pages and the order legal texts (`commerce/order-legal-documents.md`). The top N passages go to the model as `pg_ai_data_block()` blocks cited by page; it answers only from them. No source: "I don't know, let me connect you", the Live Chat waiting-chat flow (existing bubble), else the contact form. Endpoint down: the bubble shows the hand-over, not an error.
- Model: Pinegrap AI with the Premium key only (`includes/workspace/ai.php`, `ws_ai_http()`); not the Claude queue (asynchronous); no new provider. Per-site daily cap and per-visitor rate on the WAF background bucket.
- UI: the Live Chat bubble gains an "Assistant" first step when enabled; staff see conversations and can take over; a thumbs up/down feeds a weekly summary. Legacy Live Chat is unchanged when the assistant is off.
- Disclosure: a fixed translated sentence in the bubble and the first message, no setting; the Workspace "bot" rules apply (`workspace-module-decisions.md`): the visitor assistant is read-only by construction.
- Table `visitor_ai_chats` by `install_create_table()` in `get_tables()`, label read when pushed: `id`, `visitor_id` INT UNSIGNED (from the stats layer, no account needed), `conversation_key` VARCHAR(40), `role` VARCHAR(10), `message` MEDIUMTEXT, `cited_items` VARCHAR(255), `rating` TINYINT NOT NULL DEFAULT 0, `chat_id` INT UNSIGNED, `created_at` INT UNSIGNED; keys (`conversation_key`, `id`), (`visitor_id`), (`created_at`); 30-day cleanup; erasure and export cover it.
- Actions beyond answering (add to cart, track an order) come only after `storefront-api-agentic-commerce.md`; guest tracking by order number and e-mail exists (`commerce/guest-shipment-lookup.md`).

## What remains?
- Slices: retrieval helper over `search_items` + products (M), endpoint + bubble step (M), staff view + takeover (S–M), feedback and weekly summary (S), order-tracking action (S, after dependencies).

## Open questions?
- Multilingual sites: answer in the visitor's language using translated texts when present, else the default language with a note.
- Should the assistant see prices for signed-in B2B customers (account prices)? Only when the visitor is signed in; otherwise list prices.

## Where to start?
- Not before the owner reopens the plan (2026-10-30). Then slice 1 (no schema) after #3292; read `includes/fn/site_search.php` and `_pg_sw_search_collect()` in `includes/fn/widgets.php` (search), `chat.php`, `includes/workspace/ai.php`.
