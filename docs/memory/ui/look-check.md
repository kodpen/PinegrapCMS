---
name: look-check
description: tools/check_look.php (#8905): off-recipe CSS sizes, weights, colours and old btn btn- / nav-tabs panel markup may only fall against its baseline; what it skips, --update
---

## What does it count?
- In the panel stylesheets of `pg_look_stylesheets()` (`tools/look_rules.php`): `backend.src.css`, `pg_dash.src.css`, `pg_visitors.src.css`, `ws_today.src.css`, `pg_image_editor.src.css`, `pg_tour.css`, `pg_datetime.css`.
- `size`: a `font-size` in px, rem or pt that is not on the scale (11, 12, 13, 14, 15, 17, 24 px; rem at 16 px). `weight`: a `font-weight` other than 400, 500, 550, 650 or `normal`. `colour`: each hex, `rgb()`, `rgba()`, `hsl()`, `hsla()`, `white` or `black` in a value.
- Skipped: comments, custom property declarations (they define tokens), the look's own blocks, relative sizes (em, %, keywords), anything read through `var()`, the `font` shorthand, inline `<style>` blocks of PHP screens.
- A look block is a banner comment opening with a row of `=` and naming `Look A:`; it runs to the next banner or the end of the file. A new recipe block gets such a banner.

## What does it count in panel markup?
- Per file of the panel's PHP screens and script sources (`bs6_scope_kind()` = `panel`, `.php` or `.js`): `button` = each `btn btn-`, `tabs` = each `nav-tabs`, `nav-pills` or `nav-underline` class, JavaScript selectors such as `.nav-tabs` included (`pg_look_markup_counts()` in `tools/look_rules.php`).
- Why: every newly designed panel screen uses look A, `pg-button` for buttons and `pg-seg` for tabs (`look-new-parts.md`); the count stops a new old-class line in CI; the old screens moved with the plan panel-old-screens (`look-overview.md`), what is left is `look-open-work.md`.
- Skipped: comments, site output (`site` and `shared` files, `@pg-site-output` ranges; Bootstrap 5.3.8), the visual designer (`style_designer*` and the `editor` files), `.min.js` bundles, `assets/lib`.
- Only files holding an old class are in the baseline; a file missing from it counts as 0, so a new file or a renamed one with a `btn btn-` fails. A rename moves its entry in `check_look_baseline.json` by hand.
- First counts (2026-11-17, from `origin/development`): 1,865 buttons (`workspace.js` 259), 6 tab strips.

## How does the baseline work?
- `tools/check_look_baseline.json` holds the counts per stylesheet and kind. CI (`php-checks.yml`) and `tools/pre_push.sh` run `php tools/check_look.php`: a count above its baseline fails and names the file.
- A count below its baseline is only a notice; `php tools/check_look.php --update` writes the lower counts back and drops a markup file whose counts reached 0. It never raises one: a rise is fixed in the CSS with the recipes and `--pg-*` tokens (`look-type-and-recipes.md`), in markup with `pg-button` and `pg-seg`.
- First counts (2026-11-12): 495 sizes, 337 weights, 575 colours; most in `backend.src.css`. The screen family steps (DL5) bring them down.

## What is the rule behind it?
- A panel screen is built from the recipes; a screen's own class only places things. A part no recipe draws is drawn first and asked on an `architect` issue, never written as a new colour or size in a screen (key rule in `ui/README.md`).
- Only the colour, size and weight literals are counted; spacing and radius are not (yet).

Related: docs/memory/ui/look-type-and-recipes.md
