---
name: custom-form-logic
description: The designed forms' Shown-when rules, steps with progress and payment step as one feature: what binds all three, which questions were decided (#5035), where each part is described
---

## What is it?
- Designed forms (visual designer, `custom_form.php`) can show a field only for an answer, run in steps with progress, and take a fee as their last step. Built in #5932, #6011, #6128, #6129, #6130, #6367, #6368, #6406, #6467, #6494; changelog line `[NEW] Added conditional fields, multi-step forms and a payment step to the visual designer's forms`.

## What binds all three?
- Designer-only: the legacy form renderers (`includes/fn/content.php`) are untouched.
- A form without rules, step breaks or a fee renders byte for byte as before (the parity tests in `custom_form_shown_when_test.php` and `custom_form_steps_test.php`); a form without a fee gets no payment step.
- A control change goes to both twins of the field read (`_pg_cf_controls()` and `_cfFieldsOf()`; types `_pg_cf_type_by_input()` and `CF_TYPE_BY_INPUT`); a new palette control goes into `CF_PALETTE`, which has no PHP twin. Then `php tools/build_js.php` and `system-widget-change-checklist.md`.

## Which questions were decided? (#5035)
1. A hidden field's value is dropped and its checks skipped on the server, not only in the browser (`custom-form-shown-when.md`).
2. The fee is a one-line order of a product, with no `form_payments` table. The record points at the order (`forms.order_id`, `paid_at`; schema in `workflow/decisions-made-schema-9.md`, #6129), and the design templates ship hidden fee products (`design-template-form-fees.md`).
3. Owner: an unpaid record is saved, listed with an "Unpaid" badge, and gets no confirmation mail until paid (`custom-form-mails.md`).

## Where is each part?
- Rules: `custom-form-field-rules.md`, `custom-form-shown-when.md`. Steps: `custom-form-steps.md`.
- Fee: `custom-form-payment.md` (setting, amount, order), `custom-form-payment-step.md` (the site), `custom-form-payment-staff.md` (staff screens, API, `form.paid`), `custom-form-mails.md`.
