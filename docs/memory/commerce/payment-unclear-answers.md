---
name: payment-unclear-answers
description: The 'unclear' flag of a payment driver's answer, how the iyzico refund sets it, why a return's refund stays refunded on it and who honours it (#970, PR #1260)
---

## What is an unclear answer?
- `pg_payment_answer()` carries `unclear` (bool): a failure that does not say whether the service did the operation. False on every success and on a plain refusal.
- Set by the driver (`'unclear' => true`, or `pg_payment_unclear($error, $raw)`), by `pg_payment_answer()` when a success has a status the operation cannot have, and by `pg_payment_call()` when the driver throws an exception (message "The payment service could not be reached.", `raw.exception`). A thrown PHP `\Error` (no curl extension, a bug in the driver) is a plain refusal: nothing was sent.

## How does the iyzico refund tell them apart?
- `pg_payment_iyzico_refund_answer()` (pure): `success` → refunded; `curl_<n>` with n in `pg_payment_iyzico_curl_unsent_codes()` (DNS, connect, TLS handshake, certificate or CA bundle: the request never left) → a plain refusal with cURL's words, so the return can be refunded again once the server is fixed; any other `curl_<n>` (28 timeout, 52 empty reply, 55/56 send/receive) or a body without a status → unclear; a body that is not JSON (the SDK's mapper throws `TypeError`, e.g. a 502 page) → unclear "cannot be read", `raw.exception` = `TypeError`, its text in `raw.message`; any other status → a refusal with iyzico's words.
- 28 stays unclear although it can be a connect timeout: telling them apart means reading cURL's English text.
- Unclear errors end in "Check the payment on the service's own screen before trying again.", so the order screen shows that warning too.

## What does a return's refund do with it? (pg_return_refund())
- A driver refund never stores the form's typed reference; only the service's refund id.
- Refusal: the return goes back to `received`; `pg_return_refund_refused_line()` logs the return, order, amount, service and what came back.
- Unclear: the return stays `refunded`, `orders.refunded_amount` and `order_refunds` are written (notes `Return request #N (unconfirmed: no clear answer from <service>)`, no reference), the audit row too; `ok` false, `unclear` true.
- Screen and log: `pg_return_refund_service_words($answer)` gives the screen words (`raw.message` only when `raw.exception` is not set) and the log detail (`raw.message` always); neither falls back to `error`, an empty one reads "no answer". `pg_return_refund_unclear_lines(…, $message, $log_detail)`. Before, the screen showed PHP's TypeError text with the server path, or nested the driver's whole sentence.
- No mail and no ERP hand-off, then or later: staff confirm the refund on the service's screen, then tell the customer and issue the return invoice by hand.
- Why booked: whichever way the check goes, the books are then right; left unbooked the order kept money "left" for a second refund.

## Who honours unclear?
- Only `pg_return_refund()`. The order screen's refund (view_order.php) only shows the warning and offers the refund again. Stripe never returns `unclear`: a timeout there is still a refusal, and `/v1/refunds` has no Idempotency-Key. Both are follow-up work.

## What was the symptom? (#970)
- A timeout or a 502 reopened the return to `received`; the next click sent a second AmountBaseRefund (three clicks, three requests, one log line).

## Tests?
- `tests/payment_unclear_answer_test.php` (stand-in HTTP client returning raw bodies or throwing `\Error` / `RuntimeException`), the screen text never carrying `.php` or the driver's nested sentence.

Related: docs/memory/commerce/order-returns-refund.md, docs/memory/commerce/payment-refunds.md, docs/memory/commerce/payment-drivers.md
