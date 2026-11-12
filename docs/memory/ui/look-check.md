---
name: look-check
description: tools/check_look.php (#8905): counts panel CSS outside look A's recipes (font size or weight off the scale, colour literals) against a baseline that may only fall; what it reads and skips, --update, the key rule
---

## What does it count?
- In the panel stylesheets of `pg_look_stylesheets()` (`tools/look_rules.php`): `backend.src.css`, `pg_dash.src.css`, `pg_visitors.src.css`, `ws_today.src.css`, `pg_image_editor.src.css`, `pg_tour.css`, `pg_datetime.css`.
- `size`: a `font-size` in px, rem or pt that is not on the scale (11, 12, 13, 14, 15, 17, 24 px; rem at 16 px). `weight`: a `font-weight` other than 400, 500, 550, 650 or `normal`. `colour`: each hex, `rgb()`, `rgba()`, `hsl()`, `hsla()`, `white` or `black` in a value.
- Skipped: comments, custom property declarations (they define tokens), the look's own blocks, relative sizes (em, %, keywords), anything read through `var()`, the `font` shorthand, inline `<style>` blocks of PHP screens.
- A look block is a banner comment opening with a row of `=` and naming `Look A:`; it runs to the next banner or the end of the file. A new recipe block gets such a banner.

## How does the baseline work?
- `tools/check_look_baseline.json` holds the counts per stylesheet and kind. CI (`php-checks.yml`) and `tools/pre_push.sh` run `php tools/check_look.php`: a count above its baseline fails and names the file.
- A count below its baseline is only a notice; `php tools/check_look.php --update` writes the lower counts back. It never raises one: a rise is fixed in the CSS with the recipes and `--pg-*` tokens (`look-type-and-recipes.md`).
- First counts (2026-11-12): 495 sizes, 337 weights, 575 colours; most in `backend.src.css`. The screen family steps (DL5) bring them down.

## What is the rule behind it?
- A panel screen is built from the recipes; a screen's own class only places things. A part no recipe draws is drawn first and asked on an `architect` issue, never written as a new colour or size in a screen (key rule in `ui/README.md`).
- Only the colour, size and weight literals are counted; spacing and radius are not (yet).

Related: docs/memory/ui/look-type-and-recipes.md
