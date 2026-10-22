---
name: page-drafts
description: Drafts of visual-designer pages (2026.4.7): the private Drafts folder, page_drafts and the folder a page returns to, how a save sets the state, new pages start as drafts, Save vs Publish, copies.
---

## What is a draft?
- A page in one private folder, "Drafts" (`config.draft_folder_id`, made on first use, `pg_page_draft_folder_id()`): the router, site map and search already keep visitors out of a private folder; administrators, designers and managers can still open it. Test: `pg_page_is_draft($folder_id)`.
- `page_drafts` (page_id, folder_id, drafted_at, drafted_by) keeps the folder the page goes back to. `pg_page_draft_publish_folder($page_id)` returns it while it exists outside the recycle bin, else the top folder; new code that decides by draft state resolves the folder with it.
- Schema 2026.4.7: the config column and the table arrive together (`pg_page_draft_ready()`).

## How does a save set it?
- The editor shows and sends `page_folder` as the folder to return to everywhere, and the state as `page_draft`; both go through `pg_designer_save_page()` (`pg_designer_page_draft_plan()`), no endpoint of their own. A save without `page_draft` (install, external API, the AI assistant) and every content-level save keep the stored state; the server's default is on the site.
- The home page stays on the site (with a warning). Every move uses `pg_page_draft_move()`, also for the designs list and scheduled times (`page-schedule.md`).

## How does the editor show it?
- A page the editor creates starts as a draft: a new path that creates a tab sets `page_draft: _pgNewPageDraft()` on it.
- On a draft tab the main button is "Save", with `#sd-publish-page` "Publish" beside it (`_pgSetDraft([active key], false)`); a live tab has one button, "Publish". One save writes the whole design: Save on a draft also puts the pending changes of the live pages on the site, and the message says so.
- `_pgSetDraft()` marks nothing while shared components load or in view mode: a mark left behind would show a state the site does not have.

## What do copies and pickers do?
- Copying a page (`duplicate_page_f.php`) or a design (`duplicate_style.php`) also copies a draft's `page_drafts` row.
- Folder pickers drop only the Drafts folder's own row, not its subtree: a page in a folder made inside it would otherwise open with no folder selected and be moved by the next save.

Related: docs/memory/designer/designer-save-shortcut.md, docs/memory/designer/designer-home-screen.md
