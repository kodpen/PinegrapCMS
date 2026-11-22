---
name: decisions-made-panel-16
description: Decisions list, panel part 16 (2026-11-22 on): the bell's drawer redrawn as drawn in ui/bell-redraw-drawing (poll 218): icon-button head, one selector, chips on one line, search in the head
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-panel-15.md`.

## Is the bell's drawer redrawn? (owner, 2026-11-22, agent channel poll 218, message 11888)
- Owner (2026-11-22, agent channel message 11882): "bildirim panelinde kapatma düğmesi yanındaki düğme html buton gibi kalmış masaüstünde sorun yoktu. Bildirim panelini baştan cizermisin olmuşken tam olsun modernize edelim tekrar".
- Asked with the drawing `ui/bell-redraw-drawing/bell.html` (Z1 desktop, Z2 phone, Z3 states): "Panel bu çizime göre yapılsın mı?" His answer: "Evet, böyle yapılsın".
- Binds: the drawer is built as drawn. The head holds only icon buttons (Search, More actions, Close) and the unread pill. All, Unread and Asked of me are one segmented control. The module chips stand on one line and work as toggles. The search opens in the head. "Mark all as read" stands at the list's first day heading and in the head's menu. An unread row is tinted, the time is short. The loading, failed, empty, all read and no match states and the foot's switch are as drawn.
- It replaces, in the entries of 2026-11-11 (`decisions-made-panel-4.md`), only the place of three filters: "Unread only" is no switch and "Asked of me" no chip (both are choices of the selector), and the search box is no line of its own. The four filters themselves stay.
- Unchanged: a row is read when it is opened or its dot is pressed; a row's button opens the place or acts in place; the drawer opens from the right edge, 30rem, the whole width on a phone.
- Why: on a phone the head's menu button was drawn as a raw browser button, and the chips, the search line and the switch took three lines before the first notification.

## How is it built? (architect, 2026-11-22)
- `plans/bell-redraw.md`, steps BR1 to BR3, one worker at a time. The raw button itself was fixed apart, as a small fix, before the plan.
