---
name: legacy-layouts-converter
description: The layout pass of the Bootstrap 3 to 5.3.8 converter (LL2, #8986): pg_lbs_layout_html() over a page type's output, its rules beyond the map, what stays, the starting layouts' counts
module: legacy_bootstrap
---

## What is it for?
- Architect (2026-11-12, #8969): a page type's markup (template, `data/layouts/<id>.php`, the database layouts) is mapped at output, on a page of a `bootstrap5` visual design only; no converted copies, since only the output shows the names PHP writes. LL3 calls it in `get_page_content.php` ([[legacy-layouts-output-join]]).
- `pg_lbs_layout_html($html)`: `html`, `converted`, `kept`, `by_hand`, `done`. `pg_lbs_layout_source($code)` runs it over a file's text for counts: PHP kept, `php` counts class lists PHP writes in part. Pure; tests in `tests/legacy_bootstrap_layout_test.php`.

## What does it change?
- Start tags only: the `class` value, and the `data-toggle` family's names where Bootstrap's scripts read the tag. Text, comments, other attributes and the inside of `script`, `style`, `textarea`, `pre` stay byte for byte, so a visitor's escaped text never changes; a list with nothing to convert keeps its white space.
- A tag is read as a browser reads it: a quoted value may hold `>`, bare values, the first of a repeated attribute.
- It fails open: a tag or quote that never ends returns the markup as given, `done` false. A second pass changes nothing; 5.3.8 markup passes untouched.

## Which rules differ from a Legacy design's tree?
- No theme names: `bg-primary` and the rest of `pg_lbs_theme_names()` are 5.3.8's own utilities here.
- Ambiguous names (`pg_lbs_layout_ambiguous_names()`) convert only in Bootstrap 3's shape: a partner name on the element (`label label-success`), or a `checkbox`/`radio` block whose first tag is an input of the type or a label opening with one. A bare one is kept and counted: the form views date posts with a bare `.label`, the payment options sit in a theme `.radio`.
- The data family is renamed only where a value starts a plugin 5.3.8 has (`pg_lbs_layout_triggers()`; not `data-toggle="lightbox"`) or on a `.modal` or `.carousel`.
- A class an inline script of the markup names (`pg_lbs_layout_script_names()`) is written beside its 5.3.8 names; a state name (`hidden`, `hide`, `in`, visibility) stays alone, or a `removeClass('hidden')` would leave `d-none` behind. A data attribute a script reads is written twice.
- An element hidden at one width is shown again as its tag's display (`pg_lbs_layout_display()`): `<td class="hidden-xs">` gets `d-sm-table-cell`, not a block that leaves its row.

## What changed for both paths?
- `btn-default` beside a colour (`btn-primary` …) is dropped instead of becoming `btn-light`, which comes later in 5.3.8's sheet and would win; `form-group` beside a bottom margin of its own (`mb48`, `mb0`, `my-2`) is dropped, since `mb-3` is `!important`. The starting layouts and templates write `btn-default` beside a colour 199 times.
- `pg_lbs_css()` leaves a comment inside a selector alone.

## What did the starting layouts give?
- 177 layout files and 46 templates finish: 4487 uses converted, 22 kept (that `.radio` 21, one input's own `checkbox`), 460 by hand for LL4 (`input-group-btn` 252, `dl-horizontal` 120, `pager` 53, `form-inline` 23, push/pull 12). 127 class lists are written in part by PHP.
- No site script (`frontend.src.js`, `add_to_cart`, `cross_sell`, `chat_site`, the scripts the `get_*.php` files load, the layouts' own) names a converted class or reads the data family, and no site sheet selects one; a test fails when one starts to.
- About 1.5 ms per 100 KB of output (PHP 8.3, PCRE JIT), `pg_lbs_html()` 1.2 ms.
