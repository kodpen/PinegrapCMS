---
name: office-sheet-format-borders
description: The border of a format entry (office_format.php): none, all sides, outline or bottom of the range turned into each cell's sides, how a side is written
module: office_format
---

## How is a border given to each cell?
- `pg_office_format_border_sides()` turns the range's border into each cell's sides: `all` a thin line on every side of every cell, `none` every side off, `outline` the range's outer edges, `bottom` the bottom edge of its last row; a side left out keeps what the cell has.
- A side is written thin, `<color indexed="64"/>` (empty for none), in its place in a copy of the cell's border. A side drawn already keeps its line and colour; diagonals stay. No borders list: `format_styles`.

## Where is it checked?
- `tests/office_format_test.php`: each border's sides per cell, the borders written beside the old one, an outline saved and read back as each corner's `bd` look (`office-sheet-format-controls.md`).
