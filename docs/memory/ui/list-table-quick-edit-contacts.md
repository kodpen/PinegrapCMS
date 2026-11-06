---
name: list-table-quick-edit-contacts
description: Contacts list quick edit (includes/fn/contact_list_edit.php): which cells are fields in which view, the split phone and address columns, how a change is checked and written like edit_contact.php
module: contact_list_edit
---

## Which cells change?
- `pg_contact_quick_edit_columns($filter)` (pure, by view), given as the source's `edit` in `pg_contact_list_source()`: first name, last name, company (50 characters, the screen's boxes); the views without an address also the business, home and mobile phones (50), the e-mail (100) and opt-in (Yes/No, not in the duplicates view, which has no such column); the address views Address 1, Address 2, city, state, country (50) and zip code (10) of their own address (`pg_contact_quick_columns()`: `business_`/`home_`).
- To make each value a field, the one Phone cell ("Business: …<br>Home: …") became three sortable columns (`pg_contact_list_phone_columns()`), and the address views' Address cell (line 1, line 2) became Address 1 and Address 2.
- Contact Groups (`multiselect`, where the list shows them): the groups the contact screen lets the user tick (`pg_contact_quick_group_options()`: groups they may edit, not filled by rules); the value is the contact's groups among them. Written by the screen's `pg_contact_form_save_groups()` with the screen's post (`pg_contact_quick_groups_post()`: opt-in as stored, on for a group joined); a User keeps the contact in one of their groups (`pg_contact_form_keeps_a_group()`); other groups are left alone.
- Text, as computed or gathered: the picture (the editor link and letters), User (the connected account), the duplicates view's address summary (both addresses in one cell), Last Modified. The rest of the contact screen (salutation, job, fax, membership, affiliate, notes, picture) is not shown by the list. `tests/contact_list_edit_test.php` fails for a new column that is neither a field nor listed as text.

## Why did the contacts list show no Quick edit button?
- Its columns carried no `editable`, so `pg_list_table_edit_spec()` dropped the edit: no field ever opened. `pg_contact_list_editable_columns()` now marks each column a field writes (`tests/contact_list_edit_test.php`).

## How is a change checked and written?
- `view_contacts.php` answers `pg_list_edit_request()` right after `init.php`, before the screen copies `$_REQUEST` into the session filters (else `field`, `value` and `id` became filters). Gate: `validate_contacts_access($user, true)`; a User (role 3) gets no edit through the duplicates view, as the screen refuses the view.
- `pg_contact_quick_update()`: `validate_contact_access()` for the contact (a User only reaches contacts of their groups), the e-mail through `validate_email_address()` (empty allowed, as on the screen), one `UPDATE contacts SET <column>` stamped with `user` and `timestamp`, the audit line, "contact (first last) was modified" in the site log.
- An e-mail or opt-in change gives the address's other contacts this contact's opt-in status (`pg_contact_form_sync_opt_in()`), as the screen's save does.
- The answer re-reads the row in its view and redraws every cell, so a name change redraws the picture's letters; a row that left the view (an opt-out in My Subscribers) keeps the stored value in the cell until the list reloads.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/contact-shared-form.md
