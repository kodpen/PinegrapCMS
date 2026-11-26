---
name: picture-text-translation-fit-letters
description: Picture text translation, a translated text's fit and its letters: the owner's words (2026-11-17, 2026-11-25), the floor by a text's size, the face asked per layer text, what is open
module: picture_translation
---

## What did the owner say?
- Owner (2026-11-17, in the architect's window), naming two points that might have been passed over, a text longer than its source shrinking to its box and the font files holding the target language's letters so that no empty box is drawn: "bunlar için gerekirse iyileştirme yaparız. görsellerdeki metinler auto-fix yolu ile yerine aldığı metin kadar alana sığabilir."
- The architect's reading, not his words: the fit as built is what he expects, and an improvement is made only where a point below turns out to need one. These words changed no behaviour.

## What does the draw already do for a longer text?
- `M.languageFitted()` (`picture-text-translation-draw.md`): the text grows into its free room, is wrapped between words, then the size shrinks until it is inside that room, never under its floor (below). Under it the text is drawn at the floor, so it runs past the room, and the language is listed as "Look by hand" until that text is corrected by hand (`picture-text-fit-correct.md`).
- The room: `picture-text-fit-room.md`. The server follows the same rule with its own letter widths (`picture-text-auto-server.md`).
- Wider than its room a translation is first wrapped between words, then made smaller (`picture-text-fit-wrap.md`).

## What floor does a large text get?
- Owner (2026-11-25), after a slightly longer English translation was marked at once: "küçültme oranı 70% ile sınırlı olsa da belli bir boyut üstünde tolerans artsın."
- The numbers are the architect's reading: the floor goes by the source text's size as a share of the picture's shorter side. Up to 4% the floor is 70%; from 8% it is 50%; between them a straight line. On a 1080 px picture a text of 43 px keeps 70% and a headline of 86 px or more may go down to half, still as large as the small text that keeps its floor.
- Built in `M.languageFloor()` / `M.languageFit()` and their PHP twins `pg_picture_tr_language_floor()` / `pg_picture_tr_language_fit()` (PR #11698): the side is `M.docSize()` in a browser, the artboard on the server; a text with no size or a picture with no side keeps 70%. The floor is rounded to four places.

## What does the draw already do for letters?
- A browser: `loadTextFont()` (`assets/js/pg_image_editor_text.js`) links a Google family once per face (font string and family) and asks the face again for each layer text's letters it was not asked for yet (`M.textFontLetters()`), so a second text of one face in another part of the font (digits, then Cyrillic) waits for that part too;
- Each language is drawn by a painter with its own font list. A letter the family lacks is drawn by the browser in the next family: never an empty box, but another face's letter, and nothing says so.
- The server: text only in Latin, Greek and Cyrillic, and a letter the font file lacks leaves the whole document to a browser (`platform/image-server-draw.md`): it draws no empty box either.
- Right-to-left languages are not drawn (`picture-text-overview.md`).

## What is open? (architect, 2026-11-17, read in the code, not tried in a browser)
- A family with no letters of the language (a display face without Cyrillic or `ğ`, `ş`, `ı`): the picture is drawn with borrowed letters and listed as drawn. A mark on the Languages row would be a new control, so the owner's look.

## Where is the rest of the fit?
- Owner (2026-11-26, poll 229, `workflow/decisions-made-product-113.md`): a translated text grows into the free room, then wraps, then shrinks to the floor above, and one language's texts are corrected by hand: `picture-text-fit-room.md`, `picture-text-fit-wrap.md`, `picture-text-fit-correct.md`, `picture-text-fit-view.md`.
