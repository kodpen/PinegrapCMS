---
name: decisions-made-panel-7
description: Decisions list, panel part 7 (2026-11-12 on): look A's second pass and dialogs page as reference (#8860); add, edit, list widths, no pager; Design Canvas icon (#8903); dashboard keeps its look (#8938)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Is the second pass of look A the reference? (owner, 2026-11-12, #8860)
- Yes: depth on surfaces, a filled button with a gradient, a segmented control in place of filter chips, status pills with a dot, an icon tile on each settings row, headings in Inter's Display cut. Numbers: `plans/panel-design-language-look.md`; pages: `plans/panel-design-language-sheet.md`.
- Owner (2026-11-12), who had asked for a more modern drawing ("tasarımları biraz daha modernize edermisin"), on the second pass: "bunları sevdim".
- The bell's drawer redrawn in the second pass (`plans/panel-design-language/bell-drawer.html`), owner (2026-11-12): "uygun, çekmeceyi de plana ekle".

## How wide is an add or edit screen? (owner, 2026-11-13, #9070)
- Centred and at most 1700 px: status column 376, 24 between, the form column the rest. A field is as wide as its content asks; an input never runs the width of the window or of its column. The save bar sits at the bottom, as wide as the content. The layout's three parts stay (`ui/edit-screen-wide-layout.md`).
- Owner (2026-11-12, #8860) had taken 1120 px (760 + 336) as drawn, against inputs that ran the width of the window. On the real screens (#9070): "yeniden tasarlanan sayfalardaki daralma sayfaları anlamsız derecede darlatmış"; then (2026-11-13): "hatta ekleme ekranları 1700 e kadar çıkabilir yeni pano genişliği".

## How wide is a list, and how does it page? (owner, 2026-11-13, #9070)
- As wide as its columns need: at least 1700 px (the window when it is narrower), at most the window, centred; only the name column takes the slack. With the details panel open its width is added, up to the whole window; the table gives way only when the window has no room left.
- Owner (2026-11-13), with two pictures of the Pages list: "aslında sayfalar ekranı doğru olmuştu. detay paneli kapalıyken 1700 civarı açınca tam ekran oluyordu"; "diğer sayfalarda container standart genişliği olmuş nedense". It replaces the 1280 px minimum (#8860). Plan `plans/look-widths-and-switch.md`.
- No pager: rows are added on scroll, with "Daha fazla göster" in the foot (owner, 2026-11-12, #8860).

## Is the dialogs and drawers page the reference? (owner, 2026-11-12, #8860)
- Yes: the confirm, form and settings dialogs and the drawer as drawn (`plans/panel-design-language-dialogs.md`).
- Owner (2026-11-12), shown the page in both themes: "tasarımlarını onaylıyorum bu arada plana koyabilirsin".

## Which drawing is the Design Canvas icon? (owner, 2026-11-12, #8903)
- Drawing B: an artboard with crop marks and a pen curve with its two anchors, the curve in the accent hue. Plan step NM2 (`plans/design-names-menu-steps.md`) draws it into `pg_menu_icon()`.
- Owner (2026-11-12), shown the sheet of the three candidates: "B". Not chosen: A (an easel), C (a canvas and a brush); the architect had recommended A.
- The architect's note, not the owner's words: at menu size the crop marks and the anchors stay readable and the icon does not read as the crop tool's; thin the marks before touching the curve.

## Does the dashboard move to look A? (owner, 2026-11-12, #8938)
- Not for now: the dashboard keeps its own drawn look (`ui/dashboard-v2-drawn-look.md`, `ui/dashboard-v2-looks.md`). No step of the design language re-dresses it; DL5a is not given.
- Owner (2026-11-12), shown the dashboard's cards drawn in look A and asked among three: "pano ekranı şimdilik kalsın istiyorum." Not chosen now: look A as the default with the personal card styles kept; the dashboard wholly in look A.
- The architect's reading: the question returns only when the owner raises it; nobody proposes the re-dressing before.
