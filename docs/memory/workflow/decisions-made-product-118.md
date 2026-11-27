---
name: decisions-made-product-118
description: Decisions made, product part 118 (2026-11-27 on): panel languages start with the mechanism and German; a touched old screen is brought to the look; a new setting sits beside its kin
---

## How do the panel's language packs start? (owner, 2026-11-27, agent channel poll 247, message 14843)
- Asked after the count: the setting that holds the panel's language, `config.software_language`, takes only English and Turkish, so a further language needs the column's type changed (a schema change, no data lost); 79,407 texts are translated by sessions as machine translation nobody reads line by line, and a text left out shows English; once a language has its files, site pages in that language show the software's own texts in it too; a language per user is not in this work.
- Owner: "Önce düzenek ve Almanca". Offered beside it and not chosen: five languages at once; Arabic and the right-to-left panel first; wait for now.
- So: the column becomes a `VARCHAR` by a migration step (his answer is the yes for that MODIFY, and for nothing else in the schema), then German. Arabic with the right-to-left panel and further languages come after German. Plans: `plans/panel-language-packs.md`, `plans/panel-language-german.md`.
- His decision of 2026-10-09 (`core/software-text-in-site-languages.md`) is not changed: it is about a language with no file.

## What does a feature do with an old screen it touches? (owner, 2026-11-27, agent channel message 14844)
- Owner: "arayüzler modern tasarımla yapılacak, hep modern methodları kullanacağız" and "modern olmayan bir sayfa ile bağlantılı bir özellik yapılırken dokunan sayfalarıda modernleştirelim".
- So: what a step draws is in the panel's current look (the recipes of the Design examples screen, `ui/screen-composition-rules.md`), and a screen it adds a part to that is not yet in that look is brought to it in the same work.
- Architect's reading, not his words: "touches" means the step changes what that screen shows, not that it reads a function the screen also uses. The screen keeps its fields, its rights and what it saves; when bringing it over would pass the size of a small PR, it is a step of its own in the same plan, before the feature's part.

## Where does a new setting go? (owner, 2026-11-27, agent channel message 14844)
- Owner: "ayarları çok fazla dağıtmadan ilgili bir ayar varsa birleştirebiliriz".
- So: a new setting is put beside the setting it belongs with (the same card or group of Site Settings, or the screen that already holds its kin), and a plan may join two settings that say the same thing into one. A new settings screen or category is opened only when nothing related exists.
- Joining two stored settings into one changes what sites have saved: the plan says what each stored value becomes, and a dropped column is asked like any other.

## What else did he say in that message?
- "soru gerekmeyenleri kendin çözdürt" and "çizim gerekirse çizersin": what needs no decision of his is settled by the architect, who also draws a part that needs a drawing (`workflow/architect-draws-new-part.md`).
- The night of 2026-11-27 ends as the one before it (`workflow/bug-hunt-areas-6.md`, "How does the night end?"): work first, one short hunt round when none is left, then the coordinators end; the merging session lands what is left and removes its timer too.

Related: docs/memory/workflow/decisions-made-product-117.md, docs/memory/plans/panel-language-packs.md
