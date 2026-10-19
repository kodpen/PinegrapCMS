---
name: checkout-legal-checkboxes
description: How the legal documents' checkboxes reach every checkout, what the cart document page shows (drawn snapshot or the values its link posts), its amounts, the server check in submit_order.php
---

## How do the checkboxes reach every checkout?
- `pg_legal_documents_inject($html, $values)` wraps the three checkout renderers: the designer's express order widget (`_expand_system_widgets()` in `includes/fn/designer.php`) and the classic express order and order preview (`get_page_content.php`). It puts two required checkboxes `legal_preinfo` / `legal_contract` (value 1) in front of the tag named `submit_purchase_now`; `get_express_order.php` / `get_order_preview.php` are not edited. No purchase button, or the page already has a `legal_preinfo` field (a designer tree that binds it), means nothing is added. The designer's starter tree binds them itself: docs/memory/designer/express-order-legal-documents.md.
- Ticked state comes from `pg_legal_documents_form_values()` read BEFORE the renderer runs: the designer widget drops the liveform from the session while drawing messages.
- `submit_order.php` refuses the order with "{var:1} must be accepted." per document when the switch is on (same block as the terms check, so both checkout types and not on payment returns). `express_order.php` lists both fields as single values.

## What does the cart document page show?
- `legal_document.php?type=preinfo|contract`: the visitor's own cart (`$_SESSION['ecommerce']['order_id']`), `no-store`, `noindex`; off or unknown type gives "This page is not available.".
- A cart row has no totals. `pg_legal_document_cart_order()`: subtotal and tax from `order_items` (`price * quantity`, `tax_total`), shipping from `ship_tos.shipping_cost`; the total is the checkout's hidden `total` field read by `pg_legal_document_form_snapshot()` when the page was drawn (kept in `$_SESSION['ecommerce']['legal_documents_seen']` with typed billing values, the selected country and the ticked payment method).
- Difference to the row sums: total = goods + tax means no shipping chosen yet (a recipient row keeps a stale cost; seen in the sandbox: page 0, row 15.00); lower is a discount; higher is shipping. Known gap: a stale shipping row together with a discount prints the wrong split; the stored copy at submit is exact.

## How does the document show what the visitor has typed?
- `pg_legal_documents_inject()` also loads `assets/js/pg_legal_documents.js` (both ways out, so a designer tree that binds the boxes gets it too; `window.pgLegalDocumentLinks` keeps one listener). A plain left click on a link to this site's `legal_document.php?type=` (no `order_id`) posts the checkout form's billing fields (visible ones; `pg_legal_document_billing_fields()`, the script's list is tested against it), the payment method (a hidden single method too) and the form's `token` (else `software_token`) to the link's address in the link's window. Modified clicks, other hosts, kept copies and a form without a token keep the plain GET. PR #NNN.
- `legal_document.php` takes the post only when `pg_legal_document_token_matches()` (no `validate_token_field()`: a wrong token falls back to the drawn snapshot, not an error) and lays it over the snapshot with `pg_legal_document_typed_snapshot()`: each field one line, control characters out, 255 characters (country 10), a known payment method only, never the total (the form's hidden total can carry an instalment fee). A posted empty field clears the row's value (`typed`); a drawn empty one does not. Nothing is stored. Delivery addresses still come from the saved recipient rows.

Related: docs/memory/commerce/order-legal-documents.md
