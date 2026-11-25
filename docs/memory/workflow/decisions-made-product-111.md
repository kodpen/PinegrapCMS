---
name: decisions-made-product-111
description: Decisions made, product part 111 (2026-11-25 on): the Sketch tool's "Read the picture" runs on the browser's built-in model; no picture path to Pinegrap AI until the owner says it is good enough
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-110.md`.

## Which model reads the Sketch tool's reference picture? (owner, 2026-11-25, agent channel poll 223, message 13373)
- Asked in message 13370 with three choices: after the launch, now with a picture path to Pinegrap AI, or the browser's model. Owner: "tarayıcıyı deneyelim kalite düşük olursa alternatif düşünürüz. bugün için pinegrap ai yeterli kalite ve stabiliteye sahip değil. iyileştirmleri yapıldığında özellik bağlamalarını yaparız."
- "Read the picture" (`designer/designer-sketch-drawing-picture.md`, board E5) runs on the browser's built-in model, as alt text does (`ui/ai-write-alt-text.md`): the picture is read in the browser and sent nowhere, and no endpoint sends a picture to Pinegrap AI.
- The rule of `ui/ai-write-decisions.md` stays: a picture path to Pinegrap AI waits. It now waits for his word that Pinegrap AI is good and stable enough; features are bound to it then.
- If the browser's model reads a picture poorly, he thinks of another way. The step's PR says what the model made of two or three real pictures.
- Why: Pinegrap AI is not good or stable enough today, and the browser's model already reads pictures for alt text with nothing leaving the computer.

## What follows for the step? (architect, 2026-11-25, agent channel message 13373)
- The row "Read the picture" stands under the caption "Reference picture" of the "More" menu, not under "Pinegrap AI", and shows only where `PinegrapAiWrite.imageReady()` is true; elsewhere there is no row.
- Its first-use line reads "“Read the picture” reads this picture with your browser's built-in AI. It is sent nowhere." Busy, the proposal, "Keep", "Discard" and the two messages stay as drawn.
- It is a step of its own after SK7's reference picture (#11557), given out by coordinator 1.

## How are shared components and system widgets marked on the designer's canvas? (owner, 2026-11-25, in the architect's window)
- Owner: "tasarımcı içinde ortak bileşenler hızlıca belirgin olsun istemiştim anca bu yeşil mor bantla sarılma olayı canımı sıkıyor geçen gün müşterim neden bunlar böyle sarılı dedi, açıklamakla uğraştım. bu konuda özellikle senin bir el atmanı istiyorum."
- The wrapping that is always on goes: at rest the canvas draws a shared component and a system widget as the site does. He still wants them told apart quickly, so a part shows its frame and name under the pointer and when chosen, and one View switch lights them all.
- Why: a customer looking at the designer asked why the parts were wrapped, and it took explaining.

## What follows for the canvas? (architect, 2026-11-25)
- Drawn on boards Q1 to Q4 of `designer/designer-shared-marks-drawing.md`; built by step SM1 of `plans/designer-shared-marks.md`.
- The switch "Shared parts" is off at first: the default is the page with no marks, and a designer who wants the parts lit turns it on once.
