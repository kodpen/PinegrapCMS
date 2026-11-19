---
name: decisions-made-product-77
description: Decisions list, product part 77 (2026-11-17): the Connections management screen's look accepted (poll 149): the list, a service's page, feature cards without a key, the "Bağlantılar ›" entry
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-76.md`.

## Is the Connections management screen's look accepted? (owner, 2026-11-17, agent channel poll 149, message 9423)
- He chose "Evet, böyle olsun" for one sheet of twelve pictures (the mock of agent channel messages 9405 to 9422), the screen he asked for in poll 146 (`decisions-made-product-76.md`).
- The list: one row per service with its state and one action, "Yönet", or "Bağla" when nothing is set up. E-mail sending is one row whose "Yönet" opens Site Settings › "E-posta gönderimi" (poll 148).
- A service has a page of its own. GitHub's: "Durum" (reconnect, disconnect), "Bağlantı bilgileri" (client ID and secret), "Kullanıldığı yerler", "Depo bağlantıları". The "GitHub depoları" card leaves Site Settings for this page and its old address opens it. The MCP connector that reads code stays its own row with its own token.
- Google's page: the application, which feature uses which account, and the connected accounts, each reconnected or disconnected on its own.
- OpenAI's page: one key. The poll told him that the agents' keys and the voice transcripts' key are typed separately today; at the update equal keys join one connection, and an assistant with a different key keeps working with its own ("Kendi anahtarını kullanıyor") until someone switches it to the connection's.
- A feature card (Voice message transcripts) holds no key: the provider is chosen, the connection it uses is shown, "Bağlantılar'da yönet" leads there. With the provider not connected the card says so and the feature cannot be turned on.
- The entry: a "Bağlantılar ›" row above "Sistem Araçları ›" at the end of the Site Settings list. Until then Connections opens only from System tools.
- Also told to him: nobody's rights change (who may open a section today sees it), no table and no column, and the order of the build: GitHub, the AI keys, Google and Microsoft first, the other service groups after.
- Not chosen: the same without the entry row; "Hayır, böyle olmasın".

## How is it cut into steps? (the architect's cut, not his words)
- Architect (2026-11-17), in `plans/connections-merges.md` at priority 10, one step per PR: the frame (a service's page with "Durum" and "Kullanıldığı yerler" from the registry, the row's one action, the entry row; no field moves, a service without a page keeps its drawer until its step); GitHub; the AI providers (after CX2 has landed); Google and Microsoft; then the other groups, one step each in the census's order.
- CX1 is closed by it. CX4 (a provider's client typed in one place) is answered by the provider's page. CX5 (the names) comes when the pages exist. CX2 and CX3 go on as chosen in polls 147 and 148.
- Each step's pictures (1440 and 390, light and one dark, Turkish) come to the architect before the step is marked ready. The owner is asked again only for a page that needs something the sheet does not show; a table or a column is asked of the architect first.
- The technical rules of a page: `plans/connections-merges-panel-rules.md`. One note from the look: Google's "Hesap bağla" is a quiet button, the filled one on a page is a card's "Kaydet".

Related: docs/memory/workflow/decisions-made.md, docs/memory/workflow/decisions-made-product-76.md
