---
name: workspace-command-status
description: /status (/durum) in a Workspace channel (PR #1896): the status words it reads, ws_task_set_status(), the lines it posts, its live line and the statuses popup
---

## What does /status (/durum) do? (PR #1896)
- `/durum G-42 bekliyor`: `ws_command_parse_status()` takes the task (`ws_command_task_ref()`) and the first word; `ws_parse_status_word()` reads yapılacak/todo, sürüyor/devam/doing, bekliyor/waiting, bitti/tamam/done, iptal/cancelled (folded, any case).
- `ws_command_status()` writes through `ws_task_set_status()`. It posts "X set G-42 to Waiting" in the channel where it was written, except when a task becomes done in its own channel: `ws_task_set_status()` already posts that line there. With no word, it shows the status to the writer only. With the task's own status, it answers "already". An unknown word is refused with the words it reads.

## How is it offered?
- Registry entry `status` (commands.php): args `task` (suggest tasks) and `status` (kind status, suggest `statuses`, from `ws_command_suggestions()`: yapılacak sürüyor bekliyor bitti iptal / todo doing waiting done cancelled, labels as meta). `live` is set, so the composer shows `ws_command_status_suggest()` (workspace-command-live-line.md).

## Tests?
- tests/workspace_commands_test.php: names, parse, every suggested word read, the live commands. Not run on a live instance.
