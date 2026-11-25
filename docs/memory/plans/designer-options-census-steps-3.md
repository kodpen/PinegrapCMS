---
name: designer-options-census-steps-3
description: Plan designer-options-census, DO21 to DO27 and sheets DD22 to DD26: back to top, scroll progress, scrollspy, scrolled navbar, a collection with its pages, favourites, find and replace, Markdown
priority: 7
---

## What holds for these steps?
- The frame of `designer-options-census.md`. What Bootstrap Studio has and ours lacks: `designer/options-panel-census-bss.md`. "Check for issues" is `designer-publish-check.md` and a designer's own options on a component are `designer-component-overrides.md`: neither is built here.

## Which steps add page components and the rest? (line "page and site")
- DO21 (S) Two palette components: "Back to top", a button shown once the page is scrolled, and "Scroll progress", a thin bar at the page's top. Their site script is printed only on a page that holds one.
- DO22 (S) after DO21: on a menu, "Mark the section in view" for a one-page menu (Bootstrap's scrollspy, `data-bs-spy`) and "After scrolling": a distance and what changes (background, shadow, height), the navbar's scrolled state.
- DO23 (M) after DO22 and DD26: "New collection with pages", a row of the Pages window and of the command palette: it asks the collection's name and fields, or takes a collection that is there, and makes the collection, its list page and its detail page with the form list and detail widgets bound.
  - By `designer/collections-decisions.md`; it ends with a line naming what it made, each with its link. Nothing is made until "Create".
- DO24 (S) after DO23: favourites: a star on a palette component pins it under "Favourites" above "Recently Used", kept per person where that list is kept.
- DO25 (M) after DO24: "Find and Replace" beyond the open page: a scope (This page, All pages, Code files), the list of what would change per page before "Replace All", one undo step a page; a page another editor has open is left out and named.
  - Not in records: a record's text is content, changed where records are edited.
- DO26 (M) after DO25: a "Markdown" palette component: its text is written as Markdown and drawn as HTML by the reader the element notes use, one pure function on the site and its browser twin; no raw HTML passes. What a page needs and that reader lacks (tables, pictures) is added to it.
- DO27 (S) after DO6, DO14, DO17, DO20 and DO26: close-out: the lasting rules into `designer/`, the census files corrected to what was built, the plan's four files deleted, the `[NEW]` line named.

## Which sheets are drawn first, and by whom?
- By the architect, who is the designer too since 2026-11-25 (`workflow/design-work-session.md`), by the rules of `designer-drawings.md`: a sheet with a light and a dark picture in a folder under `docs/memory/designer/`.
- DD22, folder `designer-options-states-drawing`: the State row, a section's dot, "Reset this state", the dimmed sections, Transition, Transform. Holds DO10. Drawn (#11573).
- DD23, `designer-options-grid-drawing`: Columns and Rows, a child's rows, the areas grid. Holds DO6.
- DD24, `designer-options-layers-drawing`: the layer list with a gradient's stops, Clip shape and Mask. Holds DO13.
- DD25, `designer-options-curve-drawing`: the curve editor, "Page transition". Holds DO19.
- DD26, `designer-options-collection-drawing`: "New collection with pages" and its closing line. Holds DO23.
- DD22 comes with the first sheets of the other plans, since the longest line waits on it; the others after the sheets of `designer-drawings.md` and `designer-drawings-levels.md`.
