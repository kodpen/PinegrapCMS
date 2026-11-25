---
name: designer-component-overrides-drawing-fields
description: Boards C1 and C2 of the component overrides drawing (step CO3): the "Can change per place" menu row and list, the label, the mark, Overview, the list in the component's settings
---

## Where is a field opened?
- Inside a shared component (opened on its own, or edited from a page) the element's three-dot menu gains one row after "Duplicate", "Delete", "Show in Overview": "Can change per place", with an arrow. Only for the roles that may edit any component (Administrator, Designer); elsewhere the menu is as today.
- The row opens a list headed "Can change per place": one switch per field the element has, of "Text", "Picture", "Link", "Icon", and always "Shown". A switch that is on shows a "Label" field under it.
- The command palette has the same row, "Can change per place", for the selected element.
- A field bound to data is dimmed, its switch off, with the reason under it: "Bound to data: it already changes with the record."

## What is the label?
- The name the field carries in every place. It starts as the element's name ("Button"); when a second field of the same element is opened both take the field's name after it ("Button text", "Button link"). An empty label goes back to that default.

## What does the first use show?
- One line under the list, once to a person, the first time a field is opened: "Each place of “Promo card” can now have its own button text. Select a place to change it under “This place”." with "Got it". Never again.

## How is an open field marked?
- A small green two-arrow sign (`bi-arrows-angle-expand`) after the row's label in the Options panel, with the tip "Can change per place: label". Choosing it opens the same list. The sign is a shape, the tip names it: no colour alone.
- Overview shows the same sign at the right edge of the node's row; its tip lists the labels ("Can change per place: Button text, Button link"). The component's own row keeps its green name and puzzle badge.

## What does the "Shared Component" section list?
- "Name", "Content editors" and "Detach" stay as they are today. Between them, under the small head "Can change per place", one row per open field in the order every place shows them: a grip, the kind's icon, the label, a three-dot button.
- Rows are dragged by the grip; with the keyboard Alt+Up and Alt+Down move the focused row. The order is the order of the rows under "This place".
- A row's menu: "Rename" (the label becomes a field in place; Enter keeps, Escape leaves), "Show in Overview" (selects the element), "Close the field".
- "Close the field" asks nothing: the places' values stay where they are and are ignored, never drawn, and the Publish check lists them as left-over. The answer is a Result line: "“Button link” is closed. Places show the component's link again." with "Undo".
- No field yet: "No field yet. Select a text, picture, link or icon in the component and choose “Can change per place” in its menu."

## How is it saved, and what does the keyboard do?
- Nothing is sent by itself: the marks are saved with the component, by "Save". One step of undo takes a switch back.
- In the menu the arrow keys move, Right or Enter opens the list. In the list Up and Down move between rows, Space flips the switch, Tab goes to "Label", Escape closes the list and then the menu.
