---
name: designer-panels-measures-3
description: Plan designer-panels, the measure sheet as text, part 3: where each of today's Options sections goes in the three tabs, and the measures of the Overview rows, the page strip and the canvas bar
---

## Where does each of today's sections go? (`measures-4-sections-rules.jpg`)
- The element's own section: Content › the element's name, open.
- Link, Bind data: Content, closed (summary: the target, "not bound"). Translation: Content, closed.
- Quick Layouts (on a row): Content › Row, open.
- Text options (15 rows): Style › Text, open on a text element.
- Spacing (10): Style › Spacing, the box drawing, open.
- Colours (13): Style › Colour and background, closed.
- Border (8): Style › Border and corner, closed.
- Display and size (8): Style › Size and display, closed.
- Formatting (3) and shadow: Style › Shadow and effect, closed.
- Flexbox (12): Advanced › Layout, by context (D19).
- Show by visit: Advanced, open when a condition is set.
- Accessibility (5), Tooltip (3): Advanced, closed.
- Search and SERP, Interactions: Advanced, closed.
- A section an element kind adds that is not named here goes where its nearest neighbour above goes, and the step's PR body lists it.

## Which measures has the Overview?
- Row 28 px (22 today); indent 12 px; at most six indents, then a depth badge; the label at least 150 px.
- A section row: 28 px, 12.5 px at weight 640, ground `#1f242c`, border `#2a313b`, 4 px above it.
- Guide lines 1 px `rgba(255,255,255,.07)`.
- Drop: the gap target reaches 7 px each way, drawn as a 2 px `#5b9bff` line with an 8 px dot; the middle 14 px of a row is "inside". One level is 12 px of pointer travel.
- Long drag: a branch opens after 600 ms; the scroll zones are the top and bottom 48 px, 6 to 24 px a frame.

## Which measures has the page strip?
- A tab 30 px, corner 8, 12.5 px. The open group's box carries the group's colour and name; a folded group is a chip with name and count.
- The state marks: home icon, a blue dot for changed, a "draft" pill, a lock with the name in the tip.

## Which measures has the canvas bar?
- Bar 44 px; a control 28 px; 6 px between controls of a group; a 1 px line and 12 px between groups.
- The screen sizes are one group of six segments of 28 px; the width text follows it.
- The drawing fits the bar into 930 px; narrower, the width text goes, then the sizes fold into one dropdown.
