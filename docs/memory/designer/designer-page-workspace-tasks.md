---
name: designer-page-workspace-tasks
description: The visual designer tab menu's "Workspace tasks" item: the Workspace record drawer of page:<id> (tasks, open one, new task tagged with the page), its gates and wiring
---
## What does "Workspace tasks" open?
- The tab menu item below "Page Settings" opens the same Workspace drawer the order, product and contact screens carry (`ws_record_button()`, `openRecord()` in `assets/js/workspace.js`) for `page:<page_id>`: the tasks tagged with the page (open, with the task drawer), "New task" tagged `<#page:id>` with the page name as title, the channel messages that mention the page and "Share in a channel".
- A task points at a page through `ws_refs` (`ref_type = 'page'`), written by `ws_refs_store()` when the task's refs or text carry the tag; `ws_record_refs()` lists only what `ws_can_see_task()` / `ws_can_read_channel()` admit, so the rights are the task screens' own.

## When is it offered?
- `designer_screen.php` prints `ws_record_drawer_assets($user, 'page')` (screen.php: the gates of `ws_record_button()` without the button: Workspace installed and on, a team member, `page` in `ws_ref_types()`) and sets `sdDesign.workspaceTasks`. Not for a shared component opened alone.
- In the menu: a saved page (`page_id > 0`) that is not a page picked from another design and still pending.

## How is it opened?
- `_pgTabsOpenWorkspaceTasks(p)` clicks a detached `<a data-ws-record="page:id" data-ws-label="name">`; `ws_record_loader()` fetches `workspace.min.js` on the first click and hands it the click (`PGWsPendingRecord`). The item does not switch tabs.
- The drawer skips its fields section for a kind outside `BOOT.quick_edit` (a page, an invoice): it used to print "Invalid request." under an empty title.

## Where did it come from?
- The last item of the Workspace phase 3 leftovers plan (retired with this PR); the other items: app handles plan, app commands (#2213), `workspace.app.mentioned` (#809), ERP overdue tasks (#1860).
- Checked by `tests/designer_page_tasks_test.php` (wiring). Not run in a browser.
