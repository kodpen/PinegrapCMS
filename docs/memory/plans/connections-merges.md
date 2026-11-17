---
name: connections-merges
description: Plan: Connections becomes the one place where outside services are set up (owner, poll 146); CX1 stopped at its sheet, CX2 the voice card, CX3 E-mail sending, the management screen drawn next
priority: 10
---

## What is asked?
- Built so far: the registry and the Connections screen (`api/connections-registry.md`, `ui/connections-screen-layout.md`); first asked in message 7359 (`workflow/decisions-made-product-69.md`).
- Owner (2026-11-17, poll 146, message 9372): "If we do not put these adapters in order they become a mess; it must not wait until after the launch. We should make a modern management screen where the adapters are managed in one place. This one and the others."
- The architect's reading (message 9372), the plan's rule: no more piece-by-piece merges. Connections is the one management place: everything typed to connect to an outside service (key, secret, token, account, address) sits in that service's own panel on Connections: connect, test, disconnect, where it is used.
- Feature cards (GitHub repositories, Voice message transcripts…) hold no key: they show the connection they use and lead to it.
- Narrowed by poll 148 (message 9374): the sending service's key and the SMTP server stay in Site Settings › E-mail sending; Connections shows e-mail sending as a row whose Manage opens it. Google's and Microsoft's application and accounts are managed on Connections.
- Scope: every service, first GitHub, the AI keys and mail, then the other groups. Before the launch (priority 10). Every split place: `integrations-census-screens.md`.

## What binds every step?
- A drawing first: nothing moves before the owner's yes to the management screen's sheet.
- Connection fields live on Connections, replacing the 2026-10-30 reading "no setting field on Connections"; a feature's own settings stay on its card.
- A moved card keeps its address and old anchors: a `pg_settings_legacy_anchors()` row (owner 2026-10-30, `workflow/decisions-made-product-14.md`).
- A moved place changes the registry row's `place` in the same step (`api/connections-registry.md`).
- No table and no column without the architect: a panel that would need one (one provider key shared by several features) is named beside its drawing. The connected accounts' rules stay (`api/api-connected-accounts.md`).
- Drawn at 1440, 1024 and 390 in look A; texts through `lang()`.

## Which steps are there?
- CX1 GitHub: stopped at its sheet. Drawn: messages 9308 to 9320; poll 146 (message 9334) answered by the rule above.
- CX2 Voice message transcripts: Owner (poll 147, message 9350): the card moves to Assistants and agents, the category is named "Kanallar"; the key moves to Connections later. Drawn: messages 9321 to 9339; built.
- CX3 Mail, as its sheet (messages 9351 to 9365). Owner (2026-11-17, poll 148, message 9374): a Site Settings category "E-posta gönderimi" between Communication and Mailboxes; SMTP Settings leaves System tools, its old addresses open it; the campaign's two texts and MailChimp join it. Two steps, CX3a and CX3b.
- CX6 The management screen's sheet (mock, no product code): the Connections list as the one place, three service panels (GitHub, Google, OpenAI), e-mail sending as a list row, one feature card after the change. Drawn: agent channel message 9376 and replies; the owner's look: waiting.
- The build steps come after the owner's yes to CX6 and are cut from its drawing.
- CX4 A provider's client typed in one place and CX5 the names ("Connections", "Connected accounts", "Connected applications"): wait for the owner's look at CX6's mock.

## What else is still the owner's?
- The public figure's wording and when it is first shown (`workflow/decisions-made-product-69.md`).
- Connect with one click through a broker is not this plan: it waits for the owner's decision on Google's verification (`reverse-oauth-leftovers.md`). Plan `github-channel-connection.md` stays separate.

## How is it closed?
- When the last step lands: its lasting rules into `ui/` and `api/`, this file deleted.
