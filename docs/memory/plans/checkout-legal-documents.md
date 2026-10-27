---
name: checkout-legal-documents
description: Plan (approved 2026-10-19): pre-information form, distance sales contract, checkout KVKK built; left: designer KVKK node, order view link to the kvkk copy
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
4. Done (PR #2346): checkout KVKK notice link and optional consent box, kept as type `kvkk` (commerce/checkout-kvkk-notice.md; the notice is a page, not a template, for the config row's room). Done (PR #2397): 4b the sign-up forms' KVKK lines, consent in the consent log only. Left: 4c a designer node/token for the KVKK lines (injected meanwhile) and the order view link to the kvkk copy.
5. Done (PR #798): a site installed from the Turkish starter starts with the switch on; its template columns stay NULL and read the built-ins (commerce/order-legal-documents.md). Done (PR #816): the last day of the right of withdrawal on the order view and the panel card, the latest delivery's deadline (commerce/order-withdrawal-deadline.md).
6. Done (PR #648): the document link posts the typed billing fields and payment method to the document page, in its own window (commerce/checkout-legal-checkboxes.md).

## Open questions?
- Should the contract PDF be produced (dompdf is already vendored for ERP) or is HTML enough? Start with HTML; PDF later if customers ask.

## Where to start?
- Step 4c (KVKK): read commerce/checkout-kvkk-notice.md first. Express-order totals stay untouchable: the checkout part only wraps the renderers' output.
