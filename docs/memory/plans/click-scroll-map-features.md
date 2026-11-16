---
name: click-scroll-map-features
description: Plan click-scroll-map, features C1 to C17: what is counted, the beacon, how it is kept and bounded, privacy and the switch, the Visitors section, the report route, the designer's layer
---

## What is counted?
- (C1) A click on a link, a button or a submit control, named by four parts: the part of the page (header, navigation, main, footer, aside, else page, by the nearest landmark), link or button, the address without its query string and fragment, and the label (the visible text, else `aria-label`, else a picture's alt) with white space folded, cut at 60 characters.
- (C2) Each element counts once per page view, so a share never passes 100. Nothing typed is read, no coordinates are kept, and clicks in the panel bar and the cookie notice are left out.
- (C3) The deepest tenth of the page's height that the bottom of the window reached, 0 to 10, once per page view.

## How does it reach the server?
- (C4) One beacon per page view on `visibilitychange` hidden or `pagehide`: the page, its signature, the device class (as the speed beacon's), at most 20 elements and the tenth. A page back from the back-forward cache sends nothing twice.
- (C5) The script is on an HTML page only with the switch on, the page's folder open to everyone, and none of the speed beacon's exclusions (staff, the panel bar, a preview, a background render, the designer, the prototype). The stored copy of the page cache carries it.
- (C6) The server takes a beacon only with a signature valid for the page and today or yesterday, under 30 a minute per address, not from a crawler; a bad shape drops the whole beacon.

## How is it kept?
- (C7) Aggregated when written, one `INSERT … ON DUPLICATE KEY UPDATE` per row: per page, day and device a row counting the views, a row for the deepest tenth reached, and a row per element. The key is a sha1; the folded parts are kept beside it.
- (C8) At most 200 different elements a page and day; further ones count in one "other" row. The number of different elements is kept on the views row, never found by `COUNT(*)`.
- (C9) Kept 400 days, swept once a day by the general job. Derived data: a wrong day is deleted, never repaired.
- (C10) No cookie, no identifier, no address of the visitor, nothing per person. No consent is asked, and the Features card says what is kept.
- (C11) The switch "Clicks and scrolling" in Site Settings, Features, a settings text, off on a running site.

## What does Visitors show?
- (C12) A section "Clicks and scrolling": for the period, the pages with counted views, the tenth half of their views reach, and their most clicked element with its share; a page opens its own view.
- (C13) A page's view: its elements (part, label, address, clicks, share of views), the share of views reaching each tenth, a device filter, "other" as one line.
- (C14) `GET /reports/page-clicks` gives the same figures for a period, a page and a device.

## What does the designer show?
- (C15) A "Clicks" toggle: badges on the canvas' links and buttons with their share over the last 30 days, found by the same four parts folded the same way as the script (a twin test); lines at the tenths with the share reaching each; a device choice.
- (C16) Elements counted on the site that the canvas does not hold are listed beside the canvas, not dropped.
- (C17) Only for whoever may open Visitors. An experiment's variants are not told apart.
