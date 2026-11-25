---
name: sketch-page-details
description: "Page details" of a Prototype view sketch page (SK5): the More menu and palette row, the seven rows and defaults, checks, the sketch_details endpoint, where values are kept
module: designer
---

## Where does it open?
- From the "More" button (three dots, `.sd-sketch-more`, SK7's, shown on the selected page while the sketch switch is on) in a sketch page's head: the row "Page details" is one of `_sdSketchMoreRows(page)`, between the reference picture rows and "Delete the page". New rows of the menu go into that list too; SK5 has no button or menu of its own (#11545 settled its clash with SK7 #11557).
- From the command palette: Ctrl+Shift+P works inside the Prototype view; the row "Page details" is listed first while a sketch frame is selected (`_getCpActionItems()`, `_sdSketchSelected()`).
- No bar button. The window is the designer part `sketchdetails` (`assets/js/pg_sketch_details.js`, pure, `window.PGSketchDetails`, then `style_designer_sketchdetails.js`), handed to every editor; the core keeps the stub `_sdSketchDetails()` and the host `_sdSketchDetailsHost()`.

## What are the rows and their defaults?
- As drawn (`designer-sketch-drawing-details.md`), each a Settings window row (`.sd-set`): Name (the name the tool gave, "Page 7"), Address (made from the name by `PGSketchDetails.address()`: Turkish letters folded, hyphens, at most 100; follows the name until typed, emptied it follows again), Page type, In the menu, Title (empty, the name as placeholder), Description (empty), Who may see it.
- Who may see it: the folders of the Settings window's `#pg_page_folder`, default the open page's folder; the line under it is the Settings window's access sentence.
- Page types: Standard page, Catalog, Blog, Booking, keyed by the sketch reader's widget kinds (`PGSketchDetails.TYPES`); a non-standard type says which widget it brings ("Catalog listing", "Blog list", "Booking").
- In the menu: "Not in a menu", or a menu and beside it "At the start" / "After “item”" over the menu's top items; a new place goes after the last item.

## Which checks run at Save?
- In the window (`PGSketchDetails.check()`): a name; an address without ?, % or +, at most 100, not used by a tab of the design or another sketch page (case ignored); title and description at most 255.
- Then the server, `designer/sketch_details` with the address (`pg_designer_sketch_details()`, `includes/fn/designer.php`): `used` (the page with that page_name), `taken` (`pg_designer_page_name_taken_elsewhere()`), `error` (`pg_designer_page_name_error()`). Without an address it answers the menus (`pg_designer_sketch_menus()`, pure). Designers only; it writes nothing.
- A wrong row gets the rose edge and line, the first is focused; while the server answers the foot says "Saving…" and its buttons are off.

## Where are the values kept?
- With the sketch: the page's `name` and `details` `{address, typed, type, menu, menuName, after, afterName, title, description, folder}` in `pg_proto_sketch_<style id>`, kept through `_sdSketchClean()` (`_sdSketchDetailsKeep()`); a save is a step of the sketch's undo and the frame's head takes the name at once. SK4 builds the page with them; after the build the window is to read and write the page's own values (Architect, 2026-11-24). No table or column was added.
- Read-only (a content-level operator or view mode): every control off, the foot "You may see this page's details but not change them." with only Close; no server call.

## What was not checked?
- The window was not run in a browser (no running site in the session); the pure values and checks, the server's menu shape, the wiring and the stored details are unit tested (`tests/sketch_details_test.php`, `tests/designer_sketch_test.php`).
