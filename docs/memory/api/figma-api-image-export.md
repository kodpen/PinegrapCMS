---
name: figma-api-image-export
description: Figma REST picture export (read 2026-11-24): GET images as png, jpg, svg or pdf, scale 0.01 to 4, 32 megapixels, 30-day links; image fills with 14-day links
---

## How is a frame rendered as a picture?
- `GET /v1/images/:key?ids=…&format=…&scale=…`: Tier 1, `file_content:read`; answers a map of node id to a download link.
- Formats `jpg`, `png`, `svg`, `pdf`; `scale` 0.01 to 4.
- At most 32 megapixels; a larger render is scaled down.
- A `null` link means the render failed, e.g. an invisible node or 0% opacity. A 500 is a render error.
- Options: `svg_outline_text` (default true: text as paths), `svg_include_id`, `svg_include_node_id`, `svg_simplify_stroke`, `contents_only`, `use_absolute_bounds`.
- Links expire after 30 days, so the server fetches the file at once into the file manager.
- One call takes many ids: the frame list's thumbnails are one call, not one per frame.

## How are pictures placed inside a design fetched?
- `GET /v1/files/:key/images`: Tier 2, `file_content:read`; a map of `imageRef` (as in a node's image fill) to a download link of the original upload.
- Links expire in at most 14 days.
- Vectors are rendered with GET images as `svg`; with `svg_outline_text=false` to keep live text.
