---
name: prototype-canvas
description: Plan (owner 2026-11-15): the designer's Prototype view redrawn like Figma but clear: page pictures, the selected page's arrows, a link list, on a stronger graph canvas core; PV1 to PV12
priority: 6
---

## What is asked?
- Owner (2026-11-15, in the architect's window), on the Prototype view of a design with about forty pages: "sayfalardaki prototip ekranı çok karışık ben hiç birşey anlamıyorum açıkçası. bu ekran tasarımı kolaylaştırmalıyken koca bir kargaşa olarak kalıyor. hepsi yan yana kimden çıkan kime gidiyor belli değil."
- And: "ben aslında figmadaki gibi ama daha modern ve kullanışlı hali şeklinde hayal etmiştim". Figma's own view, every arrow drawn at once, he calls tangled too.
- On the second drawing (agent channel message 5499; `plans/prototype-canvas/drawing.html`): "evet buna yakın, planını yaz". He liked that broken links are found.
- The engine: "akış çekirdeği ile harmanlanması devam etsin, ayrı motor yerine akış motorunu güçlendirme yoluyla ilerleyelim. birkaç pr daha fazla geçeriz ama prototip ve ileride akışları belli mantıksal işler için ortak kullanabiliriz."
- On detail: "iyi detaylandırarak plan yap yoksa muhtemelen özellikler … atlanır". So the features are numbered and every PR accounts for its own numbers (`prototype-canvas-rules.md`).

## What is wrong today?
- `PGProtoHotspots.layout()` puts every frame in one row, 248 wide and 160 apart: forty pages are 16,000 units wide and Fit ends near 10%.
- Every arrow is always drawn; a shared header's links alone cross the whole row.
- A frame is an 84-high band sketch and one row per link, not the page: nothing shows where a link sits on it.

## What is built?
- The numbered features: `prototype-canvas-features.md` (frames, arrangement, arrows), `-features-2.md` (the list, broken links, drawing an arrow, the bar) and `-features-3.md` (his answer of 2026-11-16: further problems, suggested targets).
- What today's view does that no step may lose, the rules of every step and what a PR body carries: `prototype-canvas-rules.md`.

## Which steps are there?
- The engine, as optional parts of `pg_graph_canvas.js` that Flows does not pass: `prototype-canvas-steps.md`.
- The view on it: `prototype-canvas-steps-2.md`. The page picture and the marks on it: `-steps-3.md`. His answer's steps: `-steps-4.md`.
- Order: PV1 and PV7 at once; PV2 and PV3 after PV1, side by side; PV4 after both; PV4b after PV4; PV5 after PV4b; PV6 after PV5; PV8 after PV7 and PV2; PV9 after PV8 and PV5; PV10 after PV9; PV11a after PV10; PV11b after PV11a; PV12 last.
- Claim as `[plan:prototype-canvas] <step> — <topic>`.

## Whose look?
- The owner's, three times: after PV5 (the arrangement and the arrows), after PV10 (pictures, marks, drawing an arrow) and after PV11b (the Suggest targets dialog), each as one sheet and one short question the architect brings him. None of the three is marked ready before it.
- Every other step: the architect's, against the drawing.

## What is left out?
- Flows using the new parts (a Tidy up, the checks, the routing): a later yes of the owner.
- A stored screenshot of a page: a picture is the real page (`workflow/decisions-made-product-35.md`).
- No schema change in any step.
