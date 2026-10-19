---
name: ai-write-buttons
description: Plan (approved 2026-10-19, not started): "Write with AI" buttons in the product builder, page SEO fields, image alt text and blog records, reusing the Workspace AI client; AI-generated labelling
---

## Why?
- AI text today reaches records only as Workspace proposals (`includes/workspace/changes.php`, bulk `generate` in `bulk.php`) or the designer page assistant; the product builder, page settings and form fields have no AI help. Every compared builder and Turkish SaaS has in-form generation. The EU AI Act (general application 2 Aug 2026) asks AI-generated content to be labelled where it matters.

## What is decided?
- One endpoint `includes/panel/ai_write.php` (role ≤2 or `manage_ecommerce`/content rights, CSRF, rate limit per user) that takes a target kind (`product_description`, `product_short`, `seo_title`, `seo_description`, `alt_text`, `record_field`), the record context (name, group, attributes, price, existing text, page purpose, brand style note from Settings) and returns a draft. It calls the existing model client `ws_ai_*` (Pinegrap AI with the Premium key) or the Claude queue when that is the configured assistant; no new provider. Buttons are hidden when no assistant is configured.
- UI: a small "Write with AI" button beside the field (product builder description/short description, page settings title/meta description, image node alt in the designer, form record text fields in the form widget edit chip), opening a popover with the draft, "Use", "Try again", tone options. The draft never saves itself; the user accepts it.
- Labelling: an accepted draft adds `data-ai-generated="1"` on the field's save for products and pages (`products.ai_fields`, `page.ai_fields` JSON list) so the panel can show a small marker and sites that must disclose can print a note through a widget token `__ai_generated`.
- Translation stays with the Translations module; the button produces text in the site's default language.
- Alt text: for images without alt the designer validator offers "Generate" that sends the image URL (public) to the same endpoint; the model must support vision, else the button is hidden.

## What remains?
1. Endpoint + prompt templates + tests on the prompt builder (S).
2. Product builder and page settings buttons (S).
3. Designer alt-text button (S); form record fields (S).
4. `ai_fields` columns in a migration step, panel marker, token (S).
5. Memory: `docs/memory/ui/ai-write-buttons.md` when shipped.

## Open questions?
- Daily cap per site to protect the Premium quota: setting, default 200 calls/day.

## Where to start?
- Step 1; read `includes/workspace/ai.php` (client, tool names) and `includes/designer_ai.php` (how proposals call the model).
