---
name: designer-hidden-page-endpoints
description: Editor endpoints asked by page id keep the hidden-page rule of the tab list: page_load, form_fields and notes_fetch refuse a page behind a folder the operator may not know about
---

## Which rule do the editor's page endpoints follow?
- The tab list leaves out a page whose `pg_designer_page_access()` answer is `'hidden'` (a private or membership folder the operator has no access to); a `'locked'` page is listed and loaded but not saved (`designer-content-roles-save-gate.md`).
- An endpoint of `pg_panel_designer()` (`includes/panel/designer.php`) that a content-level operator may call with a page id asks the same question: `page_load` answers "Page not found." and `form_fields` "Invalid ID." for a hidden page; `notes_fetch` answers "Permission denied.". A designer gets `'edit'` everywhere, so nothing changes for them.

## What did it fix?
- `page_load` and `form_fields` are on the content-level allow-list and read the page by id without asking. A user with designer access could read the whole tree, settings and form of a page in a folder they have no access to by sending its id.

## What does a new endpoint taking a page id need?
- Reading: refuse `'hidden'`. Writing: require `'edit'` (`pg_designer_revision_page_ok()`, `pg_designer_gate_tree()`).

Related: docs/memory/designer/designer-content-roles-save-gate.md
