---
name: details-panel-rule
description: The one structure rule of a list's details panel ("Detaylar"): fixed section order, label/value lines with a fixed label column, empty values left out; how the table driver applies it
module: list_table
---

## Why is there a rule?
- Owner (2026-11-04): the details panel of the new lists looked scattered. The File Manager's panel was only ever a stopgap that other screens copied. Every details panel now follows this one rule.

## What is the rule?
- Sections, always in this order:
  1. a header strip: the word "Detaylar" (`lang('Details')`) and the close button;
  2. the record's name as the heading (the list's first column);
  3. its pictures (image columns), without a label;
  4. one line per other column, in the user's column order: a fixed label column (`--pg-list-table-label`, 8rem, small muted text) and the value beside it, a hairline between lines;
  5. the row's buttons (the actions column) in one row, without a label.
- A column whose cell shows nothing (no text, image, icon, field or link) is left out; it is never shown as an empty line or a dash.
- Spacing is the same everywhere: 0.75rem between sections, 0.5rem above and below each line; values wrap, never overflow.
- A phone card (below 768 px) uses the same label width.

## Where is it built?
- `detailsSections()` in `assets/js/pg_list_table.js` (Node test `test_list_table_js_details_sections`) sorts the columns into the sections; `showDetails()` draws them (`.pg-list-table-details-title`, `-pictures`, `dl`, `-actions`); the look is the `.pg-list-table-details` block of `backend.src.css`. A list needs nothing of its own to follow it.

Related: docs/memory/ui/list-table-layout.md
