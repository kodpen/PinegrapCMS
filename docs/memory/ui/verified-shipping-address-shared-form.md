---
name: verified-shipping-address-shared-form
description: The verified shipping address create and edit screens share includes/forms/verified_shipping_address_form.php: what it holds, what stays in the screens
---

## Where is the verified shipping address form?
- `includes/forms/verified_shipping_address_form.php` (gate `PG_FUNCTIONS_DIR`). `add_verified_shipping_address.php` and `edit_verified_shipping_address.php` stay at the root as thin wrappers and `require_once` it.
- `pg_verified_shipping_address_form_card($liveform, $status_rows = '')` prints the form column (the Verified Shipping Address card: company, address 1 and 2, city, the state combobox, zip code) and the sticky status column (B11, `ui/edit-screen-wide-layout.md`): a Summary printing the address as it reads (`pg_verified_shipping_address_form_preview()`, pure, "city, state zip" last, empty parts left out; a script redraws it as the fields change, the state through jQuery `change`), then the screen's rows (`…_status_row()`): edit adds Created and Last Modified with the user. `…_layout($cards, $buttons)` adds the sticky save bar.
- `…_state_options($states, $current_state_id)` builds the combobox options from `get_state_options()` (its labels come escaped) with the current state selected.

## What does a save share?
- `pg_verified_shipping_address_form_validate($liveform)`: address 1, city, state and zip code are required.
- `pg_verified_shipping_address_form_values($liveform)`: the six columns as column => value in INSERT/UPDATE order; the screens escape them into their raw `db()` query.
- `pg_verified_shipping_address_form_name($company, $address_1)`: the name the activity log shows, the company or else address 1.

## What stays in the screens?
- Create: the state preselected from `?state_id=`, the INSERT with created/modified user and time.
- Edit: the not-found guard, the record load, the hidden id, delete, the UPDATE.
- Tests: `tests/verified_shipping_address_form_test.php`.
