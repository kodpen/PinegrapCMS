---
name: widget-parts-anywhere-editor
description: W17b (#3111): the designer copies or moves a read-only element of a detail widget out as a part (partOf), refuses what the server would not draw, previews it
---

## How does the designer place one? (W17b, #3111)
- An element below the root of a detail widget (`_sdPartOffered()`): the canvas menu's "Copy outside the widget" / "Move outside the widget", or the same two buttons on top of its settings. `_sdPlacePart()` puts `_sdPartNode()` (a copy with fresh ids and `props.partOf` = the widget's shared id, no `partOf` below) right after the widget's placement on the page (`_sdPlacementOnPage()`; a widget inside a component sends it to the page's end). Moving takes it out of the widget's tree and marks that tree dirty.
- What the server would refuse is refused with its reason as a toast and nothing changes: `_sdPartRefusal()` twins `pg_sw_away_read_only()` (reason codes control, action, messages, code, shared, region, loop), `SD_PART_KINDS` twins `pg_sw_away_kinds()`, `_sdPartOf()` twins `pg_sw_away_of()`.
- The palette also places one: the detail widget's parts and values, outside every widget, land as its parts ([[widget-parts-palette]], W20a).

## How does the canvas show it?
- `buildNodeEl()` draws a part node of the page tree through `_sdBuildPartEl()`: while its subtree is drawn `_sdPartDrawing` holds the widget, and `_sdValueSidOf()` (read by `_sdSystemWidgetCfgOf()`, `_getSystemWidgetRegionType()`, `_getSystemWidgetCustomFormPageId()` and the `__record__` check of `_displayValueForBinding()`) answers with it, so a binding previews as inside the widget. No fetch of its own; `_isInsideSystemWidget()` is unchanged.
- `_sdPartInfo()` gives the state: ok, inside (a `partOf` in a shared tree means nothing), gone (the page shows no such widget), kind, or a refusal. Not ok: faded (`sd-part-off`), the reason as its tip and in the settings box. A part is outlined dashed; the overview names it "<label> — part of <widget>" (`_sdPartTreeLabel()`); its settings say which widget and offer "Select the widget".

## What do the panels offer a part? (#4152, #3988)
- `_sdPartSidAt(node)` gives the widget of the part a page-tree node is or stands in (0 for no part, `inside`, `gone`, `kind`). Bind Data (`_propsDataBindingCore()`), the condition values (`_sdCondTokenOptions()`, `_sdConditionRows()`) and the list band (`_sdListBand()`) run under `_sdWithValueSid()` with it, so a part gets its widget's values with their labels, as inside the widget. Page-record values are not offered on a part: the server draws the part in widget scope, where `pg_page_record_bind_props()` does not run and a `__record__…` binding is swept empty; the widget's own values cover the record. A change still marks only the page tree (`_sdNodeSetBinding()` marks `_isInsideSystemWidget()`'s row only). Action, format and hide-empty rows stay widget-only.

## Does a part holding a list save? (#4152, #3987)
- `_vt_walk()` (page-save validator) accepts a `loop_area` only below a `partOf` node and only sourced (Breadcrumb, Cross-sell); whether the part draws stays `pg_sw_away_read_only()` / `_sdPartRefusal()`. Every refusal of the walk is a translated sentence (`lang()`), never a node type name.

## Tests?
- `tests/widget_parts_anywhere_designer_test.php` (Node): the kinds twin, refusal and `partOf` vectors against the server's functions, the placed node's shape and its marker. The menu, the canvas drawing and the settings box had no browser run. `tests/designer_part_tree_test.php`: a page tree with Breadcrumb and Cross-sell parts saves, translated refusals, `_sdPartSidAt()` under Node, the panels wired to it.
