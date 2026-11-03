---
name: prototype-places
description: Where the Prototype view keeps a moved frame or chip: frames shared on the design in the tab layout's proto (#6232), the rest per browser; the twin normalisers
---

## Where is a moved frame kept?
- A frame of a saved tab (`p:p<page_id>`) or a shared component (`s:<id>`) moved by an operator who may arrange tabs (`_pgTabLayoutEditable()`) is kept on the design: `_sdProtoKeep()` writes `_pgTabProto` (`p<page_id>` / `s<id>` → `{x, y}`, whole units) and calls `_pgTabsLayoutSave()`, which posts the whole tab layout to `designer/tab_layout` with `proto` in it (designer/designer-tab-groups.md). Its browser place is dropped.
- Chips (their id `c:<n>` is an index, not stable), unsaved tabs and a content-level operator's moves stay in `localStorage` `pg_proto_pos_<style id>` (id → x, y).
- `_sdProtoSaved()` hands `layout()` the shared places (`PGProtoHotspots.places(_pgTabProto)`), the browser's on top. `_pgTabsApplyLayout()` loads `_pgTabProto` from `sdDesign.tabLayout.layout.proto`.

## What is a valid place?
- `pg_designer_tab_layout_proto()` (PHP, in `pg_designer_tab_layout_normalize()`): key `^[ps][1-9][0-9]{0,9}$`, a `p` key only for a page of the design, x and y numeric and finite, rounded, bounded to ±1,000,000; at most 500; others dropped. The key is left out of the layout when there are none.
- JS twin `PGProtoHotspots.places(proto)` (same rules, page membership left to the server) answers frame ids; `placeKey(id)` maps a frame id back (`p:p12` → `p12`, `s:5` → `s5`, else ''). Parity is tested in `tests/proto_hotspots_test.php`; the PHP side in `tests/designer_tab_layout_test.php`.

## What is not covered?
- Two editors moving frames at once: the last tab layout save wins, as for tab order.
- The drag-to-save wiring was not run in a browser.
