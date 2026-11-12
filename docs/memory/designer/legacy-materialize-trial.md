---
name: legacy-materialize-trial
description: The LB1b hand trial: a Legacy design whose theme loads Materialize, Bootstrap 5.3.8 beside it as a trial design loads it; names both own, what each sheet does to the other
---

## What was tried?
- A sandbox fixture only, nothing added to the product (`plans/legacy-bootstrap-helper-rules.md`): two classic styles on english_default with one head (Materialize 1.0.0 from npm, `livesite.src.css`, a small theme file as the styles' own Theme file) and a Materialize landing page in the body. They formed a set and moved by the normal run into "material-about set" (framework `legacy`, 2 pages, 6 parts, asset rows `materialize.min.css`, `materialize.min.js`, the `M.AutoInit()` line).
- The page "material-home" in Playwright, 1280×800 and 390×844, deviceScaleFactor 1. Trial column: 5.3.8's sheet printed before the design's rows (where a `bootstrap5` design prints its framework), its bundle at the end, the body through `pg_lbs_html()` (LB2). Nothing saved.
- Pictures, Legacy left: `plans/legacy-bootstrap/lb1b-materialize-wide.png` (half size), `lb1b-materialize-phone.png`. The Material Icons font is offline in the sandbox: both columns show the icon names as text.

## Which names do both frameworks own?
- Materialize 1.0.0 and 5.3.8 both define 22 names: `active`, `badge`, `breadcrumb`, `btn`, `card`, `card-title`, `carousel`, `carousel-item`, `clearfix`, `col`, `container`, `disabled`, `modal`, `modal-content`, `modal-footer`, `pagination`, `progress`, `rounded`, `row`, `small`, `text-primary`, `toast`.
- The fixture's tree: 58 names, 310 uses over two pages and six parts; 8 names in both sheets (`col` 28, `row` 16, `container` 14, `card`, `card-title`, `active`, `btn`, `badge`), 47 Materialize's alone (`s12`, `m6`, `center`, colour names, `waves-*`, `input-field`, `collapsible`, `chip`), 3 the theme's, none 5.3.8's alone.
- Bootstrap 3 defines 6 of them too (`row`, `container`, `btn`, `badge`, `active`, `right`): a census by class names alone takes a Materialize design for a Bootstrap 3 one. Only the classic framework sheet's asset row (`bootstrap.css`, `bootstrap.min.css`) tells them apart.

## What does each sheet do to the other?
- 5.3.8 loads first, so Materialize wins each rule both write with the same selector (`.btn`, `.card`, `.container` width and margins). What 5.3.8 writes alone stays: `.row` is flex with `> * { width: 100% }`, `.col` is `flex: 1 0 0%`.
- Wide: the hero's two buttons in a `.row` each take the full width and stack; the rest moves a few pixels (page 1856 → 1861 px).
- Phone: the cards' `col s12 m6 l4` sit two to a row, squeezed, as 5.3.8's `.col` shares the row; the page is 3254 → 2656 px.
- Reboot changes little that is visible: Materialize sets its own base.

## What did the converter do?
- `pg_lbs_tree()` on the design: 2 changes, both `data-target` → `data-bs-target` on the phone menu's trigger. Materialize's sidenav reads `data-target`: in the trial the menu no longer opens; with the name kept it opens (checked by a click).
- No class was converted: Materialize has no Bootstrap 3 name the map renames (`hide` would become `d-none`, the same meaning).

## What does it mean for the helper?
- On a Materialize design the helper changes nothing useful and breaks two things: 5.3.8's grid rules under Materialize's markup, and the sidenav's data attribute. The rule until the owner answers holds: run only where the classic framework sheet is Bootstrap 3, found by the asset row, not by class names.
- Asked of the owner with the pictures: what the helper does on such a design (`plans/legacy-bootstrap-helper-rules.md`).
