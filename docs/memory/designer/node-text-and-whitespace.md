---
name: node-text-and-whitespace
description: Designer node props.text: innerHTML (content nodes) vs plain text (semantic nodes), and whitespace-sensitive tags pre/textarea/code in generateHTML().
---

## How is `props.text` written for each node kind?
| Node | How `props.text` is printed |
|---|---|
| `content` (paragraph/heading) | innerHTML — the writing side does `esc()` |
| `semantic` | text node on the canvas, `esc()` in `generateHTML()` — written raw |
- Mixing them is silent in both directions: one applies tags, the other prints `&lt;div&gt;` to the visitor.
- `esc()` writes `& < >` and the double quote as entities, as `h()` does on the server (`_render_tree_node()`), so one helper serves text and double-quoted attribute values alike; a `"` in an id, a link or a custom attribute no longer ends the attribute. The apostrophe stays raw.

## Why is whitespace inside `<pre>` / `<textarea>` / `<code>` content?
- `generateHTML()` (the one compiler, through `_sdEmitNode()`) puts indentation + a newline inside every tag. In these three tags that whitespace is what the visitor sees, so they are written on a single line with nothing added inside.
- A new whitespace-sensitive tag must be added to `_semWsTags`.
