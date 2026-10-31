---
name: speculation-prefetch-left-out
description: What the Speculation Rules block leaves out: link paths, page types, and designer pages whose system widget signs in or out, runs cart or checkout or shows an account
module: speculation
---

## Which link addresses are dropped before any page is read?
- `pg_speculation_path()`: anchors, other schemes, other hosts (allowed: `HOSTNAME`, `HOSTNAME_SETTING`), relative paths, any `?`, paths outside `PATH`, the software directory (panel, `get_file`, sign-out), a last segment with a file extension other than `.html`/`.htm`, dot segments.

## Which pages are left out?
- Then one query reads the named pages and the home page: only an existing page whose folder is `public` and whose type `pg_speculation_page_type_kept()` lists (standard, folder view, photo gallery, catalog, catalog detail, calendar view, calendar event view, form list/item view, form view directory, custom form). Sign-in, sign-out, registration, cart, order, checkout, account and password pages, search results and any type added later are left out. The page shown is left out.

## How is a visual designer page left out?
- A visual designer page is type `standard`; a system widget does the work. Before #4011 such sign-in, sign-out, password, cart and checkout pages were listed (`/cikis` too). Now the same query flags (`acting`) a page whose tree (`pg_page_tree_sql_expr()`) holds a `sharedId` of a system widget that `pg_speculation_widget_kept()` refuses: the widget of each page type left out (`pg_sw_page_type_widgets()`), every `account_*` kind and `wishlist`. One more query per request reads those widget rows (`pg_speculation_acting_widget_ids()`). `login_region` and `cart_link` keep a page: they sit in every page's header. Only widgets placed in the page's own tree count, not ones inside another shared component (as `pg_sw_widget_pages()`). Covers navbar links and Menu widget rows alike.

Tests: `tests/speculation_rules_test.php`. Overview: `speculation-prefetch.md`.
