---
name: new-design-window
description: The New design window (#sdStartModal) on the Page Design Studio home: the list of starts, each start's pane, the foot's button, ?start=, and how a later start is added
---

## What is the New design window?
- One dialog for every way to start a design (`pg_designer_start_window()`, includes/designer_screen.php); it replaced the framework dialog and the separate template gallery dialog (ST2 of `plans/designer-start-import.md`).
- Left: the starts as a vertical tab list, each row a small drawing (`pg_designer_home_drawing()`), a title and one line; the rows are the settings rail's `.pg-sm-link`. Right: the chosen start's pane. Foot: Cancel quiet and one filled button.
- Opened by the home's "New design" button and by each start card, on the card's own start (`data-sd-start`); `view_system_styles.php?start=<key>` opens it on load.

## Which starts does it list today?
- In order (`pg_designer_start_kinds()`): Blank, Template, ZIP file, Pasted markup.
- Blank, ZIP file and Pasted markup ask the framework as choice cards (Bootstrap 5 or Custom); each radio carries the address the foot's button takes (`pg_designer_start_go_href()`: `add_system_style.php?framework=…`, plus `&start=import|paste`, plus `&from=pages`). The editor then shows its file box or paste box.
- Template: the gallery (`pg_designer_template_gallery()`, the theme picker and the cards); each card has its own "Use This Template", so the foot's button is hidden there.
- Without templates the Template row is shown disabled ("There are no templates.") and `?start=template` falls back to Blank (`pg_designer_start_key()`).

## How is a new start added (Sketch it, Figma, Canva, Describe it)?
- One entry in `pg_designer_start_kinds()` at its place in the plan's order (Blank, Template, Sketch it, Figma, Canva, ZIP file, Pasted markup, Describe it), its drawing kind in `pg_designer_home_drawing()`, and a case in `pg_designer_start_pane()`.
- The foot's button reads the pane's `data-go` words and the checked radio's `data-href`; a pane with no such radio hides it.
- The start questions (`plans/designer-variables.md`, DV4) come after a start's own form, before the design opens.

## Where is the script?
- `assets/js/designer_home.js`: tab choice, Up/Down/Home/End, the foot's button, `?start=`. After "Turn into a template" the gallery printed with the page is stale: choosing the Template start reloads `?start=template`.

## Tests?
- `tests/designer_home_test.php`: the starts' order, the template start waiting for a template, the start key, the button's address, the window's tabs and panes.
