---
name: contact-shared-form
description: The contact create and edit screens share includes/forms/contact_form.php: what it holds, what stays in the screens
---

## Where is the contact form?
- `includes/forms/contact_form.php` (gate `PG_FUNCTIONS_DIR`). `add_contact.php` and `edit_contact.php` stay at the root as thin wrappers and `require_once` it.
- Markup: `pg_contact_form_name_row($values, $is_new, $image_html)` (name + picture card), `pg_contact_form_detail_cards($values, $is_new, $work_extra, $groups_html)` (work, home, membership, notes, affiliate, groups). `$values` is the raw stored row, `array()` on create; escaping is inside. `$is_new` picks the "New Contact's …" card titles and the picker's input name.
- Groups: `pg_contact_form_groups($user)` (allowed, by name), `…_group_states($id, $groups)` (member / opted in), `…_group_cells($groups, $states)`.
- Description has no `maxlength`: `contacts.description` is LONGTEXT and site forms fill it, so a 50-character cap refused longer notes on save (#2892).

## What does a save share?
- `pg_contact_form_email()` (trim + invalid stops), `…_affiliate_code($post, $id)` (duplicate check, 0 = new; generates when approved without one), `…_columns($post, $user_id)` + `…_affiliate_columns()` (strings for `db_q()`; an array-posted field saves `''`), `…_sync_opt_in()` (same-address opt-in with its audit read/record), `…_save_groups()` (ticked in, unticked out, opt-in rows, join/leave events), `…_affiliate_key_code()`, `…_redirect()` (send_to).

## What stays in the screens?
- Create: the subscriber card (opt-in ticked), the role-3 "at least one group" check, the INSERT, the create audit line and `pg_announce_contact_created()`, affiliate columns written even while the program is off.
- Edit: the not-found guard, `validate_contact_access()`, the toolbar (orders, ledger, privacy, workspace), the picture/avatar markup, the subscriber card with mailto, the connected account card, the Parasut field (`$work_extra`), delete, the UPDATE with its audit line, the key code only on first approval.
- Tests: `tests/contact_form_test.php`. Screen pins (organize, description, totals, names): `tests/contact_screens_test.php`.

## Which keyboards?
- `pg_contact_form_text_field()` takes a fifth `$keyboard` argument printed through `pg_contact_form_keyboard_attributes()`: `'tel'` → `inputmode="tel"` (Business Phone/Fax, Home Phone/Fax, Mobile Phone), `'url'` → `inputmode="url" spellcheck="false" autocapitalize="off"` (Website; iOS capitalised its first letter). Same set as the Contacts filters (`contact-filter-keyboards.md`).
- Every box stays `type="text"`: phone values carry spaces, `+` and parentheses, and Website accepts a bare `www.example.com`. Zip codes keep the text keyboard; the e-mail box (`inputmode="email"`) and VKN/TCKN (`inputmode="numeric"`) are written in their own markup.
- Test: `tests/contact_form_keyboards_test.php`.
