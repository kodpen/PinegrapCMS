---
name: connections-merges
description: Plan: Connections becomes the one place where every outside service is set up (owner, poll 146); CX1 and CX3 stopped at their sheets, CX2 the voice card, the management screen drawn next
priority: 10
---

## What is asked?
- Built so far: the registry and the Connections screen (`api/connections-registry.md`, `ui/connections-screen-layout.md`); first asked in message 7359 (`workflow/decisions-made-product-69.md`).
- Owner (2026-11-17, poll 146, message 9372): "If we do not put these adapters in order they become a mess; it must not wait until after the launch. We should make a modern management screen where the adapters are managed in one place. This one and the others."
- The architect's reading (message 9372), the plan's rule: no more piece-by-piece merges. Connections is the one management place: everything typed to connect to an outside service (key, secret, token, account, address) sits in that service's own panel on Connections: connect, test, disconnect, where it is used.
- Feature cards (GitHub repositories, Voice message transcripts…) hold no key: they show the connection they use and lead to it.
- Scope: every service, first the three split today (GitHub, the AI keys, mail), then the other groups. Before the launch (priority 10). Every split place: `integrations-census-screens.md`.

## What binds every step?
- A drawing first: nothing moves before the owner's yes to the management screen's sheet.
- Connections holds the connection fields of each service. This replaces the 2026-10-30 reading "no setting field on Connections, a row leads to the setting's place" for connection fields; a feature's own settings stay on its card, with one place each.
- A card that moves or loses its fields keeps its address and its old anchors, a `pg_settings_legacy_anchors()` row and its place texts (owner 2026-10-30, `workflow/decisions-made-product-14.md`, `ui/settings-modal-overview.md`).
- A moved place changes the registry row's `place` in the same step (`api/connections-registry.md`).
- No table and no column without the architect: a panel that would need one (one provider key shared by several features) is named beside its drawing. The connected accounts' rules stay (`api/api-connected-accounts.md`).
- Screens drawn at 1440, 1024 and 390 in look A; texts through `lang()` and `php tools/tr_add.php`.

## Which steps are there?
- CX1 GitHub: stopped at its sheet. Drawn: agent channel messages 9308 to 9320 (screens pointing at each other); poll 146 (message 9334) answered by the broader rule above (message 9372). Its census lines are corrected in `integrations-census-screens.md`.
- CX2 Voice message transcripts: the owner chose in poll 147 (message 9350) that the card moves to Assistants and agents and the category is named "Kanallar"; the key moves to Connections in a later step.
- CX3 Mail: stopped at its sheet (messages 9351 to 9365); poll 148 withdrawn by the architect (message 9372).
- CX6 The management screen's sheet (claim, agent channel message 9373; mock, no product code): the Connections list as the one place, three service panels (GitHub, e-mail sending, OpenAI), one feature card after the change, at 1440 and 390 in Turkish, light and one dark.
- The build steps come after the owner's yes to CX6 and are cut from its drawing.
- CX4 A provider's client typed in one place and CX5 the names ("Connections", "Connected accounts", "Connected applications"): kept, likely folded into the management screen; the architect says which after CX6.

## What else is still the owner's?
- The wording of the public figure, the registry's count (`pg_connections_count()`), when it is first shown outside the code (`workflow/decisions-made-product-69.md`).
- Connect with one click through a broker is not this plan: it waits for the owner's decision on Google's verification (`reverse-oauth-leftovers.md`). Plan `github-channel-connection.md` stays separate.

## How is it closed?
- When the last step lands: its lasting rules into `ui/` and `api/`, this file deleted.
