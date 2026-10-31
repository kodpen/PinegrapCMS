---
name: contact-group-shared-form
description: The contact group create and edit screens share includes/forms/contact_group_form.php: what it holds, the create-only difference, what stays in the screens
---

## Where is the contact group form?
- `includes/forms/contact_group_form.php` (gate `PG_FUNCTIONS_DIR`, PR #2604). `add_contact_group.php` and `edit_contact_group.php` stay at the root as thin wrappers and `require_once` it.
- `pg_contact_group_form_card($liveform, $required = false)` prints the Main Informations card: name, the e-mail subscription switch with its Open/Closed type and the description shown on My Account pages.
- `$required` (create) adds `required` to the name input, as the create screen always did; edit has no browser-side required name.

## What does a save share?
- `pg_contact_group_form_validate_name($liveform, $id)` (`null` on create, the posted id on edit): required, at most 100 characters (`mb_strlen`), then not used by another contact group (edit skips its own row with `id != ?`). A required or length error skips the name lookup.
- Why the length check? `contact_groups.name` is VARCHAR(100) and the session is not strict: the box allowed 255, so a long name was cut on save and two names sharing their first 100 characters passed the duplicate check and became one name. The name box has `maxlength` 100, the description textarea 255 (its column).
- `pg_contact_group_form_values($liveform)`: name, subscription, subscription type, description, in INSERT/UPDATE order; the screens append the user id (and the edited id).

## What stays in the screens?
- Create: the role-3 refusal, the INSERT, the notice on the group list.
- Edit: the not-found guard, `validate_contact_group_access()`, the contact count (delete is refused while the group holds contacts), the record load, delete with its four xref/opt-in tables, the UPDATE, the hidden id.
- While the group holds contacts, Delete is a disabled `type="button"` with the reason as its title and `aria-describedby`; it used to submit after an `alert()` and come back with the same refusal (#2892). The server-side refusal stays.
- Organize Contacts (`organize_contacts.php`) reads ticked boxes with `querySelectorAll('input[name="…"]:checked')`: with one group the named form member is the checkbox itself, `.length` is undefined and the selection was dropped (#2892).
- Tests: `tests/contact_group_form_test.php`; `db_q_contacts_forms_test.php` (edit screen, form) and `db_q_small_functions_test.php` (create screen) pin the queries.
