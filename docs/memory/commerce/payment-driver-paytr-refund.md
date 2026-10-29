---
name: payment-driver-paytr-refund
description: PayTR refund API (PR #3055): the order screen's refund through /odeme/iade, the hash, decimal return_amount, our reference_no, refusal vs unclear answers
---

## What is sent?
- `refund` (capability `partial_refund`), from the order screen's Refund (`payment-refunds.md`): `pg_payment_paytr_refund_with()` posts to `https://www.paytr.com/odeme/iade` merchant_id, merchant_oid (the order's `transaction_id`, `pg_payment_paytr_oid_valid()`), `return_amount` = decimal text with a dot (`pg_payment_decimal()` of the rounded hundredths: PayTR's refund API takes "11.97", not kuruş), `reference_no` = `PG<order id>R<time>`, `paytr_token` = base64 HMAC-SHA256 (key) of merchant_id + merchant_oid + return_amount + salt (`pg_payment_paytr_refund_hash()`).
## How is the answer read?
- `status=success` → `refunded`, `amount` = PayTR's `return_amount` in hundredths, `refund_reference` = our reference_no (PayTR has no refund id). `error`/`failed` → refusal with `err_msg` (code in `raw.code`). No connection (cURL errno in `pg_payment_paytr_curl_unsent_codes()`) → refusal; a timeout or an answer that is not JSON → `unclear` (`payment-unclear-answers.md`).
- No `cancel`: a cancelled PayTR order is not voided automatically (REFUND ACTION REQUIRED).

## How was it verified?
- `tests/payment_paytr_test.php` (fixed refund hash vector, sent fields, success, PayTR's error 006, failed, unsent and unclear answers). No live or test PayTR call; the format follows PayTR's documentation (dev.paytr.com/iade-api).

Related: commerce/payment-driver-paytr.md, commerce/payment-refunds.md, commerce/payment-unclear-answers.md
