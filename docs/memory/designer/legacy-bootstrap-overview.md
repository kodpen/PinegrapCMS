---
name: legacy-bootstrap-overview
description: Overview of the Legacy to Bootstrap 5.3.8 helper (#8856, LB1-LB7): why a trial design beside the Legacy one, what it converts, how a designer runs it, what stays reversible, and the file of each part
---

## What is it?
- Owner (2026-11-11, #8671, `workflow/decisions-made-product-43.md`): "Bootstrap 5.3.8'e geçiş yardımcısı", the last of the four Legacy parts (`legacy-overview.md`).
- A Legacy design carries a classic theme: Bootstrap 3 markup, the theme's CSS and jQuery scripts. The helper turns it towards a Bootstrap 5.3.8 design that wears a look and a palette: a start a designer finishes in the editor, not a pixel copy (architect).

## Why a trial design, never the Legacy one changed?
- A design's framework is written once, when its row is made (`design-framework.md`). So "Make a trial design" makes a new row beside it, framework `bootstrap5`, "<name> (Bootstrap 5)", with converted copies of the shared parts; no page uses it until a designer moves pages.
- Nothing is removed (#6006, #6251): the Legacy design, its parts, files and the classic styles stay. A page's Legacy tree is kept on the move; "Send back to Legacy" gives it back.

## Where is each part?
- Census and hand trials: `legacy-bootstrap-census.md`, `legacy-bootstrap-trial.md` (`legacy-bootstrap-trial-why.md`), `legacy-materialize-trial.md`; pictures in `legacy-bootstrap/`.
- The map and converter (LB2): `legacy-bootstrap-map.md`; its rules beyond renames: `legacy-bootstrap-rules.md`.
- The card and report (LB3): `legacy-bootstrap-card.md`.
- The trial design and its preview (LB4): `legacy-bootstrap-trial-design.md`.
- Move pages and back (LB5): `legacy-bootstrap-move.md`.
- Theme parts as designer twins (LB6): `legacy-bootstrap-twins.md`.
- The theme as a palette and a look (LB7): `legacy-bootstrap-theme.md`.

## What is outside it?
- The two open faults of a Legacy design itself (a style moved alone without asset rows; the photos in `.background-image-holder`): `legacy-overview.md`. The trial design has its own photo backgrounds (LB6).
- The page types' custom layouts: `legacy-layouts-output-pass.md`. Bootstrap 6 on sites: a later framework key (`design-framework.md`).
- Joining the designs moved one by one to their set's design: never proposed (#8671).
