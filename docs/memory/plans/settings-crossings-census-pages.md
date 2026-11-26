---
name: settings-crossings-census-pages
description: Crossings census, the pages: for each service page of Connections the way back to its card, and where the person stands after Connect, Save and Disconnect
---

## Which pages lead back to their card from "Where it is used"?
- One row linking to the card: IndexNow (SEO), Google Analytics (Visitor tracking), Google Cloud Translation, Apple (Sign in card), Turnstile and hCaptcha, the eight card gateways, Stripe, PayPal, PayTR (Payment Methods), Givex, the six label services and UPS, FedEx, USPS (Shipping), Google Places, Mapbox, Photon, Merchant Center (Product Feeds).
- The same, continued: Paraşüt and Logo İşbaşı (E-Invoice, twice), Google reviews, Mailchimp (Campaign mail), WhatsApp, İYS and the SMS providers (their Communication card), Slack, Teams, Telegram (Alerts and Chat notifications, two rows), Claude, the MCP connectors (MCP servers).
- An AI provider: one row per assistant, each to Workspace agents; OpenAI also Voice message transcripts. With no assistant, one row "Workspace assistants".
- Figma: Page Design Studio › New design (`view_system_styles.php?start=figma`).
- GitHub: its own Repository links card on the page; nothing leads to the Channels card.
- Meta CAPI, TikTok Events, GA4 MP: "On this page", no link; Cookie consent is linked only in the warning shown while the cookie notice is off.
- OneNote: its place is `connections.php`, so its row reads "System tools › Connections" and leads to the Connections list.

## Which pages only show a state and say "Open its settings"?
- Those whose settings stay on a card or screen: Pinegrap AI, Pinegrap licence, ShipWorks, OpenAI product feed, VIES, DB-IP, Frankfurter, HexaRate, StopForumSpam, ActivityPub, the seven mail routes, Sign in with Google and with Microsoft, Google and Outlook Calendar, Google Drive, OneDrive, Dropbox, n11, Trendyol, Search Console, Cloudflare.
- Their "Where it is used" row repeats the same link (`connections.php:4390-4420`).

## Where does the person stand after each action?
- Save, Save and try, Generate (IndexNow), Disconnect, Remove: the same page (`connections_return`). For Google's application, `?service=google`; for its sign-in application, `?service=google#connections-signin`.
- Connect an account (Google, Microsoft, GitHub, Merchant Center, Analytics, Paraşüt's OAuth, an MCP connector's sign-in): back on the page it started from. The MCP connector lands on `#connections-details`.
- Connect for backups from the Backups card: the Backups card (`settings_maintenance.php?backup_connect=<type>#pgset-backup`). Connect for mailboxes from the Mailboxes card: the card (`mail_return`). Started on the page itself (no parameter), both stay on the page.
- `mail_return` and `backup_return` live only in the address the card opened: a Save on the page (it redirects to `?service=<key>`) or a detour to Google's page drops them, and the later Connect comes back to the page, not the card.

## Which pages send the person on to a third page?
- Google Analytics and Merchant Center without Google's application: "Connections › Google" (`connections.php:3295`, `3683`).
- Every page of a Google or Microsoft account service with no Connection details of its own (the calendars, the drives, Gmail and Microsoft 365 mail, OneNote): "Accounts are connected on the Google (Microsoft) page of Connections, where its application is set up." (`connections.php:4376`); Dropbox's says its panel of the Connections list.
- Google Drive, OneDrive, Dropbox show that sentence and, below it, their own "Connect a … account for backups" button: two answers on one card.
- The GA4 Measurement Protocol: the Measurement ID is on Google Analytics' page (`ad_conversions_ga4.php:714`).
- Google's page: its sign-in part leads to "Accounts and sign-in ›"; its "Where it is used" lists each served service's page, never a card.

Related: docs/memory/plans/settings-crossings-census.md
