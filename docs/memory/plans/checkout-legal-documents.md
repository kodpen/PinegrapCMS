---
name: checkout-legal-documents
description: Plan (approved 2026-10-19, not started): pre-information form and distance sales contract generated per order at checkout, approval before payment, stored copy, KVKK consent texts
---

## Why?
- Turkish distance-selling rules (Mesafeli Sözleşmeler Yönetmeliği, consolidated text in force 1 Jan 2026) require a pre-information form and a contract with the seller, buyer, goods, total price with taxes, delivery, the 14-day withdrawal right and the named return carrier. Today the express order widget shows only a fixed "Order Terms" modal (`includes/fn/widgets_express_order.php:1561`) and a terms-page link; no copy is bound to the order. Turkish SaaS competitors generate both documents from placeholders and keep a copy on the order.

## What is decided?
- Templates live in Settings › Ecommerce as two rich-text documents with placeholders (seller name, MERSİS/VKN, address; buyer name, address, e-mail; order lines, totals, delivery method, estimated delivery, return carrier, withdrawal address, dispute venue note). Placeholder syntax follows the mail-merge `^^field^^` style already used by e-mails.
- Checkout shows one required checkbox per document (pre-information read, contract accepted) before the payment step; the rendered documents open in a modal and are stored with the order at submit time, so a later template edit never changes an accepted copy.
- New table `order_documents` (order_id, type `preinfo|contract|kvkk`, html, accepted_at, ip, user_agent); created only by a 2026.11.0 migration step.
- The accepted documents are attached to the order receipt e-mail as HTML and shown under My Account › order view and in the panel order view. They are available over the API as `GET /orders/{id}/documents`.
- Registration and checkout gain a KVKK notice (aydınlatma) link and an optional explicit-consent checkbox whose text is a setting; the answer is stored in the same table (`kvkk`).
- The legacy multi-step checkout (`billing_information.php` → `order_preview.php`) gets the same checkboxes on the preview step, behind the same setting; nothing in its totals or flow changes.
- Switch: `ECOMMERCE_LEGAL_DOCUMENTS` (off by default on upgrade, on for new Turkish installs). Sites outside Turkey can use the same mechanism for their own terms.

## What remains?
1. Settings card, templates, placeholder renderer (`includes/fn/legal_documents.php`), test for the renderer.
2. Migration step for `order_documents`; writer called from `submit_order.php` right after the order row exists (no change to totals).
3. Express order widget: checkboxes, modal, server-side validation, bindings for the designer (`__legal_*` tokens, check with `tools/check_bindings.php`).
4. Legacy preview step, receipt e-mail attachment, My Account and panel views, API route and schema entry.
5. Template seed for Turkish sites (installer and `install_note()` for upgrades) and the withdrawal timer shown on the order (14 days from delivery, see `customer-returns-flow.md`).

## Open questions?
- Should the contract PDF be produced (dompdf is already vendored for ERP) or is HTML enough? Start with HTML; PDF later if customers ask.

## Where to start?
- Step 1 and 2 in one PR, then the widget. Read `docs/memory/commerce/express-order-*.md` and the decisions skill (express-order totals are untouchable) first.
