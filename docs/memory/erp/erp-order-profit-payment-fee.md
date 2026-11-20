---
name: erp-order-profit-payment-fee
description: The fee a payment service kept (#10655): pg_payment_answer()'s fee keys from iyzico, Stripe and PayPal, the conversion, the gateway cost row written by submit_order.php
---

## Where does a payment fee come from?
- A driver's success answer: `fee` (hundredths of `fee_currency`, 0 or more), `fee_currency` ('' = the order's own), `fee_rate` (order currency per unit of the fee's, as the payment itself was converted; 0 = none). `pg_payment_answer_fee()` keeps only that shape; a failure or an unreadable success has `fee` null. Drivers build them with `pg_payment_fee()` (`includes/payments/driver.php`).
- iyzico (`pg_payment_iyzico_fee()`): `iyziCommissionRateAmount` + `iyziCommissionFee` of the payment answer, direct, 3-D Secure return and Pay With Iyzico alike. `merchantCommissionRateAmount` (the instalment charge the buyer paid) is revenue, not a fee. In the protected currency: the fee in that currency, rate = order total / `paidPrice`.
- Stripe (`pg_payment_stripe_fee()`): `authorize()` and `complete()` expand `latest_charge.balance_transaction`; a `succeeded` intent answers its `fee`. In another settlement currency, rate = intent amount / balance `amount`. A held (`requires_capture`) or `processing` payment has none.
- PayPal (`pg_payment_paypal_fee()`): a COMPLETED capture's `seller_receivable_breakdown.paypal_fee`; a PENDING capture has none. A fee in another currency than the gross is given with no rate.
- PayTR and the classic gateways give no fee: the payment method's typed rate stays.

## How is it written?
- `submit_order.php`, after `pg_order_complete()` placed the order, when a driver took the payment and `fee` is not null: `pg_payment_fee_record()` (`includes/fn/payments.php`). Accounting off (`ERP_ENABLED`): nothing is loaded, read or written.
- `erp_order_profit_record_fee()` (`includes/erp/order_profit.php`): one `erp_order_costs` row, kind `payment`, source `gateway`, `source_ref` `gateway` (`ERP_ORDER_PROFIT_GATEWAY_REF`, one per order; a repeated checkout replaces the amount), then `erp_order_profit_mark()`. The pass keeps it and drops the method's rate row (`erp-order-profit-record.md`).
- Conversion only in `erp_order_profit_fee_amount()`: the order's currency (`erp_base_currency()`) as is; else `erp_to_base()` (round) at the answer's `fee_rate`, else at the day's rate (`erp_fx_rate_for()`); no rate: no row, a Site Log line, the typed rate stays.
- No receipt, till, bank or account move (architect, channel message 10189).

## What is not covered?
- A payment completed by the service's own message (`order-completion-from-payment.md`, the webhooks) writes no fee: only the checkout's answer does. A refund does not lower the fee.
- Verified on recorded answers only (`tests/payment_*_test.php`, `tests/erp_order_profit_test.php`); no live or sandbox payment was run.
