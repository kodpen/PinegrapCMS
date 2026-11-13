---
name: legacy-layouts-output-map
description: How a bootstrap5 design's page types get 5.3.8 names (architect, #8969): the system region's output mapped per request, no converted copies; the rules every change keeps
---

## What was decided after LL1? (architect, 2026-11-12, #8969)
- Map at output: on a page whose style is a visual design with framework `bootstrap5`, the system region's output passes through the layout variant of the class map. No converted copies of layouts or templates are written.
- Why: it alone reaches every source the census found (templates, the pages' layout files, the layouts kept in the database, the 2189 tags whose names PHP builds: [[legacy-layouts-census]]). Copies miss the PHP-built names and need a column for the database layouts.
- A classic style and a Legacy design keep their output byte for byte, and "Send back to Legacy" needs no clean-up. Cost about 0.8 ms per 100 KB ([[legacy-layouts-map-cost]]).
- Converted copies come back only as a new question, if the names shown in the layout code tab come to matter.

## Where does the pass run?
- One place: `$system_output` in `get_page_content.php` ([[legacy-layouts-output-join]]), only when the page's style is a visual design whose framework is `bootstrap5`. The key alone is not enough: a classic style reads as `bootstrap5` too.
- No other path: e-mails, PDF, feeds and API answers are not mapped.

## What does the pass change, and what never?
- One map: the layout variant is the map of `includes/fn/legacy_bootstrap.php` with the theme names rule switched off; no second list of names.
- Tags only: `class` attributes and the `data-toggle` family. Never the text between tags, never the inside of `script`, `style`, `textarea` or `pre`, never another attribute's value: a visitor's escaped text does not change.
- Ambiguous names (`label`, `checkbox`, `radio`, `panel`, `well`, `media`, `thumbnail`, `caret`) are renamed only where the Bootstrap 3 shape is there: a partner name on the same element (`label-*`, `panel-*`) or the known block shape of `checkbox` and `radio`.
- A bare ambiguous name is kept and counted; the compatibility sheet draws a bare `checkbox` (below). The tests on the starting sites' layouts fix the list.
- A second pass changes nothing, and 5.3.8 markup (a system widget, a layout written new) passes untouched.

## What about the names a script uses?
- LL2 starts with a count of the selectors and run-time class changes that name a mapped class (`.form-group`, `.help-block`, `.has-error`, `.input-group-btn`, `hide`, `in`), in the site-side scripts and in the layouts' own inline scripts.
- For such a name the map prints the 5.3.8 name and keeps the old one beside it, unless 5.3.8 gives the old name another meaning; then the script learns both names in LL3. No page type's script may stop finding its elements.

## What happens when the pass cannot finish?
- It fails open: a `preg_*` call that returns `null` or a broken tag leaves the region as it came. Never an empty region.

## What does the designer see?
- The layout code tab shows the file as written, with one line that a `bootstrap5` design prints its Bootstrap 3 names as 5.3.8 names ([[legacy-layouts-output-pass]]).
- The "Towards Bootstrap 5" report names a system widget as the better move for each page whose type has one ([[legacy-bootstrap-card]], #9259).
- What the compatibility sheet still draws and what no rename reaches: [[legacy-layouts-left]].
