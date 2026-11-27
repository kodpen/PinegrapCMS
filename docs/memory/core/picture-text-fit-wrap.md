---
name: picture-text-fit-wrap
description: A translated picture text wider than its room is wrapped between words before it shrinks (PF2): M.languageWrap(), languageWraps(), languageWrapped(), PHP twins, the lines written into the layer's text
module: picture_server_draw
---

## When is a translated text wrapped?
- In `M.languageFitted()` / `pg_picture_tr_language_fitted()`, after the room is read (`picture-text-fit-room.md`): when the translation at the source's size on its own lines is wider than its room. With no room (the old rule) nothing is wrapped.
- Owner's order (2026-11-26, poll 229, `workflow/decisions-made-product-113.md`): grow, wrap, shrink.

## How is a text broken?
- `M.languageWrap(text, max, measure)` / `pg_picture_tr_language_wrap()`: each of the text's own lines wider than `max` is broken between words (at spaces) into the fewest lines that fit, balanced: the break with the narrowest widest line; of two as narrow, the last line is the shorter.
- A word is never broken: a word wider than `max` sets the width. A line of one word, or of more than 40 words (every run of words is measured), is not broken. A broken line's words are joined by one space.

## Which wrap is drawn?
- `M.languageWraps(text, measure)` / `pg_picture_tr_language_wraps()`: the text as it is, then its widest own line balanced in 2, 3 … lines, the whole text wrapped to each of those widths, each wrap once.
- `languageWrapped()` / `pg_picture_tr_language_wrapped()` measure them with the draw's own layout at the source's size and keep the one that needs the least shrink (`min(1, room w / w, room h / h)`); of two alike the fewer lines. So a wrap that fits whole is drawn at the source's size, and the shrink (`M.languageFit()`, floor unchanged) runs on the wrap kept.
- The text as it is is always a candidate: no text ends smaller than the growth alone made it.

## How do the draws get the lines?
- The kept wrap is written into the language document's layer `text` with new lines; the stage's painter (`drawLanguage()`), the save's renderer and the server's `pg_image_draw_plan()` already break a text only at its new lines (`ui/image-editor-text.md`), so they paint the same lines with no rule of their own. The source document is not changed.
- The server measures with its own letter widths (`pg_picture_tr_server_layout()`), so its break may differ from a browser's where a line is near the room's width.

## How is it tested and measured?
- `tests/picture_text_fit_test.php`: `wrap.json` (the break and the candidates, measure ten px a letter) in both twins; `room.json` (21 cases) now holds texts wrapped at their size, wrapped then shrunk, and one long word still marked at its floor, its lines compared exactly between the twins.
- `measure.json`'s translations are words of seven letters. "Look by hand" at 1.0, 1.3, 1.6, 2.0, 2.5 times the width: 0/0, 0/0, 4/0, 8/0, 24/0 (36 of 120 before the room, 2 with the growth alone, 0 with the wrap); 24 texts are wrapped.
