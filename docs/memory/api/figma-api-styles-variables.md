---
name: figma-api-styles-variables
description: Figma styles and variables over REST (read 2026-11-24): style endpoints give published metadata only, values come from the node; Variables API (file_variables:read) is Enterprise only
---

## What do the style endpoints give?
- `GET /v1/files/:key/styles` (published styles of one main file, `library_content:read`), `GET /v1/teams/:team_id/styles` (a team library, paged, `team_library_content:read`), `GET /v1/styles/:key` (`library_assets:read`). All Tier 3.
- Only metadata: `key`, `file_key`, `node_id`, `style_type`, `name`, `description`, `thumbnail_url`, dates. No colour or font values.
- A style that was never published to a library is not listed there; it is still named in GET file's top-level `styles` map.

## Where do colour and text style values come from?
- From the nodes: a node's `fills` and a text node's `style` hold the values, and its `styles` field names the style used. Reading the frames' nodes gives both the value and the style name, at no extra call.
- For a published style, the `node_id` from the style endpoints can be read with GET file nodes (Tier 1).

## Can variables be read?
- `GET /v1/files/:key/variables/local` and `/variables/published`: scope `file_variables:read`, Tier 2.
- Enterprise plan only, a Full seat in that organization, view access to the file; guests cannot.
- On Starter, Professional and Organization plans a variable reaches the REST API only as a value already applied to a node (and the node's `boundVariables` ids, which cannot be resolved without the Variables API).
