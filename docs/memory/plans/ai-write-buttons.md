---
name: ai-write-buttons
description: Plan (approved 2026-10-19, in progress: endpoint, text buttons, browser model, panel AI marks done): "Write with AI"; image alt text, designer marks and the __ai_generated token left
---

## Why?
- AI text reaches records only as Workspace proposals (`includes/workspace/changes.php`, bulk `generate` in `bulk.php`) or the designer page assistant; the product builder, page settings and form fields have no AI help. Every compared builder and Turkish SaaS has in-form generation. The EU AI Act (general application 2 Aug 2026) asks AI-generated content to be labelled where it matters.

## What is done?
- Step 1 (PR #581): the `ai_write` panel action, prompt rules, answer reading, quota guards and tests. Behaviour: docs/memory/ui/ai-write.md.
- Step 2 (PR #583): buttons and the draft panel on the product screen and the page properties screen (`assets/js/pg_ai_write.js`), the `[NEW]` changelog line. Behaviour: docs/memory/ui/ai-write-panel.md.
- Step 3 (PRs #585 and #586): custom form records (`record_field`) on the record add and edit screens; the visual designer's page settings (title, meta description). Behaviour: the same two files.
- The browser's built-in AI (PR #597): a site without Pinegrap AI drafts with the person's browser model (Prompt API), Turkish through the Translator API. Behaviour: docs/memory/ui/ai-write-browser.md.
- Step 4, first part (PR #811): `products.ai_fields`, `page.ai_fields` (11.0.23), the marker on the product and page properties screens. Behaviour: docs/memory/ui/ai-write-marks.md.

## What is decided?
- One endpoint (`ai_write`, `includes/panel/ai_write.php` + `includes/fn/ai_write.php`), no new provider. The draft never saves itself; the person accepts it.
- Pinegrap AI (`pg_ai_chat()`); without it the browser's own model (owner's request, 2026-10-19: Pinegrap AI is tuned to the level of Chrome's current built-in model). Claude is not offered for the buttons: a routine run takes 1–3 minutes and counts against a few runs a day.
- Daily cap (was the open question): `PG_AI_WRITE_DAILY_CAP` in data/config.php beside the other Pinegrap AI knobs, default 200, 0 = off; no settings column.
- Brand voice: the style note of Settings › Languages (`TRANSLATION_STYLE_NOTE`) is reused rather than a second note.
- UI: a small "Write with AI" button beside the label, opening a panel between the label and the field (not a popover: it works the same in a modal, on a phone and with a rich text editor) with the draft, "Use", "Try again", tone (neutral, friendly, formal, persuasive). Text fields get the draft by `.value`, the rich text editor gets the server-built HTML.
- Labelling: an accepted draft marks the field (`products.ai_fields`, `page.ai_fields`; schema approved 2026-10-20): a marker in the panel, and a note that sites which must disclose print through a widget token `__ai_generated`. A hand edit removes the mark (2026-10-20).
- Translation stays with the Translations module; drafts are in the pages' language.
- Alt text needs a model that reads images. Nothing in the Pinegrap AI connection says whether its model does (the code sends text only, and the model it tunes for, gpt-oss, reads text), so that button stays hidden until the connection can tell.

## What remains?
3. The alt-text button in the designer once the Pinegrap AI connection can say its model reads images, or on the browser's model, whose Prompt API takes images (`expectedInputs` type `image`) (S).
4. The rest of the labelling (S): the designer's page settings mark Page Title and Meta Description on `pg:ai-write-used` and save `page.ai_fields` (`pg_ai_fields_after_save()`; after the open PRs on `includes/designer_screen.php` and `style_designer.js`); the widget token `__ai_generated` printing a note where `pg_ai_fields_marked()` names a column. Copies: #2123.
5. When done: decisions move to docs/memory/ui/ai-write.md and this file is deleted.

## Where to start?
- The rest of step 4: docs/memory/ui/ai-write-marks.md; the designer listens for `pg:ai-write-used` as `assets/js/pg_ai_fields.js` does.
