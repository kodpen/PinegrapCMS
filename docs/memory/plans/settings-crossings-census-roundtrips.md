---
name: settings-crossings-census-roundtrips
description: Crossings census, the round trips with nothing to do at one end: a card that only points to a page, a page that only points back, a lead that lands on the list, a way back that names the wrong place
---

## Where is there nothing to do on the card?
- The service's block on the card shows the state and one lead, and the whole service is set on the page (the card's other fields are other features): IndexNow (SEO), Campaign mail's Mailchimp block, Google Reviews (Integrations), Channels' GitHub repositories, MCP servers' connector line, Visitor tracking's Google Analytics block (tag, property and figures all on the page). A first set-up goes card › page and the page's "Where it is used" invites a trip back for nothing.
- Decided as such: the card stays and leads (poll 146, `decisions-made-product-76.md`); what TR2 may weigh is whether the page then offers the trip back.

## Where is there nothing to do on the page?
- Status-only pages opened from the list: 28 services whose settings stay on a card or a screen (`settings-crossings-census-pages.md`). Was list › page › "Open its settings" › card; since TR3d the list opens the card (`ui/connections-list-opens-card.md`).
- Gmail and Microsoft 365 mail: folded into "E-mail sending" on the list (decided, poll 148), still a status page each when reached from Google's or Microsoft's page.
- Sign in with Google and Sign in with Microsoft: a status page beside the provider's page that holds the application; reached from Google's or Microsoft's "Where it is used", it only says "Open its settings".
- n11, Trendyol: Marketplace settings does it all; the page is a state.
- OneNote: no card, a person's own account; the page's only row leads to the Connections list.

## Where does a lead land on the list, not the page?
- E-mail sending's "Set up the application on Connections first." (`fn/mail_settings.php:108`) and Backups' "Connected accounts" (`maintenance.php:301`): the person has to find Google's or Microsoft's row again.
- MCP servers with no connector picked: the list's MCP group, in a new tab.

## Where is the way back to a different place than the one left?
- Workspace agents: back to the Workspace agents card, not the assistant's drawer the lead was in.
- Merchant Center from Marketplace settings: back to Ecommerce › Product Feeds, not Marketplace settings.
- Figma from the designer's New design: back to New design (right), but a manager reaching it from the list gets the same row.
- Backups and Mailboxes after a detour to Google's page: back to the drive or Google page, not the card (`mail_return`, `backup_return` dropped).
- Any page: the breadcrumb is System tools › Connections, never the Site Settings card the person came from.

## Where does the trip lose work?
- Every lead inside the Site Settings dialog: unsaved choices on the pane are dropped without the unsaved-changes question (it guards closing only). Worst where the lead appears because of the choice: Payment Methods' gateway select, Shipping's service, SMS provider, Address Autocomplete provider, an assistant's Provider select in its drawer.

Related: docs/memory/plans/settings-crossings-census.md
