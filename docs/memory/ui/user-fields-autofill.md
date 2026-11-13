---
name: user-fields-autofill
description: Password managers writing "admin" into Username (Create User, Edit User, Users quick edit): opt-out attributes, data-pg-own-value, quick edit saves typed text only (#9005)
---

## What was the symptom?
- Create User, Edit User and the Users list's quick edit (#9005): a password manager took Username for a sign-in name (no password field on the form) and wrote the saved one, "admin", into it: over what was typed, into an account only opened and saved, and into 7 of 13 quick edit boxes at once. Each save was refused as "already in use"; with a free name it would have renamed the account.

## What keeps it out now?
- Username and User Email carry `pg_user_form_no_autofill()` (`includes/forms/user_form.php`): `autocomplete="pg-no-autofill"` (a token Chrome does not know is respected, "off" is not) and the vendor opt-outs `data-lpignore`, `data-1p-ignore`, `data-bwignore`, `data-form-type="other"`, the set `pg_settings_stamp_autocomplete()` uses (`settings-modal-autofill-lock.md`).
- Username also carries `data-pg-own-value` (`assets/js/backend.src.js`, any panel field may use it): the person's own edits come with a trusted `beforeinput`; a value without one (browser autofill, an extension's own events) is taken back to the last own value on `input` and `change`, and a write with no event at all on submit. The first own value is the one the page brought (`defaultValue`).
- User Email goes without `data-pg-own-value`: its input mask (`data-inputmask-alias="email"`) writes the typed characters itself and may not raise `beforeinput`.

## And the list quick edit?
- `assets/js/pg_list_table_edit.js` stamps the same opt-outs on every text box (`noAutofill()`), on every list. A text box (`quickTyped()`: not select, search or multiselect) is saved only when the person wrote in it: a trusted `beforeinput` since it opened or was last sent. A filled box shows the stored value again (`fieldStored()`, also Escape's restore) and sends nothing; a refused typed value stays the person's and goes again with Enter.

## What was not checked?
- A real manager or Chrome's own autofill: the sandbox simulated fills with script writes and events. The guard assumes Chrome's autofill raises `input` and `change` but not `beforeinput`.

## Tests?
- `tests/user_fields_autofill_test.php` (the attributes on both screens, the guard run in Node against a stand-in document), `tests/list_table_quick_edit_js_test.php` (`quickTyped()`).
