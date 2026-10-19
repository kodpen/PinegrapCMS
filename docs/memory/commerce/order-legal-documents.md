---
name: order-legal-documents
description: Legal documents (pre-information form, distance sales contract): settings, templates, order_documents, kept copy for customer and staff, receipt attachments, panel card
---

## What is built?
- Settings › Ecommerce › Legal Documents (`pgset-legal`): switch `ECOMMERCE_LEGAL_DOCUMENTS` and two HTML templates (schema 11.0.1, PR #540). Card and save only when the column exists.
- `includes/fn/legal_documents.php` (tests `tests/legal_documents_test.php`), `legal_document.php` (cart document page), store call in `submit_order.php` after the order number (PR #540), checkout part PR #559, kept copy for customer and staff PR #PRNUM.

## How does a template work?
- `^^field^^`, `[[text ^^field^^ text]]`, `[[...||other]]`; values escaped, `^^order_lines^^` is the only markup. Unknown field or over 65535 bytes refuses the settings save. Built-ins `includes/legal_documents/<type>.<lang>.html` (tr, en); an unchanged built-in is stored as NULL.
- Seller block from settings (`ORGANIZATION_*`, store e-mail, `ERP_SELLER_VKN`, `ERP_SELLER_TAX_OFFICE`, `ERP_WEB_ADDRESS`); MERSİS and return carrier are typed into the template.

## What does order_documents hold and who reads it?
- One row per type per order: rendered `html`, `accepted_at` (NULL = not accepted), `ip`, `user_agent`, `created_at`; a second store call writes nothing.
- `legal_document.php?order_id=N&type=...` shows the kept copy with "Accepted by the customer on …" (`pg_legal_document_acceptance_note()`) to `pg_legal_document_may_view()`: staff with `USER_MANAGE_ECOMMERCE`, the signed-in owner (`orders.user_id`), the guest whose `completed_order_id` it is. Anyone else, an incomplete order or a missing type gets "This page is not available.". Readable after the switch is turned off.
- The order receipt e-mail (`submit_order.php`) carries both copies as HTML attachments (`pg_legal_documents_attachments()`, names like `mesafeli-satis-sozlesmesi-1173.html`); they travel through the mail queue as base64.
- Panel order view (`view_order.php`, left column under Affiliate): `pg_legal_documents_panel_card()` lists each document, its acceptance time or "Not accepted", the IP as title, a link to the copy. No rows, no card.

## Where is the checkout side?
- docs/memory/commerce/checkout-legal-checkboxes.md

## What is left?
- docs/memory/plans/checkout-legal-documents.md (designer bindings, My Account link, API, KVKK).
