---
name: decisions-made-schema-13
description: Decisions list, schema part 13 (2026-11-08 on): the agent channel switch on ws_channels (step AC); the channel memory table and an agent's mark (step A5, #8188)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the migration shape.

## Which tables and columns were approved?
- Agent channel switch (owner, 2026-11-08, in the architect's window; `plans/agent-channel-flag.md`): `ws_channels.agent_channel` TINYINT(1) NOT NULL DEFAULT 0, added with `install_add_column()` in a re-runnable sub-step of `2026.11.0.php`. Every existing channel starts off, so nothing changes on a running site; the `/agent-channel` command turns it on and off. Chosen over "a channel with an agent member is an agent channel": an explicit switch is a clear test for keeping the channel's decisions apart (`decisions-made-workspace-9.md`).
- Channel memory and an agent's mark (owner, 2026-11-08, #8188; step A5 of `plans/agent-channel.md`): one re-runnable sub-step `upgrade_2026_11_0_ws_channel_memory()` of `2026.11.0.php` with the `install_*` helpers, the table in `get_tables()`.
  - `ws_channel_memory`: id (PK AI), channel_id (KEY channel_id, pinned, updated_at; rows go with the channel), title VARCHAR(150), text TEXT (at most 4000 characters), tags VARCHAR(400) (comma list, lower case, at most 10 of 32), pinned TINYINT UNSIGNED DEFAULT 0, expires_at INT UNSIGNED DEFAULT 0 (KEY; 0 never; the general job deletes expired rows after 30 days), author_kind ENUM('user','app') DEFAULT 'user', author_id, updated_by_kind, updated_by_id, created_at, updated_at. Chosen over entries as `ws_notes` rows.
  - `ws_messages.marked_by_kind` ENUM('user','app') NOT NULL DEFAULT 'user' after `marked_by`, which holds the application id for an agent's mark. Existing marks read `user`. Chosen over recording an agent's mark under the application's owner, which would read as an owner decision.
  - A channel decision is not copied into the table: the digest reads the marked messages beside the entries.
