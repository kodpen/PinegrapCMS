---
name: bug-hunt-areas-designer-states
description: The bug hunt in the designer's states, interactions, collection window and Google Fonts browser (agent channel 15408, 2026-11-28): what was fixed, what was read clean, what is left
---

## Which area was it?
- Third area of `bug-hunt-areas-7.md`: `assets/js/style_designer_states.js` (the Options panel under Hover, Focus, Pressed), `_interactions.js` (the Interactions section), `_collection.js` ("New collection with pages") and `_fonts.js` (the Google Fonts browser).

## What was fixed?
- "Done" on the collection window's closing line, Escape there and every name link did nothing: `_ncBack()` started the window afresh with a state that had no view and hiding it threw, so the made page never opened. The fresh state now keeps the view.
- The Google Fonts preview asked for weights 100 to 900 in one request, which Google refuses whole for a family missing one, so most display and script families previewed in sans-serif. The regular face is linked on its own too (`sd-gf-preview-plain`).
- The font list could not be used without a mouse: each row is now `role="button"` with `tabindex="0"`, opened by Enter or the space bar, with the editor's focus ring.
- The Google Fonts browser is a modal dialog named by its title, and closing it focuses what opened it (before, the focus stayed on the hidden dialog).
- A name with a dollar sign ("Rates $$", "$&") was changed by `String.replace()` in the collection window's sentences and in the title of the collection page it saves. Names go in through `_ncLit()`, a function's answer, taken as text.
- Tests: `tests/collection_pages_test.php` (`done_turns_back`, `dollar_names_kept`), `tests/designer_parts_test.php` (the `fonts_part` cases).

## What was read and found clean?
- States: under a state only the Color, Border and Shadow sections write (`WRITE`), Text, Spacing and Size stay dimmed and closed, Transition keeps one value; a change is caught in the capture phase and written to the state's rule; the canvas view is a style element only, removed on Normal; the Transform ghost restores the element's inline transform after measuring.
- Interactions: `_sdFxSet()` drops the start, the distance and a count's duration where they no longer apply; rows follow `_sdFxNormalize()` (a count and a chapter have no trigger, so their Duration row is drawn once); a typed number is one undo step per focus and redraws the panel on change; Play loads `pg_fx` first when the canvas lacks it.
- Collection: names, slugs and field keys (`_ncSlug`, `_ncFree`, `_ncKey`), step 1 refusals, the address checks against the design and the server's `taken` answer, the making order and "Try again" making only what is missing.
- Fonts: Update keeps a turned-off font off and in place; the catalogue is the shipped list, so names are not escaped.

## What is left?
- A Google font saved with a weight the family lacks loses its whole stylesheet on the site, the canvas and Preview: the browser offers 100 to 900 for every family and the links ask for the ticked weights in one request. Needs the weights each family has, or a fallback link on the site: a `bug` issue.
- In the collection window, pressing a field's bin or kind button while another, empty line holds the focus removes the empty line and redraws before the click ends, so the click is lost and must be repeated.
- A failed collection list reads "The site has no collection yet." in "One that is there".

Related: docs/memory/workflow/bug-hunt-areas-7.md, docs/memory/designer/designer-fonts-part.md, docs/memory/designer/collection-pages.md
