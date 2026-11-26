---
name: designer-aspect-ratio-object-fit
description: Aspect Ratio as CSS aspect-ratio on any element (DO9): the prop, presets, per size, today's ratio box kept until changed; Object Fit and Focus on a video
---

## Where does Aspect Ratio sit?
- Style › Size and display › Aspect Ratio: one text field with the presets as its list (1:1, 4:3, 3:2, 16:9, 21:9, 2:3, 4:5, 9:16, `_SD_RATIOS`), placeholder Auto. An Image's Content › Image › Aspect Ratio list writes the same prop. No bar button.
- Typed `16:9`, `16x9`, `16/9` are written `16 / 9`; one number (`1.5`) and `auto` stay; a zero side, a unit or a second value is refused under the field ("Use a ratio such as 16:9, 4/5 or 1.5.", `_sdLenCheck('ratio', …)`).

## What does it write?
- The prop `aspect`, printed as `aspect-ratio:<value>` at the end of the Sizing style: `getNodeExtraStyle()` and the page's `_pg_node_sizing_decls()`, both through `_sdRatioCss()` / `pg_designer_css_ratio()` (same cases in `tests/designer_options_controls_test.php` and `designer_image_size_test.php`). No wrapper, no class: an Image keeps its `<img>` alone.
- Per screen size through the size chooser (part `spacing`, `designer-size-per-screen.md`): `PGSizeStyle.PROPS.aspect` is `aspect-ratio`; the first value at a size moves the every-size ratio into the base rule.

## What happens to today's ratio box?
- An element with the old Sizing switch (`aspectRatioEnabled`, classes `ratio ratio-16x9`, `--bs-aspect-ratio`) or an Image with its own preset (`aspectRatio: '16x9'`, the `div.ratio` wrapper) draws as before. The field shows its ratio (`_sdAspectOf()`: `16x9` as `16 / 9`, a custom `56.25%` as `100 / 56.25`) and a line says its content fills the box until the ratio is changed.
- Any write of the ratio (field, list, a size, several elements) runs `_sdRatioFree()`: the three old props are emptied and the `ratio` classes go, in the same undo step. The children then no longer fill the box by force.
- Known edge: the Image's list writes the every-size prop; where sizes already hold rules, the inline value wins over them (use the Size and display field at a size).

## Object Fit and position on a video?
- Content › Video: "Object Fit" (the Image's five classes, `_SD_OBJECT_FITS`). "Fill its box" (background video) still reads as filling with any fit, so Contain keeps it a background (`_sdVideoFills()`, `tests/designer_video_item_test.php`).
- Position is the Focus control (owner decision, `image-focus-overview.md`): shown under Object Fit when the video has a poster (the control draws the poster); written into the style attribute as `object-position` / the `pg-focus` custom properties, like a background picture (`_sdFocusStyleWrite(style, values, 'object-position')`). No poster: no Focus. "Set focus" on the canvas stays for images and backgrounds.
- The Focus frames take the element's ratio in either form (`PGImageFocus.shapes()` reads `16x9`, `16 / 9` and one number).

Related: docs/memory/designer/designer-size-per-screen.md, docs/memory/designer/image-focus-control.md, docs/memory/designer/options-panel-census-layout.md
