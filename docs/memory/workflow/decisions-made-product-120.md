---
name: decisions-made-product-120
description: Decisions made, product part 120 (2026-11-28 on): documents, tables and presentations are the product's own types (note, table, canvas); Office files are the way in and out
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-119.md`.

## Which way do documents, tables and presentations go? (owner, 2026-11-28, agent channel poll 256, message 15450)
- His ask (message 15440): the .pptx preview "içeriği doğru vermiyor bunun içinde bir görüntüleyici yapabiliriz"; the Word, Excel and pptx views are apart with different interfaces; "niyetim tek bir markalaşma adında toplayıp tek bir arayüz modeli olması artifact canvas gibi, bunun adı da Pinegrap Office olabilir".
- On the first poll (254) he wrote: "konuyu değerlendirmen ve tartışman benim için önemli direkt olarak bahsettiğim sistemii yapman değil o yüzden görüşünü de yaz daha iyi bir plan varsa bahset."
- The architect's evaluation (message 15449): one shell and a visible name are a gain, but a brand built on Office files promises what the scope cannot give (`platform/office-files-scope.md`: no library, no document server), while the product's own types already carry agents, the API, share links and channel cards.
- Owner: "Yerli türler merkezde, Office dosyaları köprü". Not chosen: Office files in the centre; only the .pptx preview.
- So:
  - The family is the product's own types: a document is a Note, a table a Data table, a canvas the Artifact Canvas, a presentation a canvas with scenes.
  - Office files are the way in and out: opened, corrected, converted. The family's name and promise are the own types'; never "replaces Office".
  - A .pptx gets no viewer of its own: its slides are drawn by the canvas's renderer.
  - The windows of a note, a table, a canvas, a Word and an Excel file share one head; their bodies stay apart.
  - The family's name is asked of him once the shared head is drawn.
- Timing, the launch being aimed at 1 January 2027: before it the .pptx preview and the shared head; after it Word to Note, Note to Word, canvas to .pptx and one "New" menu.
- The architect's reading, told to him under the poll (message 15455): editing a Word or Excel file in place stays as built, its open plan finishes (`plans/office-document-abilities.md`; simple formulas are done, `platform/office-sheet-formulas.md`) and nothing more is added there. The message still counted the formulas plan as open.
- Plan: `plans/document-family.md`; the standing rule: `platform/office-direction.md`.
