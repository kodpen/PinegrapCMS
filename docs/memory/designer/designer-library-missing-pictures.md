---
name: designer-library-missing-pictures
description: My library, a copy whose pictures this site lacks (owner poll 224): the line on adding says how many are missing, addresses stay, no picture files in the export; decided, not built
module: designer_library
---

## What was decided?
- Owner (2026-11-26, agent channel poll 224): "Ekleme satırı kaç resmin bu sitede olmadığını söylesin, adresler kalsın" (`workflow/decisions-made-product-112.md`).
- Adding a copy from My library says in its line how many of the copy's pictures are not on this site. A picture's address stays as written: never cleared, never changed, no amber mark on the node.
- The export file carries no picture files: `format` `pinegrap-library`, `version` 1 as `designer-library-export-import.md` describes it. The import's report says nothing about pictures.
- Not built yet. The PR that builds it corrects this file to what the code does.

## Which pictures are counted?
- Architect (2026-11-26, agent channel request 3056): the distinct picture addresses of the copy (its tree, its widgets' settings and the rules added with it) that point at this site's own files and have no file there.
- Not counted, because it cannot be checked: an address on another host, a `data:` address, an address that is a variable or a binding's placeholder.
- An address is counted once however many places of the copy use it.

## How is it built?
- `pg_library_item_into_design()` (`includes/fn/designer_library.php`) answers the number beside `cleared`, from one pure function over the addresses and a "does this site have the file" callback, so the test needs no file system.
- The line is one more entry of the lines the part `mylibrary` shows after a copy is added (`assets/js/style_designer_mylibrary.js`), through `_sdT()` with its Turkish from `tools/tr_add.php`: "Added from my library. {var} pictures of the copy are not on this site: put them in the file manager at the same place or choose other pictures.", with its own sentence for one picture.
- No schema change and no new control. The failing test first: a copy with two missing pictures, one used twice, one on another host and one that the site has answers 2.

## What stays as it was?
- A missing page, folder, form, collection, product group, calendar or menu is a record: cleared and marked on insert (`designer-library-item.md`).
- The checks an import runs on a picture's address (`designer-library-import-checks.md`).
