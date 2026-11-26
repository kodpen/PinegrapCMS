---
name: options-panel-census-today
description: Options panel census, part 1: every Options panel control on 2026-11-24 per element kind and Style/Advanced section, and which rows take a per screen size value
---

## What is this file?
- The panel as counted on 2026-11-24, before the census rows. The rows and components DO2 to DO26 added since, each with its file: `options-panel-census.md`; the per size list below is kept current.

## What does every element get in Style and Advanced?
- `rowCss()` builds them for every kind; the Text section only for headings, paragraphs, spans and text tags (`propsTextOptions()`).
- Text: Alignment (per size), Color, Color Opacity, Text Bg, Case, Font Size, Font Weight, Font Style, Line Height, Decoration, switches Reset Color, Monospace, No Wrap, Truncate, Text Break.
- Spacing: the box (`designer-spacing-box.md`) for margin and padding on each side, Bootstrap steps 0 to 5 and auto, per size.
- Color and background: Text Color, Text Opacity, Background Color (theme colours), Bg Opacity, Gradient (the `bg-gradient` switch), Custom Background Color (Solid, Linear or Radial with two colours, angle or shape and position, a middle), Bg Image, Bg Size, Bg Pos, Bg Repeat, Bg Attach, Opacity (0, 25, 50, 75, 100).
- Border and corner: Border sides, Color, Width 1 to 5, Radius and per corner side (Bootstrap steps, circle, pill).
- Size and display: Display (per size), Position (static to sticky, with Top, Right, Bottom, Left lengths), Float (per size), Z Index (a fixed list), Width, Height, each with Min and Max lengths, Overflow with X and Y, Aspect Ratio (1:1, 4:3, 16:9, 21:9, custom).
- Shadow and effect: Shadow (none, sm, default, lg), Placeholder with Animation (glow, wave), Focus Ring.
- Advanced › Layout (by context, `designer-options-layout.md`): Flex Container, Direction, Wrap, Justify, Align Items, Align Content, Gap; Align Self, Order, Grow, Shrink, Fill; all per size.
- Advanced: Show by the visit, Accessibility (Screen Reader Only, Role, Aria Label, Labelledby, Describedby), Tooltip (on, Placement, Text), Search and SERP, Interactions (`designer-interactions-panel.md`: Fade in, Slide up, down, left, right, Scale on load, view, hover or click, Sticky with Top offset, Parallax, Zoom, Fade and Rise on scroll, Count up, Colour chapter, Pinned scene; Duration, Delay, Easing, Distance, Stagger, Every time).

## What does each kind add in Content?
- Container: Container or Container Fluid. Row: Gutter, Justify, Align, Quick Layouts. Column: Width (per size, 1 to 12 or auto), Offset, Offset MD, Column order.
- Picture: Object Fit (contain, cover, fill, scale-down, none), Object Position, Loading attribute, the picture's own fields.
- Heading, paragraph, span: the Text section above. Link, button, form field: element type, link, variant, size and the field's rows (`propsButtonLinkOptions()`, `propsFormInputOptions()`).
- Semantic tags and components (card, carousel, modal, offcanvas, dropdown, alert, progress, tabs, navbar, table, video, scroller, place map, variant picker, popover): their own section of Bootstrap choices; a table, alert or modal ancestor's section shows above.
- System widgets, loop and list areas, shared components: their data and display options, no look rows of their own beyond the common Style tab.

## Which rows take a per screen size value today?
- Display, Float, every Layout row, Gap, Spacing, text Alignment, Column width; Offset only at MD. Since DO2: Width, Height, their Min and Max, Top, Right, Bottom, Left and Font Size (`designer-size-per-screen.md`). Since DO4 to DO6 and DO9: grid Columns, Rows, a child's span, start and area, and Aspect Ratio. Everything else holds one value for all sizes.
