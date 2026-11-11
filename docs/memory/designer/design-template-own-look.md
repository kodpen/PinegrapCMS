---
name: design-template-own-look
description: Rule (owner, 2026-11-07): sector sections first as palette blocks, reused not duplicated, general ones in general groups; own composition and motion on home and signature pages
---

## Why this rule?
- Owner, 2026-11-07 (`workflow/decisions-made-product-37.md`): the templates came out alike (the same bands, only the site name and intros change), which makes polishing them later hard. In 2026-11 29 of the 47 template folders defined the same `$framed` and `$story_band` builders, 21 `$ask_band`, 38 `$figures`, 31 `$testimonials`; 44 used `<prefix>-eyebrow` and an FAQ accordion; no template used the interactions (`_fx`).
- The news templates were the model: their sections were built as the `news-` palette family first (`designer-blocks-news-maintenance-creative.md`).

## What does a template build first?
- The sector sections it needs that the palette lacks, as a palette block family: its own `componentType` prefix and palette group, built to `palette-component-rules.md`, `designer-palette-tabs-and-blocks.md` and the `designer-block-*.md` files (markup, style, theme colours and surfaces, sample content, forms, motion). Its own step, before the pages; the family lives in its own source file (`plans/design-template-own-looks.md`, B0).
- The template's pages then place those blocks: the PHP tree draws the block's structure and classes, and its CSS comes through `pg_designer_block_css()` (the JS rules are the only copy), as the news template does with `pg-marquee`.

## When is a block reused instead of added?
- No duplicate blocks (owner, 2026-11-07). Before adding one, a step searches the palette and the families already added for a block of the same structure; if only the content differs, it places that block with its own content, or extends it with an option, never a copy.
- A template's family holds only what is truly sector-specific; a block several sectors can use goes into a general group (Marketing, Creative, Content Lists, Services & Booking, Social Proof…) under a neutral name, so the next template finds it.
- Each step's PR body names the blocks it reused and the ones it added. Copying another template's builders is never reuse.

## What makes its pages its own?
- The home and the signature pages (the sector's own: a launch's features and compare, a resort's conditions) have a composition of their own: no other template's builders and no stock band sequence (story band, figures row, testimonials, ask band, FAQ accordion on every page).
- The standard, account and request pages may stay plain ([[design-templates-standard-pages]]).

## Where does motion come from?
- Where the sector calls for it (a product launch, an event, a venue), from the interactions on nodes (`props._fx`: entrance, hover, click, sticky, parallax; `designer-interactions.md`), never a template's own JavaScript. Reduced motion is honoured by the platform script and the head style; a block's own motion follows `designer-block-motion.md` (no autoplay but the hero video decided there, no countdown).
- A quiet sector (law, pharmacy) may stay still: motion is chosen, not sprinkled.

## What does its plan say?
- A new template plan starts with its block family step, then the pages; it says what makes the template unlike the shipped ones (composition, signature sections, motion, type). "Built like X" in a plan means the file layout (main file plus a folder, one file per step) only, never the look.

## What about the templates shipped before the rule?
- Each gets one polishing round: its family, then its home and signature pages rebuilt, version raised (`plans/design-template-own-looks.md`).

Related: docs/memory/designer/design-templates-file-format.md, docs/memory/workflow/coordinator-no-plans.md
