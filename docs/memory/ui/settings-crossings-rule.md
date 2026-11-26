---
name: settings-crossings-rule
description: The rule for a hand-over between a Site Settings card and its Connections page (owner, poll 232): one trip, the page finishes it, it returns by itself, nothing lost, no empty page
module: connections_screen
---

## What is decided?
- Owner (2026-11-26, agent channel poll 232): "Tek gidiş, kendiliğinden dönüş" (`workflow/decisions-made-product-114.md`), after the crossings census (#11829) found seven hops for a first Backups set-up and every return to the card done by hand.
- Where keys and switches sit stays: keys on the service's page, behaviour on the card, which shows the connection and leads to it (poll 146, `-76.md`; poll 149, `-77.md`); a feature not connected cannot be switched on (`-77.md`); E-mail sending keeps its keys and SMTP in Site Settings (poll 148, `-76.md`); Claude and the MCP servers on Connections (poll 222, `-100.md`); anchors and keys kept (`-14.md`).

## What is the rule?
- One trip: a first set-up is card, the service's page, card. A lead never lands on the Connections list and never opens a new tab.
- The page finishes it: whatever the service lacks is asked on its own page; a Google or Microsoft account service with no application shows the application's fields there (`connections-page-finishes.md`).
- The page returns by itself: after a Save or Connect that leaves the service ready, the person is back on the card with a line saying what was connected. A refused Save, Disconnect and Remove stay on the page (`connections-return-to-card.md`).
- The way back is shown: "Back to {card}" at the head of a page reached from a card; reached from the list, none.
- Nothing is lost: the pane's unsaved fields and open drawers are kept in the browser for the trip and put back (`connections-lead-keeps-pane.md`).
- No empty page: a service whose page would only show its state opens its card straight from the list; the page's address still answers (`connections-list-opens-card.md`).

## What must the return never be?
- An address: a lead names its card by the settings registry's keys (`from=<category>/<card>[/<drawer>]`), the page builds the link with `pg_settings_link()`, an unknown key is dropped. No open redirect, nothing from the request printed raw.
- Lost on the sign-in: it travels in the flow's own state. `backup_return` and `mail_return` are read only from old links.
- A secret: password and key fields are never kept in the browser.

## What does a new lead do?
- Built with `pg_connections_lead_url()`, in the same tab, from inside the dialog; a lead in a drawer carries the drawer's id. Nothing else is needed for the return, the kept fields or "Back to {card}".

## What was left out?
- GitHub's repository links, OneNote, Meta CAPI, TikTok Events and GA4 MP ("On this page"): no card to return to.
- The names at both ends: no naming round. The page's hand-written "Where it is used" lines in `connections.php` are typed, not read from `pg_settings_categories()`, so a renamed card leaves them behind.
- Still differing: Turnstile and hCaptcha say "Accounts and sign-in › Spam Protection" (card "Forms and spam"), Claude "Assistants and agents › Claude" (card "Claude in the channels"), Google Analytics "Visitor Tracking" (card "Visitor tracking"). A step that touches such a lead corrects its word with `php tools/tr_add.php`.

## Where was it built?
- #11862 the return, #11880 the kept fields, #11894 the page finishing, #11911 the list opening the card; one at a time, as they share `connections.php`.

Related: docs/memory/ui/connections-one-place.md, docs/memory/ui/connections-return-to-card.md, docs/memory/workflow/decisions-made-product-114.md
