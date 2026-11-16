---
name: page-clicks
description: Clicks and scroll depth counter (page_click_daily, 11.0.224, #9902): four row kinds, the beacon in page_view.php, the fold, the 200-element bound, figures, 400-day sweep
module: page_clicks
---

## What does the table hold?
- `page_click_daily` (`upgrade_2026_11_0_page_click_daily()`, in `get_tables()`): `id` CHAR(40) ascii_bin primary key, `day`, `page_id`, `device` ENUM('desktop','mobile','tablet'), `kind` ENUM('view','depth','element','other'), `depth` 0-10, `area` VARCHAR(8) and `role` VARCHAR(6) ascii_bin, `url` VARCHAR(255) and `label` VARCHAR(60) utf8mb4_bin, `hits`, `elements`.
- Keys: (`kind`, `day`, `page_id`) for the period's page list and the sweep, (`page_id`, `day`) for one page. Architect (2026-11-16, agent channel message 6041): no third secondary key; binary comparison so the grouping is the key's identity (case or accent never merges two elements).
- Per page, site day and device: a `view` row (`hits` = views, `elements` = element rows made), a `depth` row per deepest tenth reached, an `element` row per element, one `other` row past the bound. `id` = sha1 of the parts as stored, one per line (`pg_page_clicks_key()`).
- A deleted page's rows run out with the 400 days; readers join `page`. A wrong day is deleted, never repaired.

## How does a beacon reach it?
- `page_view.php`: `isset($_POST['c'])` before the speed part (`vp`) and the page-view payload: `pg_page_clicks_beacon()`, then exit unless the same post carries `vp`.
- Fields: `cp` page id, `cs` signature (HMAC-SHA256 of `'page-clicks:' . page_id . '|' . day` over `ENCRYPTION_KEY`; today and yesterday taken), `c` JSON: `d` device, `t` deepest tenth 0-10 (absent: not measured), `e` at most 20 elements of four parts (area, `link`/`button`, address, label).
- Refused: over 30 a minute an address (waf bucket `clicks`), a crawler, anyone signed in (staff or member), a visitor who refused statistics (`platform/consent-anonymous-counts.md`). A bad shape (unknown device or area, tenth out of range or not an integer, over 20 elements, an element not of four strings, over 8 KB) drops the whole beacon.

## How are address and label folded?
- `pg_page_clicks_fold_url()`: no query or fragment; this site's host by its path, another as `//host/path`; `mailto:`, `tel:`, `sms:` and messaging schemes or hosts (wa.me, t.me, m.me …) keep their kind only (`tel:`); a path part over 16 characters with letters and digits and no hyphen becomes `*`; script or unresolved addresses are ''; cut at 255 characters.
- `pg_page_clicks_fold_label()`: control characters out, white space folded, cut at 60 characters; empty when it holds an e-mail address or seven digits in a row.
- An element folded alike counts once a beacon; one with neither address nor label is dropped.

## What is the bound?
- 200 element rows a page and day: the sum of `elements` on the page's three view rows. A known element keeps counting; a new one past it raises the `other` row. Beacons in flight can pass it by a few (accepted, 6041).

## Where are the figures?
- `pg_page_clicks_pages()` (period's pages, views, tenth half the views reach), `pg_page_clicks_page()` (views, reach per tenth, elements with share, other), pure `pg_page_clicks_reach()`, `pg_page_clicks_half_tenth()`, `pg_page_clicks_share()`. CM3 draws them (#9913).

## How long are rows kept?
- 400 days (`PG_PAGE_CLICKS_DAYS`): `job.php` runs `pg_page_clicks_sweep()` once a day (`data/temp/page_clicks_sweep.json`), `DELETE … WHERE kind = ? AND day < ? LIMIT 5000` kind by kind within 5 seconds.

## What switches it on?
- Settings text `page_clicks` (`PG_PAGE_CLICKS_SWITCH`) = `1`; missing is off, so every site starts off (C16). `pg_page_clicks_switch_on()` is its one reader (#9913): Visitors, CM2's script and card, CM4's layer.

## What is not there yet?
- The script and the Features card (CM2), the designer layer (CM4). Until CM2 nothing sends a beacon.

## Tested?
- `tests/page_clicks_test.php`. The step was run twice on MariaDB; record, bound, figures and sweep against it. No browser run (no script yet).
