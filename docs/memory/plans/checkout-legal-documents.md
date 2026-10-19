---
name: checkout-legal-documents
description: Plan (approved 2026-10-19; checkout, designer bindings, kept copy and API built): pre-information form and distance sales contract; left: My Account link, KVKK consent, Turkish seed
---

## Why?
- Turkish distance-selling rules (Mesafeli Sözleşmeler Yönetmeliği, consolidated text in force 1 Jan 2026) require a pre-information form and a contract with the seller, buyer, goods, total price with taxes, delivery, the 14-day withdrawal right and the named return carrier. Today the express order widget shows only a fixed "Order Terms" modal (`includes/fn/widgets_express_order.php:1561`) and a terms-page link; no copy is bound to the order. Turkish SaaS competitors generate both documents from placeholders and keep a copy on the order.

## What is decided?
- Templates live in Settings › Ecommerce as two HTML templates with `^^field^^` placeholders in the mail-merge style. Seller name, address, e-mail, VKN and tax office come from existing settings; MERSİS, return carrier and withdrawal address are written into the template by the merchant (no columns).
- Checkout shows one required checkbox per document (pre-information read, contract accepted) before the payment step; the rendered documents open in a modal and are stored with the order at submit time, so a later template edit never changes an accepted copy.
- New table `order_documents` (order_id, type `preinfo|contract|kvkk`, html, accepted_at, ip, user_agent); created only by a 2026.11.0 migration step.
- The accepted documents are attached to the order receipt e-mail as HTML and shown under My Account › order view and in the panel order view. They are available over the API as `GET /orders/{id}/documents`.
- Registration and checkout gain a KVKK notice (aydınlatma) link and an optional explicit-consent checkbox whose text is a setting; the answer is stored in the same table (`kvkk`).
- The legacy multi-step checkout (`billing_information.php` → `order_preview.php`) gets the same checkboxes on the preview step, behind the same setting; nothing in its totals or flow changes.
- Switch: `ECOMMERCE_LEGAL_DOCUMENTS` (off by default on upgrade, on for new Turkish installs). Sites outside Turkey can use the same mechanism for their own terms.

## What remains?
1. Done: settings, templates, `order_documents`, checkboxes on all three checkouts, cart page, server check, `[NEW]` line (docs/memory/commerce/order-legal-documents.md, checkout-legal-checkboxes.md).
2. Done: designer bindings (`legal_preinfo` / `legal_contract` in the field list and prefill, `has_legal_documents` flag, `__legal_*_url` href tokens, both boxes in the PHP and JS starter trees, PR #570; docs/memory/designer/express-order-legal-documents.md).
3. Done: receipt attachments, panel card, kept-copy page, `GET /orders/{id}/documents` (`OrderDocument`, docs/memory/commerce/order-legal-documents.md). Left: a link in My Account's order view (`order_view` widget, designer session; `legal_document.php?order_id=N&type=...` is ready).
4. KVKK notice link and optional consent checkbox (text setting: needs its own config column, ask first), stored as type `kvkk`.
5. Template seed for Turkish sites on install and the withdrawal timer shown on the order (14 days from delivery, see `customer-returns-flow.md`).
6. Optional: the document page reads what the visitor has typed but not yet submitted (a modal posting the form's fields) instead of the values the page was drawn with.

## Open questions?
- Should the contract PDF be produced (dompdf is already vendored for ERP) or is HTML enough? Start with HTML; PDF later if customers ask.

## Where to start?
- Step 4 (KVKK): ask about the config column for the consent text first. The My Account link is the designer session's. Express-order totals stay untouchable: the checkout part only wraps the renderers' output.
