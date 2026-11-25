---
name: designer-options-curve-drawing-pages
description: "Page transition" and "Keep across pages" as drawn for DO20 (options-curve.html, boards P1 to P3): the section in Settings, Theme, its rows, what is written and where, the name that joins two elements
---

## Where is "Page transition"?
- Settings, Theme, tab "Look and colours": a section under "Colour mode" in that section's form, a title, one line ("How one page of the design gives way to the next. A visitor who asks for less motion gets none.") and a group of buttons None, Fade, Slide. None is the start, with "None: a page opens as before."
- Fade: the old page fades out as the new one fades in. Slide: the new page comes in from one side while the old one leaves the other way; "From" lists The right, The left, Below.
- Both have "Duration" (a slider with its number in ms, up to 1,000) and "Easing" (the row of DO19, curve button and all). Until changed they follow the look and are drawn in grey, as Transition's rows.
- A small figure of two pages shows the start, half way and the end; "Play" plays the transition in the figure.

## What is written, and where?
- `@view-transition { navigation: auto; }` and the two rules of the old and the new page, inside `@media (prefers-reduced-motion: no-preference)`, in the design's variables file (`design-variables-tab.md`): no column. A design never given a transition is byte for byte what it was.
- A browser without page transitions opens the page as before; no script is added.
- It plays on the site and in the Preview when a link leads to another page of the same design. The canvas never plays it.

## How is an element kept across pages?
- Advanced tab, the Interactions card: a sub-head "Between pages" after the effect's rows, with the switch "Keep across pages".
- Switched on, the element gets a name made from its kind and its section ("hero-picture") in a row "Name"; under it: "An element with the same name on the page that opens takes this one's place in one move."
- "Name" lists the names the design has, each with the pages that hold it ("page-title", "Classes, About"), then "A new name…" (small letters, digits and hyphens). The element given the same name on another page is its partner.
- It writes `view-transition-name` on the element's own class in User Styles; the class is added the first time, as Transition does. An element of the header or the footer is on every page, so its switch alone is enough.

## Where is nothing kept, and what is said?
- The same name twice on one page: a warn line on both, "Two elements on this page are named “hero-picture”. A name is used once a page, so neither is kept."
- "Page transition" on None: an info line, "Nothing is kept until a “Page transition” is chosen in Settings, Theme."
- An element repeated in a list: the switch is off and dimmed, "An element repeated in a list cannot be kept across pages."
- A kept element whose name the next page does not have changes with its page; nothing is said.

## What do the canvas and the Preview show?
- The canvas draws a kept element as any other; its selection tag carries the link mark and the name, so a partner is found by reading.
- The Preview, as the site, changes the pages by the design's transition and moves and resizes a kept element from its old place to its new one.

Related: docs/memory/designer/designer-options-curve-drawing.md, docs/memory/designer/design-colour-mode.md, docs/memory/designer/design-variables-tab.md
