---
name: connections-merges-build
description: Plan connections-merges, the build after poll 149: CX7 the frame, CX8 GitHub, CX9 AI providers, CX10 Google and Microsoft, CX11 the other groups; the architect's cut
---

## Where does the build come from?
- Owner (2026-11-17, poll 149, message 9423): yes to the management screen's sheet (agent channel messages 9405 to 9422). The architect cut it into steps (message 9425), one step per PR in this order; a step past about 1,000 product lines is resized.
- The page's technical rules: `connections-merges-panel-rules.md`. The rules binding every step: `connections-merges.md`.

## What does every build step bring to the architect?
- Its pictures from a real panel, 1440 and 390, light and one dark, Turkish, before it is marked ready (`ui/panel-screenshots.md`).
- A page that needs something the sheet does not show, or a table or a column, goes to the architect first.

## Which steps are there?
- CX7 The frame: a service's page (`connections.php?service=<key>`, breadcrumb, "Durum", "Kullanıldığı yerler" read from the registry), the list row with its one action, the "Bağlantılar ›" row in the Site Settings list. No field moves yet; a service without a page keeps its drawer until its step. Done in #10400; the mail routes folded into one row in CX3c (#PRNUM).
- CX8 GitHub: "Bağlantı bilgileri" on its page; the repository links move from the "GitHub depoları" card, with a `pg_settings_legacy_anchors()` row and the registry row's `place` in the same step. Done in #10418: the page is `ui/connections-service-page.md`; the Channels card stays at its address as a pointer.
- CX9 AI providers, after CX2 has landed: one connection per provider, the idempotent data step and the feature cards without a key (Voice message transcripts, the assistants and agents), as `connections-merges-panel-rules.md` says.
- CX10 Google and Microsoft: the application and each connected account on the provider's page. It answers CX4 (a provider's client is typed on its page).
- CX11 The other groups, one step each in the census's order (`integrations-census-screens.md`: SEO, Visitors and privacy, Languages and Translation, Communication, Mailboxes, Accounts and sign-in, Ecommerce, Accounting, Integrations, Updates and maintenance, the Workspace's categories), on the same cards: CX11a, CX11b… cut when the step before lands.

## What binds CX9 already?
- Architect (2026-11-17, message 9391): one connection per provider, no table and no column, stored where the registry's key services keep theirs.
- An idempotent data step of the open version: where no connection exists the first stored key becomes it; a feature's stored key equal to the connection's is emptied; a feature whose key differs keeps it, its row reads "Kendi anahtarını kullanıyor" with "Bağlantıdaki anahtarı kullan".
- `pg_connections_count()` stays 78; the list folds the seven mail routes into one row and the filter counts rows (done in #PRNUM, `ui/connections-screen-layout.md`). Each section keeps its gate (`pg_connections_screen_may_see()`).
- Google's "Hesap bağla" is a quiet pg-button (message 9424).

Related: docs/memory/plans/connections-merges.md, docs/memory/plans/connections-merges-panel-rules.md, docs/memory/plans/integrations-census-screens.md
