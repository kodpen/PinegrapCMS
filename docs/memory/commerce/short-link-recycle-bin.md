---
name: short-link-recycle-bin
description: How short links treat a product, product group, page or file in the Recycle Bin (lists, save checks on every entry point), where a deleted short link goes, and posted lists on the short link screens.
---

## Where are short links made and changed?
Three entry points share one rule set:
- File Manager wizard (`view_folders.php?view=short_links`, JS in
  `view_folders.php`; server `explorer_short_link_*` handlers in
  `includes/explorer/handle_short_links.php`, checks in `pg_short_link_read_request()`
  (`includes/explorer/short_links.php`)).
- Classic screens `add_short_link.php` / `edit_short_link.php` (liveform).
- External API `includes/api/resources/short_links.php`, which calls
  `pg_short_link_read_request()` through `api_short_link_destination_in()`.
- The name rule, the 301/302 answer and the loop checks all three share are
  in `platform/short-link-redirects.md`.

## What happens with a target in the Recycle Bin?
Products/groups are binned by the `recycled` flag (`pg_catalog_*_binned()`);
pages and files by folder: the bin folder or a folder sent into it
(`pg_recycle_page_binned()`, `pg_recycle_file_binned()`, `fn/designer.php`).
- Lists leave them out: `get_product_options()`,
  `get_product_group_options(..., $include_recycled = FALSE, $keep_ids)`,
  `get_page_options(..., $include_recycled = false, $keep_id)` (default call
  unchanged for its other callers), `get_file_options($design, $keep_id)`.
- The one a link already points at stays, marked "[IN RECYCLE BIN]" (a page
  or file under its unparked name), so saving keeps it. Wizard:
  `explorer_short_link_options` takes `item_id`; `kept: true` tells the screen
  not to cache those lists; `shortLinkTargetMissing()` asks again when the
  link's target is not on the cached lists.
- Save refuses any other binned target ("The page is in the Recycle Bin.
  Restore it in the File Manager or choose another page." and the product,
  group, file twins): create always; change unless it is the stored one
  (`pg_short_link_read_request($request, $user, $current)`; classic edit
  compares with `$short_link`; the API passes its stored `$row`).
- Symptom before (#482 for products/groups, #487 for pages/files): the
  wizard opened such a link with nothing chosen and Save asked for it; binned
  files (as `pgbin_<id>_name`) and pages in binned folders were offered as
  new targets.

## Where does a deleted short link go?
To the bin (`recycled = 1` plus a `recycle_bin` row,
`pg_short_link_to_bin()`) from the wizard, the API and, since #487, the
classic edit screen's Delete (before, it deleted the row outright). Restore or
permanent delete happen in the File Manager's bin. `view_short_links.php`
and the rich text editor's link picker (`editor_select_page_or_file.php`,
type `short_link`; since #1017, before it offered them and the link put in
the content answered 404) leave binned links out; `edit_short_link.php`
answers 409 "This short link is in the recycle bin…" for one. Restore
renames a taken name (`pg_short_link_free_name()`) and refuses a link that
would close a loop with links saved meanwhile (it stays binned; #1235).
Before the
2026.4.4 upgrade, delete is still permanent.

## What about values posted as a list?
They count as not given (`core/request-parameter-arrays.md`): the wizard reads
every field with `is_scalar()` (`item_id[]` no longer hits link 1, `name[]` no
longer names a link "Array"); classic screens call
`pg_liveform_single_values()` (15 former 500s).

## What name does a copy or a renamed restore get?
`pg_short_link_free_name()`: the name, else `name[1]`, `[2]`… cut at its end
to fit the column (`pg_short_link_numbered_name()`, 191; 100 before 11.0.12).
Before #1024 a name of 189-191 characters lost the suffix on INSERT: two
links with one name.

Tests: `tests/short_link_requests_test.php`, `short_link_redirects_test.php`.
PRs: #482, #487, #1024, #1235.
