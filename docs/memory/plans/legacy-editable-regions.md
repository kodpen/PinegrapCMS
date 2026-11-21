---
name: legacy-editable-regions
description: Plan legacy-editable, regions: one door through the move screen, the run's new page_parts step, Login, Cart and Comments in the run, the true canvas of every region left, designs moved earlier
---

## How does a classic style reach the designer from now on?
- One door: "Open in the visual designer" on All Page Styles lands on Move to the Page Design Studio with that style, or the set it belongs to, ticked and its dry run drawn. Convert runs the move; when it ends "Open the design" opens the editor.
- The open that wrote nothing before Save (`?classic=1`) is no longer a button; `pg_classic_style_design_open()` stays as the run's own function.
- Why: two doors gave two results; the one most pressed converted nothing.

## What does the run convert that it left before?
- A new step `page_parts` after the pages are saved, for each moved page's own tree: a designer region node becomes its component (`pg_designer_region_component()`) placed as a `shared_ref`; a common region node the `shared_ref` of its component; a Menu, Menu Sequence, Ad or Tag Cloud node its widget, as the `frame` step does.
- The same walk enters the trees of the components the run made (a common or designer region's placeholders stayed region nodes): one level a pass, an ancestor's name never (no loop), as `pg_di_designer_region_tree()` does.
- Login, Cart and Comments Block nodes join the kinds the `frame` and `page_parts` steps make widgets of, by server twins of the editor's "Make designable" (CB10); twin and editor change together.
- A designer region with PHP: its blocks are Custom PHP elements as today; one that cannot be cut is one Custom PHP element. The dry run counts both under "stays code".
- A Dynamic region, Mobile Switch and PDF stay region nodes.
- Every converter returns the row it made before: a resume or a second run is safe.

## What does the dry run say?
- Three groups for each style or set: "Becomes editable" (page content, common regions, designer regions, menus and widgets, page types that get a widget), "Stays code, drawn as on the site" (each with its reason), "Skipped" (held pages, refused styles).
- Before Convert, one line a common region: it is edited in the designer from then on, on classic pages too.

## What does the canvas draw for a region that stays?
- Not a label box: a band (the kind's icon, its name, a pill "Code" or "Classic") and under it the region as the server draws it for the signed-in user, read only.
- `legacy_region_preview` answers every region node of the page, not only the page's own System region: while marking is on, `get_page_content.php` wraps each region pass's output in markers carrying the type, the name and its order; the cut, the background render and the cleaning (no script, frame, id, `on*`, `javascript:`) stay as they are.
- Asked once a page, again after a Save; a node not saved yet keeps the band alone. For everyone who may open the page in the designer; drawn as the caller.
- The Region panel says in one line why it stays and shows the one action: "Edit code" (Dynamic region, administrators), "Edit layout (PHP)" or "Convert to design elements" (System region), nothing for Mobile Switch and PDF.

## What about designs moved earlier?
- A card "Designs moved earlier" on the move screen lists each Legacy design with its count of fully editable pages; its dry run and run work the `page_parts` and page type steps on the design's pages, with no style change.
- A design moved before the frame split holds its page regions inside one component, where they cannot go into the pages: the card says "Send back to classic, then move again" with the Back to classic link.

## How is "fully editable" counted?
- `pg_classic_closed_census($tree)` (pure): the region nodes left in a page's tree and in the components it places, by kind. A page with none is fully editable. The move screen, the card and the run's report read it.

Related: docs/memory/plans/legacy-editable.md, docs/memory/designer/legacy-frame-split.md, docs/memory/designer/region-node-to-widget.md
