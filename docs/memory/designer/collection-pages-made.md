---
name: collection-pages-made
description: What "Create" of "New collection with pages" (DO23) makes: one call per thing, the Collections folder closed to visitors, drafts, upload folder, tabs, a stop half way, the closing line
module: collection_pages
---

## What does Create make, and how?
- One call per thing, `designer/collection_pages` op `make` (`pg_coll_pages_make()`, `includes/fn/collection_pages.php`, loaded only there; designers only): the widget row, then the page through `pg_designer_save_page()` (a refused page takes its row back).
- Order: the collection ("<name> collection", a `custom_form` widget, form settings `form_name` + `collection` 1 via `pg_cf_reconcile_page_form()`), the list page (heading + `form_list_view`, cards of Title, first image, first text, each a stretched link), the detail page (`form_item_view`, `title_field` the Title, a reference printed through `__ref`). The detail page then becomes the list's `detail_page_id` (`pg_coll_pages_link_detail()`).
- Closed to visitors (architect, 2026-11-26): the collection's page is saved, not as a draft, into the private folder "Collections" (`lang()`) directly under the top folder, found by name and access outside the recycle bin, made on first use like the Drafts folder (`pg_coll_pages_folder_id()`, `pg_coll_pages_folder_pick()`); no config column. Staff who open a private folder add the items.
- The list and detail pages start as drafts in the folder "Add Page" gives; every file control of the form uploads there (`pg_coll_pages_upload_folder()`), never into Collections, where uploads stay closed to readers. "One that is there" leaves the collection's page as it is.
- The Pages window marks a collection's page with the collection icon and a lock ("Closed to visitors").
- Each made page opens as a saved tab (`page_load`, `_pgTabsAddSaved()`), in the chosen group; a new group is made with the first page. No undo step.
- A failure marks its line "not made" with the reason; "Try again" makes only what is missing, "Close" ends on the line naming what there is.

## What is the closing line?
- `_ncMadeHtml()`: "Made the collection “T” with N fields, the list page “T” and the detail page “T”." (for one that is there: "Made … for the collection “W”."); each name opens its page. Under it, for a collection without items, "Add the first item" (`add_submitted_form.php?page_id=`, a new tab).

Related: docs/memory/designer/collection-pages.md, docs/memory/designer/page-drafts.md, docs/memory/designer/designer-form-uploads-for-readers.md
