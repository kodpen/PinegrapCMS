---
name: no-image-placeholder
description: assets/images/no-image.svg, the fallback for a missing or failed image, is an icon without words so it reads the same in every site language (PR #4472)
---

## What is the placeholder?
- `assets/images/no-image.svg`: the picture-frame icon the designer image renderer, the product carousel and the `onerror` fallback show when an image is missing or fails to load.

## Why does it carry no text?
- A static file cannot follow the page's language: it drew "No image available" in English on Turkish sites (issue #4409; an earlier version drew it in Turkish on English sites). Since PR #4472 it is the icon only, centred, `aria-hidden="true"`; the `<img>` element's `alt` names the item.
- Do not add a caption back to the file; a visible text belongs in the page markup through `lang()` / `_sdT()`.
- `tests/site_language_texts_test.php` fails on a `<text>` element or an `aria-label` in the file.
