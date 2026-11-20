---
name: page-clicks
description: Clicks and scroll depth counter (page_click_daily, #9902): row kinds, the beacon in page_view.php and its gate, the fold, the 200-element bound, figures, 400-day sweep, the hunt of 10214
module: page_clicks
---

## What does the table hold?
- `page_click_daily` (`upgrade_2026_11_0_page_click_daily()`): `id` CHAR(40) key, `day`, `page_id`, `device` (desktop, mobile, tablet), `kind` (view, depth, element, other), `depth` 0-10, `area`, `role` (ascii_bin), `url` (255), `label` (60) utf8mb4_bin, `hits`, `elements`.
- Keys: (`kind`, `day`, `page_id`) for the period's page list and the sweep, (`page_id`, `day`) for one page. Architect (2026-11-16, message 6041): no third key; binary comparison, so case or accent never merges two elements.
- Per page, day and device: a `view` row (`hits` views, `elements` element rows made), a `depth` row per deepest tenth, an `element` row per element, one `other` row past the bound. `id` = sha1 of the parts, one per line (`pg_page_clicks_key()`).
- A deleted page's rows run out with the 400 days; readers join `page`. A wrong day is deleted, never repaired.

## How does a beacon reach it?
- `page_view.php`: `isset($_POST['c'])` before the speed part (`vp`); `pg_page_clicks_beacon()`, then exit unless the post carries `vp`.
- Fields: `cp` page id, `cs` signature (HMAC-SHA256 of `'page-clicks:' . page_id . '|' . day` over `ENCRYPTION_KEY`; today and yesterday taken), `c` JSON: `d` device, `t` deepest tenth 0-10 (absent: not measured), `e` at most 20 elements of four parts (area, `link`/`button`, address, label).
- Refused: over 30 a minute an address (waf `clicks`), the switch off, a crawler, anyone signed in, a visitor who refused statistics (`platform/consent-anonymous-counts.md`); one pure gate, `pg_page_clicks_may_count()`. A bad shape (unknown device or area, a tenth not 0-10, over 20 elements, an element not four strings, over 8 KB) drops the whole beacon.

## How are address and label folded?
- `pg_page_clicks_fold_url()`: no query or fragment; this site's host by its path, another as `//host/path`; `mailto:`, `tel:`, `sms:` and messaging schemes or hosts (wa.me, t.me, m.me …) keep their kind only (`tel:`), and a kind folds to itself (`telegram:`); a path part over 16 characters with letters and digits and no hyphen becomes `*`; script or unresolved addresses are ''; cut at 255 characters.
- `pg_page_clicks_fold_label()`: control characters out, white space folded, cut at 60 characters; empty when it holds an e-mail address or seven digits in a row.
- The server folds the script's folded values again; an element folded alike counts once a beacon; one with neither address nor label is dropped.

## What is the bound?
- 200 element rows a page and day: the sum of `elements` on the page's three view rows. A known element keeps counting; a new one past it raises the `other` row. Beacons in flight can pass it by a few (accepted, 6041).

## Where are the figures?
- `pg_page_clicks_pages()` (pages, views, half tenth), `pg_page_clicks_page()` (views, reach, elements with share, other) over pure `_reach()`, `_half_tenth()`, `_share()`.

## How long are rows kept?
- 400 days (`PG_PAGE_CLICKS_DAYS`): `job.php` runs `pg_page_clicks_sweep()` once a day (`data/temp/page_clicks_sweep.json`), `DELETE … LIMIT 5000` kind by kind within 5 seconds.

## What else is there?
- The switch: `page-clicks-switch.md`. The script (#9946): `page-clicks-script.md`. Visitors section and `GET /reports/page-clicks` (#9913): `ui/visitors-clicks-section.md`. Designer: `page-clicks-designer-layer.md`.

## What did the bug hunt find? (agent channel message 10214, #10604)
- Fixed: a beacon counted with the switch off (a cached page keeps the script an hour, a signature a day); the second fold made `telegram:`, `signal:`, `messenger:`, `teams:` '' (an icon link dropped); the script's part could pass 8 KB and lose the view.
- Read clean: bound, signature, forged body, figures, sweep, API.

## Tested?
- `tests/page_clicks_test.php`; the step, record, bound, figures and sweep were run on MariaDB.
