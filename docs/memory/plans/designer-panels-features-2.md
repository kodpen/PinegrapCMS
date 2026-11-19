---
name: designer-panels-features-2
description: Plan designer-panels, features D16 to D28: the spacing box and the size chooser, corners, layout by context, the search that also adds, the review view, copy and paste style
---

## How is spacing set? (`options-2-style-advanced.jpg`, `measures-3-controls.jpg`)
- (D16) One box drawing in Style › Spacing: outer (margin) and inner (padding), four sides each. A number is clicked and typed (0 to 5 or "auto"), or dragged; Shift changes the opposite side too, Alt all four. Grey is an inherited or default value, filled blue is set here, a frame is the one being typed.
- (D17) The screen-size chooser XS, SM, MD, LG, XL in the section's head: the section's rows then write that size's classes; a size that holds a value is drawn lighter. It writes the classes written today (`mt-*`, `mb-*`, `ms-*`, `me-*`, `pt-*` and their size forms), nothing else.
- (D18) Corners the same way in Style › Border and corner: the four corners on one drawing, today's radius classes.

## What does Layout show? (Advanced › Layout)
- (D19) It reads the context. The element is a flex container: direction, wrap, alignment, gap. Its parent is flex: its own alignment, order, grow, under a tag "parent: flex". Neither: only "Make it a container".
- (D20) A note names the rows that are hidden and why ("This element is not a flex container; direction, wrap and gap are hidden").

## How does the search work? (`options-3-search-review.jpg`)
- (D21) One box, "Search or add an option…". `/` focuses it; Esc clears it, a second Esc leaves it. Results open 120 ms after typing.
- (D22) It looks in all three tabs at the row label, the section name, the CSS property and the class prefix ("margin" finds Outer spacing, `mt-*`, `mb-*`), with Turkish letters folded (ı and i, ş and s, ğ and g, ö, ü, ç).
- (D23) A result shows its path ("Style › Shadow and effect"); Up and Down move, Enter switches the tab, opens the section and puts the focus in the control; a section opened this way is marked "just opened".
- (D24) No result says so (the line of PR #10055, its wording kept) and offers under it to add the typed word as a class ("+ Add the class "glow"").

## How is what was changed reviewed?
- (D25) A switch "Only what I changed" with the count, always at the panel's bottom. On, it puts one list in the tabs' place: every changed row with its path (tab › section), its value and a reset arrow.
- (D26) Under that list: "Copy style" and "Reset all" (one undo step, asked first).

## What is copy and paste style?
- (D27) "Copy style" on the identity row's brush and on the selected element's canvas toolbar takes the element's changed Style rows (classes and inline styles), never its content, link or data binding. "Paste style" writes them onto the selection as one undo step; a row the target kind does not have is left out and the toast says how many were left out.
- (D28) The copied style is kept in the browser, so it can be pasted on another page of the design; it is not sent to the server.
