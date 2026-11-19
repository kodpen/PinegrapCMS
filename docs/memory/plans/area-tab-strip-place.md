---
name: area-tab-strip-place
description: Plan (owner 2026-11-19): an area's tab strip keeps one place on every tab screen whatever the screen's width (Communication first); where it sits is owner poll 153; TS1, TS2
---

## What did the owner ask?
- Owner (2026-11-19, in the architect's window, agent channel message 9493), with two pictures of Communication: "tablardan birisi sola dayanıyor. diğeri tabı ortaya çekiyor (tablo genişliği yüzünden) bu başka sayfalarda da fark ettim kullanıcı deneyimini etkiliyor."

## Why does the strip move? (read in the code and memory on 2026-11-19, not tried)
- The strip (`nav.pg-messages-tabs`, `pg_messages_tab_strip_for()`) is printed inside `#content`, and each tab screen takes its own width: Incoming e-mail fills the window, Campaigns is a list, as wide as its columns, at least 1700 px and centred (`ui/look-page-widths.md`). In a window wider than the list the strip starts at the centred list's left edge on one tab and at the window's on the other.
- No rule says where an area's strip sits; the page widths were decided screen by screen.

## What is built?
- Owner poll 153 (2026-11-19) decides the place: centred in the window; or at the left with every tab of the area as wide as the window; or at the window's left with the content left centred.
- TS1, Communication. The strip takes the chosen place on its six tabs and the old screens inside the shell, at every window width from 390 to 2560 px; the pointer stays on the strip from tab to tab. `tests/messages_area_test.php` and `tests/look_page_widths_test.php` pin it. One composed picture of two tabs at 2560 px, before and after, to the architect.
- TS2, the other areas. The worker first lists every group of screens that share a strip of links (the `.pg-seg` strips printed above a screen's toolbar, the ERP and store areas among them) with each screen's width class, then gives them the same rule through one shared class, never per screen.
- The rule is written into `ui/look-page-widths.md` by TS1.

## How does the plan close?
- `ui/look-page-widths.md` and `ui/messages-area-shell.md` say the rule and this plan is deleted.
