---
name: decisions-made-schema-16
description: Decisions list, schema part 16 (2026-11-11 on): ws_mail_items.body_html, kind mailbox on ws_channels, ws_agents.seen_at, ws_checks.app_id, ws_ai_requests.held_until for the edit hold
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Which tables and columns were approved?
- A mail's HTML and a mailbox kept in no channel (owner, 2026-11-11, #8765; step WM11 of `plans/workspace-mail-screen-steps-2.md`; the behaviour: `decisions-made-workspace-17.md`): `ws_mail_items.body_html` MEDIUMTEXT NULL by `install_add_column()`, and `ws_channels.kind` widened by the value `mailbox` only when it is missing, as for `guest` (2026.4.5) and `thread` (2026.4.8); a re-runnable sub-step of 2026.11.0.
  - Owner (2026-11-11), his vote on the one-choice poll in the agents channel: "Onaylıyorum (Önerilen)". Not chosen: "Yalnızca HTML sütunu"; "Değişiklik istiyorum".
  - `body_html` is the mail's HTML after cleaning, NULL for a mail with none or one over the limit; the plain `body_text` stays what the search and the card read.
  - `mailbox` is the room of a box kept in no channel: no channel list, picker or search shows it; who may read it is its membership, as for any channel.
- An agent's last seen and an application's tick (owner, 2026-11-12, #8762; the behaviour: `decisions-made-workspace-16.md`): `ws_agents.seen_at` and `ws_checks.app_id`, each INT UNSIGNED NOT NULL DEFAULT 0 by `install_add_column()`, in a re-runnable sub-step of 2026.11.0.
  - Owner (2026-11-12), in the architect's window: "Onaylıyorum (Önerilen)". Not chosen: "Yalnızca son görülme sütunu"; "Değişiklik istiyorum".
  - `seen_at` is the time of the agent's last call with its key, written at most once a minute, 0 for an agent never seen. `app_id` is the application that set the tick, 0 when a person did (`user_id`).
- The edit hold of a request (owner, 2026-11-12, #8870; step A10 of `plans/agent-channel-steps-3.md`; the behaviour: `decisions-made-workspace-14.md`, #8723): `ws_ai_requests.held_until` INT UNSIGNED NOT NULL DEFAULT 0 by `install_add_column()` in a 2026.11.0 step, no index.
  - Owner (2026-11-12), asked with the three stores: "Sütunu ekle (Önerilen)". Not chosen: a file per message under `data/`; a column on `ws_messages`.
  - The beat sets now + 120 for the message's open requests, save and cancel set 0, every picker reads `held_until <= now`. Existing rows get 0: nothing is held after the upgrade.
