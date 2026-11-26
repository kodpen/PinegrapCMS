---
name: collection-pages
description: "New collection with pages" (DO23): the Pages window's last row and palette row, the part 'collection', the three things Create makes one call each, the closing line, the endpoint and tests
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

## What does Create make, and how?
- One call per thing, `designer/collection_pages` op `make` (`pg_coll_pages_make()`, `includes/fn/collection_pages.php`, loaded only there; designers only): the widget row, then the page through `pg_designer_save_page()` (a refused page takes its row back).
- Order: the collection ("<name> collection", a `custom_form` widget, form settings `form_name` + `collection` 1 via `pg_cf_reconcile_page_form()`), the list page (heading + `form_list_view`, cards of Title, first image, first text, each a stretched link), the detail page (`form_item_view`, `title_field` the Title, a reference printed through `__ref`). The detail page then becomes the list's `detail_page_id` (`pg_coll_pages_link_detail()`).
- Each made page opens as a saved tab (`page_load`, `_pgTabsAddSaved()`), in the chosen group; a new group is made with the first page. New pages are drafts where "Add Page" makes drafts. No undo step.
- A failure marks its line "not made" with the reason; "Try again" makes only what is missing, "Close" ends on the line naming what there is.

## What is the closing line?
- `_ncMadeHtml()`: "Made the collection “T” with N fields, the list page “T” and the detail page “T”." (for one that is there: "Made … for the collection “W”."); each name opens its page. Under it, for a collection without items, "Add the first item" (`add_submitted_form.php?page_id=`, a new tab).

## How is it checked?
- `tests/collection_pages_test.php`: the make request's checks, the page tree and fields, the endpoint closed to content roles, and under Node the addresses, step 1's refusals, the field defs, the closing line.
- Sizes (#PR of DO23): core `style_designer.min.js` +1,584 bytes (+586 gzipped), `pages` part +755, the new part 26,094 (8,455 gzipped), loaded only when the row is chosen.

Related: docs/memory/designer/designer-options-collection-drawing.md, docs/memory/designer/designer-pages-window.md, docs/memory/designer/collection-fields.md
