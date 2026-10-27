---
name: workspace-checklist-to-task
description: A checklist item of a Workspace message made into a task (ws_checklist_item_task(), step 9b): read as /task reads it, item tied by message + words, button, tag, edits, rights, API
---

## What does it do?
- A checklist item (`- [ ] …`) of a channel message, note or decision gets a small "Make a task" button (`.ws-check-make`, shown on hover, always on a touch screen); the message's context menu on an item offers the same ("Make this item a task"). Both call panel action `ws_check_task` (`message_id`, `item`).
- `ws_checklist_item_task()` (`includes/workspace/task_work.php`) reads the item's words with `ws_checklist_item_task_fields()`: `ws_command_parse_task()` plus `ws_command_typed_assignees()`, so "Call Ayşe tomorrow @ahmet 1.5 saat" gets the due day, ahmet and 90 minutes as /task would. A task given to nobody is the writer's own.
- The task is made with `ws_task_create()` in the message's channel (a discussion's task goes to its channel, `ws_task_validate()`), `source_message_id` = the message, `description` = the item as written. No task card is posted.

## How does an item know its task?
- No schema: by the message (`source_message_id`) and the item's words (the task's `description`). `ws_checklist_item_tasks_pair()` gives each task, oldest first, the first item with its words no earlier task holds, the way ticks follow an edit (`ws_checks_remap_in()`).
- So an edit that moves an item keeps its task; one that changes its words drops the tag and offers the button again (the old task stays). Changing the task's description, or moving it to another channel, also unties it.
- `ws_checklist_item_tasks_map()` reads one query per page, narrowed by `channel_id` (indexed; `source_message_id` is not). Other tasks with a `source_message_id` point at their card message, whose body is empty, so they never pair.

## How is it drawn?
- `ws_message_payloads()` adds the paired tasks' `<#task:N>` to the batch `ws_refs_resolve()`, so each reader gets the task's tag as their rights allow (a muted chip when they may not see it). `ws_message_payload()` passes `item_tasks` (tasks, make) to `ws_render_body()`'s sixth argument; `ws_check_task_html()` (render.php) draws the tag (`.ws-check-task`) or the button.
- Notes, task descriptions, guest pages and the drawer's list leave the argument out and draw checklists as before.

## Who may?
- `ws_checklist_item_task_offered()`: the reader may post in the channel (not an earlier era), the message is a person's or an application's `message`/`note`/`decision`, not deleted, with a checklist. `ws_checklist_item_task_check()` adds: the item exists, has no task yet, gives a title, `ws_can_assign_to()`, no hard leave clash (refused; /task shows it and may force), then `ws_task_create_check()`.

## API?
- `POST /workspace/messages/{id}/item-task` (`workspace.messages.item_task`, `tasks:write`, `dry_run`, 201 `WorkspaceTask`) runs the same check; an item already made into a task is 422, so a retry does not make two.

## Tests?
- `tests/workspace_item_task_test.php`: the fields read from an item, pairing after edits and duplicates, which messages offer it, the tag and button drawn. The action and the button were not run on a live instance.
