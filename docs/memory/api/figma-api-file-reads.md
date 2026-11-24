---
name: figma-api-file-reads
description: Figma REST file reads (read 2026-11-24): GET file and nodes, depth and ids, frame auto layout (with GRID), text style, component instances, the styles map
---

## Which calls read a file?
- `GET /v1/files/:key`: the whole file as JSON: `name`, `lastModified`, `version`, `document` (node tree), and top-level maps of the components, component sets and styles used. Tier 1, `file_content:read`.
- `GET /v1/files/:key/nodes?ids=…`: only the listed nodes with their children and ancestor chain; a missing id comes back `null`. Tier 1, same scope.
- `GET /v1/files/:key/meta`: creator, last change, link; Tier 3, `file_metadata:read`.
- The key is the part after `/design/` (or `/file/`) in a file's link; a frame's `node-id` in the link is written with `-` where the API writes `:`.

## Which parameters matter for an import?
- `depth=1` returns only the pages, `depth=2` pages and their top frames: enough for a frame list without the whole tree.
- `ids` (comma list) narrows to the ticked frames.
- `geometry=paths` adds vector paths (left out by default).
- `version` reads a stored version; `branch_data=true` adds branch facts; `plugin_data` adds plugin data.

## What does a frame give?
- `layoutMode`: `NONE`, `HORIZONTAL`, `VERTICAL` or `GRID`.
- Flex-like: `itemSpacing`, `paddingLeft/Right/Top/Bottom`, `primaryAxisAlignItems`, `counterAxisAlignItems`, `layoutWrap`, `layoutSizingHorizontal/Vertical` (fixed, hug, fill), `primaryAxisSizingMode`, `counterAxisSizingMode`.
- Grid: `gridRowCount`, `gridColumnCount`, `gridRowGap`, `gridColumnGap`, `gridColumnsSizing`, `gridRowsSizing`, `gridAutoTracks`; children carry `gridRowSpan`, `gridColumnSpan`, `gridChildHorizontalAlign`.
- So auto layout maps to flex (horizontal/vertical) and grid nodes without guessing from positions; a frame with `NONE` has only absolute boxes.

## What does text and a component give?
- TEXT: `characters`, `style` (a TypeStyle: family, weight, size, line height and the like), per-character overrides in `characterStyleOverrides` and `styleOverrideTable`.
- INSTANCE: `componentId` (the source component), `componentProperties`, `overrides`; the same `componentId` on several frames is what makes one shared component.
- Any node: `styles` maps a style kind to a style id, looked up in the file's top-level `styles` map (name and kind).

## Which errors come back?
- 400 bad parameter, 403 bad or expired token, 404 no such file (or no access), 429 rate limit (`figma-api-rate-limits.md`).
