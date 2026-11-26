---
name: decisions-made-product-113
description: Decisions made, product part 113 (2026-11-26 on): a translated picture text grows, wraps, then shrinks, corrected by hand per language; a Canva .pptx opens in the Artifact Canvas by a page chooser
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-112.md`.

## What does a translated picture text do when it does not fit its source's box? (owner, 2026-11-26, agent channel poll 229, message 14130)
- Owner (2026-11-26, agent channel message 14119): "daha ilk denemede bu içerik çevirilemiyor gözden geçirilmeli uyarısı aldım, tek bir metin vardı. karışık çizimlerde başarısızlık oranı çığ gibi büyüyecektir."
- "kullanıcı tasaırm gereği yazıyı sağa/sola/alta/üste dayamak isteyebilir veya 2 metini birbirine çok yakın konumlandırmak isteyebilir. bunlar bugün sorun yaratıyor."
- What was found (architect): a translation had to fit its source's own box, which follows the source's letters closely; it could only shrink, to 70% (a headline to 50%), and under that the language was marked "Look by hand". The empty room around the text was never used and no line was broken.
- Asked with three choices: grow into the free room, then wrap, then shrink, with a correction by hand per language; grow and shrink with no wrap, with the correction; or the rule as it is with the correction alone.
- Owner: "Önce boş alana büyüsün, sonra satıra bölünsün, en son küçülsün; dil başına elle düzeltme de gelsin".
- So: a translated text first uses the free room up to the picture's edge and the next layer, keeping the edge's margin; a text that leans on an edge grows away from it. If that is not enough it is broken into lines between words; if that is not enough it shrinks as before.
- What still does not fit is corrected by hand: in the Artifact Canvas that language's text is moved and sized. The source picture does not change.
- This replaces the architect's line of 2026-11-16 "no box or size per language" (`core/picture-text-overview.md`) and settles the wrap question `core/picture-text-translation-fit-letters.md` left open. The floor of 2026-11-25 stays for the last step.
- The rules, the screens and the steps: `plans/picture-text-fit.md`.

## How does a design made in Canva open in the Artifact Canvas? (owner, 2026-11-26, agent channel poll 230, message 14151)
- Owner (2026-11-26, agent channel message 14119): "canvadan görsel tasarımcıya düzenlenebilir içerik aktarmanın yolu olursa çok başarılı olur, bu sayede oradan bir şekilde tasarladıkları içerik burada düzenlenebilir olur ve çeviri sistemi ile de mükemmel çalışır."
- He sent a ten-page design downloaded from Canva as .pptx and .pdf (messages 14149, 14150): "birden fazla sayfalı canva çıktısı bunlar, tek sayfalık değil duruma göre nasıl kullanırız bakarız."
- What was found (architect): the .pptx keeps every text as text with its box, paragraph, font, size and colour, and every picture apart with its crop (`plans/artifact-canvas-canva-census.md`). It is the person's own download: no Canva connection and no Canva call, as his choice of 2026-11-24 (`decisions-made-product-107.md`).
- Asked with three choices for a file of many pages: a page chooser where each ticked page becomes a canvas of its own, or all become scenes of one canvas when wished; always one canvas with a scene a page; or one page only.
- Owner: "Sayfa seçici: işaretlenen her sayfa ayrı bir tuval olur, istenirse tek tuvalin sahneleri olarak açılır".
- So: the Artifact Canvas opens a .pptx as editable layers; its pages are shown to pick from, each ticked page becomes a canvas file, and a tick box opens them as the scenes of one canvas.
- The rules, the chooser and the steps: `plans/artifact-canvas-design-file.md`.
