---
name: ai-write-alt-text
description: "Write with AI" for an image's alt text in the visual designer: the image_alt target, the browser's model only, the image read in the browser, where the button sits and when it shows
---

## Where is it?
- Visual designer, an image's properties, under the Alt input (`id="sd-prop-img-alt"`). The server draws the button once, hidden, in `<template id="sd-ai-alt-button">` (`pg_designer_ai_alt_template()`, includes/designer_screen.php); `_sdAiAltButton()` (style_designer.js, from `bindPropEvents()`) puts a copy in the row's `.sd-ai-alt-slot` and shows it when `PinegrapAiWrite.imageReady()` resolves true. The panel opens under the row (adapter `place`); "Use this text" fills the input, whose input event writes `props.alt` like typing (one undo step).
- Semantic `<img>` elements and the Attributes panel's alt row have no button.

## Which model?
- Only the browser's (Prompt API, image input), on every site where the feature is on, Pinegrap AI or not: nothing in the Pinegrap AI connection says its model reads images, and the image never goes to the server. `pg_ai_write()` refuses an `image` target ("Only the browser's built-in AI can describe an image.").
- `imageReady()` asks `LanguageModel.availability()` once with `expectedInputs` text + `{type: 'image'}`, the same language ladder and Translator bridge as text (docs/memory/ui/ai-write-browser.md); a browser whose model takes no images never shows the button.

## What does the model get?
- Target `image_alt` (`pg_ai_write_targets()`, flag `image`, record `page`, line, ≤125): the usual `step: prompt` / `step: finish` through `ai_write`. Context: page title and address, `image` (the src input, "Image address") and `image_alt` (the alt now, sent as `current_text`); the page's saved headings and text join as for other page fields. The rules add that the image comes with the message and nothing it does not show may be guessed. No tone (the select is hidden).
- The image: `fetch()` of the src input's address (relative to the page, http(s) only, cookies of this site), a `Blob` of an `image/*` type, sent in the same user message as the rules and data (`{type: 'image', value}`). A file another site does not let the page read fails with "The image could not be read…"; an empty src with "Choose an image first…".

## Marked?
- No: the alt text lives in the page's tree, not in a column; `pg_ai_fields_columns()` has no entry and the column test skips `image` targets.

## How was it checked?
- `tests/ai_write_test.php` (`test_ai_write_image_alt_*`), `tests/ai_fields_test.php`, `node --check`. Not run in a browser: the sandbox has no Prompt API, and image input needs a recent desktop Chrome.
