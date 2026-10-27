---
name: db-q-converted-contacts-forms
description: Contact, contact group, form field and submitted form panel screens converted to db_q() (PRs #2299, #2316): what stayed raw and why, guard test.
---

## Which files are converted, and what stayed raw?
- PR #2299: `edit_contact.php` 18, `view_contacts.php` 7, `edit_submitted_form.php` 18, `view_submitted_forms.php` 11, `edit_field.php` 14 calls with values. Every value is passed `(string)`: the old SQL quoted all of them, also the ids, counters and constants it wrote unescaped inside quotes (`$contact_group['id']`, `$field['id']`, `$form['id']`, `$user['id']`, `AFFILIATE_GROUP_OFFER_ID`, `$count`, `$form_data_type`), so the SQL text sent is the same.
- PR #2316: `edit_contact_group.php` 10, `edit_contacts.php` 8, `add_contact.php` 6, `import_contacts.php` 4, `add_field.php` 7, `delete_fields.php` 8, `add_submitted_form.php` 6, `import_submitted_forms.php` 7 calls, `(string)` as above (also `$new_complete`, `$form_id`, `$file_id`, `generate_form_reference_code()`). Raw stay the contact INSERT of `add_contact.php` (`$sql_image`, `$sql_file_id`), the CSV contact INSERTs of `import_contacts.php` (`$data_list`, escaped values), the field INSERT of `add_field.php` (`$sql_upload_folder_id_value`, `$sql_translate_values_value`) and its `$form_type_filter` reads, the bulk contact list of `edit_contacts.php` (`$where`) and the queries without values.
- `edit_field.php`'s form name lookup (and `add_field.php`'s, `delete_fields.php`'s) keeps its table name (`$form_settings_table`, from `pg_form_settings_table()`) and `$sql_quiz` in the SQL text; only `page_id` is bound.
- Raw stay the calls without values (contact group lists, the e-mail address list, `MIN(timestamp)`, the form count, the custom form list built by `pg_form_page_sql()`, `MIN($date_type_column)` with a bare column name) and those completed by a clause built elsewhere: the contact UPDATE (`$sql_affiliate`, `$sql_file_id`, `$sql_image`), the contact lists (`$where`, `$my_contacts_where`, `$join_table`), the submitted form UPDATE (`$sql_form_editor`), and in `edit_field.php` the field UPDATE (`$sql_upload_folder_id`, `$sql_translate_values`) and the sort order reads (`$form_type_filter`).
- `tests/db_q_contacts_forms_test.php` guards all thirteen: no escaped value in a `db_q()` SQL, none written into a raw query outside those clauses, one value per `?` in each literal value list.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-placeholder-helper.md
- Since PR #2509 the submitted form list builds its filter with bound values in `includes/fn/submitted_form_list.php` (`$sql_data_joins` and `$where` are gone); the test counts that file too.
