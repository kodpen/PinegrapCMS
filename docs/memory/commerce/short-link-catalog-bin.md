---
name: short-link-catalog-bin
description: How short links treat a product or product group in the Recycle Bin (wizard lists, save checks on every entry point) and posted lists on the short link screens.
---

## Where are short links made and changed?
Three entry points share one rule set:
- File Manager wizard (`view_folders.php?view=short_links`, JS in
  `view_folders.php`; server `explorer_short_link_*` in
  `view_folder_and_files_f.php`, checks in `pg_short_link_read_request()`).
- Classic screens `add_short_link.php` / `edit_short_link.php` (liveform).
- External API `includes/api/resources/short_links.php`, which calls
  `pg_short_link_read_request()` through `api_short_link_destination_in()`.

## What happens with a product or group in the Recycle Bin?
- Lists leave it out (`get_product_options()`,
  `get_product_group_options(..., $include_recycled = FALSE, $keep_ids)`).
- The one a link already points at stays, marked "[IN RECYCLE BIN]", so
  saving the link does not drop it. Wizard: `explorer_short_link_options`
  takes `item_id` and keeps that link's product/group; `kept: true` tells the
  screen not to cache those lists. The screen caches the plain lists and asks
  again with `item_id` only when the link's product/group is missing from
  them (`shortLinkTargetMissing()`).
- Save refuses any other binned product/group ("The product is in the Recycle
  Bin. Restore it in the Catalog Manager or choose another product." and the
  group twin): create always; change unless it is the stored one
  (`pg_short_link_read_request($request, $user, $current)`, the classic edit
  compares with `$short_link`, the API passes the stored `$row`).
- Symptom before (PR #423/#429 leftovers): the wizard opened a link to a
  binned product with no product chosen and Save said "Product is required";
  its group list still offered binned groups as "[DISABLED]"; a form drawn
  before the delete could still point a new link at a binned product/group.

## What about values posted as a list?
They count as not given (`docs/memory/core/request-parameter-arrays.md`):
- Wizard: every field is read with `is_scalar()`; an item id list is no id
  (`pg_short_link_by_id()`), a name list is no name. Before, `item_id[]=3`
  cast to 1 and rename/change/copy/delete acted on link 1; `name[]` made a
  link named "Array"; warnings "Array to string conversion".
- Classic screens: `pg_liveform_single_values()` right after
  `add_fields_to_session()`; before, `escape()`/`preg_match()` answered 500.

## How was it checked?
Sandbox: wizard and classic screens on old and new code, a binned product
and group with links to them; HTML of the screens unchanged apart from the
wizard JS; no log lines. Tests: `tests/short_link_requests_test.php`.

PR: #482.
