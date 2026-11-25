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

## What does the site log say?
- `pg_designer_page_draft_log($was, $draft, $created)` picks the line: "taken off the site as a draft" only for a stored page that was on the site, "was published" only for a stored draft put back. A page the save creates gets only "was created": a new page starting as a draft was never on the site (#4494).

## How does the editor show it?
- A page the editor creates starts as a draft: a new path that creates a tab sets `page_draft: _pgNewPageDraft()` on it.
- On a draft tab the main button is "Save", with `#sd-publish-page` "Publish" beside it; a live tab has one button, "Publish". Both Publish buttons open the Publish sheet (`designer-publish-sheet.md`). Save on a draft writes the whole design: it also puts the pending changes of the live pages on the site, and the message says so.
- `_pgSetDraft()` marks nothing while shared components load or in view mode: a mark left behind would show a state the site does not have.

## Can page properties or the file explorer move a draft?
- Architect (2026-11-26, agent channel request 2989): no. A draft leaves the Drafts folder only through Publish in the Page Design Studio, past its approval, experiment and bundle holds (`prototype-approval-hold.md`); a Move is not a Publish, so neither screen calls `pg_page_draft_move()`. Bug #11702.
- Page properties (`edit_page.php`): for a draft, Folder shows `pg_page_draft_publish_folder()` with the hint "This page is a draft. It goes to this folder when it is published."; a save writes it to `page_drafts.folder_id` (an upsert: a page moved in by hand has no row), never to `page.page_folder` (`pg_page_form_folder_write()`). Posting the Drafts folder itself is refused (`pg_page_form_folder_errors()`, also on New Page).
- File explorer Move (`pg_explorer_move_page_refusal()`): a draft, and any page moved into the Drafts folder, is refused for that page with its own message; the other selected items still move.
- `tests/prototype_approval_hold_test.php` lists every direct write of `page.page_folder` and fails for a new one.

## What do copies and pickers do?
- Copying a page (`duplicate_page_f.php`) or a design (`duplicate_style.php`) also copies a draft's `page_drafts` row.
- Folder pickers drop only the Drafts folder's own row, not its subtree: a page in a folder made inside it would otherwise open with no folder selected and be moved by the next save.

Related: docs/memory/designer/designer-save-shortcut.md, docs/memory/designer/designer-home-screen.md
