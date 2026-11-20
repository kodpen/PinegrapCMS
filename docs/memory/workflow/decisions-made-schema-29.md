---
name: decisions-made-schema-29
description: Decisions list, schema part 29 (2026-11-20 on): Workspace calls tables; content and component_id on auto_dialogs; prototype_links.bundle_id
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before is schema part 28 (decisions-made-schema-28.md).

## Which tables do Workspace calls add? (owner, 2026-11-20, poll 158, channel message 9839)
- "Evet, böyle olsun", in the open version 2026.11.0, to: `ws_calls` (a call: channel, who started it, kind voice or video, state, start and end time), `ws_call_members` (who joined and left when; a guest is a row too), `ws_call_signals` (the browsers' short-lived connection messages, deleted when the call ends and after 2 minutes).
- Said with it: sound and picture are stored nowhere and there is no recording.
- The architect's reading, not his words: the columns' names and types are the migration step's (CL1 of `plans/workspace-calls.md`), within what the poll names; a column beyond it is asked again.

## Which columns does PU6 add to `auto_dialogs`? (architect, 2026-11-20, agent channel, answer to K2's request 1584)
- `content` VARCHAR(10) NOT NULL DEFAULT 'url' after `place` (url or component, checked in code; missing or unknown reads as url) and `component_id` INT UNSIGNED NOT NULL DEFAULT 0 after it; no index, no backfill, no foreign key.
- Both are named by `plans/auto-dialog-popups.md` under the owner's yes to new columns (channel message 5139); not chosen: `component_id` alone, as a row keeps its address while it shows a component.

## Does `prototype_links` get a column for a release bundle's share link? (owner, 2026-11-20, poll 159, channel message 9931)
- "Evet, eklensin", to: `bundle_id` (INT, default 0: not tied to a bundle) on `prototype_links`, a table the open version 2026.11.0 added; existing links do not change.
- Not chosen: no share link for a bundle's preview.
- The architect's reading, not his words: INT UNSIGNED NOT NULL DEFAULT 0 with KEY `idx_bundle`, written into the table's CREATE of step 11.0.35 and added by `install_add_column()` where that step already ran; RB6 of `plans/release-bundle-steps.md`.

Related: docs/memory/workflow/decisions-made.md, docs/memory/release/released-vs-open-version.md
