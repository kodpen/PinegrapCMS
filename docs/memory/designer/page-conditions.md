---
name: page-conditions
description: W18: an element outside a widget shown by visit facts (signed in, cart) or the page's record (found, allow-listed values); stored-body markers decided per request, cache, editor
module: page_facts
---

## What can a page element be shown by?
- Owner answer "Page-level facts?" ([[widget-designability-decisions-2]]): three facts of the visit, `__visit_signed_in`, `__visit_cart_has_items` ('1' or ''), `__visit_cart_count` (the quantity, '' for none; `SUM(quantity)` of the active lines, as the cart link). They only choose which element shows; nothing is printed.
- The condition is the widget one ([[widget-conditions]]): `show_if` rows, operators, All/Any. Every row must read a fact (`pg_page_fact_spec()`); a node outside a widget mixing a fact with another value, or with no fact, draws as before, unmarked.

## What is the page's record? (W18b)
- The record the address opens: the product of a product page, the event of an event page, the record of a form record page. Its widget publishes it while it draws (`pg_page_record_publish()` in the catalog item view, the event view, the form item view; the first one counts). Tokens (`pg_page_record_tokens()`): `__record_found` ('1' or '') and `__record` + each allow-listed token of [[widget-entity-fields]] (`__record__is_free_shipping`, `__record__event_reservations_open`, `__record__quiz_score`); no record: every one ''.
- `get_page_content.php` starts each draw with no record and gives the earlier slot back after (`pg_page_record_begin()` / `_end()`), so an e-mail drawn meanwhile keeps neither's.
- Conditions only for now; showing the record's values outside the widget is W17. The reading of the owner's "the page's own record" is the coordinator's; the owner is asked to confirm (#2334).

## How is it drawn?
- `_render_tree_node()` outside a widget scope writes `<!--pg-pf:ID:SPEC-->` node `<!--/pg-pf:ID-->` (`pg_page_fact_wrap()`, `includes/fn/page_facts.php`): SPEC is the rows as URL-safe base64 JSON, ID twelve hex characters of a hash of SPEC and the node, so a body drawn again from the same tree is the same bytes and the stored body and a shared component drawn now never share an id.
- The stored body keeps the markers (also the translated bodies). `get_page_content.php` decides them once every shared component and widget is drawn (`pg_page_facts_apply()`): the facts are read only when a marker is there; spans are found with `strpos()`, outermost first, a kept span read again for those inside it.
- Inside a widget render the finish pass decides a node as any condition; the facts are not filled there (a fact reads empty).

## Why is caching right?
- The record depends on the address alone. `page_cache.php` bypasses a signed-in session and one holding an order, so a stored or CDN copy is always drawn for a signed-out visitor with an empty cart, which is who it is served to ([[page-cache-gate]]).
- An e-mail, the search index and the SEO score are drawn for no visit: signed out, empty cart (`pg_page_fact_values()`).

## What fails closed?
- A span whose SPEC cannot be read, names another value, an unknown operator or misses a needed compared text is cut; an open marker without its close is dropped and its node shows.

## What does the editor offer?
- Bind Data's "Show only when a value…" rows (`_sdConditionRows()`) on any element outside a widget, in a section "Show by the visit"; the list is `SD_PAGE_FACTS` (= `pg_page_fact_tokens()`, `tests/page_facts_test.php`) and, when the page holds a detail widget, the record's (`_sdPageRecordOptions()`: found, then "Record: <value>" of the kinds present). A hand-written row on another value is listed and the note says the element always shows. The canvas names the condition with the chip and keeps drawing the element.

## What is left?
- Showing the record's values outside the widget (W17); facts inside a widget's tree; a canvas state for the facts.

## Tests?
- `tests/page_facts_test.php`.
