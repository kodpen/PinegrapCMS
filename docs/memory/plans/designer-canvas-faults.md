---
name: designer-canvas-faults
description: Plan (owner 2026-11-13, #9104): round pictures drawn oval on the designer canvas, a picture that does not show, the card inside a loop (toolbar block, sample cards, editing); steps CF1-CF5
---

## What is asked?
- Owner (2026-11-13, #9104), with four canvas pictures: "sık rastladığım tasarımcı hataları: görsel 1 ve görsel 4 - circle alanları oval. görsel 2 - sağda görsel var çıkmıyor. görsel 3 - loop içinde karmaşalı card tasarımları".
- Asked what the loop card's mess is, he ticked all four: "Mavi blok kartı örtüyor", "Kart yayındakine benzemiyor", "Tek kart, kalanı boş", "Kartı düzenlemek zor". He had raised the loop card before and was not understood: a step unsure of his meaning asks the architect, it does not guess.

## What do the pictures show?
- 1 and 4: the author photo of the Google reviews card and the member photo of the team card are grey ellipses about twice as wide as high, with "Görsel" and the token name.
- 2: in the hero of a new site ("Merhaba, Pinegrap!") the picture on the right is an empty selected box of about 670 by 35 px.
- 3: the "Blogdan" loop holds one card at the left third and nothing else; its selected picture (415 × 233) is half covered by a filled blue rectangle carrying the element toolbar's icons.

## Which rules does every step keep?
- The canvas shows what the published page shows. A fault the published page has too is fixed in the component (PHP tree, JS starter, server fallback: `designer/system-widget-change-checklist.md`), a canvas-only fault in the canvas.
- Chrome never covers what it marks (`designer/designer-canvas-chrome-placement.md`). Bands and chips stay on; `plans/designer-canvas-real-samples.md` tidies them and gives the values.
- Each PR: the owner's picture before and after, light and dark; a test for a pure function it changes.

## Which steps?
- CF1 (S) Round stays round. A picture's canvas stand-in takes the node's own box: a circle (`rounded-circle`, equal sides) is drawn as a circle, a ratio class keeps its ratio; the stand-in brings no shape of its own. Checked on the two cards and on every starter with a round picture; where the published page is oval too with a photo that is not square, the starter gets equal sides and a covering fit.
- CF2 (S) The picture that does not show. Reproduce on a new site with picture 2's template; find why the right picture is an empty strip (no source, an unfilled path, a file not copied, a late load) and fix the cause. A picture with no source or a failed load draws the no-image stand-in at the node's ratio (`designer/no-image-placeholder.md`), never an empty strip.
- CF3 (S) The toolbar block. A selected element's toolbar is the small strip at its spot (`_sdTbSpot`), never a filled rectangle over the element, also for a picture in a card in a loop; picture 3's cause is found and tested.
- CF4 (M) The loop as published. A loop area draws as many cards as one row of its grid holds on the published page (three at desktop in picture 3): the first editable, the others as the hatched copies of the Google reviews loop; in every loop kind, also those `designer/widget-condition-canvas.md` leaves without sample rows. A bound picture draws a sample picture of the product's own at the node's ratio, the token's label in its title, not a grey box with the token name.
- CF5 (M) Editing a card in a loop. First a walk on the blog, team and reviews cards: pick each element, move one, change the card's layout, with a picture of every place where frames, the band, the size badge or selection boxes lie over each other or the selection is unclear; then those are fixed. The pictures after go to the owner through the architect before the step lands (an `architect` approval issue).

## In which order?
- CF1, CF2 and CF3 at once; CF4 after CS1 of `designer-canvas-real-samples.md` (the same band code); CF5 last. Claim as `[plan:designer-canvas-faults] <step> — <topic>`.
