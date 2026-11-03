---
name: short-link-binned-destination
description: A short link whose page or file is in the Recycle Bin: the list shows the original name marked, the CSV writes it, a 301/302 sends nobody to the parked pgbin_ address
module: short_link_redirects
---

## How is a binned destination recognised?
`pg_short_link_binned_original($link)` (pure): the page (page, product group,
product) or file name starts with `pgbin_<its own id>_` and a name follows;
it returns that original name, else ''. The row needs `page_id`/`file_id`
besides the names.

## What does each place do with it?
- List (`view_short_links.php`): the original name with `[IN RECYCLE BIN]`.
- CSV export (`pg_short_link_csv_target()`): the original name, the address
  the page takes back on restore; re-importing never makes links to `pgbin_`.
- Redirect (`pg_short_link_location()`): '' — no 301/302. The router then
  serves the link in place, and the parked page answers as a binned page.

## Why?
A 301 to `/pgbin_…` (410) was cached by browsers and search engines and kept
sending visitors there after a restore; the list and the CSV leaked the
internal name (#5337).
