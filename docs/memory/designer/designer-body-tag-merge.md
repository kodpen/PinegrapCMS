---
name: designer-body-tag-merge
description: Designer <body> tag: class/style/id from Body Settings and the Attributes panel are merged, not dropped; three lockstep code paths; page_tree_code is a snapshot.
---

## How is the `<body>` tag assembled?
Two places describe `<body>` (custom controls in Body Settings, and the Attributes panel below) and both can write `class` / `style` / `id`. Rule: **merge.**
| Part | Sources (in order) |
|---|---|
| `class` | style-level "Ek gövde sınıfları" -> root `cssClass` -> `_attrs` `class` |
| `style` | `fontFamily` -> `inlineStyle` -> `_attrs` `style` |
| `id` | root `id`, if empty `_attrs` `id` |

- `id` is the exception: one tag cannot hold two ids, one must be authoritative. For the rest there is no reason to drop. **A field that does not publish what it stores is worse than a field that is rejected.**

## Which three places stay in lockstep?
- `generate_style_code_from_tree()` (PHP, writes the frontend), `_sdBodyAttrParts()` -> `generateHTML()`, and the same helper -> canvas `renderCanvas()`.
- Previously PHP dropped all three, JS knew only `style`, the canvas applied only `cssClass` + font: one tree produced three different bodies.

## Do existing pages pick up a body-tag fix?
- **`page_tree_code` is a snapshot.** Any fix that changes the body tag appears only in new saves; existing pages are not published with it until re-saved once.
