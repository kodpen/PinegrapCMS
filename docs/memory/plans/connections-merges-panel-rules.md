---
name: connections-merges-panel-rules
description: Connections management screen (plan connections-merges): the architect's rules for a service's panel: one connection per provider and its data step, the count stays 78, gates, card titles
---

## What is this file?
- The architect's technical rules for the screen the owner asked for in poll 146 (`workflow/decisions-made-product-76.md`), given on the first mock (agent channel message 9376, answered 2026-11-17). The plan is `connections-merges.md`.

## How is a key shared by several features kept?
- Architect (2026-11-17): one connection per provider (OpenAI, Anthropic…), no table and no column, stored where the registry's key services keep theirs. A feature names the provider, never a second connection of the same provider.
- The move is an idempotent data step of the open version: where no connection exists, the first stored key becomes it; a feature's stored key equal to the connection's is emptied; a feature whose stored key differs keeps it and goes on working with it.
- Such a feature's row under "Kullanıldığı yerler" reads "Kendi anahtarını kullanıyor" with the action "Bağlantıdaki anahtarı kullan". The update changes nothing a running site calls with.

## Does the registry's figure change?
- Architect (2026-11-17): no. The registry keeps its services and `pg_connections_count()` stays 78; the figure is the owner's public one (`workflow/decisions-made-product-69.md`).
- The list folds the seven mail routes (Postmark, Mailgun, Brevo, Resend, Amazon SES, Gmail, Microsoft 365 mail) into one "E-posta gönderimi" row whose "Yönet" opens the Site Settings category of poll 148; each route's name still finds the row in search. The filter counts rows.

## Who sees a panel's section?
- Architect (2026-11-17): each section keeps the gate of the place it came from (the GitHub repository links and the voice card: the Workspace settings right; the screen: a manager). A section the viewer may not open is not drawn, and a service with no section left is not a row, as `pg_connections_screen_may_see()` does today. Nobody gains or loses a right by the move.

## How is a service's panel laid out?
- A page of its own (`connections.php?service=<key>`, breadcrumb Connections › the service), not the 480 px drawer: a panel with several sections does not fit a drawer at 390.
- Cards in this order, titled "Durum" (state, reconnect, disconnect), "Bağlantı bilgileri" (what is typed to connect; "Kaydet" the one filled button, "Kaydet ve dene" quiet), "Kullanıldığı yerler" (a row per feature with its state, each a link), then a card named by what it holds ("Depo bağlantıları" on GitHub).
- No numerals in the card headings. A service with nothing of its own has no fourth card.
- The GitHub MCP connector stays its own row with its own token, named in a line under GitHub's "Kullanıldığı yerler": the connected account's right covers repository hooks only.

## What does a feature card show after the change?
- No key. A "Sağlayıcı" select, then the chosen provider's connection with its state and "Bağlantılar'da yönet"; the feature's own settings (switch, model) stay.
- When the provider is not connected the block says so and offers "Bağlantılar'da bağla"; the switch cannot be turned on.

## What did the owner accept?
- Owner (2026-11-17, poll 149): the screen as its sheet shows, with the entry: a "Bağlantılar ›" row above "Sistem Araçları ›" at the end of the Site Settings list (`workflow/decisions-made-product-77.md`, where the build's cut is too). The main menu stays as it is.

Related: docs/memory/plans/connections-merges.md, docs/memory/ui/connections-screen-layout.md, docs/memory/api/connections-registry.md
