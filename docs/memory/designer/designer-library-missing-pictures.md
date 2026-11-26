---
name: designer-library-missing-pictures
description: My library, a copy whose pictures this site lacks (owner poll 224): the line on adding says how many are missing, addresses stay, no picture files in the export; how they are counted
module: designer_library
---

## What was decided?
- Owner (2026-11-26, agent channel poll 224): "Ekleme satırı kaç resmin bu sitede olmadığını söylesin, adresler kalsın" (`workflow/decisions-made-product-112.md`).
- Adding a copy from My library says in its line how many of the copy's pictures are not on this site. A picture's address stays as written: never cleared, never changed, no amber mark on the node.
- The export file carries no picture files: `format` `pinegrap-library`, `version` 1 as `designer-library-export-import.md` describes it. The import's report says nothing about pictures.

## Which pictures are counted?
- Architect (2026-11-26, agent channel request 3056): the distinct picture addresses of what is inserted (the tree, the widgets and components made anew with their settings, the rules and variables added to User Styles) that point at this site's own files and have no file there.
- A file of the file manager is served at the site's path and its name, so an address counts when it is `PATH` + a name (or a bare name), on no host or on this site's host (`HOSTNAME`), and the name ends in a picture extension (jpg, png, gif, webp, avif, svg, bmp, ico, tif). Query and `#` are dropped, the name is URL-decoded.
- Not counted, because it cannot be checked: another host's address, a `data:` or other scheme, a variable or placeholder (`{{token}}`, `{`, `$`, `var(`), an address under a folder (`/x/a.jpg`) or outside the site's path. A component the site already has is not inserted, so its pictures are not read.
- An address is counted once however many places of the copy use it. "Has a file" is a `files` row of that name, as `router.php` serves it.

## How is it built?
- `pg_library_missing_pictures($urls, $path, $has_file, $host)` (`includes/fn/designer_library.php`, pure) counts; `pg_library_item_into_design()` answers it as `pictures_missing` beside `cleared` when `$site['has_file']` is a callable (else 0). The addresses are read with the import checks' readers (address keys, `srcset` candidates, tag attributes in markup, style `url()`), so `library_prepare` loads `designer_library_io.php`.
- The part `mylibrary` (`assets/js/style_designer_mylibrary.js`, `_settle()`) adds one warning line: "Added from my library. {var} pictures of the copy are not on this site: put them in the file manager at the same place or choose other pictures.", for one: "Added from my library. One picture of the copy is not on this site: put it in the file manager at the same place or choose another picture."
- No schema change, no new control. Test: `test_library_missing_pictures_counted` in `tests/designer_library_test.php`.

## What stays as it was?
- A missing page, folder, form, collection, product group, calendar or menu is a record: cleared and marked on insert (`designer-library-item.md`).
- The checks an import runs on a picture's address (`designer-library-import-checks.md`).
