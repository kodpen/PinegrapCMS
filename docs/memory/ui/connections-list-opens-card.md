---
name: connections-list-opens-card
description: The Connections list opens the Site Settings card (or screen) of a service whose page would only show its state; the page's address still answers (#11911)
module: connections_screen
---

## What is it?
- Built in #11911, the rule's "No empty page" part (`settings-crossings-rule.md`; owner poll 232). Before it the list opened a page that said "here only its state is shown" and "Open its settings": two hops for one.

## Which rows open the card?
- A service row of the list (not a provider, not the folded "E-mail sending" row) when `pg_connections_screen_opens_card($id, $cards, $here, $url)` (pure, tested) is true: the page's cards for this viewer (`pg_connections_screen_page_cards()`) are Status and Where it is used alone, `pg_connections_screen_page_here()` is false (no settings typed on the page), it is no provider's page (Google, Microsoft, GitHub), and its place is a panel screen other than `connections.php`.
- `connections_list_opens_card()` in `connections.php` works out the cards with the page's own gates (`connections_page_can()`, shared with the page) and `pg_connections_screen_app_here()`.
- Then the row's link is the place's address (`$row['url']`, `pg_settings_link()` for a card, else the screen: n11 and Trendyol's Marketplace settings, Search Console, Cloudflare). Its label stays Manage or Connect.

## Which stay on their page?
- A page with settings of its own (keys, accounts, targets; the AI providers, the ad services, the MCP connectors), even for a viewer who may not change them.
- Google Drive, OneDrive and Dropbox for a manager who may open Updates and maintenance: they connect the account for backups in Status. For another viewer they open the Backups card.
- A Google or Microsoft account service while its application is missing, for a manager: the page asks for it (`connections-page-finishes.md`). Once ready, the calendars, Sign in with Google and Microsoft, Gmail and Microsoft 365 mail open their card.
- OneNote: its place is the Connections list. GitHub: a provider's page. Figma: settings of its own.

## What still opens the page?
- Its address (`connections.php?service=<key>`): old links, a card's lead, "Where it is used" of Google's or Microsoft's page. It still shows "Open its settings".

Tests: `tests/connections_list_opens_card_test.php`.
