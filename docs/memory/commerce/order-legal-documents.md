---
name: order-legal-documents
description: Legal documents (pre-information form, distance sales contract): settings, templates, order_documents, kept copy for customer and staff, receipt attachments, panel card
---

## What is built?
- Settings › Ecommerce › Legal Documents (`pgset-legal`): switch `ECOMMERCE_LEGAL_DOCUMENTS` and two HTML templates (schema 11.0.1, PR #540). Card and save only when the column exists.
- `includes/fn/legal_documents.php` (tests `tests/legal_documents_test.php`), `legal_document.php` (cart document page), store call in `submit_order.php` after the order number (PR #540), checkout part PR #559, kept copy for customer and staff PR #569.

## How does a template work?
- `^^field^^`, `[[text ^^field^^ text]]`, `[[...||other]]`; values escaped, `^^order_lines^^` is the only markup. Unknown field or over 65535 bytes refuses the settings save. Built-ins `includes/legal_documents/<type>.<lang>.html` (tr, en); an unchanged built-in is stored as NULL.
- `^^order_lines^^` names each line by the product's short description, as the cart and the receipt do; the line's `product_name` (the Product ID / SKU) only when there is none or the product is gone (`pg_legal_document_item_name()`). Until #626 the documents printed the SKU.
- Seller block from settings (`ORGANIZATION_*`, store e-mail, `ERP_SELLER_VKN`, `ERP_SELLER_TAX_OFFICE`, `ERP_WEB_ADDRESS`); MERSİS and return carrier are typed into the template.

## What does order_documents hold and who reads it?
- One row per type per order: rendered `html`, `accepted_at` (NULL = not accepted), `ip`, `user_agent`, `created_at`; a second store call writes nothing.
- `ip` is `waf_client_ip()`: the visitor behind Cloudflare or a trusted proxy. Until #626 it was the raw `REMOTE_ADDR`, the proxy's address for every customer.
- `legal_document.php?order_id=N&type=...` shows the kept copy with "Accepted by the customer on …" (`pg_legal_document_acceptance_note()`) to `pg_legal_document_may_view()`: staff with `USER_MANAGE_ECOMMERCE`, the signed-in owner (`orders.user_id`), the guest whose `completed_order_id` it is. Anyone else, an incomplete order or a missing type gets "This page is not available.". Readable after the switch is turned off.
- The order receipt e-mail (`submit_order.php`) carries both copies as HTML attachments (`pg_legal_documents_attachments()`, names like `mesafeli-satis-sozlesmesi-1173.html`); they travel through the mail queue as base64.
- Panel order view (`view_order.php`, left column under Affiliate): `pg_legal_documents_panel_card()` lists each document, its acceptance time or "Not accepted", the IP as title, a link to the copy. No rows, no card.

## How does the API read them?
- `GET /orders/{id}/documents` (`orders:read`, `api_orders_documents()`, object `OrderDocument`): one page, every `order_documents` row of the order in id order, each with `type`, `label` (null for a type `pg_legal_document_types()` does not name yet, such as `kvkk`), `accepted` + `accepted_at`, `ip`, `user_agent`, `created_at` and the stored `html`. Missing table or no rows → empty list, never an error; the switch being off does not hide kept copies. `?fields=` drops the HTML. The MCP tool comes with the route.

## Where is the checkout side?
- docs/memory/commerce/checkout-legal-checkboxes.md

## What is left?
- docs/memory/plans/checkout-legal-documents.md (designer bindings, My Account link, API, KVKK).
