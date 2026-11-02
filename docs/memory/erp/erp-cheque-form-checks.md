---
name: erp-cheque-form-checks
description: Cheque and note form and step checks (PR #5636): the amount limit, the step reason in the notes, the full status filter, required and labelled fields, action-named step buttons.
---

## How large may a cheque's amount be? (#5569, PR #5636)
- At most `ERP_CHEQUE_MAX_AMOUNT` (99,999,999,999,999 minor units, just under a trillion). `erp_cheque_amount_refusal()` is asked in `erp_cheque_create()` before the `(int)` cast, so the float that `erp_kurus()` returns for a figure past `PHP_INT_MAX` gets "The amount is too large." on the Amount field. Before the fix, it logged a cast warning and became 0, reported as "Enter an amount greater than zero."
- The screen's Amount box takes 15 characters, like the expense and receipt forms.

## Where does a step's reason go? (#5570, PR #5636)
- Into the cheque's notes as `<status label>: <reason>`, after the old notes with ` · ` between them (`erp_cheque_notes_with_reason()`). When the result would pass the 255-character column, the end of the old notes is cut and marked with `…`, so the reason (at most 150 characters) is always kept whole. Before the fix, `CONCAT` added it with no label, and the column silently dropped it once the notes were long.

## Which statuses does the list filter offer? (#5578, PR #5636)
- Open, All and every status in `erp_cheque_statuses()`. An unknown `?status=` value falls back to Open on the screen, so the filter shown and the list match.

## How are the form fields named and checked? (#5579, #5580, PR #5636)
- The new-cheque form marks Account, Amount, Date, Due Date and Kept in as `required`. An empty account is refused in `erp_cheque_create()` on `account_id`, before the receipt is posted, so the Account box is marked.
- The step forms repeat their fields, so each id includes the step (`erp_<step>_date`, `erp_<step>_bank_till_id`, `erp_bounce_reason`), with a `label for` pointing at it. The date input also has an `aria-label`, because the text box that `pg_fields.js` draws next to it takes its name only from that attribute.
- The step buttons name the action (Hand to the bank, Mark as collected, Mark as bounced, Mark as paid). They are separate keys from the status labels: reusing the status words made a button read as if the step had already been taken.
