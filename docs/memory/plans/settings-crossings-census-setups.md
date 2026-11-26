---
name: settings-crossings-census-setups
description: Crossings census, first set-ups: for each feature, the hops from its card to working on a new site, counted as moves between card, Connections page and outside service
---

## How are hops counted?
- A hop is a move to another place: the card (the Site Settings dialog on it), a Connections page, the list, the outside service's site. A Save that comes back to the same place is no hop. "Card refuses" means the switch's Save fails with "…in Connections first".
- Decided parts are not counted as faults (`settings-crossings-census.md`).

## Key typed on the page, switch on the card (3 hops)
- card › page (Save) › card (switch, Save): SMS (Netgsm, İleti Merkezi, Twilio), WhatsApp, İYS, address autocomplete, the card gateways, Stripe, PayPal, PayTR, Givex, the label services, UPS/FedEx/USPS, Turnstile and hCaptcha, Google Cloud Translation, Sign in with Apple, Claude, chat notifications.
- Plus one when the person tries the switch first (card refuses). Payment Methods and Shipping: the gateway or service picked in the card's select is lost on leaving unsaved.
- Workspace agents and Voice message transcripts: the lead sits inside an assistant's drawer; the drawer and its unsaved fields are gone on return, opened again by hand.
- E-Invoice: card › page › Paraşüt's sign-in › page › card (pick, Save): 4 hops; Logo İşbaşı 3.

## Everything on the page, the card only points (2 hops, the second empty)
- IndexNow, Mailchimp (sync switch on the page), Google reviews (widget placed in the designer), Meta CAPI, TikTok Events, GA4 MP (no card lead: list › page), MCP connectors (new tab; the MCP servers row then picks tools, reload needed), GitHub repository links (page's own card), Figma (back to New design).

## Google or Microsoft account services (4 to 7 hops)
- Google Analytics figures: card › GA page › Google page (application, typed from Google Cloud console: outside) › GA page (via Where it is used) › Google sign-in › GA page (property, Save). The tag alone: card › page › done.
- Merchant Center: card › Merchant page › Google page › Merchant page › Google sign-in › Merchant page (account, Save) › Product Feeds card (feed settings).
- Backups to Google Drive or OneDrive: card › drive page (Connect disabled without the application) › Google page › drive page (`backup_return` lost) › sign-in › drive page › "Open its settings" › card (account, folder, Save): 7.
- Mailboxes through Gmail or Microsoft 365: card › Google page `#connections-mail` › Save application (`mail_return` lost) › sign-in › Google page › "Mailboxes" › card (box form, account, Save): 5. With the application already set, 3 and back on the card.
- Sign in with Google: card › Google page `#connections-signin` (Save) › "Accounts and sign-in ›" › card (switch): 3.
- Sign in with Microsoft: card › Microsoft page (no anchor; Connection details is the shared application) › Where it is used › Sign in with Microsoft's status page › "Open its settings" › card: 4.
- Sending mail through Gmail: E-mail sending › "Set up the application on Connections first." › the list › Google row › Google page (Save) › nothing leads back but the list's "E-mail sending" row or Where it is used › Gmail's status page › "Open its settings" › category (Connect, which returns there): 5 to 6.
- Calendar sync, OneNote: a person's own account, connected from their own screens; the page only shows state.

## Own screens (1 to 2 hops)
- n11, Trendyol: Marketplace settings holds all; the list's page is status only. Cloudflare, Search Console: the same with their own screens.

Related: docs/memory/plans/settings-crossings-census.md, docs/memory/plans/settings-crossings-census-worst.md
