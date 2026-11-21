---
name: legacy-editable-screens
description: Plan legacy-editable, its screens in words: the move screen's dry run in three groups, the "Designs moved earlier" card, a part that stays classic on the canvas, the Region panel's dropped pieces
---

## Which screens, and by which rules?
- Move to the Page Design Studio (`legacy_to_designer.php`), the canvas and the Region panel of the visual designer. Panel parts are copied from the Design examples screen, the section in brackets (`ui/screen-composition-rules.md`, `ui/screen-composition-nesting.md`); the editor's own parts keep the editor's look.

## How is the move screen laid out?
- A list screen: the purpose line, the measure as four figures [cards: figure]: pages fully editable, pages with a classic part, styles still classic, designs moved. Under it the styles table as today, a set a group.
- Arriving from "Open in the visual designer": the style or its set is ticked and its dry run is drawn; the screen scrolls to it.
- The dry run card: one block a style or set with three group labels and list rows [rows] under each. "Becomes editable": a row a kind (page content, common regions, designer regions, menus and widgets, page types) with its count and the names behind "Show all N". "Stays code, drawn as on the site": a row a thing with its reason in one line. "Skipped": held pages and refused styles with the reason.
- Above the card's foot, when a common region converts: one note in help size, "From now on these are edited in the designer, on classic pages too."
- Foot: "Convert" filled, "Cancel" quiet. While a run works: the progress card as today. When it ends: "Open the design" filled, "Report" quiet.
- Empty: no classic style left: one line and the "Designs moved earlier" card.

## How is "Designs moved earlier" laid out?
- A card of list rows [rows]: the design's name, one line "132 of 146 pages fully editable", a pill (teal "All editable", amber "N pages left"), "Dry run" quiet small at the row's end, "Back to classic" in the row's menu [menus].
- A design moved before the frame split: the line says its page content sits in one shared part, and the row's one action is "Send back to classic, then move again" (quiet).
- "Dry run" opens a drawer [form drawer]: the same three groups for that design's pages; foot "Convert" filled, "Close" quiet.

## How does a part that stays classic look on the canvas?
- A band on top: the kind's icon, the kind and the name ("Dynamic region · latest-news"), a pill: "Code" for PHP, "Classic" for the rest. Under the band the part as the site draws it, dimmed by nothing, not selectable inside; a click anywhere selects the part.
- Not asked yet or nothing drawn: the band alone with one muted line ("Drawn on the site; nothing to show here" or "Save the page to see it").

## What does its Region panel show?
- One line of why: "PHP code: it stays code and runs on the site as written", "This page type has no design element", "Its layout has pieces that have no design element".
- One action: "Edit code" (Dynamic region), "Edit layout (PHP)", or "Convert to design elements".
- A System region whose layout drops pieces: under the line, list rows of the pieces, each "line 42 · a condition on $watcher_option · dropped", five at most then "Show all N"; then "Convert to design elements" filled and "Edit layout (PHP)" quiet. The confirm names how many pieces go and that undo brings the region back.
- After a conversion the toast names the widget and says what was dropped, as the custom form's does.

## Which words?
- "Fully editable", "Stays code", "Classic", "Convert to design elements", "Send back to classic". Never "region node", "tree", "legacy" in Turkish labels (`designer/editor-turkish-terms.md`).

Related: docs/memory/plans/legacy-editable.md, docs/memory/plans/legacy-editable-regions.md, docs/memory/plans/legacy-editable-layouts.md
