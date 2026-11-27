---
name: bug-hunt-areas-erp-quotes-purchase-orders
description: The bug hunt in ERP sales quotes and purchase orders (k2, 2026-11-26): the guest link, a quote invoiced twice, an order received twice, rights; two faults fixed, what was read clean, what is left
---

## Which area was it?
- Architect (2026-11-26, agent channel, request 3141): `includes/erp/quotes.php`, `quote_links.php`, `purchase_orders.php`, `api_purchase_orders.php`, the quote routes of `api_documents.php`, `erp_quote.php`, `edit_erp_quote.php`, `add_erp_quote.php`, `edit_erp_purchase_order.php`, `add_erp_purchase_order.php`, the list and PDF screens.

## What was fixed? (#11858, #11860)
- A quote's status move (`erp_quote_set_status()`: the screen's buttons, `POST /erp/quotes/{id}/status`, the guest link's acceptance) wrote without a guard. One read before the quote became an invoice wrote over `invoiced`; the quote then read as accepted and could be invoiced a second time. It now moves only from the status and `invoice_id` it was judged on, reports a failed write, and announces only a move that wrote the row (erp-quote-double-invoice.md).
- The guest page's acceptance kept the signature and said "accepted" when a cancel or invoice landed between its check and its move: fixed by #11860 (erp-quote-link-accept-lock.md).
- The signature drawn on Edit Sales Quote bound the quote as it stood at the press, not as shown: a save by another person or the API meanwhile was signed unread and then read "unchanged since it was signed". The form now posts the shown hash (`d`), checked with `erp_quote_link_shown_matches()` as the guest page does.

## What was read clean?
- Guest link: token shape, hash lookup with `hash_equals`, one neutral 404, one live link, expiry, the claim (`used_at`) against a double submit, the shown-hash check, GET changes nothing, WAF allowance, no-store/no-referrer/DENY.
- Quote to invoice under the row lock; the Workspace card and the API call the same writer.
- Purchase orders: the draft under the order's lock, guarded cancel/reopen/sent, the guarded `received` announcement, the line keys (copies clear them; the API refuses a key on a draft not made from an order), what is left per line.
- Rights: every screen behind `validate_erp_access()` (a POST of the read-only accountant refused at the gate), the write forms asked with `'write'` on arrival, buttons hidden for `USER_ERP_READONLY`, API scopes `erp:read`/`erp:write` capped by `erp_owner_scopes()`. No step of quotes or orders touches a till.

## What is left?
- The signature hash leaves out the notes and the exchange rate (`erp_signature_document_hash_quote()`): a change of only those keeps a signature "unchanged". Changing the hash would mark every signed quote as changed: a product choice, asked in the PR.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/erp/erp-quotes.md, docs/memory/erp/erp-quote-double-invoice.md
