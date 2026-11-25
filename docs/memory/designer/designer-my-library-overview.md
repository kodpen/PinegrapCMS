---
name: designer-my-library-overview
description: My library in the designer: a section saved as the site's own start, inserted as a copy, managed, exported and imported as a file; what binds it and which file holds which part
---

## What is My library?
- Owner (2026-11-24): a section, row, card or group a designer or an agency built once is kept as a start for the next page and the next site; one of the seven designer additions (`workflow/decisions-made-product-106.md`).
- An item is a copy you start from: once added it is the page's own. To keep every place the same, a shared component is used instead (`shared-component-model.md`); the save window says so in one line.
- Built in ML1 to ML4 (#11521, #11575, #11608, #11580); the screens follow the sheet in `designer-my-library-drawing.md`.

## What binds it?
- Kept as design files, one per item, the way a theme is a design CSS file: no table and no column. A change that needs one stops and asks the architect (`designer-library-item.md`).
- An item never carries a record's data, a member's content, a key or a connection: bindings and references travel as settings only, and a reference the other site lacks is cleared and marked on insert.
- An imported file passes the same kind of checks as the HTML and ZIP import (`designer-import-validation-theme.md`): no script, no event attribute, no address outside the allowed kinds; one failing item refuses the whole file with the reason (`designer-library-import-checks.md`).
- A name in use is kept beside, on save and on import; nothing is replaced. Only a delete asks; there is no bin.
- Seeing and adding items is open to the roles that may add elements (designer access full or content); saving, managing, export and import are the designer roles' (`pg_library_may()`).
- No button on a bar: rows in the element's three-dot menu, the command palette and the group's last row (`designer-light-and-simple.md`).

## Which file holds what?
- `designer-library-item.md`: the item's model, inserting into another design (rules renamed, variables, references cleared), the store, rights. Module `designer_library`.
- `designer-my-library-save-and-group.md`: "Save to my library" and its window, the "My library" group atop the Blocks list, adding a copy, the amber mark of a cleared binding. Part `mylibrary`.
- `designer-my-library-manage.md`: "Manage library" (rename, Move to group, order, delete). Part `librarymanage`.
- `designer-library-export-import.md`: Export, Import, the file and the report. Module `designer_library_io`, part `libraryfile`.
- `designer-library-import-checks.md`: what an imported file is checked for and the refusal's words.
- `designer-my-library-drawing.md`: the drawn sheet and what it settles.

## How is a new part added?
- A new action is a sub action of the `designer` panel action that asks `pg_library_may()`; a new screen is its own lazy part handed only to the roles that may use it, as `librarymanage` and `libraryfile` are.
- A change to the item's format raises `version` (1 today), so an older site refuses the file with "made by a newer Pinegrap".
- Tests: `tests/designer_library_test.php`, `tests/designer_library_io_test.php`.
