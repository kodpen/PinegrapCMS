---
name: checkout-legal-checkboxes
description: How the legal documents' checkboxes reach every checkout, what the cart document page shows (drawn snapshot or the values its link posts), its amounts, the server check in submit_order.php
---

## How do the checkboxes reach every checkout?
- `pg_legal_documents_inject($html, $values)` wraps the three checkout renderers: the designer's express order widget (`_expand_system_widgets()`) and the classic express order and order preview (`get_page_content.php`). It puts two required checkboxes `legal_preinfo` / `legal_contract` (value 1) in front of the tag named `submit_purchase_now`. No purchase button, or a `legal_preinfo` field already on the page (a designer tree that binds it), means nothing is added. The designer's starter tree binds them itself: docs/memory/designer/express-order-legal-documents.md.
- Ticked state comes from `pg_legal_documents_form_values()` read BEFORE the renderer runs: the designer widget drops the liveform from the session while drawing messages.
- `submit_order.php` refuses the order with "{var:1} must be accepted." per document when the switch is on (same block as the terms check, so both checkout types and not on payment returns). `express_order.php` lists both fields as single values.

## What does the cart document page show?
- `legal_document.php?type=preinfo|contract`: the visitor's own cart (session `order_id`), `no-store`, `noindex`; off or unknown type gives "This page is not available.".
- A cart row has no totals. `pg_legal_document_cart_order()`: subtotal and tax from the lines charged today (`price * quantity`, `tax_total`), shipping from `ship_tos.shipping_cost`; the total is the checkout's hidden `total` field read by `pg_legal_document_form_snapshot()` when the page was drawn (kept in `$_SESSION['ecommerce']['legal_documents_seen']` with typed billing, country and payment method).
- Lines are read with `pg_order_item_active_sql()`; a recurring line that starts later (`pg_order_line_charged_today()`) is listed but counts in no total. Before #1281 both counted and came back as a discount the checkout never gave.
- The cart's order discount (session `order_discount`) comes first and lowers the tax by its share, as `submit_order.php` does (PR #1089; before, the tax difference went into the discount). Then against the row sums: total = discounted goods + tax means no shipping chosen yet (stale recipient cost); lower is another discount (gift card); higher is shipping. Gap: a stale shipping row with a gift card.

## How does the document show what the visitor has typed?
- `pg_legal_documents_inject()` also loads `assets/js/pg_legal_documents.js` (both ways out, so a designer tree that binds the boxes gets it too; `window.pgLegalDocumentLinks` keeps one listener). A plain left click on a link to this site's `legal_document.php?type=` (no `order_id`) posts the checkout form's billing fields (no hidden input, nothing inside a `[hidden]` block; `pg_legal_document_billing_fields()`, the script's list is tested against it; with the designer's ticked "same as shipping" box the one recipient's name and address instead, the list `express_order.php` copies, PR #1086), the payment method (a hidden single method too) and the form's `token` (else `software_token`) to the link's address in the link's window. Modified clicks, other hosts, kept copies and a form without a token keep the plain GET. PR #648.
- `legal_document.php` takes the post only when `pg_legal_document_token_matches()` (no `validate_token_field()`: a wrong token falls back to the drawn snapshot, not an error) and lays it over the snapshot with `pg_legal_document_typed_snapshot()`: each field one line, control characters as spaces, 255 characters (country 10), a known payment method only, never the total (the form's hidden total can carry an instalment fee). A posted empty field clears the row's value (`typed`); a drawn empty one does not. Nothing is stored. Delivery addresses still come from the saved recipient rows.

Related: docs/memory/commerce/order-legal-documents.md
