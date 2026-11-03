---
name: settings-public-image-pickers
description: Site Settings pickers whose picture is shown to everyone (Site Icon, App Icon, Default Share Image, Organization Logo): list and save keep only files every visitor can open
module: files
---

## Which pickers are they?
- `includes/settings/prep.php`: Site Icon (PNG/SVG) and the shared list of Default Share Image, Organization Logo and Application Icon (`pg_public_image_file_types()`: jpg, jpeg, png, gif, webp, avif, bmp). Saves: `seo.save.php` (`site_icon`, `app_icon`, `og_default_image`) and `general.save.php` (`organization_logo`).

## What do they leave out?
- Both lists add `pg_public_image_files_where()` (includes/fn/files.php): `attachment = 0`, once the column exists `erp_doc_type = ''`, and `folder IN` the folders a visitor without a session may open (`pg_public_folder_ids()`: public along the parent chain by `pg_folder_access_resolve()`, never in the Recycle Bin). Symptom fixed: the lists offered `pgbin_<id>_…` files from the bin and saved them; a visitor asking for the logo was sent to sign in (#5023). An ERP document (quote signature, expense receipt, invoice) is served to the ERP right only; the site icon is redrawn and served without a session, so picking one published it. Symptom fixed: the four pickers listed `erp-quotesignature-…png` and `erp-expense-…png` (#4041, PR #4176).

## What does a save accept?
- One check for all four: `pg_public_image_choice_ok($input, $stored, $types)`, pure part `pg_public_image_choice_verdict()` (the row carries `public`, read by `pg_public_image_row()`). Empty passes; a files row passes when it is no attachment, no ERP document, in a public folder and of the listed types; a name no row carries passes only when it is the stored value (an old typed address the picker keeps, [[stored-choice-kept]]). A stored file that is now private or in the bin is refused too, and the picker shows it marked "(visitors cannot open it)" (`pg_public_image_stored_option()`, which otherwise falls back to `pg_stored_file_option()`). The field is marked "Choose a picture from the Files screen that everyone may see." and nothing is written.

## How is it tested?
- `tests/settings_public_image_test.php`: the filter, the public folders, the verdict, the wiring of the two lists, four saves and four marks.
