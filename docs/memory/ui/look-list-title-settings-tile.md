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
- A list named in `pg_list_table_no_head_count()` (`includes/fn/list_table.php`) prints `data-pg-list-head-count="0"` and keeps its title alone; the list is empty today. All Offers (`view_offers`, a kept screen) shows its count like every list: Owner (2026-11-15): yes in poll 4850 (channel message 5351), "Evet, görünsün"; its own filter pills stay as they are.

## How are a list's filters drawn?
- `pg_list_seg_filters($list, $column, $items, $label, $current)`: a `.pg-seg.pg-list-seg` group of `button.pg-seg-item` with the driver's `data-pg-list-filter` attributes; the driver moves `aria-pressed`, which draws the thumb. An item may carry a `count` (`.pg-seg-count`). On a narrow screen the group scrolls sideways.
- Link segments (a range, a view) are `a.pg-seg-item` with `is-active` and `aria-current="true"` inside a `.pg-seg.pg-list-seg`.
- Used by Site Log (`view_log.php`): range links and type filters as segments, the range in meta text; its "N records" badge left, the header count says it.
- Not moved: lists filtering with `pg_filter_select()` or selects (Contacts, Submitted Forms), All Offers' own filter pills (a kept screen). Marketplace Products' views and filter rows took the link segments in SH10 (`ui/look-marketplace-ranks.md`).

## How does a settings row get its icon tile?
- `pg_settings_tile($icon, $tone)` printed as the first child of the row's `.form-field` (switch row) or of the cell (label and field row). The settings grid gives it a 34 px column left of the name and description, centred on them, 13 px from the text; on a narrow card it stays left and tops.
- Tone by the category's module: Commerce and Legal Documents `shop`, Contact `team`, Security and Firewall `system`, Appearance and Languages `content`.
- Architect (2026-11-15, channel message 4851): a tile only in a card whose rows all carry one; a card mixing rows with and without keeps none, or the labels lose their shared left edge. So 13 rows have it: Private Label, Shipping, Affiliate Program, Live Chat, Mailchimp, Firewall, Unknown Bots, Language Redirect, Legal Documents, Device, the three sign-in providers. Mixed cards wait until all their rows get a tile.
