---
name: designer-form-logic
description: Plan (owner 2026-11-02): designed forms get Shown-when rules, steps with progress, and a payment step that places a one-line order through the payment drivers; steps FL1-FL8
---

## What is wrong now?
- A designed form (`custom_form` widget, `designer/custom-form-model.md`) shows every field at once. The only rules are `form_fields.rules` with `min_date` and `required_when` (step 11.0.135, `designer/custom-form-field-rules.md`), checked by `pg_cf_check_rules()` on the site and switched in the browser by `pg_cf_validation_script()`; nothing hides a field by another field's answer, so a form with branches ("Are you a company?") shows every branch to everyone.
- A long form is one page: an application with forty fields or a registration with sections scrolls on; nothing says how far the visitor is.
- A form cannot take money. A registration fee, a deposit, a donation, a paid application: the templates send the visitor to a product page or ask for a bank transfer by hand. The payment drivers (`includes/payments/driver.php`) only ever see an order.

## What is proposed?
- `rules.shown_when = {"field": <name>, "answers": [...]}` beside `required_when`, same shape, same designer row pattern; the browser hides and disables the control (so it is neither validated nor posted), the server drops a hidden field's value and does not require it; everything else (collections, uploads, contact fields) unchanged.
- Steps: a "Step" break in the form palette; the renderer wraps the controls between breaks in `<fieldset>`s shown one at a time with Back, Next, a progress line and the step's own validation before Next; one `<form>`, one post, the server as today. A browser draft keeps the answers through a reload until sent.
- A payment step: the form's settings name a fee product (any product; the templates ship hidden ones), the amount from the product's price or from a field (bounded), the currency the site's. On submit the record is saved as unpaid, a one-line order is written the way `pg_subscription_renewal_order_write()` writes a renewal, charged through `pg_payment_call()` with the driver's own browser part, completed by `pg_order_complete()`, which marks the record paid, writes the order number into its system fields and sends the confirmation. Offline payment (bank transfer) is a choice when the site allows it, as at checkout. Refunds, receipts, the order screen, invoices and reports come from the order; nothing in the checkout totals changes.

## Which decisions bind it?
- Legacy forms (`includes/fn/content.php` renderers) are untouched; the three features are designer-only. A form without rules, breaks or a fee renders byte for byte as today (parity tests).
- Decision #2450 ("a value condition never hides a required control") is a widget rule; question 1 settles the form case.
- Texts through `tr_add.php`; the designer twin lists (`_pg_cf_controls()`, `CF_PALETTE`), `php tools/build_js.php`, `designer/system-widget-change-checklist.md`.

## Which questions are open? (architect; 1 before FL1, 2-3 before FL4)
1. A field hidden by `shown_when` is not required for that submission and its posted value is dropped (recommended), or hiding a required field is refused in the designer as #2450 does for widgets.
2. The fee is an order of a product (recommended: everything downstream exists), not a `form_payments` table; the fee product may be any product, and the templates add hidden `fee` products in their install dumps.
3. An unpaid record is listed to staff with an "Unpaid" badge and gets no confirmation mail until paid (recommended), or is not saved until the payment succeeds.

## Where does it start?
- FL1 and FL2 start together (question 1 first); FL4 after question 2-3. Read `designer/README.md` key rules, `designer/custom-form-field-rules.md`, `custom-form-model.md`, `custom-form-render.md`, `designer-form-palette-and-fields.md`, `commerce/subscriptions-renewal.md`, `includes/payments/driver.php`. Steps: `designer-form-logic-steps.md`, `-steps-2.md`.
