---
name: sketch-build
description: "Build the page" of a Prototype view sketch page (SK4): the part sketchbuild, the chip marks, the list of boxes to check, the draft tab built, the sketch kept in props._sketch
module: designer
---

## Where is it and how does it load?
- The designer part `sketchbuild` (`includes/designer_parts/sketchbuild.php`, `when: always`): `pg_sketch_reader.js`, `pg_sketch_builder.js`, then `style_designer_sketchbuild.js`. The core loads it when the Sketch switch first goes on (`_sdSketchSwitch()`) and draws again; it hands it `_sdSketchBuildHost()`. No bar button.
- Sizes (#11699): core 3,098,497 → 3,098,688 bytes (gzip 813,416 → 813,478); the part 34,532 → 38,818 bytes (gzip 12,476 → 13,875). Earlier (#11642): core `style_designer.min.js` 3,053,688 → 3,059,968 bytes (gzip 799,544 → 801,593); the part's bundle 34,532 bytes (gzip 12,476), fetched once, on the first Sketch on.

## What does it draw on a sketch page?
- Through `fill()` after the core's and "Read the picture"'s: a box with no chosen kind gets the reader's kind on its chip, with an amber question mark when the reader is unsure; the pill "n to check"; "Draft" once built; on the selected page with Sketch on "Build the page" (filled; dimmed "Draw a box first" with no box) or "Build again" (quiet; dimmed until the sketch differs from what was built: `boxSig()` of boxes and page type against `page.built.sig`).
- The chip's menu opens with the caption "It could be" (the reader's options, the best ticked) then "Something else" and the full list, for an unsure box (`reading()`).
- The line under the frame (`.sd-sketch-foot`, a toned tile): teal built, blue nothing to change, amber boxes left out ("Show them" picks the first), rose failed ("Try again") or refused. It goes when the boxes change. The first-use line with "Got it" shows on a page with boxes never built, until "Got it" or a build (`pg_sketch_build_seen`).

## What does "Build the page" do?
- Refused (content level, view mode, a component alone): the rose line, nothing else. Unsure boxes: one list beside the page (`.sd-skb-check`), a row a box with its reason, a segmented choice and a "Something else" select; Up/Down rows, Left/Right options, Enter builds, Escape or Cancel closes; a changed row sets the boxes' kind (one sketch undo step).
- `known(page)` hands the builder what the site knows (#11699, Pinegrap AI off too): `host.site()` (`sdDesign.siteWords.site_name`, else "Your site"; `_sdProto.env.path`; the sketch pages in stored order, name to "Page details" address or the name's slug), the page's title, description and name, the kinds' chip names (form: "Contact").
- Builds with `PGSketchBuilder.build()`: the header and footer are the design's (the first child or last child of a page that is a shared component, not a widget, not a sketch page) else a new shared component "Header"/"Footer" of the builder's site navbar and footer; catalog, blog, booking are new system widgets (`catalog_listing`, `form_list_view`, `booking`). A page type from "Page details" with no section of its widget adds one at the end.
- The page is a new draft tab (`addPage`: name from the address or the name, folder, title, description from "Page details"), not opened and not saved: Save keeps it. Not saved by the build because Save on a draft writes the whole design and puts the live pages' pending changes on the site.
- Any failure (a row the server does not make) builds no page; the sketch and the answers stay.

## Where is the sketch kept?
- In the draft's root: `props._sketch` `{uid, sig, secs: {sectionId: {sig, fp}}, page}`; `_validate_and_clean_tree_json()` keeps root props, so it goes into the saved draft and its History. No table or column.
- The browser's sketch page keeps `built: {uid, sig}` (`_sdSketchClean()`); the tab is found by the uid (`_sdSketchBuiltPage()`). A sketch page this browser lacks comes back from a tree's `_sketch` (`_sdSketchKept()`). Undo of the sketch keeps `built`.

Related: docs/memory/designer/sketch-build-again.md
