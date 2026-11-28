---
name: designer-google-font-links
description: How a design's Google font is linked on the site, the canvas, Preview and code view: the regular face beside the weighted request, pg_google_font_hrefs() and its JS twin _sdGfHrefs() (#12243)
module: designer
---

## Which links does a design's Google font print?

`pg_google_font_hrefs($font)` (`includes/fn/designer.php`) gives the URLs for one entry of the design's `fonts` JSON (family, weights, enabled): first `css2?family=<Family>&display=swap` (the regular face alone), then `css2?family=<Family>:wght@<w;w>&display=swap` when weights other than 400 alone are ticked. A disabled or nameless entry gives none.

## Why two links instead of one?

Google answers 400 for a whole css2 request when the family lacks one of the weights asked (or they are not ascending). The fonts browser offers 100 to 900 for every family, so Lobster saved with 400 and 700 loaded no face at all and the text fell back (#12243). With the regular face linked on its own the family always loads and the browser draws a missing weight from it; when every weight exists the weighted link adds the real faces.

## How are the weights cleaned?

Only whole numbers 1 to 1000 are kept, duplicates dropped, sorted ascending (Google refuses an unsorted list too).

## Where is it used?

The site page (`get_page_content.php`, the enabled fonts before `</head>`), a template's preview head (`_pg_tpl_preview_assets()`), and in `style_designer.src.js` the twin `_sdGfHrefs(f)`: the canvas (`pg-gf-` links), the code view's head and Preview (`_pvFontLinks`). Change both twins together.

## What is not covered?

`injectGoogleFonts()` on the canvas (a node's font family from the catalogue, `googleFontUrl()` asking 300 to 700) still asks fixed weights in one request. The image editor's `linkFamily()` falls back to the regular face on error on its own.
