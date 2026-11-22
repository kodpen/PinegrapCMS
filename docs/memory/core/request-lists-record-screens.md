---
name: request-lists-record-screens
description: Edit Page, Edit File, Edit Theme File and the Product Builder (add and edit) refuse a field posted as a list (name[]=x) before any write; pg_pb_list_fields() names the product form's own lists
---

## What does a record screen do with a field posted as a list?
- `edit_page.php`, `edit_file.php`, `edit_theme_file.php`, `add_product.php` and `edit_product.php` call `pg_request_unexpected_lists($_POST, …)` (`includes/fn/core.php`) right after `validate_token_field()`, before any field is read or the liveform keeps the post.
- A hit refuses the whole save with "Sorry, the value you entered for {names} is not valid.": Edit Page, Edit Theme File and Edit Product go back to the screen with the liveform error; Edit File and the new product show the error page with Go back, as their other refusals do (a long form keeps what was typed in the browser).
- Refused, not read as not given (as in the settings saves): these saves write the fields they read, so a field taken as not given would be stored blank.
- Why (PR #11076): `name[]=x` or `title[]=x` stopped Edit Page, Edit File and Edit Theme File in `trim()`, `escape()` or `mb_strrpos()` with a 500; `code[]` and `name[]` did the same on the Product Builder.

## Which lists may the product form post?
- `pg_pb_list_fields()` (`product_builder.php`) names each field the Product Builder draws as a list, with its depth (all 1): allowed zones, catalog groups, the picture picker's `selected_images`, the bundle rows, the stock locations and their earlier values, the product bin.
- A new list field on the product form goes into that function too, or every save of every product is refused. The page, file and theme file forms draw no list.
- `tests/request_unexpected_lists_test.php` reads the names the product form's files draw with `[` (`product_builder.php`, `add_product.php`, `edit_product.php`, `includes/fn/product_bins.php`, `selected_images` from `backend.src.js`) and fails when one is missing from `pg_pb_list_fields()`; it also fails when a page, file or theme file form starts drawing a list, or a screen reads a field before the check.

## Which product does Edit Product open for `?id[]=5`?
- None: the id is read with `is_scalar()` and a list is 0, which answers "Page not found." (404). `(int)` of a list is 1, so the screen opened and saved product 1.

## Related
- Settings saves refuse a list the same way: `ui/settings-invalid-vs-error.md`. Screens where a list counts as not given: `core/request-parameter-arrays.md`.
