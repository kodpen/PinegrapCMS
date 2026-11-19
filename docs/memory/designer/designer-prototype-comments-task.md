---
name: designer-prototype-comments-task
description: "Make a task" on a thread of the designer's Prototype comments list (PN4, #10459): when it shows, the task's fields, the guest's words quoted as data, a task of its own per press
module: prototype_comments
---

## When does "Make a task" show?
- On an open thread of the Prototype comments list (`designer-prototype-comments-list.md`), between Resolve and Delete, only when the list's answer says `task` true: the Workspace on and the designer a member of its team (`pg_proto_comment_task_offered_to()`; a profile can take a staff account out). Not on a resolved thread, nor on a page that is gone.
- Only the `proto_comments` answer carries `task`; the Workspace's files load for it on demand (`pg_proto_comment_workspace()`), never at editor open.
- Once pressed, the thread shows **Open task** (`workspace_tasks.php?task=`, a new tab, the editor's work stays open) until the editor is reloaded; the toast says "A task was made for this comment." with the same link.

## What does the task hold?
- Designer action `proto_comment_task` (designers only, "clean" for the page cache) calls `pg_proto_comment_task_open()`: `ws_followup_task_open()` as the designer who pressed, `$once` false, so every press and a second comment on the same page open a task of their own (P16).
- `pg_proto_comment_task_input()` (pure): title "Prototype comment on {page}", the comment's page tagged (`page`, the only tag), no channel, nobody named (the designer's own), due today, no reminder (it is due at once).
- Description: "{name} (guest, outside the team) commented on the prototype of {page}:" and the comment's words quoted line by line ("> "), so the task shows a quote and an assistant reading it sees outside text (`workspace/workspace-outside-caution.md`).

## How are the guest's words kept as data?
- `pg_proto_comment_task_words()`: `<` and `>` become ‹ ›, so no tag (`<@user:1>`, `<#order:5>`) is stored or drawn and no line leaves the quote; a line's `:::` or ``` start is dropped. The name gets the same and is one line.
- The refusals: a comment of another design ("This comment is no longer there."), the Workspace off or a non-member, a deleted page ("This page is no longer there.").

## Tested?
- `tests/prototype_comments_task_test.php`: the offer, the fields against `ws_followup_task_data()`, the quote and its render (`ws_render_body()`: a quote, no tag), `$once` false, the action and the part's button by their source.
- Not run on a site or in a browser.

Related: docs/memory/designer/designer-prototype-comments-list.md, docs/memory/workspace/workspace-followups.md
