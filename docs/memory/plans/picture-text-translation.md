---
name: picture-text-translation
description: Plan (owner 2026-11-16): the texts in pictures translated: a Design Canvas picture's text layers enter the site translation, a page in another language shows its picture; steps PT1 to PT7
priority: 6
---

## What is asked?
- Owner (2026-11-16, to the architect): "görsellerdeki metinlerde çeviri olayını da 6/10 olarak öne alalım".
- Today a page in another language translates its words and a picture's alt and title (`core/translation-attribute-texts.md`); the picture itself stays, so a banner's words stay in the source language.
- The census PT0 (PR #9962): `plans/picture-text-translation-census.md` and its four files. The steps read them; the close-out deletes them.

## What did the owner decide? (agent channel poll 98, message 6808, 2026-11-16: "Evet, böyle olsun")
- Which pictures: those made in the Design Canvas whose text stands as layers and which a page opened in another language shows. Writing inside a photo or a scan is not in the first version.
- Who draws: a signed-in person in the panel, in their browser. Nothing is drawn by itself with nobody there.
- While a language's picture is not ready, or once the source changed, a visitor sees the source picture until the new one is drawn. Not chosen: the older translated picture meanwhile.
- The languages' pictures are kept in a small table of their own; the architect confirms its columns.

## What did the architect decide? (2026-11-16, agent channel message 6748, and with the steps)
- Right-to-left languages are not in the first version: the editor has no text direction and its letter spacing breaks Arabic joining. A visitor sees the source picture there and the panel says so.
- No box or size per language. A translated text is fitted to the source's box: the size shrinks, never under 70% of the source's; a text that still does not fit is drawn and its row marked "Look by hand".
- The texts enter the store as owner type `file` (`translation_uses`: the owner is the file's id, the node the layer's id, the field `text` or `label`); the three translation tables do not change. The same words as a page's are the same string, so a translation, the glossary and a review apply at once.
- Only a picture a page in another language has shown is counted, sent to an engine and drawn: the page pass notes it, nobody marks pictures by hand.
- A language's picture is a `files` row of its own in the source's folder, so widths, formats and the object store work as for any picture. It is found only through the table, never by a rule in its name, and no file list shows it.
- The table and its columns: `workflow/decisions-made-schema-25.md`.

## Whose look is each screen?
- The owner's: the "Pictures" group on the Translations screen (PT3), the languages list in the Design Canvas (PT4), the drawing queue (PT5). The architect looks first and brings him one sheet a step; a PR stays a draft until his look.

## What is left out?
- A flat picture whose letters are pixels (a photo, a scan, an uploaded PNG); saved animations and videos; pictures in e-mails and in RSS; a Workspace canvas; the words inside an svg layer; right-to-left languages; a picture drawn with nobody there; correcting one language's picture by hand (a later step, the owner's look).
- Places the page pass does not read: a `<style>` block, the design's custom CSS file, the `image` of JSON-LD, a variant's photos in the page's script data.

## Where are the steps?
- PT1 to PT4b: `plans/picture-text-translation-steps.md`; PT5 to PT7: `plans/picture-text-translation-steps-2.md`.
