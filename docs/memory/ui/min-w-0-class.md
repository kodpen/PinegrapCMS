---
name: min-w-0-class
description: A flex column that must shrink takes Bootstrap 6's min-w-0; min-width-0 is defined nowhere and let long row titles widen a phone screen (PR #11382)
---

## Which class lets a flex column shrink?
- `min-w-0` (Bootstrap 6, `min-width: 0`). The panel had written `min-width-0` in about 60 places: no stylesheet defines it, so the text column kept its content width and a long name (a volunteer shift's place, a queue desk) pushed the page to 550-600 px at 390.
- PR #11382 wrote `min-w-0` in every panel file; `tests/panel_min_width_class_test.php` refuses `min-width-0` in panel PHP and JavaScript.

## Where is min-width-0 still written, and why?
- The site widgets `includes/fn/widgets_cart.php`, `widgets_express_order.php` and their canvas twins in `style_designer.src.js`: site output is Bootstrap 5.3.8, which has neither class, and a twin must print the widget's markup (`tests/cart_closed_blocks_test.php`). The test leaves those three files out.

## What else makes a row title shrink?
- `.pg-list-row-title` is nowrap with an ellipsis; on an inline element (`<strong>`, `<span>` outside a flex head) neither applies, so it needs `d-block` (the queue place's desk and screen rows, #11382).
- A pill inside such a title is cut off by the ellipsis: put it beside the title in a `pg-list-row-head pg-list-row-head-wrap`. The launch card's "Must be fixed" pill is drawn so (`pg_dash_cards_launch.js`, #11382): on a phone it takes a line of its own.

Related: docs/memory/ui/admin-screen-toolbar-rules.md, docs/memory/workflow/bug-hunt-areas-phone-dark-new-screens.md
