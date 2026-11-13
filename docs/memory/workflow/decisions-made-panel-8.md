---
name: decisions-made-panel-8
description: Decisions list, panel part 8 (2026-11-12 on): look A's report page as reference (#8955); a switch keeps its thumb (#9070); Design Canvas icon in one colour (#9250); cards in the form column (#9271)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Is the report screen page the reference for the report screens? (owner, 2026-11-12, #8955)
- Yes, as drawn (`plans/panel-design-language-report.md`): ready reports and the period on top, four figures, the chart against the previous period, the grouped table with its grand total; under the screen the bars card for a named level and the report settings drawer.
- Owner (2026-11-12), shown the page in both themes and asked "R: is this page the reference for the report screens? a) yes, b) changes wanted": "Ra".
- The architect's, not the owner's words: the page re-dresses the parts of the report view (`ui/report-view.md`) and adds none; step DL5e of `plans/panel-design-language-steps.md` follows it.

## Is a settings switch without a thumb approved? (owner, 2026-11-12, #9070)
- No. A switch is the reference sheet's: a thumb that can be seen in both states and both themes. Done #9197 (`ui/look-switch.md`).
- Owner (2026-11-12), with a picture of a settings row in the dark theme: "ayarlardaki görsel 1 deki gibi switchler değişik bir checkbox türüne evrilmiş ben bunu onaylamadım".

## Where is the Design Canvas icon drawn in one colour? (owner, 2026-11-13, #9250)
- Owner (2026-11-13), on a channel's "+" menu, where New canvas was the only coloured icon: "yeni tuval ikonu sırıttlı açıkçası. siyah beyaz çevirelim tekrar." Asked where, he chose "Tek renkli listelerde".
- Where the icon stands among one-colour icons it is drawn in one colour, its neighbours': Workspace's two New canvas entries, the File Manager's context menu and file tools.
- The main menu and the editor's top bar keep drawing B's two colours (#8903, `decisions-made-panel-7.md`): that decision is narrowed, not withdrawn.
- Built in DI1 (`ui/design-canvas-icon.md`): `pg_design_canvas_icon_one_colour()`.

## How do cards use the form column of an add or edit screen? (owner, 2026-11-13, #9271)
- The measure alone: a card stays one per row and its fields keep the 12-column grid, so they widen with the form column up to 1300 px (way A of #9269, `ui/look-page-widths.md`). Not chosen: fields two abreast with each input capped at about 480 px (C).
- Owner (2026-11-13): "A olsun"; told that a full-row input becomes about 1270 px against the field sentence of #9070: "C çok salkım saçak olacak ya A ya B olsun".
- The architect's, not the owner's words: he left A or B open and the architect takes A, his first answer; two cards abreast (B) is what to come back to if stretched fields look wrong on the real screens. It narrows #9070 (`decisions-made-panel-7.md`): an input may run the width of the form column, never of the window. The architect did not see the pictures.
