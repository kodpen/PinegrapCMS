---
name: custom-form-payment-step
description: The payment step of a designed form on the site (FL5, #6368): choices, pg_payment_box(), submit, the fee order's completion, 3-D Secure return, the payment alone on retry
module: form_payment_step
---

## How does the site take the payment?
- Module `includes/fn/form_payment_step.php`. The renderer calls `pg_form_payment_render()` after the liveform: `step` → `pg_cf_insert_payment_step()` puts the step before the submit (and a CAPTCHA right before it), or at the end; in a stepped form whose stepped container holds the submit a "Payment" break goes first, so payment is the last step. `replace` → the payment alone (below).
- Choices (`pg_form_payment_methods_decide()`): the card only through a hosted card driver (`pg_payment_hosted_card_driver()`, Stripe), the redirect drivers switched on (PayTR, Pay With iyzico, PayPal v2), offline when the store and the form allow it. Raw card inputs and the frozen legacy gateways are never offered. One choice is a hidden `payment_method`.
- The booking widget's deposit uses the same markup (`offline_note` replaces the offline line) and charge path: `commerce/booking-deposit-payment.md`.
- `pg_payment_box($driver, $prepare)` (payments.php) is the card box from `prepare()`; `_eo_hosted_card_html()` uses it too. `payment_hosted.js` shows it while the card is chosen.
- Submit: `pg_form_payment_check()` before anything is stored (amount refused → the amount field marked; a choice not offered → `payment_method` marked); no submitter or administrator mail for a record owing a fee (sent when it is paid, `custom-form-mails.md`); after the record and its contact, `pg_form_payment_start()` writes the order and `pg_form_payment_charge()` calls `authorize()` (lines from `pg_order_lines_sql()`, `basket_id` = form page, `return_url` = `custom_form.php?mode=<return mode>&page_id=`).
- `pg_form_payment_answer_outcome()`: paid/authorized/pending → `pg_form_payment_finish()`: `pg_form_payment_settle_query()` (method, payment id, subtotal = total) then `pg_order_complete_from_payment()`, the webhook's own path (plain receipt, no checkout page). Redirect → the settle query first, so the callback finds the order. Offline → completed, unpaid.
- Session `software.form_payment.<page id>` holds record, order, reference, send_to, driver, state, the last error. Return mode is taken in `custom_form.php` before the token check (`pg_form_payment_return()`, `complete($order, $total, $_POST, $state)`).
- Refused: the visitor goes back to the form page, which shows only the payment (`pg_form_payment_retry_html()`: amount from the order, the reason once, Pay, "Start a new submission"); `pg_form_payment_retry()` charges the same order. An order with a payment id shows "If you have just paid…". Paid elsewhere → the confirmation message.
- Paid: the visitor goes to the form's confirmation (`pg_form_payment_confirmation_url()`; the alternative page for a contact group is not applied there).

## Tested?
- `tests/form_payment_step_test.php`: choices, the step's markup per choice, where it goes, the settle query, the answer outcome, `pg_payment_box()`, the wiring. No browser or Stripe test-key run.

Related: docs/memory/designer/custom-form-payment.md, docs/memory/commerce/payment-callback.md
