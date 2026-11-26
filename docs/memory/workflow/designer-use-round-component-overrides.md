---
name: designer-use-round-component-overrides
description: Use round UR5 (PR #11853): a shared component's fields per place and its variants used in a browser; the eight faults fixed, what was left and what could not be run
---

## What was used, and how?
- A `setup_sandbox.sh` instance, Chromium through Playwright: the template "Say hello to Pinegrap" (37 pages) and its call-to-action component, placed on three pages. 1440 px and 390 px (the Options panel as the drawer), the OS theme light and dark, the keyboard alone through the command palette (Ctrl+Shift+P).
- Used: "Can change per place" from the three-dot menu and the palette, Label, "Got it", "This place" (a text typed in the row and on the canvas), "Edit the component", the read-only line, the settings list (Close the field, Undo, F2), "Add a variant", "Done", the place's "Variant" row, a variant deleted and its amber line; Save and the stored rows.

## Which faults were fixed? (#11853)
- Escape while a variant was edited and a row's three-dot menu was open ended the editing and left the menu: the guard looked for `.sd-ctx-menu`, but the editor's menu is `#sd-tree-ctx-menu`. Escape now closes that menu only (`tests/component_overrides_variants_test.php`).
- The fields' list: Enter in "Label" dropped the focus on the page, after which Escape could not close the list. Enter now keeps the label and goes back to the field's switch; Escape gives the focus back to what opened the list (the element's three-dot button when that was redrawn).
- A field row's menu (Rename, Close the field) is reached by the pointer only: F2 renames, Delete closes the field, as in the variants' list.
- After "Delete" of a variant or "Close the field" the focus was on the page; it is now on the Result line's Undo, and after Undo on the row put back. An earlier queued drawing of a detached box used the flag up first: it now waits for the box in the page.
- Choosing or adding a variant left the focus on the page (its list goes with the selection): it moves to "Done" over the canvas.
- "This place"'s text box was the code fields' monospace at 11.2 px: the panel's type at 12 px.
- Under a light OS theme the list's Label field, "Got it" and the variant line's "Done" were white Bootstrap controls; the Result line's words were the panel's light-theme grey, faded on the dark card. All on the chrome's tokens.
- Sizes off `designer/designer-chrome-measures.md`: heads, hints and counts at 10.6 to 11.2 px (now 11.5), rows at 11.5 (now 12), the three-dot button an 18 px target (now 24), the list's corner 6 (now 12).

## What was left, and why?
- At 390 px the variant's line over the canvas is under the open Options drawer: whether choosing a variant should close the drawer is a behaviour choice for the architect.
- Turkish "Her yerde değişebilir" reads "can change everywhere", not "per place"; rewording an accepted label is the architect's.
- A heading's open Text has no Options row (its text is typed on the canvas), so its only mark is Overview's.
- The editor's context menu (`showCtxMenu()`) has no keyboard and no Escape anywhere in the editor; outside this feature.
- The top bar's "Suggested groups" words run over "Click" at 1440 px; another feature.

## What could not be run?
- A content editor's panel (no content-level user on the sandbox), an empty design, and a class changed inside a variant with its amber guard line; Preview and the published site (the template's pages stayed drafts).

Related: docs/memory/designer/component-overrides-fields.md, docs/memory/designer/component-overrides-variants.md
