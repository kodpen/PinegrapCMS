---
name: site-icon
description: The site icon (favicon sizes, apple-touch-icon, site.webmanifest): setting in config_text, sizes drawn by the image engine, router addresses, head links.
module: site_icon
---

## Where is the setting?
- Site Settings, SEO, Site Icon: a PNG or SVG of the Files screen (#2176, decision #2472). Kept as the settings text `site_icon` in `config_text` (`pg_site_icon_name()`), not a `config` column: the config row has no room left ([[config-table-no-varchar]]). Empty (the default) changes nothing on running sites.
- The save (`includes/settings/seo.save.php`) refuses a name that is not a PNG/SVG row of `files`, draws the sizes at once and says when they cannot be drawn (`pg_site_icon_error_text()`). The file's "where used" list names it.

## How are the sizes drawn?
- A PNG source: 48, 180, 192 and 512 px (`pg_site_icon_sizes()`) through `pg_process_image_file()` only (`pg_site_icon_draw()`: copy, resize to the long edge, move over the old one, `stamp.txt` last) into `data/site_icon/`. The engine never enlarges: a smaller source is kept as is. The setting asks for a square picture; a wide one stays wide.
- The stamp is name + mtime + size of the source; a picture replaced in Files is drawn again by the next page or icon request (`pg_site_icon_current()`). A JPEG/WebP is not offered: the sizes must be PNG.
- An SVG is linked at its own address (`rel="icon" type="image/svg+xml"`, manifest `sizes: any`) and gets no apple-touch-icon.

## Which addresses answer?
- `router.php` hands `site.webmanifest`, `apple-touch-icon.png` and `site-icon-<48|192|512>.png` to `get_site_icon.php` before the files table is asked, only while `router_site_icon_set()` reads a value (raw query, no functions.php: [[router-no-functions-php]]). Without a setting those names go on to a file or a page as before.
- No session, a day's cache with an ETag; the manifest is `application/manifest+json` with `display: browser`.

## When does a page get the links?
- `get_page_content()` adds them before `</head>` (`pg_site_icon_page_head()`), not in e-mail and not for an output without `</head>`. What the page's own head already declares (`icon`, `apple-touch-icon`, `manifest`: `pg_site_icon_declared()`) is not added again: the design's links win. Hrefs carry `?v=<stamp>` so a long-cached icon is asked again when the picture changes.
- The panel's own icons (`includes/fn/output.php`) and the installed panel's `manifest.php` / `manifest_icon.php` (`APP_ICON`) are separate.

## How is it tested?
- `tests/site_icon_test.php`: kinds, addresses, head links, declared links, manifest, drawing with GD on temp paths, router and save wiring.
