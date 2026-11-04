---
name: design-template-form-fees
description: Fees and branches of the template forms (FL7): hidden fee products in the catalog, {{product:<key>}}, the Events optional fee, Cafe deposit, Courses enrolment fee, Cafe and Clinic shown_when
---

## How does a template give a form a fee?
- The catalog's `'fees'` (key => `sku`, `title`, `description`, `price` per currency) are made by `_pg_tpl_catalog()` with the shop only: found again by SKU, else `_pg_tpl_fee_product()` (enabled, minor units of the base currency or the USD figure, not shipped, not taxed) in no product group, so no catalog page lists them. The catalog answers `products` (fee key => id).
- The page's `form` carries `'payment' => array('product_id' => '{{product:<key>}}', 'amount_source' => 'product', 'offline' => 1)`; `_pg_tpl_fill()` makes the placeholder the id (0 without the shop, so `pg_form_fee_settings_encode()` stores no fee) and Publish writes it with the other form settings (`custom-form-payment.md`).
- A template whose catalog is only fees (the Cafe) has `'catalog' => array('fees' => …)` and no groups.

## Which forms take one?
- Events 1.1.0: the registration's `registration_fee` (`EVENTS-REGISTRATION-FEE`) priced 0 in every currency. `pg_form_fee()` takes no payment for a product priced 0, so registering stays free until the operator prices the product: the fee is optional.
- Cafe 1.2.0: the catering request's `catering_deposit` (`CAFE-CATERING-DEPOSIT`, USD 50, EUR 45, GBP 40, TRY 1500).
- Courses 1.1.0: the enrolment request's `enrolment_fee` (`COURSES-ENROLMENT-FEE`, USD 20, EUR 18, GBP 16, TRY 600).
- All three allow bank transfer where the store takes offline payments; the payment step itself is `custom-form-payment-step.md`.

## Which fields are shown only for an answer?
- Cafe: the catering address keeps `required_when` and adds `shown_when` delivery = `lang('Delivered to us')`.
- Clinic 1.2.0: a new optional field "Insurance company and policy number" (`cl-appt-policy`, name `policy`) `shown_when` insurance = `lang('Private insurance')`.
- Dates already had `min_date` today (`custom-form-field-rules.md`).

## And installed sites?
- No data step: the template files are read only when a template is opened. A running site sets a fee in Custom Form Settings.

## Tested?
- `tests/design_template_form_fees_test.php`: the placeholder, the fee product row, each form naming its own fee, the prices, the two `shown_when` rules, prepare passing the products.
