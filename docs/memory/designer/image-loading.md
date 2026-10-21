---
name: image-loading
description: How designer images load: page body order (first image first, the rest lazy), a node's own loading attribute, the Image element's Loading row and why it writes the attribute
---

## What does a page body do by itself?
- `pg_img_loading_attrs()` (`includes/fn/image.php`): while `generate_style_code_from_tree()` writes a page body (`pg_img_page_state()`), the first image gets `fetchpriority="high"`, every later one `loading="lazy" decoding="async"`. Shared components and widgets drawn at request time get nothing automatic.

## How does a node choose for itself?
- With a `loading` attribute (`_attrs`): the renderer then adds nothing automatic, and an image that chose for itself still takes the "first image" place (no `fetchpriority` moves to the next one). No attribute is written twice.
- `props.loading` (lazy/eager) is printed only when the node has no such attribute: the attribute outranks it (`tests/image_responsive_test.php`).

## What does the Loading row do?
- "Loading attribute" in the Image element's Image section and in the multi-select Options: Default / Lazy / Eager (`_sdImgLoading()`, `_sdSetImgLoading()` in `style_designer.js`, PR #693). Default removes the attribute; Lazy and Eager write it and drop `props.loading`, so the Attributes panel, the code view and the page show one value. Its tooltip explains the three; the label turns blue when set and a click on it resets.
- A value typed in the Attributes panel that is neither (`auto`) is listed as it is, so the row shows what the page gets and can replace it.

## Why the attribute and not the prop?
- Start trees (`includes/design_templates/*`) and imported markup keep `loading="lazy"` as an attribute. The multi-select row used to write `props.loading`, so on those images choosing Eager changed nothing on the page (symptom: "Loading attribute is Eager but the image is still lazy"), and the row showed Default for an image that was lazy.
- A tree saved with the prop alone is read through it; the first change in the row moves it to the attribute. No migration step: the renderer still prints the prop.
