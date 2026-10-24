---
name: record-value-page-links
description: A form list or form detail link opening another page with the record's values in its address (__page_link + _bindParams.href): shape, pre-pass, escaping, values, Publish, export, editor, code view
---

## What does it do, and where?
- Bind Data option "Another page, with values from the record" on a link's href (content link, `<a>`, Button) in `form_list_view` (and its three old names) or `form_item_view`: `/board?board=<board>`.
- Stored: `_bindings.href = '__page_link'`, `_bindParams.href = {page_id, params: [{name, token}]}`; `page_id`: an id, `tab:<key>` until Publish, or 0.

## How does the renderer draw it?
- `pg_sw_page_link_bindings(&$tree)`, beside `pg_sw_record_href_bindings()`, makes each link `__page_link_<n>` (concatenated, like `__link_label_<n>`) and collects `pg_sw_page_link_spec()`.
- `pg_sw_page_link_resolve()`: one page query (static per request), `pg_sw_page_link_base()`. Runs before the list's `$stitch` (captures by value) and the detail's `$tree_designed` copy.
- `pg_sw_page_link_tokens()` joins the record query's tokens, so a param's standard field is selected.

## How is it escaped?
- Once: `pg_sw_page_link_url()` builds the raw address (`rawurlencode` name and value), `pg_sw_page_link_values()` `h()`s it into `$extra`, which `pg_sw_apply_tokens()` prints as it is. A value's `&` is `%26`, `^^x^^` `%5E%5Ex%5E%5E`.

## Which names and values are sent?
- Names: `pg_sw_url_param_name()` (the URL-filter rule, [[form-list-url-filters]]); a refused, empty-token or repeated name is dropped; at most `pg_sw_page_link_max()` = 8 (JS `_SW_PAGE_LINK_MAX`).
- Values: `pg_sw_raw_token_value()` in `pg_sw_apply_tokens()` order: `$extra_raw` (record address and ids), a `pg_sw_standard_fields()` name the query selected, the form field as stored (no date format, joined with `, `). `id`, `user_id`, `__*` give nothing.
- A value is sent only when `pg_sw_url_param_value()` reads it back unchanged (≤ 100 characters, one line); else left out. None left: the bare page. `translate_records` on: source values (`$by_form_source`, `$custom_source`).

## What if there is no record, or no page?
- List surround and empty states (`$page_link_bare`), detail with no record: the bare page.
- Missing or binned page: `#`. A sentinel nothing expanded (another widget kind, a nested shared component): `_apply_bindings()` prints `#`, no `data-pg-bind`.

## How is a tab: page resolved and exported?
- Templates write `'page_id' => '{{tab:<key>}}'`; `_pg_tpl_fill()` gives `tab:<tab key>`.
- Publish: `pg_designer_resolve_tab_refs()` also matches `tree_json LIKE '%"page_id":"tab:%'`, resolves with `pg_sw_page_link_resolve_tabs()` (only `_bindParams.*.page_id`) and writes through `pg_designer_tree_encode()` in the config's UPDATE.
- Editor: `_swResolveTabRefs()` runs `_swResolveTreeTabRefs()` on `_sharedCache` trees and their snapshots (swapped, never retaken).
- From a design: `_pg_dtc_tokenize_props()` writes a design page as `{{tab:<key>}}`; params copied untouched.

## How does the editor offer it?
- Only for `href` when `_swPageLinkOffered(node)` (stored `regionType` in `_SW_PAGE_LINK_TYPES`); elsewhere a typed `__page_link` shows as Custom and prints `#`.
- `_sdPageLinkRows()`: page by `_swPageOptions()`/`_swPageVal()`, rows of name + field (form fields, standard fields minus `_swStandardYields()`), `_swPageLinkNameWarn()`. Edits: `_sdPageLinkEdit()` + `_swPageLinkSpec()`.
- `SW_PAGE_LINK_TOKEN` is in no palette: `{{` never offers it, and `check_bindings` needs no exemption.

## What does the code view do with the spec?
- `_sdNodeSetBinding()` never touches `_bindParams`; only the panel's `_setBinding()` seeds or drops it. A half token or a literal keeps the spec, inert; `{{__page_link}}` typed again finds it.

## How do I add it to another widget kind?
- Call the pre-pass and resolve before drawing, build its raw value map and `$extra +=` the values, then add the kind to `_SW_PAGE_LINK_TYPES`.

Tests: `tests/designer_page_link_test.php`, `tests/designer_code_view_binding_test.php`.
