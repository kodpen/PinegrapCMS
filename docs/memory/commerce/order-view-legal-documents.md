---
name: order-view-legal-documents
description: Order view links to the legal documents and KVKK copy kept with the order: __legal_*_url tokens, has_legal_* flags, starter card, cards added after older layouts, none in the e-mail
---

## What does the order view give?
- Since PR #736; before it the customer reached a kept copy only through the receipt e-mail's attachments.
- `__legal_preinfo_url` / `__legal_contract_url`: the copy of each document kept with the order, `legal_document.php?order_id=N&type=…` (`pg_legal_document_copy_url()`). Same names as the express order's links to the cart's document page, so a label and link copied between the two widgets stay right.
- `__legal_kvkk_url` (PR #2648): the KVKK copy (the notice the checkout linked and the consent sentence; `legal_document.php` says whether consent was given). Before it the copy was reached only from the e-mail attachment and the panel.
- Empty when the order has no copy of that type: `pg_legal_document_copy_urls()` over `pg_legal_documents_of_order()`, one entry per `pg_legal_document_labels()` key.
- Flags: `has_legal_documents` (a copy is kept; on the express order the same name means the switch is on), `has_legal_preinfo`, `has_legal_contract`, `has_legal_kvkk`.
- The links do not depend on `ECOMMERCE_LEGAL_DOCUMENTS`: a kept copy stays readable after the switch is turned off.

## Who can open the links?
- The widget shows an order to its signed-in owner and to the guest whose session has just completed it; `legal_document.php` lets the same visitors in (`pg_legal_document_may_view()`, plus store staff). Anyone else gets the not-found state from the widget and "This page is not available." from the page.
- The receipt e-mail (`pg_sw_render_context()` with `email`) gets no links and no card: it attaches the copies, and its reader may have no session to open a link with.

## What does the starter tree hold?
- Right column, after the Order History card: "Legal Documents Card" under `has_legal_documents`, one line per document and the KVKK copy under its own flag, each a link bound to its token.
- The links carry `target: '_blank'` as a prop, `rel` in `_attrs`, like every link of the tree; a window an older layout keeps in `_attrs` prints too (designer/link-target-in-attrs.md).

## What about layouts made before the links?
- A layout that binds none of the three tokens is given the legal documents card at its end (the KVKK line last), real elements the designer can change ([[widget-default-parts-order]]): a `container pb-4` card listing the kept copies, the shape of the returns card. A layout that binds a link places the links itself. Deleting the card brings it back; a designer who wants the links elsewhere binds a token there.
- A layout that binds a document link but not `__legal_kvkk_url` (every layout made from the starter tree before PR #2648) is given a card of its own, `pg-ov-legal-kvkk` "Personal Data Notice", under `has_legal_kvkk` (`pg_sw_part_order_legal_kvkk()`, JS twin `_sdPartOrderLegalKvkk()`).

Related: docs/memory/commerce/order-legal-documents.md, order-withdrawal-deadline.md (the withdrawal line in the same card)
