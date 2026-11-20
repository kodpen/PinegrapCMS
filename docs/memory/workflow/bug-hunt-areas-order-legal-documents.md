---
name: bug-hunt-areas-order-legal-documents
description: The bug hunt of the order's legal documents (pre-information form, distance sales contract, KVKK copy; #10663, 2026-11-20): the three faults fixed, the issue opened, what was read clean
module: legal_documents
---

## Which area was hunted?
- Architect (2026-11-20, agent channel message 10368): the order's legal documents. `includes/fn/legal_documents.php`, `legal_document.php`, the server check of the boxes in `submit_order.php`, the kept copy in `order_documents`, the receipt attachments, the order view's links.
- Left out as held by other PRs: `includes/fn/ecommerce.php`, `widgets.php`, `output.php`, the API's shared layer, `product_form_uploads.php`.

## What did #10663 fix?
- An order went through with a document box that posted '0' (the required-field check passes it), and a designer box bound to `legal_preinfo` / `legal_contract` with a value other than 1 was kept as "Not accepted" on an order the check let through. One rule now, `pg_legal_document_ticked()`, for the refusal, the kept acceptance and `orders.legal_acceptance` (`commerce/checkout-legal-checkboxes.md`).
- A province equal to the city printed twice ("06100 Ankara Ankara") in the buyer's and the delivery address: `pg_legal_document_city_line()` (`commerce/order-documents-address.md`).
- A kept copy of an order paid in instalments printed a total with the instalment difference but no line for it: `^^surcharge^^` now holds it (`commerce/order-legal-documents.md`).

## Which issue did it open?
- #10666: a subscription renewal order keeps both documents as "Not accepted", shows them red on the order screen and mails them with the renewal receipt; and `legal_acceptance` NULL (documents switched on between the redirect and the payment service's message) is read as accepted. Both rest on written rules, so the fix waits for a decision.

## What was read clean?
- The server check on every checkout path: classic order preview and express order through `submit_order.php`, the designer's express order (same handler), payment returns (the liveform's values from before the redirect), the payment service's completion (`legal_acceptance`). The API has no checkout: `POST /orders` is a counter sale.
- The kept copy: written once (a second call stores nothing), never re-rendered; a later template change reaches neither the page, the attachments, the panel card nor `GET /orders/{id}/documents`.
- Identity: `pg_legal_document_may_view()` (staff with store rights, the signed-in owner, the guest's just completed order) and the order view widget's own rule match; an incomplete or another person's order reads as missing.
- Deleting an order removes its copies (`commerce/order-delete.md`); a personal data erasure keeps the text and clears IP and browser (decided, `core/personal-data-erasure.md`).
- Escaping: values through `h()` with `nl2br`, the line table, conditionals resolved on raw values in one pass (a value is never read as a placeholder), the KVKK lines, the panel card.
- Amounts: minor units with `round()`, discount plus gift card, tax per line, the cart's order discount and its tax split, recurring lines charged later.

## What was not run?
- No database here: the fixes are tried by unit tests of the pure rules and of where each call stands in `submit_order.php` and `pg_legal_documents_for_order()`.
