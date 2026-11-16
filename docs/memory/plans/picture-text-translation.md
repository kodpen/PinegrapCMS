---
name: picture-text-translation
description: Plan (owner 2026-11-16): the texts in pictures translated: a Design Canvas picture's text layers enter the site translation, a page in another language shows its picture; census PT0 first
priority: 6
---

## What is asked?
- Owner (2026-11-16, to the architect): "görsellerdeki metinlerde çeviri olayını da 6/10 olarak öne alalım". No earlier record of the wish was found in memory, the plans, the issues or the agent channel, so what it covers is the architect's reading below, told to him the same day.
- Today a page in another language translates its words and a picture's alt and title (`core/translation-attribute-texts.md`); the picture itself stays, so a banner's words stay in the source language.

## What is the architect's reading?
- T1 A picture saved from the Design Canvas keeps its texts as layers in its document (`ui/image-editor-text.md`, `ui/image-editor-versions.md`). Those words enter the site translation as the file's texts: same engines, jobs, glossary and review on the Translations screen as a page's words.
- T2 The Design Canvas shows the site's languages for such a picture with each one's state (not translated, translated, drawn, out of date). Picking one shows the layers in that language; a text can be corrected there and given a size or box for that language only, since a longer word may not fit.
- T3 A language's picture is drawn by the renderer a save uses and kept as that language's version of the file.
- T4 A page drawn in a language shows that language's version wherever the picture is drawn, the source picture where there is none.
- T5 When the source is saved again with other words, the changed texts go back to pending; what a visitor sees until the language's picture is drawn again is the owner's choice.
- Left out of the first version: a flat picture whose letters are pixels (a photo, a scan, an uploaded PNG): there is no document to read, and reading letters from pixels and painting over them needs a model or a service, which the canvas does not use (`ui/image-editor-overview.md`). Saved animations and videos. Pictures in e-mails.

## Which step is first?
- PT0 (S) The census, memory only: `plans/picture-text-translation-census.md`, no product code. It answers, from the code:
  - where a document with text layers lives for a file, a canvas and a workspace canvas, and how many text fields a layer has that hold words;
  - every path that writes a picture's address into a visitor's page (designer nodes, stored HTML, product pictures, `srcset`, backgrounds, the share picture) and which of them the finished-page pass (`pg_tr_render_db_texts()`) sees;
  - how the file's texts would enter the store (`translation_strings`, `translations`, `translation_uses`: owner type, a field per layer), what the Translations screen and a job need for them, what `pg_tr_translatable()` does with a banner's short words and prices;
  - who can draw a language's picture: the browser's renderer without the editor open, or the server (`platform/image-server-draw.md`: only fonts the server has a file of, three scripts), and what of a document each cannot draw;
  - what a longer or shorter translation does to a text layer's box, and what a right-to-left language needs;
  - three ways to keep a language's version (a table of its own; named in the source's document; a naming rule beside the file), each with its schema, the page pass's cost, and what a rename, a move, a delete, the Recycle Bin, Disk Usage and a private folder do to it.
- PT0 ends with the questions for the owner. The architect asks them; the steps PT1 on are written by the architect after his answers.

## What is the owner's, asked by the architect after PT0?
- Where a language's version is kept, if it needs a table or a column (schema).
- What a visitor sees while a language's picture is out of date: the older translated picture or the source.
- Whether a picture drawn by a person at the screen is enough at first, or it must be drawn with nobody there.
- Every new screen's look.
