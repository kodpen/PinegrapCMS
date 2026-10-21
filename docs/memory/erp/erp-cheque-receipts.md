---
name: erp-cheque-receipts
description: Why a cheque's or note's receipt is not cancelled on its own (screen, erp_receipt_cancel(), API 409 receipt_belongs_to_cheque), and what to do with a cheque entered by mistake
---

## Which receipts belong to a cheque or note?
- `erp_cheques.open_cash_id`: the collection that took a customer's cheque into the portfolio, or the payment that gave one to a supplier. `close_cash_id`: the payment that passed it on (endorse) or sent it back to the customer (bounce), or the transfer of a collect or pay step. `erp_cheque_for_cash($cash_id)` (includes/erp/cheques.php) finds the cheque of a movement.

## Why are they not cancelled on their own? (#1062, PR #1130)
- `erp_receipt_cancel()` reversed any collection or payment, and the cheque stayed where it was: its receipt taken back, it was still "In the portfolio". Its next step then moved the money a second time: "Bounced" made the customer owe it again on top, "Collected" moved money out of an empty portfolio.
- Now `erp_receipt_cancel()` refuses such a movement with `erp_cheque_receipt_refusal()` ("This movement is a step of Cheque A-1062, which changes only by its own steps on its screen …"), before anything is written. `POST /erp/receipts/{id}/cancel` answers 409 `receipt_belongs_to_cheque` with the same sentence. The receipt screen (`erp_receipt.php`) shows the sentence and an "Open the cheque or note" link instead of the cancel button.
- Checked on the sandbox inside a rolled-back transaction: the cheque's receipt and its bounce payment were refused, a plain receipt was still cancelled.

## What about a cheque entered by mistake?
- There is no cancel step: `erp_cheques.status` is an ENUM without one (a schema step, the owner's decision). Bouncing it sends the money back to the customer, which nets the ledger out; the cheque then reads "Bounced".

Related: docs/memory/erp/erp-receipt-corrections-and-gift-card.md
