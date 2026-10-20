---
name: checkout-legal-documents
description: Plan (approved 2026-10-19; checkout, bindings, kept copy, API, order view links, Turkish install default built): pre-information form, distance sales contract; left: KVKK, withdrawal timer
---

## Why?
- Turkish distance-selling rules (Mesafeli Sözleşmeler Yönetmeliği, consolidated text in force 1 Jan 2026) require a pre-information form and a contract with the seller, buyer, goods, total price with taxes, delivery, the 14-day withdrawal right and the named return carrier. Turkish SaaS competitors generate both documents from placeholders and keep a copy on the order.

## What is decided?
- Templates live in Settings › Ecommerce as two HTML templates with `^^field^^` placeholders in the mail-merge style. Seller name, address, e-mail, VKN and tax office come from existing settings; MERSİS, return carrier and withdrawal address are written into the template by the merchant (no columns).
- Checkout shows one required checkbox per document (pre-information read, contract accepted) before the payment step; the rendered documents open in a modal and are stored with the order at submit time, so a later template edit never changes an accepted copy.
- New table `order_documents` (order_id, type `preinfo|contract|kvkk`, html, accepted_at, ip, user_agent); created only by a 2026.11.0 migration step.
- The accepted documents are attached to the order receipt e-mail as HTML and shown under My Account › order view and in the panel order view. They are available over the API as `GET /orders/{id}/documents`.
- Registration and checkout gain a KVKK notice (aydınlatma) link and an optional explicit-consent checkbox whose text is a setting; the answer is stored in the same table (`kvkk`).
- The legacy multi-step checkout (`billing_information.php` → `order_preview.php`) gets the same checkboxes on the preview step, behind the same setting; nothing in its totals or flow changes.
- Switch: `ECOMMERCE_LEGAL_DOCUMENTS` (off by default on upgrade, on for new Turkish installs). Sites outside Turkey can use the same mechanism for their own terms.

## What remains?
1. Done: settings, templates, `order_documents`, checkboxes on all three checkouts, cart page, server check (commerce/order-legal-documents.md, checkout-legal-checkboxes.md).
2. Done (PR #570): designer bindings (designer/express-order-legal-documents.md).
3. Done: receipt attachments, panel card, kept-copy page, `GET /orders/{id}/documents`; order view links (PR #736, commerce/order-view-legal-documents.md).
4. KVKK notice link and optional consent checkbox (text setting: needs its own config column, ask first), stored as type `kvkk`.
5. Done: a site installed from the Turkish starter starts with the switch on; its template columns stay NULL and read the built-ins (commerce/order-legal-documents.md). Left: the withdrawal timer shown on the order, 14 days from delivery (below).
6. Done (PR #648): the document link posts the typed billing fields and payment method to the document page, in its own window (commerce/checkout-legal-checkboxes.md).

## Open questions?
- Should the contract PDF be produced (dompdf is already vendored for ERP) or is HTML enough? Start with HTML; PDF later if customers ask.

## Where to start?
- Step 4 (KVKK): ask about the config column for the consent text first. Express-order totals stay untouchable: the checkout part only wraps the renderers' output.
- Step 5, timer: `pg_return_delivery_state()` gives each delivery's deadline (`pg_return_window()`, estimated until shipments keep a delivery date). The returns form shows it only while `ECOMMERCE_ORDER_RETURNS_ALLOWED` is on; the right exists either way. Likely places: an order view token in the Legal Documents card (both starter trees, `pg_legal_documents_order_view_block()`) and the panel card. Open: one date per delivery or the latest. Returns panel work (customer-returns-flow 3) may touch `view_order.php`.
