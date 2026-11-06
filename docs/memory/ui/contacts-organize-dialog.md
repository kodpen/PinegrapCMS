---
name: contacts-organize-dialog
description: Organize Selected on the Contacts list is an in-page pgUI dialog (no popup), offers only groups filled by hand, counts only contacts whose groups changed
---

## How does Organize Selected work?
- `view_contacts.php` prints the button in the selection bar of every view but the duplicates one, whether or not the groups column shows. It opens `#organize_contacts_dialog` (`data-bs-toggle="dialog"`), printed after `</main>`, outside the list form.
- The dialog holds two `multiselect-checkbox-container` lists (Add to / Remove from Contact Groups). Ticking a group in one list unticks it in the other; Save (`#organize_contacts_save`) stays disabled until a box is ticked.
- Save writes the comma lists into the list form's `add_to_contact_groups` / `remove_from_contact_groups`, sets `action=organize` and submits in `pgUI.dialog.hide(dialog).then(...)` (`dialog-result-after-close.md`).
- `organize_contacts.php`, once a 500x500 popup writing into `opener.document.form`, only redirects to the list. The popup was stopped by popup blockers, opened a tab on phones and threw a TypeError when opened alone (no `window.opener`).

## Which groups are offered?
- `pg_contact_list_organize_groups($user)` (`includes/fn/contact_list.php`): the groups the user may edit, `WHERE pg_audience_hand_sql()`, with their contact counts. A group filled by rules is not offered, as on the import screen and the contact card; `edit_contacts.php` still refuses it (`core/audiences-doors.md`).

## What does the save report?
- `edit_contacts.php` `organize` counts `pg_contact_list_organized_count($moved)`: the distinct contacts really added or removed. It used to count every accessible selected contact, so "2 contacts were organized" showed and was logged when nothing changed.
- The two fields are read by `pg_contact_list_posted_group_ids()`: text only, a field sent as an array names no group. `explode()` on an array ended the request with a fatal TypeError.
- No group posted (and none refused by rule): notice "No contact group was selected.", no write, no log.
- Tests: `tests/contact_list_test.php` (count, dialog pins), `tests/contact_screens_test.php` (ticked boxes read by selector), `tests/settings_group_names_test.php` (Select All names).
