---
name: ai-write-decisions
description: Why "Write with AI" is built the way it is (decided 2026-10-19/20): one endpoint, the models, daily cap, brand voice, the panel, labelling and the marks, translation, alt text on the browser's model
---

## Why does it exist?
- AI text reached records only as Workspace proposals or the designer's page assistant; every compared builder had in-form generation. The EU AI Act (general application 2 Aug 2026) asks AI-generated content to be labelled where it matters.

## What was decided?
- One endpoint (`ai_write`), no new provider. The draft never saves itself; the person accepts it.
- Pinegrap AI; without it the browser's own model (owner, 2026-10-19: Pinegrap AI is tuned to the level of Chrome's built-in model). Claude is not offered: a run takes minutes and few run a day.
- Daily cap `PG_AI_WRITE_DAILY_CAP` in data/config.php (default 200, 0 = off); no settings column.
- Brand voice: the style note of Settings › Languages (`TRANSLATION_STYLE_NOTE`), not a second note.
- UI: a small button beside the label opening a panel between label and field (not a popover: it works the same in a modal, on a phone and with a rich text editor); draft, "Use", "Try again", tone.
- Labelling: an accepted draft marks the field (`products.ai_fields`, `page.ai_fields`, schema approved 2026-10-20); a hand edit removes the mark (2026-10-20); sites that must disclose print the note `__ai_generated` (docs/memory/ui/ai-write-note.md).
- Translation stays with the Translations module; drafts are in the pages' language.
- Alt text needs a model that reads images: the browser's (docs/memory/ui/ai-write-alt-text.md). A Pinegrap AI path waits until its connection can say its model reads images.

## What is still open?
- Which widget would print a page's own marks (`page.ai_fields`): none does; the marked page texts (title, meta description) are not in the page body.
- The API, imports and Workspace proposals do not mark texts.

## Which PRs?
- #581 endpoint, #583 product and page screens, #585/#586 form records and designer settings, #597 browser model, #811/#2123/#2453 marks, #2949 the note.
