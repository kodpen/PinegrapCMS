---
name: erp-bank-statement-delete
description: A bank statement with no recorded or matched line deletes with its open lines; otherwise refused naming them; a file with no dated amount is refused at upload
---

## When can a statement be deleted? (#5649, PR #6372)
- When none of its lines is `recorded` or `matched`. The statement row and its `new` and `ignored` lines are deleted in one transaction (`erp_bank_statement_delete()` in `includes/erp/bank_import.php`, lines read `FOR UPDATE`).
- Otherwise it is refused and the lines are named by date and amount: "The statement cannot be deleted: these lines are recorded or matched to the books: …" (`erp_bank_statement_delete_refusal()`, pure). A recorded line wrote a till movement for it and a matched line points at one; deleting would cut the movement off from its source. Undo a match first ("Not this movement") to free a matched line.
- Why: a wrong file, or one taken into the wrong bank account, stayed in the list for good.

## Where is the button?
- `erp_bank_statement.php`, toolbar "Delete statement" with a confirm (`data-confirm-content`), `erp_action=delete`, token, cash right; the read-only accountant is refused by `validate_erp_access()` on POST and the button is not drawn. `log_activity()` "erp bank statement (#n) was deleted"; back to the list.

## Which file is refused at upload?
- One where no row is a dated amount (`erp_bank_cells_are_movement()`) — for a CSV on any separator the column screen offers (`erp_bank_read_has_movement()`). Before, a two-line text file became a statement "Columns to choose · 0".
- Tests: `tests/erp_bank_statement_delete_test.php`.
