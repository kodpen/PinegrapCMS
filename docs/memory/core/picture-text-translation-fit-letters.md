---
name: picture-text-translation-fit-letters
description: Picture text translation, a translated text's fit and its letters: the owner's words (2026-11-17, 2026-11-25), the floor by a text's size, what the draw does, what is open
module: picture_translation
---

## What did the owner say?
- Owner (2026-11-17, in the architect's window), naming two points that might have been passed over, a text longer than its source shrinking to its box and the font files holding the target language's letters so that no empty box is drawn: "bunlar için gerekirse iyileştirme yaparız. görsellerdeki metinler auto-fix yolu ile yerine aldığı metin kadar alana sığabilir."
- The architect's reading, not his words: the fit as built is what he expects, and an improvement is made only where a point below turns out to need one. These words changed no behaviour.

## What does the draw already do for a longer text?
- `M.languageFit()` (`picture-text-translation-draw.md`): the size shrinks until the text is inside its source's box, never under 70%. Under it the text is drawn at 70%, so it runs past the box, and the language is listed as "Look by hand". The server's draw follows the same rule with its own letter widths (`pg_picture_tr_language_fit()`, `picture-text-auto-server.md`).
- A line breaks only where the text has a new line (`ui/image-editor-text.md`): a long translation is made smaller, never wrapped.

## What floor does a large text get?
- Owner (2026-11-25), after a slightly longer English translation was marked at once: "küçültme oranı 70% ile sınırlı olsa da belli bir boyut üstünde tolerans artsın."
- The numbers are the architect's reading: the floor goes by the source text's size as a share of the picture's shorter side. Up to 4% the floor is 70%; from 8% it is 50%; between them a straight line. On a 1080 px picture a text of 43 px keeps 70% and a headline of 86 px or more may go down to half, still as large as the small text that keeps its floor.
- Step ON3 of `plans/owner-notes-2026-11-25.md` builds it in `M.languageFit()` and its PHP twin; until it lands the floor is 70% for every size.

## What does the draw already do for letters?
- A browser: `loadTextFont()` (`assets/js/pg_image_editor_text.js`) asks the face with the layer's own text, so the part of a Google font that holds those letters is loaded before the draw; each language is drawn by a painter with its own font list. A letter the family lacks is drawn by the browser in the next family: never an empty box, but another face's letter, and nothing says so.
- The server: text only in Latin, Greek and Cyrillic, and a letter the font file lacks leaves the whole document to a browser (`platform/image-server-draw.md`): it draws no empty box either.
- Right-to-left languages are not drawn (`picture-text-overview.md`).

## What is open? (architect, 2026-11-17, read in the code, not tried in a browser)
- Under the floor a text is still drawn at its floor and marked; wrapping it onto a second line stays the owner's, asked when he names it.
- The font list's key is the face and the family, not the text. Two text layers of one face, of which only the second has letters of another part of the font (digits, then Cyrillic), wait only for the first part, so the second may be drawn in the next family at the first draw. A small fix for a worker: ask the face per layer text.
- A family with no letters of the language (a display face without Cyrillic or `ğ`, `ş`, `ı`): the picture is drawn with borrowed letters and listed as drawn. A mark on the Languages row would be a new control, so the owner's look.
