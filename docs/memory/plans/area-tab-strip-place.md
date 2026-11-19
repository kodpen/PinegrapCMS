---
name: area-tab-strip-place
description: Plan (owner 2026-11-19, poll 153): an area's tab strip sits centred in the window on every tab screen whatever the screen's width; Communication first, then the other areas; TS1, TS2
---

## What did the owner ask?
- Owner (2026-11-19, in the architect's window, agent channel message 9493), with two pictures of Communication: "tablardan birisi sola dayanıyor. diğeri tabı ortaya çekiyor (tablo genişliği yüzünden) bu başka sayfalarda da fark ettim kullanıcı deneyimini etkiliyor."

## Why does the strip move? (read in the code and memory on 2026-11-19, not tried)
- The strip (`nav.pg-messages-tabs`, `pg_messages_tab_strip_for()`) is printed inside `#content`, and each tab screen takes its own width: Incoming e-mail fills the window, Campaigns is a list, as wide as its columns, at least 1700 px and centred (`ui/look-page-widths.md`). In a window wider than the list the strip starts at the centred list's left edge on one tab and at the window's on the other.
- No rule says where an area's strip sits; the page widths were decided screen by screen.

## What is built?
- Owner (2026-11-19, poll 153, `workflow/decisions-made-panel-10.md`): "Pencerenin ortasında". The strip is centred in the window's content area, not in its screen's `#content`, so a list's or an edit screen's own width no longer moves it; the lists' width rule stays.
- TS1, Communication (done, #10390). The strip takes that place on its six tabs and the old screens inside the shell, at every window width from 390 to 2560 px; the pointer stays on the strip from tab to tab. `tests/messages_area_test.php` and `tests/look_page_widths_test.php` pin it.
- TS2, the other areas (done, #10413). Listed every `.pg-seg` strip: only the marketplace products screen's views are an area strip besides Communication (the ERP has none; the ready reports row and toolbar filters are tools). Both take the shared class `.pg-area-tabs` (`ui/area-tab-strip-centred.md`).
- The rule is written into `ui/area-tab-strip-centred.md` by TS1 (linked from `ui/look-page-widths.md`, which was at its size limit).

## How does the plan close?
- `ui/look-page-widths.md` and `ui/messages-area-shell.md` say the rule and this plan is deleted.
