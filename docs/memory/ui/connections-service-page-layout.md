---
name: connections-service-page-layout
description: How a service's page on Connections is laid out: a page not the 480 px drawer, the cards Status, Connection details, Where it is used and one named card, the filled Save button, who sees a card
module: connections_screen
---

## Why a page and not the drawer?
- Architect (2026-11-17): a service with several sections does not fit the 480 px drawer at 390, so it gets `connections.php?service=<key>` with the breadcrumb Connections › the service. A provider without a page of its own keeps its drawer (`connections-screen-drawers.md`).

## Which cards, in which order?
- "Status" ("Durum": state, reconnect, disconnect), "Connection details" ("Bağlantı bilgileri": what is typed to connect), "Where it is used" ("Kullanıldığı yerler": a row per feature with its state, each a link), then at most one card named by what it holds ("Repository links" / "Depo bağlantıları" on GitHub).
- No numerals in card headings. A service with nothing of its own has no fourth card. The order and gates come from `pg_connections_screen_page_cards()`.
- In Connection details "Save" ("Kaydet") is the one filled button, "Save and try" ("Kaydet ve dene") is quiet. On Google's page "Connect a Google account" is quiet too (`workflow/decisions-made-product-77.md`).

## Who sees a card?
- Architect (2026-11-17): each card keeps the gate of the place it came from (GitHub's repository links and the voice card: the Workspace settings right; the screen: a manager). A card the viewer may not open is not drawn; a service with no card left is not a row (`pg_connections_screen_may_see()`).

## What does a feature card show instead of a key?
- A "Provider" ("Sağlayıcı") select, then the chosen provider's connection with its state and "Manage in Connections" ("Bağlantılar'da yönet"); when not connected the block says so with "Connect in Connections" ("Bağlantılar'da bağla") and the switch cannot be turned on. A feature still keeping a key of its own: `api/ai-connections-feature-cards.md`.

## How is the GitHub MCP connector told apart from GitHub?
- It stays its own row with its own token (`connections-mcp-connector-page.md`), named in a line under GitHub's "Where it is used": the connected account's right covers repository hooks only.

Related: docs/memory/ui/connections-one-place.md, docs/memory/ui/connections-service-page.md
