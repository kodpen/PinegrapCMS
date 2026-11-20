---
name: message-table-narrow-column
description: A table in a Workspace message at phone width and in the discussion panel: the ws-table container, cells that break a long path, the frame that scrolls many columns (#10816)
---

## How does a message's table fit a narrow column?
- Files: `assets/css/backend.src.css` ("Tables and checklists written in a message"), drawn by `includes/workspace/render.php` as `.ws-table-wrap > table.ws-table`.
- The frame `.ws-table-wrap` scrolls sideways (`overflow-x: auto`, `overscroll-behavior-x: contain`) and is a named container (`container: ws-table / inline-size`).
- `@container ws-table (max-width: 30rem)`: every cell and the `code` in it take `overflow-wrap: anywhere` and a cell keeps `min-width: 6rem`. A table of two or three columns fits the frame; one of more columns scrolls inside the frame. The page never scrolls sideways.
- Above 30rem (the channel's column on a wide window) nothing changes: a long word widens the table and the frame scrolls it.
- The same rule serves a forwarded copy (`.ws-fwd-body`), a discussion's source and its messages: all draw the same frame.

## Why was it cut off at phone width? (#10816)
- A cell had `overflow-wrap: normal`, and `max-width: 24rem` does not bound a table cell. A path, an address or a `code` span in a cell made the table hundreds of pixels wider than a 360 px phone (826 px measured), so a two-column table with a long sentence showed only its first column. The frame did scroll in Chromium, but nothing showed it could.
- Bootstrap gives `code` its own `overflow-wrap: break-word`, which does not lower a cell's minimum width: `code` is named in the narrow rule too.
- With `anywhere` alone the cells shrank to 4rem in a four-column table and split ordinary words: the 6rem minimum keeps them whole.

## Why 30rem?
- Phone frames measured: 278 px at 360, 332 px at 414; the discussion panel's frame 322–383 px. The channel's column beside an open discussion at 1280 is 521 px and keeps the wide behaviour.

## Gotchas?
- `tests/workspace_narrow_column_test.php` reads the frame and the container block from the stylesheet.
- `container-type` on the frame is safe because its width comes from its parent; see `message-parts-narrow-column.md`.
