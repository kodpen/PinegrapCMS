---
name: order-legal-documents-writers
description: Which order completions keep legal document copies (checkout, payment message) and which keep none (subscription renewal #10666, API, counter, marketplaces); the NULL legal_acceptance limit
module: order_completion
---

## Who writes order_documents rows?
- Only `pg_order_complete()` calls `pg_legal_documents_store()`. Its callers: the checkout (`submit_order.php`, the types the visitor ticked) and a payment service's message (`pg_order_complete_from_payment()`, the types `orders.legal_acceptance` holds).
- Every type is kept accepted or not, so the order shows what was in front of the customer (order-legal-documents.md).

## Does a subscription renewal keep copies?
- No (#10666). `pg_subscription_renewal_order_write()` passes `'renewal' => true` in the context; `pg_order_complete_keeps_legal_documents()` reads it and the store is skipped. No checkout showed a renewal any document; the first order's copies stand.
- Without rows the order screen's Legal Documents card, the receipt's attachments and `GET /orders/{id}/documents` are empty by themselves.
- Symptom fixed: every renewal showed both documents in red as "Not accepted" and mailed copies "kept with the order without being accepted".
- Not retroactive: rows written before #10666 stay. Decision: decisions-made-product-83.md.

## And the other ways an order is made?
- The API's POST /orders and the counter sale (`includes/fn/local_order.php`) and marketplace imports do not call `pg_order_complete()`, so they keep no copies.

## What is the known limit?
- A NULL `orders.legal_acceptance` is read as preinfo and contract accepted, kvkk not (#3001, decisions-made-schema-3.md). The column is written at the redirect only while the documents are on.
- So an order at the payment service when the store switches the documents on keeps copies as accepted though its checkout did not show them. Kept as is: closing it needs a mark at every redirect or risks losing a real acceptance (decisions-made-product-83.md).
