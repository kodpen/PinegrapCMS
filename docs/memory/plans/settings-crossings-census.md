---
name: settings-crossings-census
description: Census of the crossings between Site Settings cards and Connections pages (plan settings-tidy-round, TR1): their shared shape, what is decided, which file holds what
---

## What is this?
- Step TR1 of `settings-tidy-round.md`: every Site Settings card (and the few other screens) that leads to a Connections page, and every service page of the registry, read as one path. Read on development 2026-11-26; paths under `pinegrap/`. No code changed.
- The files: the leads card by card `settings-crossings-census-leads.md`; each page's way back and where Connect, Save and Disconnect leave the person `settings-crossings-census-pages.md`; the names at both ends `settings-crossings-census-names.md`; first set-ups feature by feature `settings-crossings-census-setups.md`; round trips with nothing to do at one end `settings-crossings-census-roundtrips.md`; the ten worst paths `settings-crossings-census-worst.md`.

## What shape do all crossings share?
- A card leads with a quiet button "Manage in Connections" (connected) or "Connect in Connections" (not), to `connections.php?service=<key>`, in the same tab, and the Site Settings dialog is left. Exceptions: `settings-crossings-census-leads.md`.
- A page leads back through its "Where it is used" card: one row linking to `settings_<category>.php#<card>` (`pg_settings_link()`), which opens the dialog on that card. A page whose settings stay on a card shows "Open its settings" under Status instead.
- Save, Save and try, Disconnect and Remove on a page post `connections_return` and come back to the same page (`connections.php:1667-1673`). Nothing returns to the card by itself.
- Connect (an account through the provider's sign-in) comes back to the page, but for two: Backups (`&backup_return=1`) and Mailboxes (`&mail_return=`) return to their card (`connections.php:4950-4954`, `5197-5201`).
- The card refuses its switch while the service is not connected ("Set up the {var:1} keys on its page of Connections first.", "Connect {var:1} in Connections first."), so a first set-up is always card, page, card.
- A link out of the dialog asks nothing about unsaved changes: only closing the dialog asks (`backend.src.js:12429-12444`, no `beforeunload`). A choice made on the card before following its lead is lost.
- Breadcrumb on every page: System tools › Connections › the service (`connections.php:4933`), whichever card the person came from.

## What is decided, and so no fault here?
- Keys on the service's page, behaviour (switch, choice, texts) on the card, the card showing the connection and leading to it: owner poll 146 (`workflow/decisions-made-product-76.md`) and poll 149 (`-77.md`).
- A feature not connected cannot be switched on: poll 149 (`-77.md`). The card, page, card order of a first set-up follows from it.
- E-mail sending keeps its keys and SMTP in Site Settings › E-mail sending, and the Connections list folds its seven routes into one row that opens the category: poll 148 (`-76.md`).
- Claude's routine and the MCP servers on Connections, a leading line left on the Assistants and agents tab: poll 222 (`-100.md`).
- Old anchors and settings keys kept, one Save a pane, the "Connections" row above "System tools" in the Site Settings list: `-14.md`, `-77.md`.
- Application Access stays a screen of its own beside Connections, each linking the other: `-88.md`.
- What TR2 may still choose: how each end hands over (where the person stands after Save or Connect, which way back is offered, the label words, the new tab). None of it is an owner decision today.

Related: docs/memory/plans/settings-tidy-round.md, docs/memory/ui/connections-one-place.md, docs/memory/ui/connections-service-page.md
