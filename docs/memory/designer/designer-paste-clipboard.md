---
name: designer-paste-clipboard
description: Designer Ctrl+V: internal node clipboard vs external markup, /*pinegrap-nodes*/ prefix, conditional keydown preventDefault, text sinks, paste target.
---

## How does one Ctrl+V handle two clipboards?
- The clipboard holds either nodes copied here or markup from outside. Decide by the clipboard CONTENT, not by the key.
- Internal copy also writes to the system clipboard: `/*pinegrap-nodes*/` + node JSON (`_sdClipCopy`). Side effect: copy-paste between two editor windows works.
- The `paste` event pastes nodes if it sees this prefix, markup otherwise.
- `preventDefault()` in keydown is CONDITIONAL (`if (_sdClipStamp) return;`). Unconditional, the `paste` event would never fire and an operator who copied a node once could not paste from outside for the whole session. If the write is refused (insecure context / no permission) the old in-memory path works as before.

## What is the text bucket?
- `p`, `pre`, `code`, `h1`-`h6` and `paragraph`/`heading` content nodes (`_sdIsTextSink`): markup pasted there goes through `esc()` and becomes TEXT. The criterion cannot be the markup itself (a code sample and a real block to put on the page are byte-identical); only the operator's target distinguishes them.
- In inline text editing a paste is also reduced to plain text (so the source site's spans and colors do not leak).

## Where does it paste?
- Into the selected node if it can hold children, beside it if not (`_sdPasteTarget`). Pasting an internal node adds a sibling (the intent there is duplication). Pasted controls pass through `_cfAdoptOnDrop()`.

Related: docs/memory/designer/html-import-and-paste.md
