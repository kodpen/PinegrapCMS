---
name: artifact-canvas-content-census-proposals
description: Plan artifact-canvas-content, step AC1: ten proposals for the Artifact Canvas's ready content, most use for least work first, with size, source and licence
---

## Which ten, in which order?
- Gaps, sources and licences behind each line: `artifact-canvas-content-census-gaps.md`. Sizes: S well under 1,000 product lines, M about 1,000, L more.
1. Site colours (S): a "Site colours" swatch row first in every colour field, read from the site's designs' palettes and variables. Own code, no content, no licence. Every design gets the brand without typing a code.
2. Text styles (S): Add a heading, Add a subheading, Add body text at the top of the Text panel. Own; OFL faces already shipped.
3. Unsplash in Import (S): "From Unsplash" in Import's menu with the dialog the rich text editor already has, shown when the site set its key. Unsplash License; the guidelines' hotlink, download call and credit; the open question on keeping a chosen photo as a file goes to the architect first.
4. Font pairs (S): about twelve heading-and-line pairs as groups of two text layers under the text styles. Own; OFL.
5. Ready designs, first set (M): "Start from a design" in the New image dialog, 30 own designs across the five sizes and eight purposes (post, story, banner, announcement, sale, event, quote, invitation), photos as "Your picture" boxes. Own content, about 150 KB read when the row opens.
6. Resize to other sizes (M): "Resize" in the Canvas tool, each picked size a new scene or a new canvas, layers placed and scaled by a pure, tested rule. No content. Paid in Canva, Adobe Express and VistaCreate.
7. Element sets (S to M): lines, dividers, badges, ribbons, blobs, speech shapes and ornaments as svg layers, about 40 shapes, in the Sticker tool. Own.
8. Photo frames (M): a picture layer cut to a shape (circle, rounded, arch, blob, the element set's shapes), the photo moved inside its frame. Own code; the crop's shapes are a start (`ui/image-editor-crop.md`).
9. A second icon set (S to M): Tabler Icons (MIT, outline, one stroke width) beside Bootstrap Icons, as svg layers taking a colour and a width, loaded when opened.
10. Openverse photos (M): a keyless photo search for sites without an Unsplash key, CC0 and public domain only, the record's attribution kept with the layer.

## Why this order?
- 1 to 4 reuse what exists (palette data, the Unsplash dialog, the font list) and change the first minute most for the least code.
- 5 is the owner's "boş gibi": the biggest single change, but its content is made by hand and is best made once 2, 4 and 7 exist to build it from.
- 6 is worth most to a person making a campaign in many sizes, and stands alone.
- 8 to 10 widen choice once the basics are there.

## What is not proposed?
- AI-generated designs or pictures, video and music libraries, illustration sets whose licence forbids redistribution in a product, and anything taken from Canva (`api/canva-api-terms.md`).
- Opening a design made in Canva: its own steps (`artifact-canvas-design-file.md` in #11831).

## What does AC3 ask?
- The owner picks by poll which of the ten come first; the architect turns them into build steps in `artifact-canvas-content.md`.
