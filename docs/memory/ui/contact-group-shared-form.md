---
name: contact-group-shared-form
description: The contact group create and edit screens share includes/forms/contact_group_form.php: what it holds, what stays in the screens, one required message, the list sorted on shown values
---

## Where is the contact group form?
- `includes/forms/contact_group_form.php` (gate `PG_FUNCTIONS_DIR`, PR #2604). `add_contact_group.php` and `edit_contact_group.php` stay at the root as thin wrappers and `require_once` it.
- `pg_contact_group_form_cards($liveform, $form)` prints the form column (Main Informations: the name; E-mail Subscription: the switch with its Open/Closed type and the description shown on My Account pages; then edit's rule card) and the sticky status column (`edit-screen-wide-layout.md`, #6568); `pg_contact_group_form_layout($cards, $buttons)` adds the sticky save bar.
- The Summary shows the subscription (Enabled/Disabled) and its type, live; edit adds `pg_contact_group_form_members_status($member_count, $kind)`: every member (rule-added too, unlike Delete's count) and, once `pg_audience_ready()`, Filled By (Rules / By Hand).
- `$form['required']` adds `required` to the name input; both screens pass `true`. Edit had none, so an emptied name cost a round trip to the server's refusal; its Delete button carries `formnovalidate`, so a group whose name was cleared can still be deleted.

## What does a save share?
- `pg_contact_group_form_validate_name($liveform, $id)` (`null` on create, the posted id on edit): required, at most 100 characters (`mb_strlen`), then not used by another contact group (edit skips its own row with `id != ?`). A required or length error skips the name lookup.
- Why the length check? `contact_groups.name` is VARCHAR(100) and the session is not strict: the box allowed 255, so a long name was cut on save and two names sharing their first 100 characters passed the duplicate check and became one name. The name box has `maxlength` 100, the description textarea 255 (its column).
- `pg_contact_group_form_values($liveform)`: name, subscription, subscription type, description, in INSERT/UPDATE order; the screens append the user id (and the edited id).

## What stays in the screens?
- Create: the role-3 refusal, the INSERT, the notice on the group list.
- Edit: the not-found guard, `validate_contact_group_access()`, the contact count (delete is refused while the group holds contacts), the record load, delete with its xref/opt-in tables (contacts, users, opt_in, e-mail campaigns, and SMS campaigns when the table exists, #3958), the UPDATE, the hidden id.
- While the group holds contacts, Delete is a disabled `type="button"` with the reason as its title and `aria-describedby`; it used to submit after an `alert()` and come back with the same refusal (#2892). The server-side refusal stays.
- The Organize Selected dialog of `view_contacts.php` (`contacts-organize-dialog.md`) reads ticked boxes with `querySelectorAll('input[name="…"]:checked')`: with one group the named form member is the checkbox itself, `.length` is undefined and the selection was dropped (#2892).
- Tests: `tests/contact_group_form_test.php`; `db_q_contacts_forms_test.php` and `db_q_small_functions_test.php` pin the queries.

## Why one message under the name?
- The fixed `<div class="invalid-feedback">Required Area</div>` after the name box stays for the browser's `required` check, but a server refusal (name in use, name missing on edit) printed liveform's message and it together (#5260). `backend.src.css` hides a fixed `.invalid-feedback` that follows a `.pg-inline-feedback`, so every form that kept its fixed message (`ui/inline-feedback-placement.md`) shows the server's reason alone.

## How does the list sort?
- `view_contact_groups.php` shows the type and the description only for a group with an e-mail subscription, so `pg_contact_group_list_order_map()` (`includes/fn/contact_list.php`) sorts both on `IF(email_subscription = 1, …, '')`: a group without a subscription sorts as blank, not on text nobody sees (#5262). Test: `tests/contact_group_list_sort_test.php`.
