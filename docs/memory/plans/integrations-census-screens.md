---
name: integrations-census-screens
description: Census (2026-11-17) of where an administrator connects an outside service today: the screens, names that collide, GitHub in five places, AI keys and mail split, the decision binding Site Settings
---

## Where is a connection made today?
- System tools › Connections: "Application Access" (`api_settings.php`: keys, scopes, devices, webhook subscriptions, OAuth clients, the MCP guide), "Connections" (`connections.php`, "Connected accounts" until 2026-11-17: each registry service with its state, a provider's drawer with its OAuth application and accounts), "Marketplaces"; "SMTP Settings" became Site Settings › E-mail sending (#10404). Infrastructure: "Cloudflare Tools".
- The account menu: "Connected applications", "Pair a device", "Calendar subscription". The Workspace: "My MCP connections". Visitors: "Google Search Console".
- Site Settings (20 categories, 104 cards since #10404): keys and Connect buttons sit on cards of SEO, Visitors and privacy, Languages and Translation, Communication, E-mail sending, Mailboxes, Accounts and sign-in, Ecommerce, Accounting, Integrations, Updates and maintenance and the Workspace's categories.

## What is split or named twice?
- A Connect button for an outside account is on at least ten screens; the application it needs is set up on another one, Connected accounts.
- "Connections" is a System tools group, a tab of an OAuth application, a dashboard button that opens Application Access, and in help texts "Settings › Connections", which means the screen titled "Connected accounts". "Connected accounts" also heads the sign-in list of "Sessions and connected accounts". "Connected applications" is a card and a screen.
- Google's client is typed in Accounts and sign-in and reused by Connected accounts; Microsoft's the other way round; Apple's in Accounts and sign-in.
- An outgoing address is entered in five places: API subscriptions (by API only), Chat notifications, a flow's webhook box, an agent's push door, Claude's routine.

## GitHub, the worked example?
- Five places (checked in code 2026-11-17). Connections, the GitHub drawer (managers): register an OAuth app at GitHub, paste its ID and secret, Connect. The "GitHub repositories" card (a manager with the Workspace settings right): link a repository to a channel; it does not say which account it uses.
- The "MCP servers" card: the GitHub connector with a personal access token, a second credential; only an administrator adds it. Application Access: an application named "GitHub" appears by itself; deleted or switched off, the channel lines stop until the next "Link a repository" makes it again.
- The developer route is no product screen: only this repository's CI (`php-checks.yml` job `channel`, `agent-channel.yml`) with an Application Access key and four repository secrets. The Connections screen lists GitHub three times: Account providers, Other, MCP connectors.

## AI and mail?
- AI keys in six places: the subscription key on General › Software (the licence, also read by update checks and the push broker; "Pinegrap AI in the channels" has no key of its own and uses it), "Claude in the channels", a key for each assistant on "Workspace agents", "Voice message transcripts" (two keys: transcripts and spoken answers), "Translation Engines" (the Google Cloud Translation key), and AI programs that come in on Application Access, which holds no AI key of the site's.
- Mail in four: E-mail sending (SMTP Settings until #10404), the Google or Microsoft application on Connections, Mailboxes, Mailboxes; campaign texts and MailChimp in E-mail sending (CX3c).

## What binds a change?
- Owner (2026-10-30): one place per setting; one dialog and one Save per pane; the tools that leave the dialog live on System tools; card addresses and old anchors stay (`workflow/decisions-made-product-14.md`, `ui/settings-modal-overview.md`).
- The panel's old screens were redrawn in look A, Site Settings among them (`ui/look-overview.md`); the agents' cards: `workspace/workspace-agent-settings.md`.
