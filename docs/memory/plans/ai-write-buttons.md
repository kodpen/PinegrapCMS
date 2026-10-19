---
name: ai-write-buttons
description: Plan (approved 2026-10-19, in progress: endpoint and the product, page property and form record buttons done): "Write with AI"; designer page settings, alt text, labelling left
---

## Why?
- AI text reaches records only as Workspace proposals (`includes/workspace/changes.php`, bulk `generate` in `bulk.php`) or the designer page assistant; the product builder, page settings and form fields have no AI help. Every compared builder and Turkish SaaS has in-form generation. The EU AI Act (general application 2 Aug 2026) asks AI-generated content to be labelled where it matters.

## What is done?
- Step 1 (PR #581): the `ai_write` panel action, prompt rules, answer reading, quota guards and tests. Behaviour: docs/memory/ui/ai-write.md.
- Step 2 (PR #583): buttons and the draft panel on the product screen and the page properties screen (`assets/js/pg_ai_write.js`), the `[NEW]` changelog line. Behaviour: docs/memory/ui/ai-write-panel.md.
- Step 3, first half (PR #585): custom form records (`record_field`) on the record add and edit screens. Behaviour: the same two files.

## What is decided?
- One endpoint (`ai_write`, `includes/panel/ai_write.php` + `includes/fn/ai_write.php`), no new provider. The draft never saves itself; the person accepts it.
- Pinegrap AI only (`pg_ai_chat()`). Claude is not offered for the buttons: a routine run takes 1–3 minutes and counts against a few runs a day. Buttons are hidden when Pinegrap AI is not set up (`pg_ai_write_available()`).
- Daily cap (was the open question): `PG_AI_WRITE_DAILY_CAP` in data/config.php beside the other Pinegrap AI knobs, default 200, 0 = off; no settings column.
- Brand voice: the style note of Settings › Languages (`TRANSLATION_STYLE_NOTE`) is reused rather than a second note.
- UI: a small "Write with AI" button beside the field, opening a popover with the draft, "Use", "Try again", tone (neutral, friendly, formal, persuasive). Text fields get the draft by `.value`, the rich text editor gets the server-built HTML.
- Labelling: an accepted draft marks the field (`products.ai_fields`, `page.ai_fields` JSON lists) so the panel can show a small marker and sites that must disclose can print a note through a widget token `__ai_generated`.
- Translation stays with the Translations module; drafts are in the pages' language.
- Alt text needs a model that reads images. Nothing in the Pinegrap AI connection says whether its model does (the code sends text only, and the model it tunes for, gpt-oss, reads text), so that button stays hidden until the connection can tell.

## What remains?
3. The visual designer: browser title and description in its page settings; the alt-text button once a vision model is known (S).
4. `ai_fields` columns in a migration step (ask the owner first), panel marker, token `__ai_generated` (S).
5. When done: decisions move to docs/memory/ui/ai-write.md and this file is deleted.

## Where to start?
- Step 3; read docs/memory/ui/ai-write-panel.md, the `pinegrap-tasarimci-widget` skill and docs/memory/designer/designer-sdt-translation.md (the designer's words go through `_sdT()`).
