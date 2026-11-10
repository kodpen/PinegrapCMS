---
name: payment-iyzico-refusals
description: iyzico card refusals (PR #8517): the visitor sentence by error code, the Site Log line with order, payment id, error group and mdStatus, the 3-D Secure return stopping at a failed verification
---

## What does the visitor read when iyzico refuses a card payment?
- `pg_payment_iyzico_refusal_sentence()` (includes/payments/iyzico.php) picks by iyzico's `errorCode` (`pg_payment_iyzico_refusal_kind()`), on the direct payment and on the 3-D Secure return.
- `card`, a reason on the card's side (`pg_payment_iyzico_card_side_codes()`: 10051 no funds, 10093 closed to online shopping, 10084 wrong CVC, 10054 expired, 10217 debit card needs 3-D Secure…): "Your bank did not approve the payment (iyzico's message). Please check your card with your bank or try another card." iyzico words the message in the payment's locale; with none the brackets are left out.
- `other`, any other bank code (five digits from 10000: 10202 unknown, 10058 terminal, 10214 system error): "The payment could not be taken with this card right now. Please try another card or contact us."
- `plain`: a lost or stolen card and a fraud suspicion (10041, 10043, 10034), and a code that is not a bank's. The sentence stays "Payment could not be verified. Please try again." on the return and "Payment failed. Please try again." on the direct payment.
- A code joins the card-side list only when iyzico's error list says the cardholder settles it; an unlisted code is `other`, which is never wrong.
- Pay With Iyzico and the stored-card renewals keep their own sentences.

## Why does the sentence follow the code?
- Owner (2026-11-10): a refusal is told by its code as above. "Payment could not be verified. Please try again." sent the visitor back to the same card, and an `other` code must not send them to their bank.
- The symptom: a visitor's debit card was refused with 10202 on the return. The iyzico panel showed the POS bank's own answer, 0962 "virtual POS undefined or closed": the terminal iyzico routed the payment to, not the card.

## What does the Site Log keep?
- One line per refusal (`pg_payment_iyzico_refusal_log()`): order number, code, error group, message, payment id, conversation id, and on the return the posted mdStatus. An empty value is `-`.
- An `other` code adds a second line: the bank's own answer is on the payment's detail in the iyzico panel. iyzico's answer to the request does not carry it, so the payment id in the first line is what finds it.

## What does the 3-D Secure return do with a failed verification?
- iyzico posts `status`, `mdStatus` and `signature` beside `paymentId`. A `status` other than success (in any case) on the session's own row: iyzico is not asked, the row is not claimed, the session state ends, the visitor reads "3D Secure verification failed. Please try again.".
- Its log line gives the status, the mdStatus with its meaning from iyzico's list (`pg_payment_iyzico_md_status_meaning()`), the payment id and the conversation id.
- No `status` posted, or success with another mdStatus (4, a half verification): iyzico is asked as before and its answer decides.
- The `signature` is not checked: a forged failure only ends the visitor's own attempt before any charge, and iyzico refuses a forged success.

## What was not verified?
- What iyzico answers to a payment asked for after a failed verification: no sandbox run (test cards 4131111111111117 and 4141111111111115 post mdStatus 0 and 4). The stop follows iyzico's documentation: a payment is taken only after a successful verification.

## Tests
- `tests/payment_iyzico_threeds_return_test.php` (kinds, sentences, log lines, the failed verification, mdStatus meanings), `tests/payment_iyzico_test.php` (the direct payment).

Related: docs/memory/commerce/payment-iyzico-threeds-return.md, docs/memory/commerce/payment-driver-iyzico.md
