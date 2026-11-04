---
name: designer-form-logic
description: Plan (owner 2026-11-02): designed forms get Shown-when rules, steps with progress, and a payment step that places a one-line order through the payment drivers; steps FL1-FL8
---

## What is wrong now?
- Shown-when rules (`designer/custom-form-shown-when.md`) and steps with progress (`designer/custom-form-steps.md`) are built.
- A form cannot take money. A registration fee, a deposit, a donation, a paid application: the templates send the visitor to a product page or ask for a bank transfer by hand. The payment drivers (`includes/payments/driver.php`) only ever see an order.

## What is proposed?
- A payment step: the form's settings name a fee product (any product; the templates ship hidden ones), the amount from the product's price or from a field (bounded), the currency the site's. On submit the record is saved as unpaid, a one-line order is written the way `pg_subscription_renewal_order_write()` writes a renewal, charged through `pg_payment_call()` with the driver's own browser part, completed by `pg_order_complete()`, which marks the record paid, writes the order number into its system fields and sends the confirmation. Offline payment (bank transfer) is a choice when the site allows it, as at checkout. Refunds, receipts, the order screen, invoices and reports come from the order; nothing in the checkout totals changes.

## Which decisions bind it?
- Legacy forms (`includes/fn/content.php` renderers) are untouched; the three features are designer-only. A form without rules, breaks or a fee renders byte for byte as today (parity tests).
- Texts through `tr_add.php`; the designer twin lists (`_pg_cf_controls()`, `CF_PALETTE`), `php tools/build_js.php`, `designer/system-widget-change-checklist.md`.

## Which questions are decided? (#5035)
2. The fee is a one-line order of a product; no `form_payments` table; the templates ship hidden fee products in their install dumps.
3. Owner: an unpaid record is saved, listed with an "Unpaid" badge, no confirmation mail until paid.

## Where does it start?
- FL5b, FL6b and FL7 are next, FL8 last. Read `designer/README.md` key rules, `designer/custom-form-field-rules.md`, `custom-form-model.md`, `custom-form-render.md`, `designer-form-palette-and-fields.md`, `commerce/subscriptions-renewal.md`, `includes/payments/driver.php`. Steps: `designer-form-logic-steps.md`, `-steps-2.md`.
