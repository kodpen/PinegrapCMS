---
name: order-legal-documents
description: Checkout legal documents (pre-information form, distance sales contract): templates and switch in Settings > Ecommerce, ^^field^^ renderer, order_documents copy written by submit_order.php
---

## What exists today?
- Settings › Ecommerce › Legal Documents (`pgset-legal`, `includes/settings/commerce.php`): switch `config.ecommerce_legal_documents` (constant `ECOMMERCE_LEGAL_DOCUMENTS`, bool) and two HTML templates `ecommerce_preinfo_template`, `ecommerce_contract_template` (TEXT). Schema step 11.0.1 (`upgrade_2026_11_0_legal_documents()`, PR #536). The card is drawn and saved only when the column exists (upgrade bridge).
- `includes/fn/legal_documents.php`: pure `pg_legal_document_render()`, `pg_legal_document_values()`, `pg_legal_document_lines_html()`, `pg_legal_document_unknown_placeholders()` (tests in `tests/legal_documents_test.php`); DB side `pg_legal_documents_for_order($order_id)` and `pg_legal_documents_store($order_id, $accepted_types)`.
- `submit_order.php` calls the store right after the order number is written (before `order.created`); it reads the order only, totals untouched. Accepted types come from liveform fields `legal_preinfo` / `legal_contract` (value 1). The checkout checkboxes that post them are not built yet, so today every copy is stored with `accepted_at` NULL.

## How does a template work?
- Same syntax as e-mail merge fields: `^^field^^`, `[[text ^^field^^ text]]` (dropped when the field is empty), `[[...||other]]`. Values are escaped and line breaks become `<br>`; only `^^order_lines^^` carries markup (table built with escaped values).
- Field list: `pg_legal_document_placeholders()`; the card shows it. A save naming an unknown field (or over 65535 bytes) is refused with `mark_error` before anything in the Ecommerce category is written (settings-invalid-vs-error).
- Built-in templates: `includes/legal_documents/<type>.<lang>.html` (tr, en; English fallback). The card shows the built-in text when nothing is stored; saving it unchanged stores NULL (CRLF normalised), so an improved built-in reaches stores that never edited it.
- Seller block comes from settings, not the template: `ORGANIZATION_*`, `ECOMMERCE_EMAIL_ADDRESS` (else `EMAIL_ADDRESS`), `ERP_SELLER_VKN`, `ERP_SELLER_TAX_OFFICE`, `ERP_WEB_ADDRESS`. MERSİS and the return carrier have no field: the merchant writes them into the template.

## What does order_documents hold?
- One row per type per order: `type` ENUM('preinfo','contract','kvkk'), `html` as rendered, `accepted_at` (unix, NULL = not accepted), `ip`, `user_agent`, `created_at`. A second store call for an order that already has rows writes nothing: the copy is the one from the moment of the order.
- Amounts are formatted with `prepare_price_for_output(..., 'plain_text')` in the visitor's currency, entity symbols decoded (`&#8378;` printed as `&amp;#8378;` before that fix).

## What is left?
- See docs/memory/plans/checkout-legal-documents.md (express order checkboxes and modal, legacy preview step, receipt e-mail, My Account / panel view, API route, KVKK consent).
