---
name: click-scroll-map-features
description: Plan click-scroll-map, features C1 to C15: what is counted, what is never kept, consent, the beacon, which pages carry it, how it is kept and bounded
---

## What is counted?
- (C1) A click on a link, a button or a submit control, named by four parts: the part of the page (header, navigation, main, footer, aside, else page, by the nearest landmark), link or button, the address, and the label (the visible text, else `aria-label`, else a picture's alt) with white space folded, cut at 60 characters.
- (C2) Each element counts once per page view, so a share never passes 100. Nothing typed is read, no coordinates are kept, and clicks in the panel bar and the cookie notice are left out.
- (C3) The deepest tenth of the page's height that the bottom of the window reached, 0 to 10, once per page view.

## What is never kept? (the architect's view, agent channel message 5774)
- (C4) Nothing is sent from a signed-in member's view: an account bar, a cart or a greeting can carry a name.
- (C5) An address loses its query string and fragment. Of a `mailto:`, `tel:`, `sms:` or messaging link only the kind is kept. A part of the path that looks like a code (more than 16 characters, letters and digits mixed, no hyphen) becomes `*`: the key of an unsubscribe, tracking or reset link is never kept.
- (C6) A label in the form of an e-mail address, or with seven or more digits in a row, is kept empty; its element is counted by the address.
- (C7) Label and address are text from outside: the panel, the API and the designer print them as text only, and a test holds that.
- (C8) No cookie, nothing written on the device, no identifier, no address of the visitor, no row per person.
- (C9) Counting does not wait for the cookie notice. A visitor whose answer refuses statistics (`a0` in `pg_consent`), or who closed the notice without answering where it can be closed, sends nothing; one who has not answered yet is counted. The shares hold, since the views come from the same beacon.

## How does it reach the server?
- (C10) One request per page view, on `visibilitychange` hidden or `pagehide`: the page, its signature, the device class (as the speed beacon's), at most 20 elements and the tenth. With the speed measurement on, both travel in the one beacon. A page back from the back-forward cache sends nothing twice.
- (C11) The script is on an HTML page only with the switch on, the page's folder open to everyone, and none of the speed beacon's exclusions (staff, the panel bar, a preview, a background render, the designer, the prototype). The stored copy of the page cache carries it.
- (C12) The server takes a beacon only with a signature valid for the page and today or yesterday, under 30 a minute per address, not from a crawler; it applies C5 and C6 again, and a bad shape drops the whole beacon.

## How is it kept?
- (C13) Aggregated when written, one `INSERT … ON DUPLICATE KEY UPDATE` per row: per page, day and device a row counting the views, a row for the deepest tenth reached, and a row per element. The key is a sha1; the folded parts are kept beside it.
- (C14) At most 200 different elements a page and day; further ones count in one "other" row. The number of different elements is kept on the views row, never found by `COUNT(*)`.
- (C15) Kept 400 days, swept once a day by the general job. Switching off stops the counting at once; what is there runs out by itself. Derived data: a wrong day is deleted, never repaired.

The switch, Visitors, the report route and the designer's layer: `click-scroll-map-features-2.md` (C16 to C23).
