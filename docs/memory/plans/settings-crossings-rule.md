---
name: settings-crossings-rule
description: Plan settings-tidy-round, step TR2: the one rule for a hand-over between a Site Settings card and its Connections page (owner, poll 232), and what the return may never be
---

## What is decided?
- Owner (2026-11-26, agent channel poll 232): "Tek gidiş, kendiliğinden dönüş" (`workflow/decisions-made-product-114.md`). Where keys and switches sit does not change (`settings-crossings-census.md`, "What is decided").

## What is the rule?
- One trip: a first set-up is card, the service's page, card. A lead never lands on the Connections list and never opens a new tab.
- The page finishes it: whatever the service lacks is asked on its own page. A Google or Microsoft account service whose application is missing shows the application's fields there, the same fields and the same storage as the provider's page, by one shared function; then the sign-in, then the account.
- The page returns by itself: a lead carries where it came from. After a Save or a Connect that leaves the service ready, the person is back on that card, the dialog open on it, with a line saying what was connected. A refused Save stays on the page. Disconnect and Remove stay on the page.
- The way back is shown: a page reached from a card has "Back to {card}" at its head, beside the breadcrumb. Reached from the list it has none and behaves as today.
- Nothing is lost: the pane's unsaved fields are kept in the browser for the trip and put back when the card opens again, the pane marked as changed; a drawer the lead sat in (an assistant's) opens again with its fields.
- No empty page: a service whose page only shows a state opens its card straight from the Connections list. The page's own address still answers (old links, "Where it is used" of a provider's page).

## What must the return never be?
- An address. A lead names its card by the settings registry's keys (category, card, and a drawer's key where there is one); the page builds the link with `pg_settings_link()`. An unknown key is dropped and the page stays. No open redirect, nothing from the request printed raw.
- It travels through the provider's sign-in in the flow's own state, not in the address, so a detour or a Save on the way does not drop it. `backup_return` and `mail_return` become this one return.
- The kept fields never hold a secret: a password or key field is left out of what the browser keeps, and what is kept is dropped when the card has put it back or the session ends.

## How is it built?
- In four parts, TR3a to TR3d of `settings-tidy-round.md`, one at a time: they share `connections.php`.
- The return first (the helper every lead uses, the key check as a pure function with tests, "Back to {card}", the return after Save and Connect); then the kept fields; then the application's fields on the service's page; then the status-only pages.

## What is not in it?
- The names at both ends (`settings-crossings-census-names.md`): a word that differs is corrected in the part that touches its lead, by `php tools/tr_add.php`; no naming round of its own.
- GitHub, OneNote, the ad pixels' "On this page": no card to return to; they stay.

Related: docs/memory/plans/settings-tidy-round.md, docs/memory/plans/settings-crossings-census.md, docs/memory/ui/connections-one-place.md
