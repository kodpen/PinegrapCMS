---
name: page-form-page-fields
description: Create Page / Edit Page and the designer's page type pane: page settings are typed page picker fields (pg_page_form_page_field()); Settings and the user start page too (#8782)
module: forms
---

## Why are they picker fields?
- `pg_page_form_type_fields()` prints every page type's settings, hidden until the type is chosen: 35 page settings. As selects each listed every page; on a site with ~300 pages Create Page sent ~680 KB of HTML and ~10,000 DOM nodes (#6077). A `<template>` filled them when opened until PK6 of `plans/one-picker.md` (#8782) made them picker fields, which print only the stored page.

## How is one drawn?
- `pg_page_form_page_field($name, $value, $types, $label)` in `includes/forms/page_form.php`: with `pg_page_form_picker_fields()` on, `pg_picker_field(pg_picker_page_kind_for($types), $name, $value)`; else a select of `select_page($value, $types)` with "-$label-" first.
- `add_page.php` and `edit_page.php` pass `true` as the last argument of `pg_page_form_type_fields()`: it switches the fields on while it draws and appends `pg_picker_script()` to the properties.
- The designer's page type pane (`page_type_pane.php`) passes `true` too since PK8: its answer's markup carries the picker script, which the designer's screen has already loaded (`picker-designer-bridge.md`).

## Which pages does a setting offer?
- A setting that takes some page types gets the page kind listing only those (`pg_picker_page_type_kinds()` in `includes/fn/picker.php`, `picker-registry.md`): the catalog's detail page `page_catalog_detail`, a next page in checkout (`page_shipping_step`, `page_billing_step`, `page_preview_step`, `page_shipping_method`, `page_order_receipt`), `page_form_item_view`, `page_calendar_event_view`, `page_affiliate_confirmation`. Any other setting: `page`.
- A test (`tests/page_form_page_fields_test.php`) checks every `pg_page_form_page_field()` call names types some kind lists; `pg_picker_field()` prints nothing for an unknown kind.

## What happens to a stored archived or binned page?
- It stays and is posted unchanged. The field shows it under its name with a note: archived folder amber ("visitors cannot open it", `get_page.php` serves it to editors only), Recycle Bin red (its name before the bin), gone `#id` amber, a role 3 user's closed folder unnamed with a lock (`pg_picker_field_view()`).
- Browse opens on its folder, or the folder it was binned from, where it is listed marked and cannot be chosen (`picker-pages.md`).
- Before, a role 3 user's select dropped a stored page in a folder they cannot edit, and a plain Save wrote the empty option over it.

## How wide is a page field?
- The page settings sit in `col-12 lg:col-6` columns (a third left ~45px for the name at 1024px). `.pg-pfield` is a size container: below 22rem Browse shows only its icon, below 14rem the box drops its picture, below 18rem the match list grows leftwards to 18rem from the field's right edge.
- A size container has no content width, so `contain-intrinsic-inline-size: 20rem` keeps a field in a box sized to its content (`.collapse.popover`, the receipt e-mail page) from shrinking to its buttons. In Settings `.pg-f-md` / `.pg-f-lg` cap it at 28rem / 34rem like a select.

## Which other screens use the page field?
- Edit Page: the two comment e-mail pages. Settings: Commerce (reward program e-mail page, Reorder/Retrieve Order Next Page), Privacy (KVKK Notice Page), Security (membership expiration warning page). Add User, Edit User, Import Users: User Start Page.
- A cleared start page posts `''`, written as `(int)` 0 by `user_write.php` and `import_users.php`, as the old "[None]" option was.
- The other screens: `picker-page-fields-screens.md`.
