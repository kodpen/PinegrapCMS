---
name: visitor-ai-assistant
description: Plan (approved 2026-10-19, watch list): visitor-facing AI assistant in the site bubble answering from the site's own content with hand-over to Live Chat; AI disclosure; Premium licence
---

## Why?
- Visitors get a human Live Chat (`chat.php`), a browser-side summariser and "ask an AI" links on records; no assistant answers from the site's content. Turkish SaaS competitors ship chat assistants (ikas, T-Soft) and use them as a 2026 headline. The EU AI Act transparency duties (from 2 Aug 2026) require telling people they talk to an AI.

## What is decided?
- Grounding, not a free chat: answers come from the site's own sources through retrieval — the FULLTEXT `search_items` index (pages, records, products), product data (price, stock, variants, shipping methods), published FAQ/support pages, and store policies (`checkout-legal-documents.md` texts). The model receives only retrieved passages and must cite the page; questions outside the sources get "I don't know, let me connect you" and a hand-over to Live Chat (existing bubble, waiting-chat flow) or a contact form.
- Model: Pinegrap AI with the Premium key (`includes/workspace/ai.php` client) or the Claude queue; no new provider. Per-site daily cap and per-visitor rate limit through the WAF background bucket.
- UI: the existing Live Chat bubble gains an "Assistant" first step when enabled; messages are stored in a `visitor_ai_chats` table (visitor id from the stats layer, no account needed) with 30-day retention; staff see conversations and can take over; a thumbs up/down feeds a weekly summary.
- Disclosure: the bubble states it is an AI assistant, the first message repeats it, and the Workspace "bot" rules apply (`workspace-module-decisions.md`): assistants never write records; the visitor assistant is read-only by construction.
- Actions beyond answering (add to cart, track order with order number + e-mail) come only after `storefront-api-agentic-commerce.md` and `shipping-management.md` guest tracking exist.
- Legacy Live Chat behaviour is unchanged when the assistant is off.

## What remains?
- Design note: retrieval ranking, prompt, hand-over rules, data retention (owner review).
- Slices: retrieval helper over `search_items` + products (M), endpoint + bubble step (M), staff view + takeover (S–M), feedback and weekly summary (S), order-tracking action (S, after dependencies).

## Open questions?
- Multilingual sites: answer in the visitor's language using translated texts when present, else the default language with a note.
- Should the assistant see prices for signed-in B2B customers (account prices)? Only when the visitor is signed in; otherwise list prices.

## Where to start?
- Design note first; read `includes/fn/widgets.php:3786-4300` (search), `chat.php`, and `includes/workspace/ai.php`.
