---
name: api-oauth-consent-presets
description: OAuth consent screen presets (Workspace, Store, Accounting, All, PR #1716): what each ticks, why record writes are ticked only by hand, the propose route always ticked
---

## What does the consent screen tick?
- A preset, picked by a link to the same page (`pg_oauth_preset`, no script): Workspace, Store, Accounting, All (`pg_oauth_consent_presets()` in includes/oauth/rules.php). None named, or an unknown one, opens as All.
- The boxes ticked are the preset's scopes the client asked for (`pg_oauth_consent_ticked()`); the person may tick any other box the client asked for. Before #1716 every asked box was ticked.

## What does each preset hold?
- Every preset: `workspace:read/write`, `tasks:read/write`, so the client can always propose a record change as a card (`workspace.changes.propose`, workspace/workspace-change-proposals.md).
- Workspace: only those. Store: the reads of `pg_oauth_consent_store_groups()`. Accounting: `erp:read`, `erp_cash:read`. All: `api_assistant_scopes()` without `api_assistant_scopes_left_out()` (no account, key code, gift card, Site Log or system reads).
- A group the site lacks (module switched off) adds nothing.

## Why is no record write ticked?
- Owner, 2026-10-24: direct record writes by AI apps stay possible, but presets leave them unticked; the person ticks one by hand. No OAuth ceiling or column against them.
- A client asking for record writes only gets no box ticked: Allow refuses with `access_denied` until the person ticks one.
- tests/oauth_consent_presets_test.php guards: the propose writes in every preset, no other write, known scopes, the store groups existing.
