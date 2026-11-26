---
name: picture-text-fit
description: Plan (owner 2026-11-26, poll 229): a translated picture text grows into the free room, then wraps, then shrinks; one language's texts corrected by hand in the Artifact Canvas; steps PF1 to PF5
priority: 6
---

## What is asked?
- Owner (2026-11-26, agent channel message 14119): "görsellerdeki çeviri olayını güzel bir elden geçirmeliyiz". His cases: one text alone marked at the first try, a text leaning on an edge, two texts close together.
- His choice of poll 229 and what it replaces: `workflow/decisions-made-product-113.md`.

## What is there today?
- `M.languageFit()` and its PHP twin `pg_picture_tr_language_fit()` shrink a translation inside its source's box down to the floor and mark the language "Look by hand" under it (`core/picture-text-translation-fit-letters.md`).
- A text's box follows its letters (`ui/image-editor-text.md`), so a translation longer than the floor allows fails wherever it stands. Nothing reads the picture's edges or the other layers, lines break only where the text has a new line, and nothing can be set for one language.

## Where are the rules and the screens?
- The fit: `plans/picture-text-fit-rules.md`. The correction by hand and its screens: `plans/picture-text-fit-screens.md`.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- PF1 (M) Done (#11837). The room and the growth, pure, in the model and its PHP twin on shared fixtures: the owner's three cases, a text on each of the four edges, two texts side by side and stacked, a headline over a line, a text on a card, a turned text, a sticker's label.
  - The measure, in the PR: a fixture set of at least twenty layouts, each with translations 1.0, 1.3, 1.6, 2.0 and 2.5 times its source's width; how many end "Look by hand" before and after. A test holds that no text ends smaller than the old rule made it.
- PF2 (M) after PF1: the wrap in both twins; the stage, the save and the server draw the lines the fit gives; the shrink takes the wrap that needs the least.
- PF3 (M) after PF2: the correction's model, no screen: the field on a text layer, the document check, the language document and the server's draw taking it in place of the fit, the states, tests.
- PF4 (M) after PF3: the language view and the Languages list's rows as `plans/picture-text-fit-screens.md` describes them, on a phone too; "Draw the waiting pictures" opens it from a "Look by hand" row.
- PF5 (S) Close-out: a face asked with each layer's own text (the open point of `core/picture-text-translation-fit-letters.md`), the notes of the Languages list and the queue reworded, the `core/picture-text-*` files corrected, the three plan files deleted. No new `[NEW]` line: the feature has its own.

## What must it never do?
- Draw a language before every counted text is translated, or change the source picture or its document's texts (`core/picture-text-overview.md`).
- Let the browser and the server fit differently: one rule, two twins, the same fixtures (`core/picture-text-auto-draw.md`).
- Add a table or a column: the correction lives in the canvas document.

Related: docs/memory/core/picture-text-translation-draw.md, docs/memory/core/picture-text-auto-server.md
