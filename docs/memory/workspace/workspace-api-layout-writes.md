---
name: workspace-api-layout-writes
description: Workspace API layout writes the same for everybody (a channel into a group): gated like changing the channel, not ws_api_person_only(); access writes name who comes in and leaves
---

## What is not a personal preference?
- A layout that is the same for everybody, such as moving a channel into a group: no `ws_api_person_only()`; it is gated like changing the channel, with the owner's rights (an application key is accepted).
- A write that changes access names who comes in and who leaves, in the answer and in the dry run (`POST /workspace/channels/{id}/group`, as the panel's preview does). Where the panel write has no preview function, build the forecast with a function that does the sync's sums without writing (`ws_group_sync_plan()`) and answer with what the sync really did; a channel the owner cannot read is given as a count, never by name (channel group endpoints).

Related: `workspace-api-callers.md` (whose name a write carries, the person's own answers).
