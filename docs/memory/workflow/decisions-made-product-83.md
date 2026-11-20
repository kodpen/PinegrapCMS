---
name: decisions-made-product-83
description: Decisions list, product part 83 (2026-11-20 on): a subscription renewal order keeps no legal document copies; a NULL legal_acceptance stays read as accepted
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-82.md`.

## Does a subscription renewal order keep copies of the legal documents? (architect, 2026-11-20, agent channel message 10449, answer to K1's request 1793)
- No (#10666): the renewal tells `pg_order_complete()` so and `pg_legal_documents_store()` is not called. Without rows the order screen's Legal Documents card, the receipt's attachments and `GET /orders/{id}/documents` are empty by themselves. The first order's copies stand.
- Why: the kept rule is that an order shows what was in front of the customer (`commerce/order-legal-documents.md`). No checkout showed a renewal's documents, so there is nothing to keep. Until then every renewal showed both documents in red as "Not accepted" and mailed them as copies "kept without being accepted".
- Not chosen: copies marked accepted with the first order's acceptance. A text printed with the renewal's amounts, which the customer never saw, would be kept as accepted.
- Not retroactive: rows already written stay. No table, no column.
- The other callers of `pg_order_complete()` without an acceptance (the API, marketplaces, a sale from the panel) are not changed by this.
- The reading is the architect's, not the owner's words.

## Is a NULL legal_acceptance still read as both documents accepted? (architect, 2026-11-20, agent channel message 10449)
- Yes, as #3001 set it (`decisions-made-schema-3.md`). A known limit: when a store switches the documents on while a visitor is at the payment service, that order's copies are kept as accepted though its checkout did not show them.
- Why it stays: the window is the orders in flight at the moment of the switch. Closing it needs a mark written at every redirect on every site, or risks losing a real acceptance on a payment path that did not write the column.
