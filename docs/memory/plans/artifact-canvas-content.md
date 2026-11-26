---
name: artifact-canvas-content
description: Plan (owner 2026-11-26): the Artifact Canvas gets ready content and opens a design made in Canva as editable layers from the exported file; content census AC1, design file steps DF1 to DF5
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
- AC2 Done by the architect on the owner's files (2026-11-26, agent channel request 3136): `plans/artifact-canvas-canva-census.md`. The route is the .pptx; the .pdf and the .svg are not read.
- AC3 Done (2026-11-26, agent channel poll 231): the owner chose all ten, the basics first (`workflow/decisions-made-product-114.md`).
- AC4 to AC14, free now, one at a time: `artifact-canvas-content-steps.md`; their screens in words: `artifact-canvas-content-screens.md`.
- The design file, free now: `plans/artifact-canvas-design-file.md`, steps DF1 to DF5 (owner, poll 230).

## What waits for the owner?
- Nothing now: the sample files came on 2026-11-26.
- No schema change is planned.

Related: docs/memory/api/figma-canva-census.md, docs/memory/designer/canva-start.md
