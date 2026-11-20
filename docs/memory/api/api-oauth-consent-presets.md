---
name: api-oauth-consent-presets
description: OAuth consent screen presets (Workspace, Design, Content, Store, Accounting, All; #1716, #10709): what each ticks, why record writes are ticked by hand
---

## What does the consent screen tick?
- A preset, picked by a link to the same page (`pg_oauth_preset`, no script): Workspace, Design, Content, Store, Accounting, All (`pg_oauth_consent_presets()` in includes/oauth/rules.php). None named, or an unknown one, opens as All.
- The boxes ticked are the preset's scopes the client asked for (`pg_oauth_consent_ticked()`); the person may tick any other box the client asked for. Before #1716 every asked box was ticked.

## What does each preset hold?
- Every preset: `workspace:read/write`, `tasks:read/write`, so the client can always propose a record change as a card (`workspace.changes.propose`, workspace/workspace-change-proposals.md).
- Workspace: only those. Design (#10709): the reads of `pg_oauth_consent_design_groups()`: design, pages, files, experiments. Content (#10709): the reads of `pg_oauth_consent_content_groups()`: pages, files, calendars, forms, seo, content_review, comments, translations (a module's group).
- Store: the reads of `pg_oauth_consent_store_groups()`. Accounting: `erp:read`, `erp_cash:read`. All: `api_assistant_scopes()` without `api_assistant_scopes_left_out()` (no account, key code, gift card, Site Log or system reads).
- A group the site lacks (module switched off) adds nothing.
- The presets are the MCP tool profiles (#2422): no per-connection tool groups, no schema.

## Why is no record write ticked?
- Owner, 2026-10-24: direct record writes by AI apps stay possible, but presets leave them unticked; the person ticks one by hand. No OAuth ceiling or column against them.
- A client asking for record writes only gets no box ticked: Allow refuses with `access_denied` until the person ticks one.
- tests/oauth_consent_presets_test.php guards: the propose writes in every preset, no other write, known scopes, the store, design and content groups existing with a read.
- Design and Content are the AI guide's area profiles (plan ai-guide, G15): an agent working on the designer or the site's content gets that area's reads; `design:write` (proposals) stays ticked by hand like every record write.
