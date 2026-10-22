---
name: payment-unclear-answers
description: The 'unclear' flag of a payment driver's answer (no answer in time, unreadable body, a thrown driver), how the iyzico refund sets it, and why a return's refund stays refunded on it (#970, PR #PRNUM)
---

## What is an unclear answer?
- `pg_payment_answer()` carries `unclear` (bool): a failure that does not say whether the service did the operation. False on every success and on a plain refusal.
- Set by the driver (`'unclear' => true` in its answer, or `pg_payment_unclear($error, $raw)`), by `pg_payment_answer()` when a success has a status the operation cannot have, and by `pg_payment_call()` when the driver throws (message still "The payment service could not be reached.", `raw.exception`).
- A caller that would offer the same operation again (a second refund) must not do so on `unclear`.

## How does the iyzico refund tell them apart?
- `pg_payment_iyzico_refund_answer($amount, $readable, $status, $error_code, $error_message, $refund_id)` (pure): `success` → refunded; `errorCode` `curl_<n>` (the body `Iyzipay\Curl::exec()` makes of a timeout or dropped connection) or a body without a status → unclear; a body that is not JSON (`AmountBaseRefund::create()` throws `TypeError` in the SDK's mapper, e.g. a gateway's 502 page) → unclear with "cannot be read"; any other status → a refusal with iyzico's words.
- Unclear errors end in "Check the payment on the service's own screen before trying again.", so the order screen's refund (view_order.php, unchanged) shows the warning too.

## What does a return's refund do with it? (pg_return_refund())
- Refusal: the return goes back to `received`, and a log line names the return, order, amount, service and its words (`pg_return_refund_refused_line()`).
- Unclear: the return stays `refunded`, `orders.refunded_amount` and `order_refunds` are written (notes `Return request #N (unconfirmed: no clear answer from <service>)`, no reference), the audit row is written, no mail, no ERP hand-off; `ok` is false with `unclear` true and the screen says to check the service's screen and make the refund there if it is missing (`pg_return_refund_unclear_lines()`, also the log line).
- Why booked: whichever way the check goes (refund there, or made there by hand), the books are right; left unbooked they were wrong both ways and the order kept money "left" for a second refund.

## What was the symptom? (#970)
- A timeout or a 502 reopened the return to `received`; the next click sent a second AmountBaseRefund for the same goods (three clicks, three requests to iyzico, one log line). The 502 said "could not be reached" although something answered.

## Tests?
- `tests/payment_unclear_answer_test.php` (stand-in HTTP client returning raw bodies). On PHP 8.5 the iyzico SDK classes must be loaded with deprecations masked before the runner, or every driver test fails before its request.

Related: docs/memory/commerce/order-returns-refund.md, docs/memory/commerce/payment-refunds.md, docs/memory/commerce/payment-drivers.md
