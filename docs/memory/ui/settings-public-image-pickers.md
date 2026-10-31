---
name: settings-public-image-pickers
description: Site Settings pickers whose picture is shown to everyone (Site Icon, App Icon, Default Share Image, Organization Logo): list and save leave out attachments and ERP documents
module: files
---

## Which pickers are they?
- `includes/settings/prep.php`: Site Icon (PNG/SVG) and the shared list of Default Share Image, Organization Logo and Application Icon (`pg_public_image_file_types()`: jpg, jpeg, png, gif, webp, avif, bmp). Saves: `seo.save.php` (`site_icon`, `app_icon`, `og_default_image`) and `general.save.php` (`organization_logo`).

## What do they leave out?
- Both lists add `pg_public_image_files_where()` (includes/fn/files.php): `attachment = 0` and, once the column exists, `erp_doc_type = ''`. An ERP document (quote signature, expense receipt, invoice) is served to the ERP right only; the site icon is redrawn and served without a session, so picking one published it. Symptom fixed: the four pickers listed `erp-quotesignature-…png` and `erp-expense-…png` (#4041, PR #4176).

## What does a save accept?
- One check for all four: `pg_public_image_choice_ok($input, $stored, $types)`, pure part `pg_public_image_choice_verdict()`. Empty passes; a files row passes when it is no attachment, no ERP document and of the listed types; a name no row carries passes only when it is the stored value (an old typed address the picker keeps, [[stored-choice-kept]]). A stored file that is now private is refused too. The field is marked "Choose a picture from the Files screen that everyone may see." and nothing is written.

## How is it tested?
- `tests/settings_public_image_test.php`: the filter, the verdict, the wiring of the two lists and four saves.
