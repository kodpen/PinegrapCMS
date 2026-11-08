---
name: workspace-notes-groups-lock-steps
description: Steps N0 and G1-G7 of plans/workspace-notes-groups-lock.md: schema, groups store and screen, drag and drop, tabs, shared groups, API; claim as [plan:workspace-notes-groups-lock] <step>
---

## Which steps are there?
- S under 400 product lines, M about 1,000. Each step writes its tests and its topic file in `workspace/`, texts through `tr_add.php`, new actions also in `includes/panel/actions.php`. A new script joins the workspace bundle (`pg_js_bundles()`) and is hooked into `startNotes()` with a few lines.

- N0 (S) Schema. Done (PR #8027). One re-runnable sub-step of `2026.11.0.php` under the next free Workspace label, shapes in `workflow/decisions-made-schema-12.md`; `get_tables()`; `ws_note_groups_ready()`, `ws_note_lock_ready()`. Rerun twice on a dev DB.

- G1 (M) Groups store. Done (PR #8044). New `includes/workspace/note_groups.php`: a person's groups with counts; create, rename, colour (`ws_palette()`), order, delete (notes back to no group, never deleted); a note placed in a group or none and ordered in it; `ws_notes_list()` answers the groups and each note's group for the viewer; actions `ws_note_group_*`; at most 200 groups, names of 100 characters.

- G2 (M) Groups on the screen. Done (PR #8065). New `assets/js/workspace_note_groups.js`: folders in My Notes as `-design.md` draws them, open or closed kept in the browser, New group, the group's menu, the note menu's Move to group, search answered inside groups, phone.

- G3 (M) Drag and drop. After G2. Same file: notes into, out of, along and between groups; groups in the list; the questions a shared or a locked group asks (`-design.md`); Move to group and a long press as the keyboard and touch ways.

- G4 (M) Tabs. Done (PR #8095). New `assets/js/workspace_note_tabs.js`: an open note's group as tabs over it (tablist, the group's order, lock and shared marks, + for a new note in the group, arrow keys, a tab dragged along changes the order, sideways scroll on a phone); the note is saved before switching, as a list click does.

- G5 (M) Shared groups, store. Done (PR #8083). New `includes/workspace/note_group_shares.php`: a group shared with people (read or edit) or in a channel (one card for the group, none per note) through `ws_note_shares` rows with `via_group`, kept in step when a note goes in or out, is deleted or locked, a share is taken back or the group deleted; direct shares stay. People with edit put in, order and take out their own notes, the owner any; only own notes go into a shared group; one who leaves or loses access takes their notes out. One bell line per group shared or note added; note folders follow `ws_note_folder_sync()`; deletions kept for sync as `ws_note_deletion_rows()` does.

- G6 (M) Shared groups, screen. After G2 and G5. The group's Share dialog (people, read or edit, a channel, who has it, take back), shared groups under Shared with me, the channel card of a group listing its notes, `workspace_notes.php?group=N`.

- G7 (M) API and MCP. Done (PR #8114); `workspace/workspace-note-groups-api.md`. New `includes/workspace/api_note_groups.php`: list, create, change and delete groups, place a note, each with dry run through the panel's `*_check()`, MCP tools as the note routes have; `group_id` on note objects; `check_api_schema.php`. Sharing stays on the screen, as for notes.

## Where does the next session start?
- N0, G1, G2, G4, G5, G7 done; G3 (#8104) and G6 (#8107) open. G3: drag and drop, same file `assets/js/workspace_note_groups.js`; the rows come from `drawItem()` in `startNotes()`, placing goes through `ws_note_place` with `position`. API of groups: `workspace/workspace-note-groups-api.md`. Tabs: `workspace/workspace-note-tabs.md`. Screen notes: `workspace/workspace-note-groups-screen.md`; store: `workspace/workspace-note-groups.md`.
