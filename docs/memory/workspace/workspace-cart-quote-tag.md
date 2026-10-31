---
name: workspace-cart-quote-tag
description: The cart quote's Workspace tag (cart_quote, #3256): prefixes, the ecommerce right, orders:read, what the chip shows, the search, view_cart_quotes.php?id= and its Workspace button
---

## How is a cart quote tagged?
- Kind `cart_quote` (`ws_record_kinds()`, refs.php), "Cart quote" / "Sepet teklifi", icon `bi-cart3`; `#sepet`, `#sepetteklifi` or `#cartquote` picks it. The offer keeps `#teklif` and the sales quote `#satis`: no prefix is shared (`test_workspace_record_kinds_prefixes_pick_one_kind`).
- Shown with the ecommerce right, the right of the cart quote screens; without it the tag reads "Cart quote", with no number or link. Read only where e-commerce runs and the tables are there (`pg_cart_quotes_ready()`).
- Tag-only: no `ws_change_types()` kind, so no drawer, bulk change, audit line or proposal; quotes are made and changed on their own screens (`commerce/cart-quotes.md`). No scheduled-action event; not a customer kind.

## What does the chip show?
- `ws_cart_quote_ref()` (pure): "Cart quote #12", the contact's name (else the company; nothing for a quote made for nobody), "{n} item(s)" counting lines as the customer card does, and the state `pg_cart_quote_state()` gives: an open quote past `valid_until` reads Expired, as on the list, since nothing writes `expired`. The contact's name goes to every reader with the ecommerce right, as the cart quote list shows it to them.
- It opens `view_cart_quotes.php?id=12`, the list narrowed to that quote (`pg_cart_quote_panel_rows()`'s `$quote_id`); each row's number on the list links there too. Only then does the toolbar draw the Workspace button (`ws_record_button()`), so the full list (up to 200 rows) counts no conversations.
- A lone tag message is drawn as its card (`workspace-tag-card.md`) with these words.

## How is one found?
- `ws_ref_search()`: a number (`12` or `#12`) by id, else words of the contact's first or last name, full name, company or e-mail; newest first; nothing typed lists the newest.

## Which scope reads it?
- `orders:read` in `ws_api_search_record_scopes()` and `ws_ai_type_scope()`: the scope of the order routes, which the store hands to the people the cart quote screens open to (`api_owner_scopes()`, `validate_ecommerce_access()`), and the one the customer summary's cart quote section is read with (`ws_api_customer_scopes()`). `test_ws_api_search_record_scopes_name_known_kinds` holds it to `orders.list` / `orders.get`; the `cart_quote.*` routes (`plans/workspace-quotes.md`) read with the same scope, or both maps and that test change with them.
- `GET /workspace/refs`, the search route and the `pinegrap://workspace/refs/{type}/{id}` resource take the kind. Pinegrap AI's `read_record` gives the chip's words, not the lines; Claude's record route reads change kinds only.

Related: docs/memory/commerce/cart-quotes.md, docs/memory/workspace/workspace-record-kinds.md
