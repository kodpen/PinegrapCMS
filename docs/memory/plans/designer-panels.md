---
name: designer-panels
description: Plan (owner 2026-11-16): the Page Design Studio's Options panel, Overview tree, page strip and canvas bar redrawn from the approved drawings; features D1 to D62, steps DP1 to DP25
priority: 6
---

## What is asked?
- Owner (2026-11-16, discussion "Görsel Tasarımcı geliştirme", message 7186) on the four Options drawings: "4 çizimine de bayıldım. hepsi süper olur ama detaylı çizimler olmazsa … ajanları çizimdeki görünümü atlar yada yanlış yapar. birde overview de tekrar elden geçmeli uzayan içerikli sayfalarda resmen kayboluyorsun sürükleme ve yer değiştirme çok zorlayıcı oluyor".
- (7190): "bu sayfa alanı da gelişmeli ben zorlanıyorum açıkçası. özellikle katlanan sayfaları erişmesi zor. … birde alttaki canvas için olan ayarlar ile prototip te karıştı sanki aynı özellik grubu gibi iç içe oldular".
- (7266): "önerilerin yerinde hepsi kabul son çizimleri de ekle. plana koyarken de çizimleri koymayı unutma." (7297): "attığın son görünümleri de onaylıyorum."
- So the drawings are the approved design and every PR accounts for its own numbers (`designer-panels-rules.md`).

## What is wrong today?
- Measured on a 145-node home page: the Options panel shows 416 px of 2,300 to 3,900 px; the same eight general sections open on every element; 300 px of the right column go to the style and script files.
- The Overview: 502 rows all open, 13 visible, 14 levels deep, 102 labels cut, 61 rows named "Link"; a drop band is 6 px.
- The page strip: 9 of 37 tabs visible, the whole design's bin 5 px from the gear. The canvas bar: 19 controls alike in one row; Prototype opens a second bar with a second zoom.

## Where are the drawings?
- `plans/designer-panels/`: the sheets as JPEG and their source `boards.html`. Which sheet shows what, its measures as text and the steps that build from it: `designer-panels-drawings.md`.
- The measures as text, so no value is read off a picture: `designer-panels-measures.md`, `-measures-2.md` to `-measures-6.md`. Where they differ, the step asks.

## What is built?
- The numbered features: `designer-panels-features.md` (Options: column, tabs, sections, rows), `-features-2.md` (Options: spacing box, layout, search, review, copy style), `-features-3.md` (Overview), `-features-4.md` (page strip), `-features-5.md` (canvas bar).
- What today's screen does that no step may lose, every step's rules and what a PR body carries: `designer-panels-rules.md`.

## Which steps are there?
- `designer-panels-steps.md` (DP1 and the Options chain DP2 to DP5), `-steps-2.md` (Options DP6 to DP10), `-steps-3.md` (Overview DP11 to DP16), `-steps-4.md` (page strip DP17 to DP21, canvas bar DP22 to DP24, close-out DP25).
- Order: DP1 first. After it the four chains do not wait for each other; inside a chain each step waits for the one before it. Suggested start: DP17, DP2, DP11, DP22, then the chains in turn.
- Two steps that edit the same file are never open together, and `assets/js/style_designer.src.js` is held by other plans too: a step names in its claim the functions it edits and waits while an open PR holds them.
- Claim as `[plan:designer-panels] <step> — <topic>`.

## Whose look?
- Every step: the architect's, the screen beside its drawing (agent channel message 2645). The author of the drawings compares dev with the drawing after a step lands and files a difference as a `bug` issue.
- The owner's: one sheet when a chain's last step is in, as one short question. A screen no drawing shows (`designer-panels-rules.md`) is drawn first and is his.

## What is left out?
- The editor's opening time and the cost of one option change (issue #10013, done apart): no step may undo that work.
- A light theme of the editor: its chrome is always dark.
- No schema change and no new endpoint in any step.
