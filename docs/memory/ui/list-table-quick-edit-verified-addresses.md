---
name: list-table-quick-edit-verified-addresses
description: All Verified Shipping Addresses list quick edit (verified_shipping_address_list_edit.php): the address parts and the state, the address screen's checks and save
module: verified_shipping_address_list_edit
---

## Which cells change?
- Every value `edit_verified_shipping_address.php` changes (`pg_verified_address_quick_edit_columns()`): Company, Address 1, Address 2, City, Zip Code (50 characters; Address 1, City and Zip Code required) and State (a pick list of the site's states, labelled like the list's state filter: "Country: State" while states of more than one country exist). Text: Country (follows the state), Created, Last Modified. `tests/verified_shipping_address_list_edit_test.php` fails for a new column that is neither.
- A row whose stored state is not among the choices (the state was deleted) keeps its State cell as text, so the field never opens on another state.
- Before, the list had no quick edit.

## Who may change them, and how is a change written?
- The screen's rights (`validate_ecommerce_access()`: role below 3 or manage commerce); `view_verified_shipping_addresses.php` reads the states and answers `pg_list_edit_request()` before its own access check, so a refusal is JSON.
- `pg_verified_address_quick_change()` (pure) puts the one change into the stored address and applies the screen's required checks with its messages; `pg_verified_address_quick_update()` writes the whole address through `pg_store_verified_address_save()` (stamps Last Modified) and logs like the screen.
- The answer redraws every other cell of the row (`pg_verified_address_quick_saved()`): the company link shows `#id` when the company is emptied, the country follows a new state. A row moved out of the state filter stays on the page until it is reloaded.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
