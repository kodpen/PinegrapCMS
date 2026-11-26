---
name: settings-crossings-census-worst
description: Crossings census, the ten worst paths between Site Settings and Connections, ranked by hops, lost work and dead ends, each with what makes it bad; the examples TR2 asks the owner with
---

## Which ten paths are worst? (hops as in `settings-crossings-census-setups.md`)
1. Backups to Google Drive or OneDrive, first time (7 hops): Backups card › drive page, whose Connect is disabled and which says accounts are connected on Google's page › Google page (application) › back to the drive page by hand, `backup_return` gone › sign-in › drive page › "Open its settings" › card to pick account and folder.
2. Sending mail through Gmail or Microsoft 365, application missing (5-6): E-mail sending › "Set up the application on Connections first." lands on the list › Google's row › Google page › no way back but the list's "E-mail sending" row or a status page's "Open its settings" › the category's Connect. E-mail sending's own place is decided (poll 148); the lead to the list is not.
3. Merchant Center, first time (6): Product Feeds › Merchant page › Google page › Merchant page › sign-in › Merchant page (account) › Product Feeds for the feed itself.
4. Mailboxes through Gmail, application missing (5): Mailboxes › Google page `#connections-mail` › Save the application (`mail_return` gone) › sign-in › Google page › "Mailboxes" › the box's form to pick the account.
5. Google Analytics figures (5): Visitor tracking › GA page › Google page › GA page › sign-in › GA page. Nothing left to do on the card, and the page invites the trip back.
6. Sign in with Microsoft (4): the card's "Set up in Connections" opens Microsoft's page with no anchor; back is through Where it is used › the Sign in with Microsoft status page › "Open its settings". Google's sign-in has its anchor and an "Accounts and sign-in ›" button; Microsoft's has neither.
7. Payment Methods, a new gateway (3-4, work lost): picking the gateway shows its lead; following it drops the unsaved pick without asking; the card refuses Save until the keys are on the page; back on the card the gateway is picked again. Shipping, SMS and Address Autocomplete repeat it with their provider select.
8. Workspace agents, a new provider (3, work lost): the lead sits in the assistant's drawer; leaving drops the drawer's fields; the page's rows lead to the Workspace agents card, not the assistant; the drawer is opened and filled again.
9. Done by TR3d (`ui/connections-list-opens-card.md`). Status-only pages from the list (2 for 1, 28 services): list › page that says "here only its state is shown" › "Open its settings" › card; Sign in with Google and Microsoft, the mail routes and the drives stand beside the provider page that holds their application, three places for one service.
10. MCP connectors (2, a second tab): MCP servers opens the connector's page in a new tab; after connecting, the first tab's MCP servers row still shows the old state until reloaded, and the page's way back opens Site Settings again in the second tab.

## What do the ten share?
- Nothing returns to the card after Save; only two Connects do, and both lose that on a detour (1, 4).
- A third page (Google's or Microsoft's) sits in the middle of every account service (1 to 6); the service's page and the provider's page each hold part.
- The dialog's unsaved-changes question does not guard its leads (7, 8).
- Of the other panes the plan names, SEO (IndexNow), Languages and Translation (Translation), Integrations (Google Reviews) and Channels (GitHub) cross by a pointer or key-then-back; Team only through the calendars' status pages; Fediverse not at all. None is among the ten, so they follow TR5 (`settings-tidy-round.md`).
- What is decided and stays out of the rule: `settings-crossings-census.md`.

Related: docs/memory/plans/settings-crossings-census.md, docs/memory/plans/settings-tidy-round.md
