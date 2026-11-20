---
name: bug-hunt-areas-click-scroll-map
description: The bug hunt of the click and scroll map (architect 2026-11-20, channel message 10776): the three faults fixed, what was read clean, what is no fault
---

## What was hunted?
- Given on 2026-11-20 (channel message 10776), after CM1 to CM4b had landed: the door `page_view.php` (its clicks part), `includes/fn/page_clicks.php`, the Visitors section `includes/visitors/clicks.php`, the switch `visitors_clicks_switch.php`, the script `assets/js/pg_clicks.src.js`, the sweep in `job.php`, `GET /reports/page-clicks`, and the designer's layer (`assets/js/style_designer_clicks.js`, sub action `page_clicks` of `includes/panel/designer.php`).
- Asked to look hardest at the cookie notice check, counter inflation and period edges.

## What did it fix?
- The PR "Bug hunt — click and scroll map", three faults, each with its test:
- The designer's "Turn on" took any answer as on: an ended session (redirect to sign-in) or a refused token (an error page with 200) showed "No page views were counted" while the switch stayed off. The read now answers `on` (`visitors/page-clicks-designer-layer.md`; `tests/designer_clicks_part_test.php`).
- The script measured the depth when it ran, deferred, before the pictures had a height: on a page short then, every view counted as read to the end. Now from the window's load (`visitors/page-clicks-script.md`; `tests/page_clicks_script_test.php`).
- A path word beyond ASCII ("hakk%C4%B1m%C4%B1zda") was taken for a code and kept as `*`, in the server's fold and both twins: now judged decoded (`visitors/page-clicks.md`; `tests/page_clicks_test.php`, whose literal examples both twins read).

## Which issues did it open?
- None.

## What was read clean?
- The cookie notice check on both sides (`platform/consent-anonymous-counts.md`): the server asks it for every beacon, the script before it sends; the notice's markup is on every page it is enabled for.
- Inflation: one beacon a page view (hide and pagehide together, none from the back-forward cache, one request with the speed beacon), elements once a view, 30 beacons a minute an address (IPv6 by its /64), the 200-element bound, the 8 KB and shape checks.
- Periods: the signature takes today and yesterday by the site's clock; record, Visitors periods and the designer's window all read the site's zone (`init.php` sets it, never a user's).
- Figures, the device filter, labels and addresses printed as text (section, API, layer), the switch's right and token, the sweep.

## What is no fault?
- A replayed day signature counts up to the rate limit (as for a pop-up's events). One beacon a page view, sent on the first hide: clicks after a visitor comes back to the tab are not counted (`visitors/page-clicks-script.md`).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/visitors/page-clicks.md
