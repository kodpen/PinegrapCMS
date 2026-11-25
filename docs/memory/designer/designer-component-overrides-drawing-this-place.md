---
name: designer-component-overrides-drawing-this-place
description: Boards C3 to C5 of the component overrides drawing (step CO4): the "This place" section, the dot and "Reset to the component", typing in place, "Edit the component", content editors, the narrow panel
---

## When is "This place" shown, and what does a row hold?
- Only for a selected placed component that has an open field: the first section of the Content tab, above "Shared Component". A component with none keeps today's panel.
- One row per open field, in the component's order, the label being the field's label. An empty field shows the component's value as its hint and draws it; typing a value makes it this place's own. A picture opens today's picture chooser, a link today's link field.
- An open "Shown" is a switch that shows what is drawn here ("Shown" or "Hidden"); the dot tells that this place differs from the component.
- The head's count badge holds the number of changed fields and is absent at none; closed, the head shows the first changed text as its summary.

## How is a changed field marked and reset?
- The 14 px place before the label holds the 7 px dot of a changed row; under the pointer or on focus it becomes the undo arrow with the tip "Reset to the component". One step of undo. Clearing the field does the same.
- A link or picture passes the check its own field passes; a refused value keeps the rose field and the field's own message, and is not kept.

## What is in the section's foot?
- The line "The rest is the same in every place." and the quiet small button "Edit the component", which starts today's editing of the component, for every place. The command palette has the row "Edit the component".
- The button is there only for a person who may edit this component: every designer, and a content editor when the component is opened to content editors (and granted, for a user).

## What does the canvas show?
- The place keeps the green outline and name tag of a shared component. While it is selected its open elements carry a thin dashed green outline; nothing is drawn when it is not selected, and nothing in the preview or on the site.
- A double click or Enter on an open text starts typing in place; the name tag reads "label · This place" ("Title · This place"). Escape leaves with what was there, a click outside keeps the text. An emptied text shows the component's again.
- A click on an open picture or button selects the place and puts the focus in its row under "This place".
- Another element of the component is selected read-only: the panel's top line says "Part of “Promo card”: the same in every place." with "Edit the component"; its rows are dimmed and its name tag reads "Text · Promo card".
- "Edit the component" is the inline editing of today: the notice "You are editing a shared component — saving affects every style that uses it." is shown once per component, the change reaches every place, "Save" sends the component first.

## What does a content editor get?
- On a page that is theirs: the same rows, dot and reset as a designer, and nothing else of the component. No "Variant" row.
- On a page they may not change: today's read-only panel, the reason on top in today's words ("System widgets and shared components are managed by designers."), the fields disabled, the dots shown but not reset.
- A place's own text enters the site translation as its own text; the panel shows no extra control for it.
- A value whose field was closed or whose element was removed has no row; the Publish check lists it.

## What changes under 300 px, and what does the keyboard do?
- Each row stacks: the dot and the label on one line, the field under it; the foot's button goes under its line.
- Tab walks dot, field, dot, field; Enter or Space on the dot resets the row; Escape leaves a field with what it held.
