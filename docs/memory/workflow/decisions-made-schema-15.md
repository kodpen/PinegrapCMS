---
name: decisions-made-schema-15
description: Decisions list, schema part 15 (2026-11-10 on): ws_messages.via_client_id (the "via" badge of a connected application's message), ws_ai_requests.claimed_device_id (per-person @Claude sessions)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Which tables and columns were approved?
- A connected application's message and per-person `@Claude` sessions (owner, 2026-11-10, #8490; step A8a of `plans/agent-channel-steps-2.md`; the behaviour: `decisions-made-workspace-11.md`): `ws_messages.via_client_id` and `ws_ai_requests.claimed_device_id`, each INT UNSIGNED NOT NULL DEFAULT 0 by `install_add_column()`, in a re-runnable sub-step of 2026.11.0.
  - `via_client_id` is the `oauth_clients` id of the connection that wrote the message, 0 for a message not written through one. The client row is named, not the `api_devices` row: a person's device rows give way past 20, the client stays.
  - `claimed_device_id` is the `api_devices` id of the connection that claimed the request, 0 when the site's routine takes it.
  - No backfill and no index. Both tables exist on released sites (created in 2026.4.4), so the step only adds the columns.
