---
name: ai-write-buttons
description: Plan (approved 2026-10-19, in progress: endpoint done): "Write with AI" buttons in the product builder, page SEO fields, image alt text and form records; AI-generated labelling left
---

## Why?
- AI text reaches records only as Workspace proposals (`includes/workspace/changes.php`, bulk `generate` in `bulk.php`) or the designer page assistant; the product builder, page settings and form fields have no AI help. Every compared builder and Turkish SaaS has in-form generation. The EU AI Act (general application 2 Aug 2026) asks AI-generated content to be labelled where it matters.

## What is done?
- Step 1: the `ai_write` panel action, prompt rules, answer reading, quota guards and tests. Behaviour: docs/memory/ui/ai-write.md.

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
2. Product builder (full and short description, browser title and description) and page settings (classic `edit_page.php` and the designer's page settings) buttons, shared `assets/js/pg_ai_write.js`; the `[NEW]` changelog line when they ship (S).
3. Form record text fields in the form widget (target `record_field`, gate by form rights); designer alt-text button once a vision model is known (S).
4. `ai_fields` columns in a migration step (ask the owner first), panel marker, token `__ai_generated` (S).
5. When done: decisions move to docs/memory/ui/ai-write.md and this file is deleted.

## Where to start?
- Step 2; read docs/memory/ui/ai-write.md, `product_builder.php` (`pg_pb_render_product_screen()`), `edit_page.php` and the `pinegrap-ui-deseni` and `pinegrap-js-varliklari` skills.
