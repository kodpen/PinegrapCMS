---
name: coordinator-todo-list
description: Coordinator's to-do list (2026-10-27): one item per worker PR in the list shown in its own window, added when the draft is on GitHub, struck through once the PR is merged
---

## What is in the list?
- The coordinator keeps its workers' work in its own session's to-do list, the list shown in its window (the plan panel): one item per worker PR, naming the plan file and step (or the task) and the PR number.
- An item is added when the worker's draft PR is on GitHub, the moment the worker counts as started (`coordinator.md`); a worker's next step is a new item.

## When is an item struck through?
- When its PR is merged: the item is marked completed, which strikes it through, and stays in the list. A PR closed without merging is marked completed too, with "closed" in its text.
- The 10-minute check-in changes the list only when its listing of the open PRs changed, so a quiet check-in stays cheap.

## Why keep the list?
- The owner reads the session windows, not PR bodies (`CLAUDE.md`, Working mode 9): the list shows in one window what each worker is on and what has landed.

Related: docs/memory/workflow/coordinator.md
