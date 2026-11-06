---
name: template-previews-steps-2
description: Steps TP4-TP5 of plans/template-previews.md: real pictures on the Visual Page Editor home and in Choose a Template, the template's own theme first, close-out; claim as [plan:template-previews] TP<n>
---

## How are the steps taken?
- As in `template-previews-steps.md`: a draft `[plan:template-previews] TP<n> — <topic>`, the browser check against `template-previews-visuals.md` named in the PR.

## Which steps are there?

- TP4 (M) The editor home and Choose a Template. done (#7419).
  - `includes/designer_screen.php`, `assets/css/designer_home.css`, `assets/js/designer_home.js`: the large card's browser frame and every tile show the design's first openable page (`design_preview.php?page_id=`, the page the card's Edit opens); the drawing stays the fallback, the blank outline for a design with no page. The address bar of the large card stays.
  - The template start card's three minis and every card of the dialog show the template's home page (`pg_design_preview_address()`, panel); a template whose home page cannot be drawn keeps its drawing. The version badge stays on the picture.
  - Dialog: an "Önizle" button (the tour in a new tab) beside "Bu Şablonu Kullan"; "Bu temayı kullan" goes.
  - Picker (`pg_designer_template_theme_picker()`): first choices "Şablonun teması" and "Şablonun paleti", checked by default: each card shows and opens its template in the theme its file names (none named: plain Bootstrap, as today). Another look or palette redraws the cards (`PGLivePicture.set()`, 300 ms after the last change, cards in view first) and Use This Template carries it. The script that recoloured the drawings goes.
  - Memory: `designer/designer-home-screen.md` and `designer-home-colours.md` updated; tests in `tests/designer_home_test.php` (which address a card gets for a choice, when the drawing stays).

- TP5 (S) Close-out. After TP2 and TP4.
  - The lasting decisions into `designer/design-live-pictures.md`, `designer/designer-home-screen.md` and `platform/design-template-install-at-setup.md`; this plan's files and its `template-previews/` folder deleted; `php tools/memory_index.php`.
  - If the frames prove heavy on a real site (the editor home slow to settle), the stored thumbnail is opened as a new plan, not added here.
