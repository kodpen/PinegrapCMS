---
name: artifact-canvas-content-steps
description: Plan artifact-canvas-content, the build steps AC4 to AC14 (owner, poll 231): the ten kinds of ready content in order, what each step holds, what every step keeps
priority: 6
---

## What is decided?
- Owner (2026-11-26, agent channel poll 231): "Onunun hepsi, temelden başlayarak" (`workflow/decisions-made-product-114.md`). The ten: `artifact-canvas-content-census-proposals.md`; sources and licences: `artifact-canvas-content-census-gaps.md`; how each part looks: `artifact-canvas-content-screens.md`.

## What does every step keep?
- A part is a tool file of its own with its pure maths in the model and its test (`ui/image-editor-parts.md`); a hook in the core is named in the PR body.
- Ready content is a file under `assets/` asked for when its panel or row opens; nothing joins the first load, and no library comes in.
- A thing put on the board is an ordinary layer (text, svg, picture, group): it saves, translates, animates and exports as one made by hand. No new layer type but the frame of AC10.
- Every word a person reads is `lang()`; a ready design's texts are `lang()` words that become canvas texts when placed.
- Content a session makes is the product's own. Content from outside enters only with its licence named in the step's memory file, and never from Canva (`api/canva-api-terms.md`).
- No schema change. Phone and dark as the canvas does them (`ui/image-editor-phone.md`).

## Which steps? (one at a time, each after the one before; S under 400, M about 1,000 product lines; tests red first)
- AC4 (S) Done (#11854): Site colours: the action that reads the site's palettes and variables, the row in the shared colour control (`ui/image-editor-site-colors.md`).
- AC5 (S) Done (#11881): Text styles and font pairs in the Text panel (`ui/image-editor-text-styles.md`).
- AC6 (M) Element sets: about forty shapes in the Sticker tool, as svg layers.
- AC7 (M) "Start from a design": the row in the New image dialog, the tiles, the purpose filter, the index, the "Your picture" box, and the first six designs.
- AC8 (M, content) The other twenty-four designs, six a purpose where a size fits it; each opened and saved once in a test.
- AC9 (M) "Resize": the pure rule with tests, then the panel in the Canvas tool.
- AC10 (M) Photo frames: a picture cut to a shape, the photo moved inside.
- AC11 (S) "From Unsplash" in Import.
- AC12 (M) Tabler Icons beside Bootstrap Icons.
- AC13 (M) Openverse photos.
- AC14 (S) Close-out: the rules into `ui/image-editor-content.md` and its parts, the `[NEW]` line named, the census files, this file, `artifact-canvas-content-screens.md` and `artifact-canvas-content.md` deleted.

## What runs beside them?
- The design file's steps (`artifact-canvas-design-file.md`) and the picture text steps (`picture-text-fit.md`) are other plans: one step of each may run beside one step of this one. AC7 and DF4 both add a row to the New image dialog: the later one is built on the landed other.

## What did the architect settle for Unsplash? (2026-11-26, agent channel request 3151)
- The API guidelines ask that every use shows the address the API returned, that the download address is called when a photo is chosen, and that the photographer and Unsplash are credited with a link (help.unsplash.com, "Unsplash API Guidelines", read 2026-11-26).
- So an Unsplash picture layer keeps the returned address, not a `files` row: the one exception to "an inserted picture is its own files row". The layer also keeps the photographer's name and profile link, shown in its panel; the download address is called once when it is placed.
- The saved picture is a new, composed picture and is the site's file as any save. If the save, or the server's draw of a translated picture, cannot read the photo's pixels from that address, the step stops and asks the architect.
- Shown only when the site has its Unsplash key, as the rich text editor does (`ui/picker-editor-bridge.md`).

Related: docs/memory/plans/artifact-canvas-content.md, docs/memory/ui/image-editor-overview.md
