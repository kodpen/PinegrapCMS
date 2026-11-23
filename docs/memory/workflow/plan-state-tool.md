---
name: plan-state-tool
description: tools/plan_state.php <plan> [--tasks]: step states from PRs (draft held, open ready, merged done), written as agent channel tasks "<plan> <step>"
---

## What does it print?
- `php tools/plan_state.php <plan file or name>` reads the PRs titled `[plan:<plan>] <steps> — <topic>` (`.md` or not) through `gh api`: every open PR, and the merged ones the issue search finds by the plan's name (3 pages of 100).
- One line a step: `<step> <state> #<n> <PR address>`. A draft is `held`, an open PR `ready`, a merged one `done`; a PR closed without a merge counts for nothing. A step the plan file lists (`- <step> (` or `- <step>:`) with no PR prints `<step> todo`.
- Of several PRs of one step, done wins over ready over held, then the newest number.
- Steps are read from the title before the dash: `PS1, PS2`, `PS1 and PS2`, `PS1-PS3` (a range of one prefix), `A4b2`. The topic after the dash is not read.

## What does --tasks write?
- One task a step in the agent channel (`PG_WS_SITE`, `PG_WS_KEY`, `PG_WS_SECRET`, `PG_WS_CHANNEL` or `--channel`, `tools/ws_client.php`), titled `<plan> <step>`: status todo (no PR), doing (held), waiting (ready), done; the PR's address as its description.
- The channel's tasks are read once (`GET /workspace/tasks?scope=channel&status=all&agent_channels=include`, at most 250). A missing task is created (`POST /workspace/tasks` with `channel_id`); an existing one gets only the fields that differ (`POST /workspace/tasks/{id}`). Of two tasks with one title, the lower id.
- A step with no PR only creates its task; it never changes one that is there (a person may have moved it). A cancelled task is left alone unless its step is done.
- Every write sends an Idempotency-Key and is repeated once with it after no answer (0, 502, 504). Nothing secret is printed.

## What are the exits?
- 0 printed (and written); 1 GitHub or the site refused, or gave no answer (the other tasks are still tried); 2 usage or a missing setting.
- Pure parts tested in `tests/plan_state_tool_test.php`.

Related: docs/memory/plans/plan-state-channel.md, docs/memory/workflow/agent-channel-note-tool.md
