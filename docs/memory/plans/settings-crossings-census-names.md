---
name: settings-crossings-census-names
description: Crossings census, the names at both ends: the lead's label words, what the Connections page calls the card it leads back to, and where the two differ
---

## Which words does a lead use?
- Six labels for one move: "Manage in Connections", "Connect in Connections", "Set up in Connections" (Sign in with…), "Open in Connections" (MCP servers, no connector picked), "Sign in for mailboxes in Connections" (Mailboxes), and two that lead to the list, not a page: "Connected accounts" (Backups) and "Set up the application on Connections first." (E-mail sending).
- Mailboxes' text for a viewer under manager names "Settings › Connections"; the Site Settings list row says "Connections ›"; every page's breadcrumb says "System tools › Connections".
- Pages lead back with "Open its settings" (status-only pages), a "Where it is used" row, "Accounts and sign-in ›" (Google's sign-in part), "Mailboxes" and "Backups" (with an outward arrow icon though they stay in the tab), "Connections › Google".

## Where does the page name the card differently from the card's own title?
- Turnstile and hCaptcha: the page says "Accounts and sign-in › Spam Protection"; the card is "Forms and spam".
- Claude: the page says "Assistants and agents › Claude"; the card is "Claude in the channels".
- Google Analytics: "Visitor Tracking" on the page, "Visitor tracking" on the card (case).
- Slack, Teams, Telegram: "Updates and maintenance › Alerts" and "› Chat notifications": two rows, two cards; matches.
- AI providers: rows name each assistant, the card is "Workspace agents"; with none, "Workspace assistants".
- OneNote: "System tools › Connections", the list itself.
- The hand-written lines (IndexNow, Translation, Apple, payments, shipping, address, reviews, Merchant, Mailchimp, Claude, MCP, chat) are typed in `connections.php`, not read from `pg_settings_categories()`; a renamed card leaves them behind. The status-only pages read the label from the registry and stay right (`connections_place()`).

## Where does a service carry two names?
- "Sign in with Google" (registry row, status only) and Google's page part "Sign in with Google application"; the card row "Sign in with Google" leads to the second.
- Gmail and Microsoft 365 mail: list rows folded into "E-mail sending", each still a status page of its own when searched.
- GitHub (Repository events) and GitHub (MCP connector): two rows of one name, told apart by a line under GitHub's "Where it is used".
- Google Drive, OneDrive, Dropbox "Backups and the file picker" in the registry; the Backups card calls the account select "Account" and its list link "Connected accounts".

## What does a person not see at the card end?
- Meta CAPI, TikTok Events, GA4 MP: no line on Cookie consent says they exist; they are found by the search (`found_at`) or the list.
- n11 and Trendyol: their own screen, Marketplace settings, never mentions Connections; their pages only point back.

Related: docs/memory/plans/settings-crossings-census.md
