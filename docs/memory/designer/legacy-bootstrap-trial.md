---
name: legacy-bootstrap-trial
description: The LB1 hand trial (#8940): a Legacy page with Bootstrap 5.3.8 in place of the classic sheet, how it was made, what broke, how near it came; pictures beside the plan
---

## How was the trial made?
- Page "home-1" of the english_default "home-1 set" Legacy design, sandbox, Playwright at 1280×800 and 390×844 (phone), deviceScaleFactor 1, full page. Nothing was saved: the browser swapped the files and the markup per request.
- Trial column: `bootstrap.css` answered by 5.3.8's `bootstrap.min.css`, `bootstrap.min.js` by its bundle, Bootstrap Icons added; the page's classes renamed by the plan's map (27 renames on the page: grid, offsets, push/pull to `order-*`, visibility to `d-*`, floats, text sides, `btn-default`, `img-responsive`, `form-group`, `sr-only`, `in`), `data-toggle` to `data-bs-toggle`.
- The sandbox reaches no CDN, so both columns got jQuery 3.7.0 and jQuery UI from `assets/lib/Jquery/` and flexslider 2.7.2 (npm) for 2.3.0.
- Pictures, Legacy left and trial right: `plans/legacy-bootstrap/lb1-home-1-wide.png` (half size) and `lb1-home-1-phone.png`.

## What broke first?
- `scripts.js` line `$('[data-toggle="tooltip"]').tooltip();` throws under 5.3.8 ("tooltip is not a function"): the jQuery bridge is not there when the theme's ready handler runs, and every theme script after it stops: sliders show all slides stacked, the icon tabs show every pane.
- With that one line changed by hand to `bootstrap.Tooltip.getOrCreateInstance()` on `[data-bs-toggle="tooltip"]`, every theme script ran and no error was left. The pictures show this state.

## How near did it come?
- Header, menu, type, buttons, the feature cards, the slider, tabs, video strip and footer look as on Legacy. Page height: wide 6558 → 6674 px, phone 8090 → 8170 px.
- `bg-secondary` sections turn dark grey with faint text: 5.3.8's `!important` utility beats the theme's light grey (census: 92 uses).
- The image-edge part overlaps its text: its image column sits outside a `.row` and was moved by `col-md-push-6`; `order-md-last` does nothing there and the columns no longer float.
- Phone: the menu button is gone. It carries `visible-sm visible-xs`; renamed one by one they give `d-none … d-block d-sm-none` and `d-none` wins.
- Background photos are missing in both columns: the open `.background-image-holder` fault (`legacy-overview.md`), not the framework.

## What does it mean for the plan?
- The map alone is not enough for the page to run: the theme's `scripts.js` needs the tooltip line changed or a jQuery tooltip shim, or nothing animated starts.
- Three map rules beyond one-to-one renames: merge the `visible-*`/`hidden-*` set of one element; push/pull outside a row kept by hand (counted, "by hand"); theme names that 5.3.8 owns as utilities (`legacy-bootstrap-census.md`).
- Not tried: the classic custom layouts' forms (cart, checkout, sign-in) under 5.3.8, the turkish theme, a page per theme part.
