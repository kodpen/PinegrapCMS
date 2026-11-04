---
name: user-screen-input-errors
description: Create and Edit User send input errors back to the form through a liveform with what was posted; the contact a new account is linked to is checked; points and lengths are checked
module: user_write
---

## Where does an input error go on Create / Edit User?
- Back to the screen, never to the site's error page. `add_user.php` and `edit_user.php` mark the field (`$liveform->mark_error()` on the `add_user` / `edit_user` liveform), store the post (`add_fields_to_session()`) and redirect to the form (Edit keeps `id`, Create keeps `contact_id`; both keep `send_to`).
- The form prints `$liveform->output_errors()` above itself and calls `remove_form()` once drawn (`liveform-notices-trap.md`).
- `output_error()` stays only for access denial (a role the editor may not give, a record not found).
- Why: `output_error()` drew the active theme's visitor error page (site title, "Go to home page", HTTP 200) and printed the "Go back" link as text. Issue #6557.

## How is the form drawn from what was posted?
- `pg_user_form_session_values()` reads the liveform's stored values; `pg_user_form_restore_row()` lays them over the user row (unticked box = off, a page type unticked = `'0'`, since `''` reads as "never set" and draws ticked: `pg_user_form_page_type_values()`); `pg_user_form_posted_ids($post, 'edit_')` and the other prefixes give the ticked folders, regions, menus, calendars, contact groups and ad regions. All pure, in `includes/forms/user_form.php`.
- Edit drops values left by another account's form (the stored `id` must match). The heading, sign-in card and logs keep the stored username; only the inputs show the posted one. `reward_points_original` stays the stored value.
- Not restored: a private folder's view expiry date (Edit shows the stored one, Create none).

## Which values are checked before the write?
- `pg_user_form_input_problems($post)`: Reward Points (`pg_user_form_reward_points()` null → "is invalid"), username and badge label over 100 characters (`pg_user_form_text_fits()` → "is too long."). A lenient sql_mode clamped -5 to 0, cut 12,50 to 12, capped 99999999999, and cut the texts, past the uniqueness check. Issue #6556.

## Which contact may a new account be linked to?
- One that exists and no account is linked to yet: `pg_user_contact_link_problem()` (`includes/fn/user_write.php`, decision in the pure `pg_user_form_contact_problem()`). Opening Create with such a `contact_id` shows a warning naming the linked user and leaves the hidden field empty; a save that meets it (two tabs) goes back with the same sentence, and the next save creates the account without a contact.
- Why: any digit string was written to `user_contact`, so two accounts shared a contact or one pointed at nothing; membership, the contact's user card and the lookups assume one account per contact. Issue #6554.
