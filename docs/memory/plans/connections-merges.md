---
name: connections-merges
description: Plan: Connections becomes the one place where outside services are set up (owner, poll 146); CX2 the voice card, CX3 E-mail sending, the screen accepted in poll 149, the build CX7 to CX11
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
- The owner's yes to the management screen's sheet (poll 149) is given; a page beyond the sheet is drawn and asked first.
- Connection fields live on Connections, replacing the 2026-10-30 reading "no setting field on Connections"; a feature's own settings stay on its card.
- A moved card keeps its address and old anchors: a `pg_settings_legacy_anchors()` row (owner 2026-10-30, `workflow/decisions-made-product-14.md`).
- A moved place changes the registry row's `place` in the same step (`api/connections-registry.md`).
- No table and no column without the architect: a panel that would need one (one provider key shared by several features) is named beside its drawing. The connected accounts' rules stay (`api/api-connected-accounts.md`).
- Drawn at 1440, 1024 and 390 in look A; texts through `lang()`.

## Which steps are there?
- CX1 GitHub: Done by the build steps CX7 to CX11 (sheet: messages 9308 to 9320).
- CX2 Voice message transcripts: to Assistants and agents, the category named "Kanallar" (poll 147, message 9350); the key moves in CX9. Done in #10332.
- CX3 Mail. Drawn: messages 9351 to 9365. Poll 148 (message 9374): a Site Settings category "E-posta gönderimi" between Communication and Mailboxes; SMTP Settings leaves System tools; the campaign's two texts and MailChimp join it.
  Done in three steps: CX3a the logic into `includes/fn/mail_settings.php` (#10334); CX3b the category `mail_sending` with SMTP Settings' five cards, the old addresses opening it (#10404); CX3c Communication's Opt-In Label, Plain Text Footer and MailChimp card, with the Connections list's one mail row (#10434).
- CX6 The management screen's sheet. Drawn: messages 9405 to 9422; the owner's yes: poll 149 (message 9425). Done.
- The build, CX7 to CX11 (poll 149's yes, messages 9423 and 9425): `connections-merges-build.md`.
- CX4 A provider's client typed in one place: Done, answered by CX10. CX5 the names ("Connections", "Connected accounts", "Connected applications"): waits for the pages.

## What else is still the owner's?
- The public figure's wording and when it is first shown (`workflow/decisions-made-product-69.md`).
- Connect with one click through a broker is not this plan: it waits for the owner's decision on Google's verification (`reverse-oauth-leftovers.md`). Plan `github-channel-connection.md` stays separate.

## How is it closed?
- When the last step lands: its lasting rules into `ui/` and `api/`, this file deleted.
