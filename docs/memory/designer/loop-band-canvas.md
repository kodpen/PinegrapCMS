---
name: loop-band-canvas
description: The loop band and the element chips on the canvas: band height and tooltips, the chip row (Only when, Off, Shown when) in the flow, see-through wrappers, the Turkish band terms (#2523)
---

## Why does the band stay on one line?
- The band is an absolutely positioned label over the loop area (`buildInnerElement` in `style_designer.js`, the inline canvas CSS). In a narrow loop area (a 156 px ticker at the XS preview, a sidebar list) its text wrapped into four lines and lay over the area's content, and the ghost-record note ("· 2 real records after the card") fell into the middle of it (#2523). The band now carries `white-space: nowrap; overflow: hidden`, its text span `min-width: 0; overflow: hidden; text-overflow: ellipsis`, and the note `flex: none`; the band's `title` holds the whole text (set when the band is built and again when the note is appended), so a cut band still reads in full on hover. The table-row variant (`.sd-loop-band-inner`) already clipped.

## Which Turkish terms do the band and its notes use?
- One word per term, set with `tools/tr_add.php --replace`: loop area → "tekrar alanı", at runtime → "yayında", template → "şablon", canvas → "tuval", rendered → "çizilir / çizilmez". The band ("Tekrar alanı — 1 kayıt gösteriliyor (yayında her kayıt için yinelenir)"), the row band, the two drop hints ("Yinelenen şablonu buraya sürükleyin …") and the three "this widget …" notes under a loop that does not run follow it. Keep new designer texts on these terms.

## How tall is the band, and which bands keep their text in a title?
- The loop area keeps 22 px above its content (`padding:22px 0 4px`) and the band is exactly that tall (`box-sizing:border-box; height:22px`), so the first child's chip row starts under it; at 23 px it lay 1 px over the chips (#9188 CS1).
- Every band carries its full text in `title`: the loop and list band, the recipient band (`rlaBand`), the table band row (`bandInner`).

## Where do an element's chips go?
- In one chip row (`_sdChipRow()`, `.sd-chip-row`) in the flow at the top of the element's wrapper: the value condition ("Only when: …", `data-sd-cond`), the switched-off reason (`data-sd-off-reason`) and a form field's "Shown when" (`data-sd-cf-shown`). Before #9188 they were pseudo elements over the element's top-left corner: a parent's and its first child's chips lay on each other, and on the list bands (the order e-mail card of #9076).
- The row wraps; a chip that does not fit ends in "…" with its full text in its title. `width:0; min-width:100%` keeps a long chip from widening a table column; in an inline wrapper it is `inline-flex` before the element.
- A see-through wrapper (`display:contents`) keeps the row in the flow unless its layout parent sets children side by side (grid, table, flex row: `_sdChipRowBeside()`); there `_sdPlaceChromeOf()` puts it over the element's corner, no wider than the element. A summary (its own wrapper) keeps the old pseudo chips. Test: `tests/designer_canvas_chips_test.php`.
