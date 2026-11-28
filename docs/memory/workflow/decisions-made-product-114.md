---
name: decisions-made-product-114
description: Decisions made, product part 114 (2026-11-26 on): all ten kinds of ready content for the Artifact Canvas, the basics first; a settings card and its Connections page hand over in one trip
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-113.md`.

## Which ready content does the Artifact Canvas get, and in which order? (owner, 2026-11-26, agent channel poll 231, message 14182)
- Owner (2026-11-26, agent channel message 14119): "görsel tasarımcı için yeni özellik araştıralım geliştirelim, şu an için biraz boş gibi geliyor". "daha kalilteli içerikler olmalı ki karşımızda canva gibi bir platform varken".
- What was found (the content census, #11833): an empty board gives no ready design, text style, font pair, element set, photo source or brand colour, and no way to turn one design into other sizes; ten proposals, all own code (sources and licences: `ui/image-editor-content-sources.md`).
- Asked with three choices: all ten, the basics first; the first six, the other four asked later; only the ready designs and what they need.
- Owner: "Onunun hepsi, temelden başlayarak".
- So, in this order: the site's colours in every colour field, text styles, font pairs, element sets; then thirty ready designs to start from and one design turned into other sizes; then photo frames, Unsplash in Import, a second icon set (Tabler Icons), Openverse photos.
- All of it is own code and own content, or content whose licence allows it on every site; nothing is taken from Canva; ready content is read when its panel opens, never on the canvas's first load.
- Landed in AC4 to AC13 (#11854 to #12108); the parts and their rules: `ui/image-editor-content.md`.

## How does a Site Settings card hand over to its Connections page and back? (owner, 2026-11-26, agent channel poll 232, message 14187)
- Owner (2026-11-26, agent channel message 14113): "bazı ayarların bağlantılardan ayarlara ayarlardan bağlantılara kullanıcıyı yöndendirip durması kafamı karıştırdı."
- What was found (the crossings census, #11829): nothing returns to the card after a Save; a first set-up of Backups to Google Drive takes seven hops; a third page (Google's or Microsoft's) sits in the middle of every account service; a choice made on the card is lost when its lead is followed; 28 pages only show a state.
- Asked with three choices: one trip and a return by itself; never leaving the card (the key form in a window over it); only the ten worst paths repaired.
- Owner: "Tek gidiş, kendiliğinden dönüş".
- So: a card's button goes to the service's own page; everything missing is finished there; a Save or Connect that completes it brings the person back to the card they came from, their unsaved choice kept; a page that only shows a state is skipped from the list.
- Where keys and switches sit stays as decided (`decisions-made-product-76.md`, `-77.md`, `-100.md`). The rule: `ui/settings-crossings-rule.md`.

Related: docs/memory/ui/image-editor-content.md, docs/memory/ui/settings-crossings-rule.md
