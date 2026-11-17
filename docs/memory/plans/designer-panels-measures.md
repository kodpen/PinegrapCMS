---
name: designer-panels-measures
description: Plan designer-panels, the measure sheet as text, part 1: the chrome's colours with use and contrast, type, heights, corners, the focus ring and the changed dot (measures-1-tokens.jpg)
---

## Which colours, and for what?
- Grounds: panel `#171b21`; an open section card `#1f242c`; the active tab and a badge's ground `#262c36`; a field, a button group and the tab holder `#11151a`.
- Lines: border `#2a313b`; strong border `#343c48`.
- Text: `#e8ebf0` (14.5:1 on the panel); a row's label `#aab3bf` (7.4:1 on a section card, 8.2:1 on the panel); secondary text `#8c96a4` (5.8:1); `#5d6673` (2.97:1 on the panel, 2.68:1 on a section card, both under 3:1) only for an icon that says nothing by itself; never for text, and never for an icon that is the only sign of a state or an action: that one takes the secondary text colour `#8c96a4`.
- Accent `#5b9bff`; the selected segment and the primary button `#2563eb` with white text (5.2:1); a soft accent ground `rgba(91,155,255,.14)` and line `rgba(91,155,255,.45)`.
- States: screen-size badge `#f5b54a` on the soft ground `rgba(245,181,74,.14)`; positive `#3ecf8e`; error `#ff6b6b`.
- These go into the editor's `--sd-*` tokens in `style_designer.css` (DP1): today's values change and new tokens are added; no rule writes a hex of its own, and `tests/designer_chrome_contrast_test.php` checks every pair above.

## Which type?
- The identity row's name 14.5 px, weight 640. A section's head and a tab 12.5 px, 600 to 630. A row's label 12 px, 400. A field's value 12 px, 400. A segment 11.5 px, 600. A summary, a path, a hint 11.5 px, 400. A badge 10.5 px, 750. Line height 1.45.

## Which heights and gaps?
- Identity row 60 (padding 13 top, 14 sides, 11 bottom; icon box 36).
- Tab holder 40 (padding 4, a tab 30, 4 between tabs, 12 at the sides).
- Search 34. A section's head 36; sections 8 apart, 12 at the sides.
- A row at least 32; rows 6 apart; the label column 92, then a gap of 10.
- A field and a select 30. A button group 28 with segments of 24. A button 26. A badge 17 to 18. An icon button 28.
- Everything that is clicked has a target of at least 24 by 24.

## Which corners and shadows?
- The panel 14 (0 when it fills a column). A section card 12. The tab holder 11, a tab 8. A field, the search and a group 9 to 10 with a 1 px `#2a313b` border. A segment 7, a button 8. A badge 6, or 99 for a pill.
- The selected segment's shadow `0 3px 8px -3px rgba(37,99,235,.8)`.
- Focus: the accent as the border and a ring `0 0 0 3px rgba(91,155,255,.13)`.
- The changed dot: 7 px, `#5b9bff`, ring `0 0 0 3px rgba(91,155,255,.18)`.

## Which movement?
- A switch and a hover change in 120 ms; nothing moves under `prefers-reduced-motion`.
