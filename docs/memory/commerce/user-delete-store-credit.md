---
name: user-delete-store-credit
description: Deleting a user account voids its open store credit (note "Account deleted", audit revoke) and releases the closed cards; a card that lost the void race stays bound (#9016)
module: store_credit
---

## What happens to store credit when a user is deleted?
- `pg_user_delete()` (`includes/fn/user_write.php`), used by the Users list's bulk delete, Edit User's Delete and the API's user delete, calls `pg_credit_forget_user()` (`includes/fn/store_credit.php`) before the `user` row goes.
- Every card with a balance is voided through `pg_credit_void()` with the reason "Account deleted": balance 0, the note line, audit `revoke` on `store_credit`. Then cards at 0 get `user_id = 0`, since MySQL may hand the deleted id to a new account.
- A card whose void lost a race with a spend keeps its balance and its `user_id`: clearing it would make a plain gift card that pays for anyone holding the code.

## Why?
- Before, the delete left the card open with the deleted id. Edit Gift Card shows no Delete for a customer's card (#6688) and Edit User answered 404, so the only way left was typing 0 into Balance, with no void note and no audit line (#9016).
- Same treatment as erasure (`api/api-customer-credit.md`): void, then clear the account.

## What is not covered?
- Cards already left behind by a delete before this change: still open with the old id; Edit Gift Card offers no void for them.
- The delete confirmation does not mention open credit.

## Tests?
- `tests/store_credit_user_delete_test.php`.
