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

## Why is whitespace inside `<pre>` / `<textarea>` / `<code>` content?
- `generateHTML()` (the real compiler; `buildNodeHTML` is a second path) puts indentation + a newline inside every tag. In these three tags that whitespace is what the visitor sees, so they are written on a single line with nothing added inside.
- A new whitespace-sensitive tag must be added to `_semWsTags`.
