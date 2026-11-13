---
name: no-image-placeholder
description: assets/images/no-image.svg, the fallback for a missing or failed image on the page and the canvas (#9199): an icon without words; its address in data-pg-fallback, never in the onerror code
module: image
---

## What is the placeholder?
- `assets/images/no-image.svg`: the picture-frame icon the designer image renderer, the product carousel and the `onerror` fallback show when an image is missing or fails to load.

## Why does it carry no text?
- A static file cannot follow the page's language: it drew "No image available" in English on Turkish sites (issue #4409; an earlier version drew it in Turkish on English sites). Since PR #4472 it is the icon only, centred, `aria-hidden="true"`; the `<img>` element's `alt` names the item.
- Do not add a caption back to the file; a visible text belongs in the page markup through `lang()` / `_sdT()`.
- `tests/site_language_texts_test.php` fails on a `<text>` element or an `aria-label` in the file.

## What does the canvas draw for a picture that fails to load?
- The same no-image picture (#9199, CF2): the image renderer arms `_sdImgFallback(img, _sdNoImageUrl())` before it sets `src`; one error swaps in `<path><software_directory>/assets/images/no-image.svg` and marks the picture `data-sd-img-failed`; a failing fallback is not retried. `tests/designer_canvas_image_fallback_test.php`.
- Before, the browser's broken glyph with the alt text drew it as an empty strip one line high (696x24 in the Hello, Pinegrap hero when picsum.photos did not answer; the owner's picture 2 of #9104). The page already showed the no-image picture there.
- The picture keeps its own box (width/height, ratio box); with none it takes the file's square shape, as on the page. An empty `src` still draws the editor's "Double-click to select image" box.

## How does a failed designer image reach the placeholder?
- The image case of `_render_content_html()` writes `data-pg-fallback="<placeholder>"` and `onerror="<pg_img_fallback_handler()>"` (`includes/fn/image.php`): the code reads the attribute, removes it (so a failing placeholder is not retried) and sets `src`; for a responsive or bound image it first removes `<source>`s and `srcset`.
- The code holds no address and never writes `this.onerror=`: Bingbot requested the old value `this.onerror=null;this.src='/…/no-image.svg'` as a relative URL and the firewall's `xss-handler` rule (`\bonerror\s*=`) blocked and scored it (issue #6676). `tests/designer_image_fallback_test.php` runs every `waf_rules()` pattern over the code.
- The name has no `src` in it on purpose: the e-mail address rewriters (`mail.php`, `forms.php`, `order_completion.php`, `add_email_campaign.php`) and `get_page.php` match `<img\s+[^>]*src\s*=` greedily, so they would rewrite a later `…-src=` instead of the real `src`; for the same reason the address is not versioned by `pg_version_assets()`. Pages published before the change keep the old handler until Rebuild Page HTML.
