---
name: integrations-census-screens
description: Census (2026-11-17) of where an administrator connects an outside service today: the screens, names that collide, GitHub in five places, AI keys and mail split, the decision binding Site Settings
---

## Where is a connection made today?
- System tools › Connections: "Application Access" (`api_settings.php`: keys, scopes, devices, webhook subscriptions, OAuth clients, the MCP guide), "Connected accounts" (`connections.php`: for each provider its OAuth application and the accounts), "SMTP Settings", "Marketplaces". Infrastructure: "Cloudflare Tools".
- The account menu: "Connected applications", "Pair a device", "Calendar subscription". The Workspace: "My MCP connections". Visitors: "Google Search Console".
- Site Settings (19 categories, 99 cards): keys and Connect buttons sit on cards of SEO, Visitors and privacy, Languages and Translation, Communication, Mailboxes, Accounts and sign-in, Ecommerce, Accounting, Integrations, Updates and maintenance and the Workspace's categories.

## What is split or named twice?
- A Connect button for an outside account is on at least ten screens; the application it needs is set up on another one, Connected accounts.
- "Connections" is a System tools group, a tab of an OAuth application, a dashboard button that opens Application Access, and in help texts "Settings › Connections", which means the screen titled "Connected accounts". "Connected accounts" also heads the sign-in list of "Sessions and connected accounts". "Connected applications" is a card and a screen.
- Google's client is typed in Accounts and sign-in and reused by Connected accounts; Microsoft's the other way round; Apple's in Accounts and sign-in.
- An outgoing address is entered in five places: API subscriptions (by API only), Chat notifications, a flow's webhook box, an agent's push door, Claude's routine.

## GitHub, the worked example?
- Five places. Connected accounts: register an OAuth app at GitHub, paste its ID and secret, Connect. The "GitHub repositories" card: link a repository to a channel. The "MCP servers" card: the GitHub connector with a personal access token, a second credential. Application Access: an application named "GitHub" appears by itself, and deleting it breaks the links. The developer route: a key, repository secrets and a workflow file.

## AI and mail?
- AI keys in six places: the subscription key (Pinegrap AI), "Claude in the channels", a key for each assistant on "Workspace agents", "Voice message transcripts", "Translation Engines", and AI programs that come in on Application Access.
- Mail in four: SMTP Settings outside Site Settings, the Google or Microsoft application on Connected accounts, Mailboxes, and Communication for campaigns and MailChimp.

## What binds a change?
- Owner (2026-10-30): one place per setting; one dialog and one Save per pane; the tools that leave the dialog live on System tools; card addresses and old anchors stay (`workflow/decisions-made-product-14.md`, `ui/settings-modal-overview.md`).
- The panel's old screens are being redrawn in look A, Site Settings among them (`panel-old-screens.md`); the agents' cards have their own plan (`agents-card-settings.md`).
