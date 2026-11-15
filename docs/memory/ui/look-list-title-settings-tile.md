---
name: look-list-title-settings-tile
description: Look A shared recipes (SH9, #9726): a list screen's count beside its title, a list's filters as a segmented control (pg_list_seg_filters()), the 34 px icon tile of a settings row (pg_settings_tile())
module: look_list
---

## Where are the recipes?
- Helpers in `includes/fn/look_list.php`; CSS in the block "Look A: list title and settings tile" at the end of `backend.src.css`; the count is written by `assets/js/pg_list_table.js` (`Driver.prototype.headCount`). Tests: `tests/look_list_test.php`.

## How does a list screen show its count?
- The screen's title stays in the header (`#header .pg-navhead`); the list table driver adds `span.pg-navhead-count` after the title: 12 px, third text colour, tabular, on the title's line, the description under both.
- Written on every paint, so it follows the search and the filters: the rows of the condition (server mode) or the rows shown (browser mode). Text: the driver's `countOne` / `countMany` ("{var:1} record(s)"), the number in the page language's grouping.
- Only on a screen whose body has `pg-measure-list` and holds exactly one `.pg-list-table`; an embedded list (edit screens) gets none. A screen needs no change of its own.
- Of the owner's kept screens it reaches only All Offers (`view_offers`, on the driver at the list measure): its picture went to the owner through the architect.

## How are a list's filters drawn?
- `pg_list_seg_filters($list, $column, $items, $label, $current)`: a `.pg-seg.pg-list-seg` group of `button.pg-seg-item` with the driver's `data-pg-list-filter` attributes; the driver moves `aria-pressed`, which draws the thumb. An item may carry a `count` (`.pg-seg-count`). On a narrow screen the group scrolls sideways.
- Link segments (a range, a view) are `a.pg-seg-item` with `is-active` and `aria-current="true"` inside a `.pg-seg.pg-list-seg`.
- Used by Site Log (`view_log.php`): range links and type filters as segments, the range in meta text; its "N records" badge left, the header count says it.
- Not moved: lists filtering with `pg_filter_select()` or selects (Contacts, Submitted Forms), All Offers' own filter pills (a kept screen), Marketplace Products' view buttons and filter chips.

## How does a settings row get its icon tile?
- `pg_settings_tile($icon, $tone)` printed as the first child of the row's `.form-field` (switch row) or of the cell (label and field row). The settings grid gives it a 34 px column left of the name and description, centred on them, 13 px from the text; on a narrow card it stays left and tops.
- Tone by the category's module: Commerce and Legal Documents `shop`, Accounting `accounting`, Contact `team`, Visitor Tracking `reports`, Security, Firewall, sign-in limits `system`, Appearance, Features, Languages `content`.
- Given to the card-level feature switches (28 rows: the switch that opens a feature's block). Switches inside an opened block and plain field rows have none yet.
