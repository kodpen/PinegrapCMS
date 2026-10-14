---
name: order-payment-method-normalization
description: submit_order.php payment_method normalization to the four constants, the Pay With Iyzico ENUM addition (4.48) and the old Turkish layout cause.
---

## How is `payment_method` normalized in `submit_order.php`?
- The submitted value must be one of four constants: `Credit/Debit Card`, `PayPal Express Checkout`, `Offline Payment`, `Pay With Iyzico`.
- If it is not and it matches `lang($constant)` (trim, case-insensitive), it is converted to the constant with `assign_field_value`; if it matches nothing: error `Please select a payment method.`.
- Reason: old Turkish layouts (`data/backups/turkish_default/layouts/101.php`, `1077.php`) posted the radio value as the translated label; they now post the constant.

## Why was `Pay With Iyzico` added to the ENUM in 4.48?
- A value outside the ENUM becomes `''` on a connection with strict mode off.
- The step reads the current list with `install_column_info` and appends to the end, keeping `Null`/`Default`; if the column is not an ENUM the step is skipped.

Related: docs/memory/erp/erp-awaiting-transfer-orders.md
