---
name: contact-list-names
description: Contacts list (view_contacts.php): one Website filter, labels on their own field, group counts in the panel's number format, the row checkbox and Opt-In mark names, silent icon words
module: contact_list
---

## Why did the Website filter do nothing?
- The advanced filter bar printed the Website box twice with the same `id`/`name`. The GET form sent `website=<typed>&website=`, PHP kept the last (empty) value in `$_SESSION['software']['view_contacts']`, so the typed text never filtered. One box now.

## Which labels point at which field?
- Expiration Date: `<label for="expiration_date">` (it named Member ID, so `pg_fields.js` found no label for the date box). Only the label changed; the date input and its picker are untouched.
- Opt-In Status in My Subscribers: a disabled box `id="opt_in_status" name="fake_opt_in_status"` holding Opt-In, like the membership status box of the membership views; before, the label pointed at plain text.

## How are counts and rows named?
- Contact group checkbox counts (and `[None]`) go through `pg_format_number(…, 0)`, like the group select (1.234 in Turkish).
- Row checkbox: aria-label "Select {var:1}" with `pg_contact_list_row_name()` (full name, else company, else e-mail); the duplicate `class` attribute is gone.
- Opt-In column: `task_alt` is `aria-hidden` with a `visually-hidden` "Yes" (as `pg_user_list_mark()`).
- Ligatures in the Filters heading and the Update, Merge, Merge/Opt-In/Opt-Out/Delete Selected buttons are `aria-hidden`; the landmarks are `h(lang('Button Bar'))` and `h(lang('data edit buttons'))`.
- The advanced-filter close link needs no change: `pgInjectAriaAndRequiredMarkers()` copies its translated `title` into `aria-label`.

Test: `tests/contact_list_names_test.php`.
