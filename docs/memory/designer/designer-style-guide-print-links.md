---
name: designer-style-guide-print-links
description: The Style guide's names as links to a variable or component and "Print or save as PDF" (SG3, #11678): openVariable, openComponent, reveal, the @page head and foot
---

## Where does a name lead?
- With variables (`has_variables`), a colour, font, text style with a `size_var`, spacing or shape name is `a.pgsg-link[data-sg-var]`, tip "Open in Variables". Without variables, or a style with no size variable, the name is plain. Shape is linked too: its rows are variables.
- A click (or Enter) closes the sheet without giving focus back to the Preview button and calls the host's `openVariable(key)`: Settings opens on Theme, Variables, and the part `variables` answers `reveal(host, key)`: the fold opened, the row (`[data-var-row]`) marked `.is-marked`, scrolled to, its field focused.
- A component's name (`data-sg-comp-open`, tip "Open the component") calls `openComponent(id)`: `edit_system_style.php?component=<id>&id=<style>` in a new window (`shared-component-opened-alone.md`), so the design's unsaved changes stay. The palette's Shared tab has no "open" (its double-click places the component); this is the editor's one way to edit a component alone.
- The sheet opens to full access only, so every opener gets links; the server checks the component's right again.

## How is it printed?
- "Print or save as PDF" in the head ("Print" under 768 px), off until the frame is shown. A press: "Getting it ready…", every component card still grey drawn (trees fetched), pictures loaded, 6 s at most; then the frame's own `print()`, so no head or strip is printed.
- `@page` A4 upright with margin boxes: the design's name, "Style guide" and the day at the top; the site's host and "1 of 3" (`{var:1} of {var:2}` around `counter(page)` / `counter(pages)`) at the foot. A browser without margin boxes (Firefox) prints the sheet's own head on page 1 only and no page count.
- Print rules: colours exact (`print-color-adjust`) in the mode the sheet shows; cut components printed whole without "Show all of it"; cards, rows, pairs and control rows `break-inside:avoid`, headings `break-after:avoid`; underlines left out. The phone's one-column rules are `screen` only, so paper keeps the sheet's grid.
- "A block starts on a new page only when less than a third of it fits" is approximated by those break rules: the page height on paper is not known to the script.

Related: docs/memory/designer/designer-style-guide-sheet.md, docs/memory/designer/designer-style-guide-blocks-drawing.md
