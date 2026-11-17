---
name: connections-merges
description: Plan: an outside service set up in several places, merged into one only after the owner's answer each: GitHub, AI keys, mail, a provider's client, the names; steps CX1 to CX5
priority: 4
---

## What is asked?
- The owner wants connections made in one tidy place (2026-11-17, agent channel message 7359; `workflow/decisions-made-product-69.md`). The registry and the Connections screen are built (`api/connections-registry.md`, `ui/connections-screen-layout.md`); they moved no setting.
- What is still split, with every place named: `integrations-census-screens.md`.

## What binds every step?
- Each merge is asked of the owner one by one, with its own sheet, through the architect (`workflow/architect-asking.md`): no step starts before the owner's answer, and the answer decides what moves.
- One place per setting: no field is shown or saved in a second place; a card that moves keeps its address and its old anchors, a `pg_settings_legacy_anchors()` row and its place texts (owner 2026-10-30, `workflow/decisions-made-product-14.md`, `ui/settings-modal-overview.md`).
- A moved place is changed in the registry row's `place` in the same step, so the screen's button, the searches and Integrations › Related screens follow (`api/connections-registry.md`).
- No table and no column; a need for one comes back as a question to the architect. The connected accounts' rules stay (`api/api-connected-accounts.md`).
- A step with a screen change is drawn at 1440, 1024 and 390 beside its sheet; look A's recipes; texts through `lang()`, the Turkish with `php tools/tr_add.php`.

## Which steps are there?
- CX1 (M, the owner's answer first) GitHub: today five places (the provider on Connections, the "GitHub repositories" card, the GitHub MCP connector's token, the application on Application Access, the developer route). Plan `github-channel-connection.md` is separate and is not merged here.
- CX2 (M, the owner's answer first) The AI keys: today six places (the subscription key, Claude in the channels, each assistant on Workspace agents, Voice message transcripts, Translation Engines, AI programs on Application Access).
- CX3 (M, the owner's answer first) Mail: today four places (SMTP Settings, the Google or Microsoft application on Connections, Mailboxes, Communication with MailChimp).
- CX4 (M, the owner's answer first) A provider's client typed in one place: Google's client in Accounts and sign-in reused by Connections, Microsoft's the other way round, Apple's in Accounts and sign-in.
- CX5 (S, the owner's answer first) The names: one meaning each for "Connections", "Connected accounts" and "Connected applications"; help texts that say "Settings › Connections" for the Connections screen. The screen is called Connections and its provider section keeps "Connected accounts" (owner 2026-11-17, `workflow/decisions-made-product-70.md`).

## What waits for what?
- Every step waits for its own answer, nothing else; the steps are independent and may come in any order, one open at a time where two would edit the same card or screen.

## What else is still the owner's?
- The wording of the public figure, the registry's count (`pg_connections_count()`), when it is first shown outside the code (`workflow/decisions-made-product-69.md`).
- Connect with one click through a broker is not this plan: it waits for the owner's decision on Google's verification (`reverse-oauth-leftovers.md`).

## How is it closed?
- When the last step lands: its lasting rules into `ui/` and `api/`, this file deleted.
