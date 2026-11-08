---
name: decisions-made-schema-13
description: Decisions list, schema part 13 (2026-11-08 on): the agent channel switch on ws_channels (agent channel step AC)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the migration shape.

## Which tables and columns were approved?
- Agent channel switch (owner, 2026-11-08, in the architect's window; `plans/agent-channel-flag.md`): `ws_channels.agent_channel` TINYINT(1) NOT NULL DEFAULT 0, added with `install_add_column()` in a re-runnable sub-step of `2026.11.0.php`. Every existing channel starts off, so nothing changes on a running site; the `/agent-channel` command turns it on and off. Chosen over "a channel with an agent member is an agent channel": an explicit switch is a clear test for keeping the channel's decisions apart (`decisions-made-workspace-9.md`).
