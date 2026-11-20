---
name: page-clicks-script
description: The clicks and scroll depth script (pg_clicks.src.js, CM2 #9946): who gets it, how it names and folds an element, the depth, the one request it shares with the speed beacon, the consent check
module: page_clicks
---

## Which pages carry it?
- `get_page.php` calls `pg_clicks_beacon_inject()` right after `pg_vitals_beacon_inject()`, before the page cache, so the stored copy counts too. The tag is `pg_page_clicks_beacon_tag()`: `pg_clicks.min.js` deferred, `data-u` (page_view.php), `data-p`, `data-s` (`pg_page_clicks_sign()`, today).
- Only when `pg_page_clicks_beacon_wanted()` (C11): the switch on (`pg_page_clicks_switch_on()`, `page-clicks-switch.md`), an HTML page, `$access_control_type` public, not signed in (`USER_LOGGED_IN`, C4), no panel bar, not the File Manager preview, not a background render, not staff. Also `pg_page_clicks_ready()`, `ENCRYPTION_KEY` and a `</body>`.

## How does it name a click?
- A click (capture, on the document) inside a link (`a[href]`, `role=link`) or a button (`button`, `role=button`, input submit/button/image): area by the nearest landmark (header, nav, main, footer, aside or their roles, else page), `link` or `button`, the address (links only, `href` resolved), the label (visible text, else `aria-label`, else an image's alt; a submit input's own value, never a typed field).
- `foldUrl()` and `foldLabel()` are the twins of `pg_page_clicks_fold_url()` and `pg_page_clicks_fold_label()`, held to the server test's literal examples (`tests/page_clicks_script_test.php`). An element with neither address nor label left is dropped. Inside `#software_toolbar` or `#pg-cc` nothing is named.
- Each element once a page view, at most 20. The deepest tenth (`tenth()`) is measured at load, on scroll (200 ms) and when sent.

## How is it sent?
- Once, on `visibilitychange` hidden or `pagehide`; a page back from the back-forward cache sends nothing more. Fields `cp`, `cs`, `c` = JSON `{d, t, e}` (`visitors/page-clicks.md` has the server side).
- `take()` drops the last elements named while `c` is over 8 KB in UTF-8 bytes (`BYTES`, the server's limit), so the view and depth still count (#10604): twenty long addresses with four-byte labels reached about 10 KB, and the server dropped the whole beacon.
- With the speed beacon on the page it sets `window.pgVitalsSends`; `pg_vitals.src.js` then takes `window.pgClicks.take()` into its own request (and sends one with clicks alone when it measured nothing), and the clicks script sends none of its own.
- `take()` returns nothing unless `software.statistics_not_refused()` (`frontend.src.js`, `platform/consent-anonymous-counts.md`) says statistics were not refused; without that function on the page nothing is sent.
