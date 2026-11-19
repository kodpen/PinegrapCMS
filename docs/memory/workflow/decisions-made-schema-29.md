---
name: decisions-made-schema-29
description: Decisions list, schema part 29 (2026-11-20 on): three tables for Workspace calls: ws_calls, ws_call_members, ws_call_signals
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before is schema part 28 (decisions-made-schema-28.md).

## Which tables do Workspace calls add? (owner, 2026-11-20, poll 158, channel message 9839)
- "Evet, böyle olsun", in the open version 2026.11.0, to: `ws_calls` (a call: channel, who started it, kind voice or video, state, start and end time), `ws_call_members` (who joined and left when; a guest is a row too), `ws_call_signals` (the browsers' short-lived connection messages, deleted when the call ends and after 2 minutes).
- Said with it: sound and picture are stored nowhere and there is no recording.
- The architect's reading, not his words: the columns' names and types are the migration step's (CL1 of `plans/workspace-calls.md`), within what the poll names; a column beyond it is asked again.

Related: docs/memory/workflow/decisions-made.md, docs/memory/release/released-vs-open-version.md
