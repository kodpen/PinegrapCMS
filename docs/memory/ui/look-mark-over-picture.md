---
name: look-mark-over-picture
description: A mark laid over a picture (the poll's picture count, #10037): its own class, colours as the tokens --pg-media-mark-bg / -fg, the same in both themes; a recipe when a second is built
---

## How is a mark over a picture drawn?
- As the part's own class for now: `.ws-poll-count`, the count of a poll's or an agent question's pictures (#10037). One use is no recipe.
- Its surface and its text are the tokens `--pg-media-mark-bg` and `--pg-media-mark-fg` in the token block of `backend.src.css` (`panel-css-tokens.md`), one value for both themes, not `light-dark()`: a picture does not flip with the theme, so the mark on it does not either.
- Not custom properties of the screen's class: `check_look` skips custom properties because they define tokens, and a token lives in the token block.
- Type from the scale (`--pg-text-meta`, `--pg-weight-medium`, tabular figures where the number changes) and a hairline light border, so the mark stands on a dark picture.

## When does it become a recipe?
- When a second mark over a picture is built; the gallery caption is the next one. The recipe is cut from these two tokens then.

## What was decided?
- Architect (2026-11-17, agent channel message 8559), asked by the worker of #10037 whether the mark should be a shared recipe at once.
- The look itself is the owner's: poll 135 (`workflow/decisions-made-workspace-38.md`).

Related: docs/memory/ui/look-new-parts.md, docs/memory/ui/panel-css-tokens.md
