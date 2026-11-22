---
name: look-design-examples-patterns
description: The Patterns group of the Design examples screen: settings pane, connect row, empty list, starting-point sheet, numbered steps drawer, form drawer, Workspace parts, e-mail parts; their order and frame
module: look_examples
---

## Which patterns are drawn, in which order?
- Patterns (`pg_look_examples_patterns()`, under the parts): the settings pane, the connect row, a list's empty state (DX1 #10859); the starting-point sheet in its three steps and the numbered steps drawer (DX2 #10885), each in the Drawer section's standing frame.
- "Form drawer" (DX3 #11105, `pg_look_examples_form_drawer_pattern()`), after the numbered steps drawer: Application Access' `#api_drawer` at 760 px, head with tile, kind line and state pill, the tabs as `.pg-seg.pg-list-seg`, the Overview tab's groups Details, Summary (Advanced as a closed fold row) and Danger last, the foot with Cancel quiet and Save; under it the danger row in its confirm state (Cancel, Yes, delete).
- The Workspace's conversation timeline (#11047, `pg_look_examples_timeline_pattern()`), then "Workspace list row" (#11079, `pg_look_examples_ws_list_row_pattern()`, live rows in a `.ws-menu-side` box), before the e-mail parts.
- The e-mail settings drawing's seven parts close the group (DX2b #10930, `pg_look_examples_mail_patterns()`): `look-mail-patterns.md`.

## How is a drawer pattern framed?
- The patterns' frame is no `.drawer`, which sets `--bs-drawer-padding-*`: its head and body take `p-5` (16 px, measured on the real `#api_new`; the sheet's head `p-5 pb-1`), as the Drawer section does since DX2. The sheet's cards use the recipe "Look A: starting-point cards" (`.pg-start*`); the steps drawer draws Claude's own guide (`ws_claude_setup_guide()`) with its tile, line and Close, as the real drawer.

## What does the form drawer copy?
- The Overview tab of `#api_drawer` (`api_app_drawer_overview_html()`), shortened: the facts and the key row are left out; the foot has Cancel quiet beside Save, where the real drawer shows Close only for a locked application. Its test: `test_look_examples_form_drawer()`.

Related: docs/memory/ui/look-design-examples.md, docs/memory/ui/look-mail-patterns.md, docs/memory/ui/screen-composition-rules.md
