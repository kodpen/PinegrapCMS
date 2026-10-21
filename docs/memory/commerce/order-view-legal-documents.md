---
name: order-view-legal-documents
description: Order view widget links to the legal documents kept with the order: __legal_*_url tokens, has_legal_* flags, starter tree card, the card added after older layouts, none in the receipt e-mail
---

## What does the order view give?
- Since PR #736; before it the customer reached a kept copy only through the receipt e-mail's attachments.
- `__legal_preinfo_url` / `__legal_contract_url`: the copy of each document kept with the order, `legal_document.php?order_id=N&type=…` (`pg_legal_document_copy_url()`). Same names as the express order's links to the cart's document page, so a label and link copied between the two widgets stay right.
- Empty when the order has no copy of that type: `pg_legal_document_copy_urls()` over `pg_legal_documents_of_order()`, one entry per `pg_legal_document_types()` key; a stored type it does not name (`kvkk`) gets no link.
- Flags: `has_legal_documents` (a copy is kept; on the express order the same name means the switch is on), `has_legal_preinfo`, `has_legal_contract`.
- The links do not depend on `ECOMMERCE_LEGAL_DOCUMENTS`: a kept copy stays readable after the switch is turned off.

## Who can open the links?
- The widget shows an order to its signed-in owner and to the guest whose session has just completed it; `legal_document.php` lets the same visitors in (`pg_legal_document_may_view()`, plus store staff). Anyone else gets the not-found state from the widget and "This page is not available." from the page.
- The receipt e-mail (`pg_sw_render_context()` with `email`) gets no links and no card: it attaches the copies, and its reader may have no session to open a link with.

## What does the starter tree hold?
- Right column, after the Order History card: "Legal Documents Card" under `has_legal_documents`, one line per document under its own flag, each a link bound to its token.
- The links carry `target: '_blank'` as a prop, `rel` in `_attrs`, like every link of the tree; a window an older layout keeps in `_attrs` prints too (designer/link-target-in-attrs.md).

## What about layouts made before the links?
- A layout that binds neither token gets `pg_legal_documents_order_view_block()` after the widget: a `container pb-4` card listing the kept copies, the shape of the returns block. A layout that binds a link places the links itself. Deleting the card from a new layout brings the block back; a designer who wants the links elsewhere binds a token there.

Related: docs/memory/commerce/order-legal-documents.md
