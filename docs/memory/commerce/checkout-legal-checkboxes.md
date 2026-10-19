---
name: checkout-legal-checkboxes
description: How the legal documents' checkboxes reach every checkout, what the cart document page shows and how its amounts are worked out, and the server check in submit_order.php
---

## How do the checkboxes reach every checkout?
- `pg_legal_documents_inject($html, $values)` wraps the three checkout renderers: the designer's express order widget (`_expand_system_widgets()` in `includes/fn/designer.php`) and the classic express order and order preview (`get_page_content.php`). It puts two required checkboxes `legal_preinfo` / `legal_contract` (value 1) in front of the tag named `submit_purchase_now`; `get_express_order.php` / `get_order_preview.php` are not edited. No purchase button, or the page already has a `legal_preinfo` field (a designer tree that binds it), means nothing is added.
- Ticked state comes from `pg_legal_documents_form_values()` read BEFORE the renderer runs: the designer widget drops the liveform from the session while drawing messages.
- `submit_order.php` refuses the order with "{var:1} must be accepted." per document when the switch is on (same block as the terms check, so both checkout types and not on payment returns). `express_order.php` lists both fields as single values.

## What does the cart document page show?
- `legal_document.php?type=preinfo|contract`: the visitor's own cart (`$_SESSION['ecommerce']['order_id']`), `no-store`, `noindex`; off or unknown type gives "This page is not available.".
- A cart row has no totals. `pg_legal_document_cart_order()`: subtotal and tax from `order_items` (`price * quantity`, `tax_total`), shipping from `ship_tos.shipping_cost`; the total is the checkout's hidden `total` field read by `pg_legal_document_form_snapshot()` when the page was drawn (kept in `$_SESSION['ecommerce']['legal_documents_seen']` with typed billing values, the selected country and the ticked payment method).
- Difference to the row sums: total = goods + tax means no shipping chosen yet (a recipient row keeps a stale cost; seen in the sandbox: page 0, row 15.00); lower is a discount; higher is shipping. Known gap: a stale shipping row together with a discount prints the wrong split; the stored copy at submit is exact.

Related: docs/memory/commerce/order-legal-documents.md
