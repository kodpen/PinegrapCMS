---
name: options-panel-census-bss
description: Options panel census, part 4, as built: what Bootstrap Studio has beside the Page Design Studio, as have or partly, with where ours lives (DO19 to DO26)
---

## Why this list?
- Asked by the architect on 2026-11-24 (agent channel message 13097): the owner named Bootstrap Studio as the designer's model and asked what it has that ours lacks. The census plan built every "missing" of it (DO19 to DO26); two stay partly, with no step.

## Which does the Page Design Studio have?
- A note on an element for the team: the N key or the chat icon, kept in `props._notes`, listed in the Notes tab, never in the page's HTML (`designer-collab-notes-sync.md`).
- Computed styles: the Styles panel's computed list with its filter (`sd-computed-find`). Several elements changed at once in one undo step (`designer-options-multiple.md`). A shared component's "Used on:" pages (`_sharedUsageTip()`).
- Favourites: a star on a palette row pins it under "Favourites" above "Recently Used", per browser (DO24, `palette-favourites.md`).
- Find and replace: Ctrl+F's "Find and Replace" over This page, All pages or Code files, the list per page before Replace All, one undo step a page; not in collection records, whose text is content (DO25, `designer-find-replace-scopes.md`).
- An easing curve drawn and dragged wherever Easing is chosen, stored under a name (DO19, `designer-options-curve.md`).
- A collection with its list and detail pages in one go: "New collection with pages" in the Pages window and the command palette (DO23, `collection-pages.md`).
- View transitions: "Page transition" (None, Fade, Slide) as `@view-transition`, "Keep across pages" as `view-transition-name` (DO20, `designer-options-page-transition.md`).
- "Back to top" and "Scroll progress" palette components (DO21, `page-scroll-components.md`).
- A menu's "Mark the section in view" (scrollspy, `data-bs-spy`) and "After scrolling", the navbar's scrolled state (DO22, `menu-scroll-rows.md`).
- A "Markdown" palette component, drawn by the reader the element notes use, no raw HTML (DO26, `markdown-component.md`).

## Which does it have only in part?
- One "Check for issues" window: Audit (`designer-audit-*.md`) checks accessibility and HTML, the canvas marks broken links (`canvas-link-marks.md`), the SEO score is in Page Settings, the Publish check runs before publishing (`designer-publish-overview.md`). Missing: one window over the whole design with missing CSS classes and inline styles to move into classes.
- A designer's own options on a component: a shared component opens one editable block to content editors (`common-region-to-shared-component.md`), palette components have fixed sections. Missing: a toggle or list a designer defines that sets a class or attribute, the subject of its own plan (component overrides).
