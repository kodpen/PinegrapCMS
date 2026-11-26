---
name: collection-pages
description: "New collection with pages" (DO23): the Pages window's last row and palette row, the part 'collection', the two steps, how a field is stored, tests and sizes
module: collection_pages
---

## Where does it start?
- The last row of the Pages window's list, under a rule, in every filter and search (also "No page matches."), and the command palette row `act_new_collection`, which opens the Pages window on the second view (`_pgPagesOpen('collection')`). Shown where "Add Page" is (`canAdd()`). No bar button.
- The row turns the window to the part `collection` (`assets/js/style_designer_collection.js`, `when` always, loaded on first use); the window's other parts hide under `.sd-pw-second`. The core hands it `_pgCollectionHost(back)`.

## What do the two steps keep?
- Step 1: "A new collection" (Name, Fields: the Title first and locked, at most twelve, kinds Text, Rich text, Number, Date, Image, File, Option set, Reference) or "One that is there" (the site's collections with fields, items and the pages showing them). Step 2: the list and detail page's names and addresses, the group (a new one named after the collection, a design group or Other).
- What was typed stays while the same Pages window is open; shutting it drops it. Addresses start free of the design's pages and of each other (`_ncFree()`), follow the name until typed, and are asked of the server once the hand rests (op `taken`).

## How is a field stored?
- `_ncFieldDefs()`: the Title is a text box with `rss_field` 'title' (the record's name and address); image = file upload + kind image; rich = text area + wysiwyg; number = text box + kind number; option set = pick list with the typed options; reference = pick list + kind reference + `reference_page_id` (none chosen: plain text). The form tree is `_cfBuildFromFields()`'s, the list and detail trees `_buildStarterTree()`'s: nothing new is drawn by hand.

## How is it checked?
- `tests/collection_pages_test.php`: the make request's checks, the page tree and fields, the endpoint closed to content roles, the Collections folder chooser and the upload folder, and under Node the addresses, step 1's refusals, the field defs, the closing line.
- Sizes (#PR of DO23): core `style_designer.min.js` +1,584 bytes (+586 gzipped), `pages` part +755, the new part 26,094 (8,455 gzipped), loaded only when the row is chosen.

Related: docs/memory/designer/collection-pages-made.md, docs/memory/designer/designer-options-collection-drawing.md, docs/memory/designer/designer-pages-window.md, docs/memory/designer/collection-fields.md
