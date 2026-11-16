---
name: listen-block
description: Plan (owner 2026-11-16, agent channel message 5667): a "Listen" block of the visual designer that reads the page's article aloud with the browser's own voice; steps LS1 to LS3
priority: 6
---

## What is asked?
- The owner's words and the layout of the set: `plans/website-features.md`. It was item 11 of agent channel message 5657.
- A visitor presses "Listen" on an article and hears it: on the way somewhere, with tired eyes, or because reading is hard for them.

## What is there today?
- Nothing reads a page aloud. Its nearest relative is the in-browser summary of a form item view (`designer/form-item-ai-summary.md`): `pg_ai_summary_inject()` adds `assets/js/pg_ai_summary.js`, which reads the text of the nearest `article`, else `main`, and stays hidden where the browser cannot do it.
- A ready block never carries a script (`designer/palette-component-rules.md`). A platform script comes from a mark in the page's markup: `data-pg-scroller` makes `get_page_content.php` call `pg_scroller_inject()`, and the Preview loads the same file from its twin row in `_SD_PAGE_SCRIPTS` (`designer/designer-preview-page-scripts.md`; `tests/designer_page_scripts_test.php` compares the two).
- A block family is one file with one `StyleDesigner.registerBlockFamily()` call, found by itself (`pg_designer_js_sources()`); the smallest model is `style_designer_blocks_scene.js`.
- A page's language is on `<html lang>`; a machine-translated page carries a value such as `en-x-mtfrom-tr`.

## What is built?
- The numbered features: `listen-block-features.md` (L1 to L13). Steps: `listen-block-steps.md`. Claim as `[plan:listen-block] <step> — <topic>`; the rules: `website-features-rules.md`.
- In short: a block that is only markup with a mark, and one script the server adds to pages that carry the mark. The browser's own voice reads the article; where the browser has none the button never shows.

## What binds it?
- `designer/palette-component-rules.md`: no script or style in a block.
- `designer/catalog-binding-disabled-no-output.md`: never a visible element that does nothing (L8).

## What does the owner decide?
- Nothing before LS1. LS2's look is his.

## What is left out?
- A recorded voice made by an AI service and kept as a file (the Workspace already speaks through `ws_voice_speech_ready()`, `workspace/workspace-voice-speech.md`); the sentence being read marked on the page; speed and voice choices; pictures read by their alt text. Each is a later yes.

## Whose look?
- The owner's: the block is new on the site.
