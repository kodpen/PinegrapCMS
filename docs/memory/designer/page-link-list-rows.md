---
name: page-link-list-rows
description: A page link (__page_link) drawn in a list row sends values of the row: row params, props._pgRowLinks, the pgpl placeholder, stage 2 fill, settle, the panel's row group (W15, #1734)
---

## How does a link in a list row take the row's values?
- `_pg_sw_page_link_walk()` marks a param whose token is a row token of the nearest list (`'row' => true`) and lists the link's token in that loop_area's `props._pgRowLinks` (render copy only); `pg_sw_list_span()` keeps them as the template's `links`.
- While a widget renders, `pg_sw_page_link_values()` gives such a link a placeholder of plain characters, `pgpl-NONCE-<base64 . _ ~>-`, holding the page and the record's values. Stage 2 fills each row's address from it (`pg_sw_page_link_row_value()`; `__value_param` is decoded first, so it is encoded once); one no row filled is settled without row values (`pg_sw_page_link_settle()`, stage 3).
- The panel's field list starts with "Row of the list" (`_sdPageLinkRowTokens()`: the nearest list only, as the server).

## Why a placeholder, not a token?
- The record's fill writes the link's value into the list's carrier before the rows exist; a `^^token^^` in it would be swept. Plain characters pass every fill and `h()`; the nonce makes a value shaped like one text.

Tests: `tests/widget_attr_bindings_test.php`. Related: [[record-value-page-links]], [[widget-lists]].
