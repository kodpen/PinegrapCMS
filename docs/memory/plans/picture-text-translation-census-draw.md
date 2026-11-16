---
name: picture-text-translation-census-draw
description: Picture text translation PT0 census, drawing: browser renderer without the editor, the server (fonts, three scripts), a longer text box, right-to-left
---

## Can a browser draw a language's picture without the editor open?
- For an artboard (canvas) document, yes in principle: `workspace_canvas_picture.js` already does it for an agent's canvas, with no dialog: `pgImageEditor.load()`, `M.canvasOpenDoc()`, `layersReady()`, `drawArtboard()`, then posts the PNG (`workspace/agent-canvas-picture.md`). It needs a signed-in member's browser showing the message; a language version would need a page or screen that loads it the same way, and the document with that language's words put in.
- For a photo document, no path today: the browser only draws the overlay, the server draws the pixels from the base row with the recipe (`platform/image-editor-save.md`); a headless run would send the overlay to a save-like action.
- It draws everything the editor draws: Google and system fonts, stickers, effects, animation frames (a saved animation is left out).
- It needs a person: until somebody with the rights opens it, a language stays not drawn.

## What can the server draw?
- Only artboard documents, through `includes/fn/image_draw.php` (`platform/image-server-draw.md`); not a photo's document (crop, adjustments, filter on a base). Its base step (`pg_image_editor_render()`, action `base`) draws a photo without layers, so a photo plus text layers would be two parts joined.
- Text only in Latin, Greek, Cyrillic and signs (`pg_image_draw_text_plain()`): no Arabic, Hebrew, Persian, Hindi, Thai, Chinese, Japanese, Korean, no combining mark, no emoji.
- Only a face it has a file of: `data/fonts/<family>-<weight>[i].ttf` or the bundled Roboto; never `sans`, `serif` or another system list; nothing fetched. A letter the file lacks (Roboto has no `→`) leaves the document to a browser.
- Not drawn: stickers, a shadow, warp, blend, any unknown field; needs Imagick (System Status, Canvas Drawing row).
- A document it cannot draw waits for a browser, as an agent's canvas does.

## What does a longer or shorter translation do to a text layer's box?
- Lines break only at a new line (`textLayout()`, `ui/image-editor-text.md`): a translation is never wrapped. `w` and `h` follow the layout (`textFit()`): the aligned side stays (left, the middle or right), the top stays.
- So a longer text grows the box away from its aligned side at the same size and may leave the artboard or cover another layer; a shorter one leaves a gap. A translation with more new lines grows downwards.
- Choices for PT1: keep the size and let the box grow (today), shrink the size to the source width, or a size and box per language (T2, owner question 6). A multi-line text has its breaks in the source; an engine may keep or drop them.

## What does a right-to-left language need?
- The editor has no direction anywhere: no `direction` on the save's canvas, no `dir` on the stage's SVG text; `align` is left, centre or right as written. A browser shapes and orders Arabic and Hebrew in `fillText()` itself, from the canvas's inherited direction.
- Letter spacing breaks it: the stage gives every character its own x (`<tspan>` each), which cuts Arabic joining; the save sets `letterSpacing` instead.
- Needed: a direction per language (the site's languages know right-to-left: `pg_tr_known_languages()` index 5), left and right alignment mirrored for it, a font with the script (the system lists differ per reader), spacing off. The server cannot draw it at all.
