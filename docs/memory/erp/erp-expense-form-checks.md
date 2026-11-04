---
name: erp-expense-form-checks
description: Expense screen checks: non-numbers, the field a refusal marks, the paid cancel's till right, send_to, repeat end dates, refused category saves, kept dates, card-body grids, the form layout.
---

## How does the expense form read a typed amount or rate?

`erp_kurus()` and `erp_quantity_in()` drop every character that is not a
digit or separator, so `yirmi` read as 0, `-18` as a rate of 18 and `1e3`
as 13. `add_erp_expense.php` asks `erp_expense_number_typed($value, $signed)`
first (amount and tax amount signed, rate with an optional `%`) and refuses
with the field marked. The preview's `kurus()`/`rate()` read the same way
and show `—` for a value the server refuses (#5520).
The account form's opening balance asks `erp_amount_text_valid()`
(`includes/erp/money.php`: optional minus, digits, `.` `,` spaces) the
same way; before #5890 `12abc` booked 12,00 and `1e3` 13,00.

## Which field does a refused save mark?

`erp_expense_figures()` returns `field` (`amount`, `tax_rate`,
`tax_amount`) and `erp_expense_payment_refusal($expense, $payment, &$field)`
sets `payment_method`, `paid_date` or `cash_account_id`; `erp_expense_save()`
passes them on, and the API maps them through `erp_api_expense_field()`
(#5521).

## Who may cancel a paid expense on the panel?

Only a user with `manage_erp_cash`: the cancel writes money back into the
till, as the API's `erp_api_expense_cash_gate()` already required. Others see
a note instead of the cancel card; an unpaid expense's cancel needs only the
ERP right (#5523).

## Does a change keep the filtered list?

Yes: the view's Change link, the change form's address, its cancel button,
its breadcrumb and the redirect after the save carry `send_to`
(`pg_send_to_url()`), so the operator lands back on the list they came from
(#5524).

## What happens to a repeat whose end date is before its first date?

`erp_expense_recurrence_create()` refuses it with `field => repeat_end`
instead of saving a stopped row behind a "repeats from now on" notice; a new
repeat is always saved active (#5522). The new-expense form's "write the next
ones as paid" switch shows only with "Paid now", and is cleared with "pay
later", which never pays the repeats (#5526).

## What does a refused save of the categories leave behind?

Nothing: `erp_expense_categories_save()` asks
`erp_expense_categories_blank_row()` for a blank (or spaces-only) name before
any write and writes in one transaction. Before, the rows above the blank one
were saved behind a "nothing saved" error. The screen keeps the typed rows in
`$_SESSION['erp_expense_categories_sent']` and marks the refused name.

## How are invalid dates on the expense forms handled?

A repeat's "Until" on the new-expense form that is not a date is refused
before the expense is saved (it used to be dropped, so the repeat ran with
no end). On the expense view a refused Pay or Repeat prints the typed paid
date and end date back and marks the one the refusal names
(`erp_expense_pay()` returns `field`).

## Why do ERP filter cards nest a row inside card-body?

`backend.src.css` sets `.card-body { display: block }`, which beats
`.row`'s flex on a `card-body row` element and stacks every column. A grid
in a card is `<div class="card-body"><div class="row …">` (#5517).

## How is the expense form laid out?

As `erp-screen-status-column-pattern.md` asks: the cards in the form column,
a sticky "Total" card beside them with Net, tax and total (the live
`#erp_expense_preview`, or a paid expense's stored figures), the save bar
its own sticky `order-3` column. The script finds the preview inside the
form, so the status column stays inside `<form>`.
