---
name: designer-home-colours
description: Visual Page Editor home colours (#3267): neutral greys plus the panel's primary token, the previews keep theirs, token-class drawings, status as words and a shaped mark, tooltips
---

## Which colours may the screen use?
- Neutral greys plus one accent, the panel's primary token (`--sdh-accent` = `--pg-primary`, `--sdh-accent-ink` = `--pg-primary-fg-emphasis` for text and icons, `--sdh-accent-soft` = `--pg-primary-bg-subtle`), both themes from the tokens; no hex of its own. The owner asked for it on 2026-10-29 (the screen had become a riot of colours: pink drawings, pink flag, green/yellow/blue badges) and asked to keep the design previews and make the structure clearer (#3267).
- Colour belongs to the previews: the large card's browser-frame preview, the tiles' pictures and the real template thumbnails stay as they are.
- The start drawings take every colour from a class (`p g l k s sk dash cursor`, accent only `a`/`as`, at most one accented part per drawing); `tests/designer_home_test.php` refuses any colour value in their markup.
- Status is words plus a mark of its own shape (`.sdh-state`: full, half, empty, dotted circle; clock for a schedule), never a coloured badge; only "Live" wears the accent. One solid button per card (Edit); the rest are `btn-text`.

## Tooltips?
- Only icon-only controls get one, compact (`no-popover` + `data-bs-toggle="tooltip"`); see docs/memory/ui/panel-hint-tooltip-or-popover.md. The template gallery's labels use the same; its details card is a trimmed popover (`sd-tpl-info`).

Related: docs/memory/designer/designer-home-screen.md
