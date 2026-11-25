---
name: designer-my-library
description: Plan (owner 2026-11-24): a section saved to the site's own library, inserted as a copy in any design, exported and imported as a file; kept as files; steps ML1 to ML5
priority: 7
---

## What is asked?
- Owner (2026-11-24): the seven additions go in (`workflow/decisions-made-product-106.md`). This one: what a designer or an agency built once is a start for the next page and the next site.

## What is there today?
- The palette's Blocks list holds the ready sections that ship (`designer/designer-palette-tabs-and-blocks.md`); a whole design can become a template (`designer/design-templates-from-design.md`); a shared component is one thing in every place. A section of one's own cannot be kept as a start.

## What is built?
- "Save to my library" in an element's menu, for a section, row, card or group: a name, a group (typed, with the groups in use offered), and one line: "A copy you start from. To keep every place the same, make it a shared component."
- An item holds the subtree, the rules of the design's own stylesheet its classes use, the names of the variables it uses, and its pictures by reference as the template export carries them.
- The Blocks list gets "My library" as its first group: search, the hover card drawn from the item's tree (`designer/palette-block-sketch.md`), drag or double-click to insert a copy that is the page's own from then on.
- In another design: a class rule that is missing is added; a rule of that name that differs stays, the item's rule gets a new name and one line says so. A variable the design has gives the design's value, so the copy takes that design's look; a missing one is added with the item's value.
- A widget bound to a form, collection or group the design's site lacks is inserted with the binding cleared and marked, as the import marks one.
- "Manage library": rename, regroup, order, delete; "Export" writes the chosen items into one file, "Import" reads such a file from another site.
- The library is the site's: roles that may add elements see and use it; saving, managing, export and import are the designer roles'.

## What must it keep?
- An imported file passes the checks the HTML and ZIP import runs (`designer/designer-import-validation-theme.md`): no script, no event attribute, no address outside the allowed kinds; a file that fails is refused with the reason.
- An item never carries a record's data, a member's content, a key or a connection: bindings travel as settings only.
- Kept as files, one per item, the way a design's theme is a file: no table or column. A step that needs one stops and asks the architect.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- ML1 (M) The item, pure: a subtree to an item and an item into a design (rule names, variables, cleared bindings); the store as files; rights. Done (#11521, `designer/designer-library-item.md`).
- ML2 (M) after ML1 and DD9: "Save to my library" and the "My library" group with search and the hover card.
- ML3 (M) after ML2: "Manage library".
- ML4 (M) after ML1: Export and Import with the import's checks. Done (#11580, `designer/designer-library-export-import.md`, `designer/designer-library-import-checks.md`); the Export button is the foot of "Manage library" (ML3), calling `exportItems(ids)` of the part `libraryfile`.
- ML5 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- The drawing: `designer-drawings.md`. Claim as `[plan:designer-my-library] <step> — <topic>`.
