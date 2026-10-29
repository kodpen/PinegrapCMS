---
name: widget-parts-anywhere-editor
description: W17b (#3111): the designer copies or moves a read-only element of a detail widget out as a part (partOf), refuses what the server would not draw, previews it
---

## How does the designer place one? (W17b, #3111)
- An element below the root of a detail widget (`_sdPartOffered()`): the canvas menu's "Copy outside the widget" / "Move outside the widget", or the same two buttons on top of its settings. `_sdPlacePart()` puts `_sdPartNode()` (a copy with fresh ids and `props.partOf` = the widget's shared id, no `partOf` below) right after the widget's placement on the page (`_sdPlacementOnPage()`; a widget inside a component sends it to the page's end). Moving takes it out of the widget's tree and marks that tree dirty.
- What the server would refuse is refused with its reason as a toast and nothing changes: `_sdPartRefusal()` twins `pg_sw_away_read_only()` (reason codes control, action, messages, code, shared, region, loop), `SD_PART_KINDS` twins `pg_sw_away_kinds()`, `_sdPartOf()` twins `pg_sw_away_of()`.
- No palette entry: a part starts as an element of its widget.

## How does the canvas show it?
- `buildNodeEl()` draws a part node of the page tree through `_sdBuildPartEl()`: while its subtree is drawn `_sdPartDrawing` holds the widget, and `_sdValueSidOf()` (read by `_sdSystemWidgetCfgOf()`, `_getSystemWidgetRegionType()`, `_getSystemWidgetCustomFormPageId()` and the `__record__` check of `_displayValueForBinding()`) answers with it, so a binding previews as inside the widget. No fetch of its own; `_isInsideSystemWidget()` is unchanged, so the bind panels treat a part as outside the widget.
- `_sdPartInfo()` gives the state: ok, inside (a `partOf` in a shared tree means nothing), gone (the page shows no such widget), kind, or a refusal. Not ok: faded (`sd-part-off`), the reason as its tip and in the settings box. A part is outlined dashed; the overview names it "<label> — part of <widget>" (`_sdPartTreeLabel()`); its settings say which widget and offer "Select the widget".

## Tests?
- `tests/widget_parts_anywhere_designer_test.php` (Node): the kinds twin, refusal and `partOf` vectors against the server's functions, the placed node's shape and its marker. The menu, the canvas drawing and the settings box had no browser run.
