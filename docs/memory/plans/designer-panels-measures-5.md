---
name: designer-panels-measures-5
description: Plan designer-panels, several elements selected in the Options panel as text (`options-6-multiple-selection.jpg`, owner 2026-11-17): identity, tabs, "— Multiple —", the ring dot, badges, brush, filter
---

## What is decided?
- Owner (2026-11-17, agent channel poll 128, message 8263): "Evet, böyle çıksın" on the sheet of discussion message 7592, `plans/designer-panels/options-6-multiple-selection.jpg`. DP5 draws a multiple selection from it.
- The sheet draws the panel at 336 px twice: three headings on Content; a heading, a paragraph and a button on Style. Its values are samples. Under 300 px `designer-panels-measures-4.md` holds as it stands.

## What stays from today's editor?
- `renderMultiProperties()` in `style_designer.src.js`: elements are added with Shift or Ctrl (K6); only what every selected element shares is offered; a control whose values differ reads "— Multiple —" ("— Çoklu —"); a choice or a typed value is written to all.
- Text, link, picture address, translation and data binding belong to one element. One line on Content says so ("… öğeye özeldir; tek öğe seçilince görünür").
- New on the sheet: the frames, the ring dot and the counts.

## What does the identity row show?
- The kind's icon, or the layers icon for different kinds, with the count on its lower right corner: 18 px, the accent ground, a 2 px ring of the panel's ground.
- "3 öğe seçili" and a badge: the kind's name when the kinds are alike ("Başlık"), else "karışık".
- In the path's place the shared parent ("Hepsi aynı üst öğede: Satır"), else the kinds ("Başlık · Paragraf · Düğme").

## Which tabs and parts are drawn?
- Kinds alike: Content, Style, Advanced. Content holds the kind's shared options (a heading's level) in a section named after the kind, with the summary "ortak seçenekler".
- Different kinds: Content is not drawn and Style opens.
- The quick strip is drawn only when the kinds are alike; a card whose values differ reads "— Çoklu —" in grey italics.

## How is a mixed value drawn?
- Values alike: the control is the single element's.
- A button group: no segment selected, and each value present in the selection framed with a 1 px accent line. A muted line under the row may spell the values ("— Çoklu —: iki öğe H2, bir öğe H3").
- A field or a dropdown: "— Çoklu —" in grey italics; a colour's swatch is split in two.
- The spacing box: "~" in the accent colour in an accent-line frame; its core reads the count ("3 öğe").

## What do the dot and the badges count?
- The changed dot is full when every element changed the row and a ring (2 px accent, hollow) when some did; the tip says in how many ("3 öğenin 2'sinde"). The reset arrow returns the row to its default in all.
- A tab's and a section's badge count the rows changed in at least one element. A closed section's summary that differs reads "çoklu".

## What does a change do?
- It is written to every selected element as one undo step. A strip above the filter says so ("Değişiklik üç öğeye birden yazılır": accent-soft ground, accent-line border).
- The brush: "Paste style" writes to all; "Copy style" needs one element and is dimmed.
- "Only what I changed" lists each row once, with how many elements changed it ("3/3", "2/3") and one reset arrow.
