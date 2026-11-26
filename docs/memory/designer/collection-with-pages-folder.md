---
name: collection-with-pages-folder
description: Where "New collection with pages" (DO23) puts what it makes: the collection's page in a private "Collections" folder and not a draft, the two pages as drafts, the fields' upload folder
---

## How is the collection's page closed to visitors?
- Architect (2026-11-26, agent channel request 3011): by a private folder, as the templates' collection forms ("blog_new", "dish_new") sit in their template's private folder.
- The folder is "Collections" (through `lang()`), directly under the site's top folder, access `private`. It is found by that name and access outside the recycle bin, and made on first use the way `pg_page_draft_folder_id()` makes the Drafts folder.
- No config column and no schema change. A site whose language changed gets a second folder, which harms nothing.
- A folder of that name that is not private is not taken; one in the recycle bin is not taken.

## Is the collection's page a draft?
- No. It is saved into the Collections folder at once: it is never on the site, so a draft would only ask for a Publish that publishes nothing, and staff add items straight away.
- The save goes through `pg_designer_save_page()` with `page_draft` off and `page_folder` the Collections folder; no direct write of `page.page_folder` (`tests/prototype_approval_hold_test.php` lists those).

## What state do the list page and the detail page start in?
- Drafts, as every page the editor creates (`page-drafts.md`). Their folder to return to is the one "Add Page" gives a new page of that design.
- They reach the site only through Publish, past its holds. The Pages window shows them as drafts, as today.

## Where do an Image or a File field's uploads go?
- To the two pages' folder to return to, never to the Collections folder: an upload in a private folder stays closed to readers whatever a widget prints (`designer-form-uploads-for-readers.md`), so the list would show no picture.
- The check that zeroes a folder the operator may not edit stays as it is.

## Who adds items?
- Whoever opens a private folder today: an administrator, a designer, a manager, anyone else by the folder's rights. No new right.

## What about a collection that is there, and a stop half way?
- "One that is there": the collection's page is not moved, renamed or changed; only the two pages are made, as drafts.
- A stop half way keeps the collection's page where it is; "Try again" makes no second folder and no second collection.

Related: docs/memory/designer/designer-options-collection-drawing.md, docs/memory/designer/designer-options-collection-drawing-made.md, docs/memory/designer/page-drafts.md
