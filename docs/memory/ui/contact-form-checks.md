---
name: contact-form-checks
description: Create/Edit Contact save checks: membership date and negative rate refused, role 3 keeps a group on edit, picture posted as file_id or image_url, notes box, wording
---

## What does the save refuse?
- `pg_contact_form_check($user, $groups, $post)` runs on both screens before the save. `pg_contact_form_refusal()` refuses a membership expiration date that `pg_date_input_iso()` cannot read and a commission rate starting with `-`. Before, `31.12.2027` or `32/13/2027` became a lifetime membership (`0000-00-00`), `31/12/27` the year 0027 (#5256), and `-7,5` was saved as 7.50 (#5259). A rate above 100 (read with `pg_parse_commission_rate()`) is refused too: the column holds up to 999.99, so 150 was saved and credited the affiliate more than the order. The rate box has `data-inputmask-allowminus="false"` and `data-inputmask-max="100"`; it keeps the panel's rate-box convention of `.` as the decimal point (the `decimal` alias in `backend.src.js`), the server reads either separator.
- An empty date box stores `0000-00-00` (lifetime) explicitly; `pg_contact_form_columns()` reads the date through `pg_date_input_iso()`.
- `pg_contact_form_keeps_a_group()`: a role 3 user must leave at least one group they may edit checked. Create asked this, edit did not, so a role 3 user could uncheck every group and lose the contact (#5261).

## Why was a picture picked on Create lost?
- Create opened the picker without `file_input_name`, so it posted `selected_images[]`, which nothing reads. `pg_contact_form_picture_card()` now passes `file_input_name:'file_id'` on both screens (#5258); `$is_new` is no longer read there.
- An Unsplash pick has `file_id` 0; `software_image_picker()` (`backend.src.js`) now adds a hidden `image_url` with its address when the field is `file_id`, which `pg_contact_form_columns()` stores as `image`.

## Smaller fixes
- Notes box: `<textarea rows="6">`, the invalid `type="text"` gone (#5264).
- Labels: the work card's title is `lang('Job Title')` (Unvan), the membership date `lang('Membership Expiration Date')` (Üyelik Bitiş Tarihi, also in the list's advanced filters); Turkish for `Salutation` is Hitap, for the invalid e-mail error "E-posta adresi geçersiz" (#5263).
- The default rate hint uses `pg_format_number(…, 2)` (%30,00).
- Edit Contact without a user: "This contact has no user connected to it." and a "Create a user for this contact" link, instead of a sentence glued from two keys.
