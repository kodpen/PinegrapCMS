---
name: options-panel-census-bss
description: Options panel census, part 4: what Bootstrap Studio has that the Page Design Studio lacks, each as have, partly or missing, with where ours lives (view transitions to collection pages)
---

## Why this list?
- Asked by the architect on 2026-11-24 (agent channel message 13097): the owner named Bootstrap Studio as the designer's model and asked what it has that ours lacks. Counted and named only; nothing is built or removed by it.

## Which does the Page Design Studio have?
- A note on an element for the team: have. The N key or the chat icon, kept in `props._notes` with who and when, listed in the Notes tab, never in the page's HTML, synced to the other editor (`designer-collab-notes-sync.md`).
- Computed styles of the selected element: have. The Styles panel's computed list with its filter (`sd-computed-find`).
- Several elements selected and changed at once: have. One undo step per change (`designer-options-multiple.md`).
- "Find the other copies" of a shared component: have. The component's "Used on:" pages (`_sharedUsageTip()`), "Not used on any page".

## Which does it have in part?
- Favourite components in the palette: partly. "Recently Used" at the palette's top keeps the last eight. Missing: pinning a component as a favourite.
- One "Check for issues" window: partly. Audit (`designer-audit-*.md`) checks accessibility (contrast, names, alt, targets, form submit, headings) and HTML; the canvas marks broken links (`canvas-link-marks.md`); the SEO score lives in Page Settings. Missing: one window over the whole design with missing CSS classes and inline styles to move into classes.
- Find and replace: partly. Ctrl+F's "Find and Replace" (case, whole word, regex, Replace All) changes the texts of the open page's tree. Missing: other pages, code files (the code view's search finds and does not replace, `code-view-tab-search.md`) and collection data.
- An easing curve tool: partly. Interactions offers Easing as a list of stored curves. Missing: a curve drawn and dragged (`cubic-bezier`); the Transition row of `options-panel-census-look.md` would use it.
- A designer's own options on a component: partly. A shared component opens one editable block to content editors (`common-region-to-shared-component.md`) and palette components have fixed sections. Missing: a toggle or a list a designer defines on a component that sets a class or an attribute.
- A collection with its list and detail pages in one go: partly. A designed form switched to "Use as a collection" holds the records (`collections-decisions.md`); its list and detail pages are built by hand with the form list and detail widgets. Missing: one action that makes the collection, the list page and the detail page.

## Which are missing?
- View transitions between pages: missing; nothing writes `@view-transition` or `view-transition-name`.
- A scroll-to-top button and a scroll progress bar: missing as components; a designer places a link to `#top` by hand.
- Scrollspy on a one-page menu: missing; no row writes `data-bs-spy` on the body or a menu.
- A navbar's "scrolled" state after a threshold: missing; Sticky and `fixed-top` keep one look. Interactions' Colour chapter changes the page's colours by section, not the navbar's.
- A Markdown component: missing; Markdown is read only in element notes.
