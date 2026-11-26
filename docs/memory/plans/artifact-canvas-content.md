---
name: artifact-canvas-content
description: Plan (owner 2026-11-26): the Artifact Canvas gets ready content and a way to open a design made in Canva as editable layers, from the person's own exported file; census steps AC1 and AC2 first
priority: 6
---

## What is asked?
- Owner (2026-11-26, agent channel message 14119): "görsel tasarımcı için yeni özellik araştıralım geliştirelim, şu an için biraz boş gibi geliyor".
- "daha kalilteli içerikler olmalı ki karşımızda canva gibi bir platform varken, canvadan görsel tasarımcıya düzenlenebilir içerik aktarmanın yolu olursa çok başarılı olur, bu sayede oradan bir şekilde tasarladıkları içerik burada düzenlenebilir olur ve çeviri sistemi ile de mükemmel çalışır."
- The architect's reading: "görsel tasarımcı" is the Artifact Canvas. A person should make the picture on the site, not upload another tool's export: only a canvas picture's text layers are translated (`core/picture-text-overview.md`).

## What is there today?
- An empty board in five sizes (`ui/image-editor-artboard.md`), Text with Google fonts, Draw, stickers, frames, Bootstrap Icons and emoji, animation, data layers. No ready design to start from.
- Import takes pictures, videos and an SVG, which "Break apart" turns into a layer per element (`ui/image-editor-import.md`). An SVG's words stay inside svg layers, which are not translated.
- Canva's APIs read no layer and forbid, without written consent, a use meant to export designs into another design tool (`api/canva-api-terms.md`); the owner chose an upload with no connection (`workflow/decisions-made-product-107.md`). A file the person downloads from Canva is their own export: .pptx, .pdf, .svg on a paid plan, pictures.
- The own .pptx reader gives a slide's texts and pictures, not their places, fonts or colours (`platform/office-pptx-preview.md`).

## What must it never do?
- Call a Canva API or keep a Canva connection.
- Bring in a library: the canvas is own code (`ui/image-editor-overview.md`).
- Ship a ready design, an illustration or a photo whose licence does not allow it on every site: the census names each source's licence.
- Add to the canvas's first load: ready content is asked for when its panel opens.

## Which steps?
- AC1 Done (#11833): the content census, `plans/artifact-canvas-content-census.md` and its `-tools`, `-gaps` and `-proposals` files. What a person meets on an empty board, tool by tool. What Canva, Adobe Express and two other tools give at the same point: ready designs by purpose, text styles, sets of elements, a photo library, a brand's colours and fonts, one design in other sizes.
  - For each gap: what it takes in own code, where its content comes from and under which licence, a size. It ends with ten proposals, the most use for the least work first.
- AC2 (S, no product code) after the owner's sample files: the Canva file census, into `plans/artifact-canvas-canva-census.md`. One design exported as .pptx, .pdf and .svg: what each keeps as text, picture, shape and group, with place, size, turn, opacity, font and colour; what it flattens into a picture; which fonts it names and whether the font catalogue has them.
  - What the reader must learn per format; what Canva's Content License Agreement says of a design's elements used apart; one route proposed.
- AC3 (architect) after AC1 and AC2: the owner's choices by poll (which content first, which import route); this file gains the build steps.
- AC4 on: the build steps. Expected, not decided: "Open a design file" in Import (a slide's texts as text layers, its pictures as picture layers, its shapes as svg layers), an SVG's `<text>` turned into text layers by "Break apart", ready designs in the New image dialog.
- Last: the close-out; the rules into `ui/`, the census files and this file deleted.

## What waits for the owner?
- The sample files of AC2: workers cannot sign in to Canva. Asked in the agent channel (request 3122).
- No schema change is planned.

Related: docs/memory/api/figma-canva-census.md, docs/memory/designer/canva-start.md
