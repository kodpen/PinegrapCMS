---
name: user-screens-refused-save
description: Create User / Edit User: a refused save goes back to the form with the error and what was sent (liveform add_user / edit_user, pg_user_form_refill()), the field limits and the contact link check
---

## What happens when Create User or Edit User refuses a save?
- The screen marks the error on its own liveform (`add_user`, `edit_user`), stores the sent fields (`add_fields_to_session()`) and redirects to itself (`add_user.php?send_to=&contact_id=`, `edit_user.php?id=&send_to=`). The form prints `$liveform->output_errors()` above it and calls `remove_form()` once drawn.
- `pg_user_form_sent_values($form)` reads the stored fields (only after a submit: `__prefill` is false); `pg_user_form_refill($html, $values)` (pure) writes them into the drawn `<main>`: text fields take the sent value, checkboxes and radios are checked only when sent (an unchecked box posts nothing), a select picks the sent option. Hidden, password, file, button and disabled inputs keep what the screen drew (token, id, `reward_points_original`, a manager's fixed role). UI gates of the rights rows follow their ticked boxes in `backend.src.js` (`syncGate()`), the role cards through `pgUserPermissionRole()` on load.
- Edit refills only when the stored `id` is the account opened.
- `output_error()` stays for access denials (role) and the private folder date check.
- Symptom fixed (#6557): the errors drew the site's visitor error page, its "Go back" printed as text, and the role and rights were lost.

## Which fields are checked before writing?
- `pg_user_form_field_problems($post)` (pure): Reward Points through `pg_user_form_reward_points()` ('invalid'), username and badge label through `pg_user_form_text_fits()` ('too_long'); then `pg_user_write_problem()` (blank username, invalid or taken address). Messages: `pg_user_form_field_problem_message()`.
- `pg_user_create()` writes `(int) pg_user_form_reward_points()`; the edit save writes points through `pg_credit_points_staff_sql()` only when changed.
- Symptom fixed (#6556): -5, 12,50 and 99999999999 were clamped or cut by a lenient server, a 101-character username got around the uniqueness check.

## Which contact may a new account be linked to?
- One that exists and has no account: `pg_user_contact_holder()` (`includes/fn/user_write.php`) reads both, `pg_user_form_contact_problem()` (pure) answers '', 'contact_missing' or 'contact_taken'. The POST refuses with the linked username and goes back without `contact_id`, so the account can be made without it; the GET shows the same sentence as a warning and leaves the email and the hidden field empty.
- Symptom fixed (#6554): two accounts shared one contact (membership, the contact's user card and the lookups read one).
