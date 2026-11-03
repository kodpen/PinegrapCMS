---
name: designer-form-logic-steps-2
description: Steps FL5-FL8 of plans/designer-form-logic.md: the payment step on the site, the staff side and API, template fees, close-out; claim as [plan:designer-form-logic] FL<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `designer-form-logic-steps.md`.

- FL5 (M) The payment step on the site. After FL2 and FL4.
  - A form with a fee gets a last step drawn by the renderer: the amount line, the payment choice (the site's enabled drivers and, when allowed, offline payment), and the driver's browser part through a new `pg_payment_box($driver, $prepare)` built from what `pg_payment_<key>_prepare($order, $amount)` returns (Stripe elements, PayTR's frame, iyzico's card form); the billing information widget keeps its own markup in this plan. Submit: `custom_form.php` saves the record (unpaid), writes the order (FL4), calls `authorize`; a 3-D Secure or hosted page return comes back through `includes/payments/callback.php` as for any order, and the order's receipt page is the form's confirmation page (`products.send_to_page` of the fee product, set by the settings sheet), so the visitor lands where the form says. A failed payment keeps the record unpaid and shows the driver's reason; a retry charges the same order. Tests: the step's render with each payment choice, the return mapping. Browser try on desktop and phone with the Stripe test keys. Memory: `designer/custom-form-payment.md` grows.

- FL6 (S) Staff side and API. After FL4.
  - `view_submitted_forms.php` and `edit_submitted_form.php`: the Paid / Unpaid badge, the order link, "Record an offline payment" (marks the order paid through the order screen's own offline path, which then marks the record); the list filter Unpaid; the `forms` API resource and MCP expose `order_number` and `paid` as read-only fields (`api/api-endpoint-rules.md`); webhooks `form.paid` through `pg_announce()`. Memory: `designer/custom-form-payment.md` grows.

- FL7 (S) Templates. After FL5.
  - The Events template's registration takes an optional fee, the Cafe's catering request a deposit, the Courses' enrolment a fee: hidden fee products in the install dumps (question 2), the settings sheet values, `min_date` and `shown_when` where the Cafe and Clinic forms already branch. Installed sites get no data step. Memory: the templates' `designer/design-template-*.md` files.

- FL8 (S) Close-out. After FL1-FL7.
  - PR body names `[NEW] Added conditional fields, multi-step forms and a payment step to the visual designer's forms`; decisions to `designer/`; the three plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
