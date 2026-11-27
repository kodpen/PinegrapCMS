---
name: new-design-window
description: The New design window (#sdStartModal): the grouped rail of starts, one icon a start, each pane and its head, the foot's button, ?start=, how a later start is added
---

## What is the New design window?
- One dialog for every way to start a design (`pg_designer_start_window()`, includes/designer_screen.php); it replaced the framework dialog and the separate template gallery dialog (ST2 of the start plan, `designer-start-import.md`).
- At most 1120 × 720 px, 24 px from each edge below that, one height whatever the start from 768 px (`designer-home-tidy-drawing.md`).
- Left: the 256 px rail, the starts in three groups with a grey label ("Start new", "From a design tool", "From what you have"); a row (`.pg-sm-link`) is one 32 px icon tile, the title and one line that never wraps; no small drawing. Grey tiles, the accent on the chosen row's. Right: the chosen start's pane. Foot: Cancel quiet and one filled button.
- One icon a start: `pg_designer_start_icon($key)`, the same in the rail, the pane's head and the home's cards; the list is in `designer-home-tidy-drawing.md`.
- A pane's head (`.sd-ns-head`): the icon as a 44 px accent tile, the kind's `heading` (the act) and `lead` (what happens next, never the row's line).
- Opened by the home's "New design" (Blank) and its first-visit cards (`data-sd-start`, `designer-home-screen.md`); `view_system_styles.php?start=<key>` opens it on load.

## Which starts does it list today?
- In order (`pg_designer_start_kinds()`): Blank, Template, Describe it (`describe-start.md`), Figma (once its import is there), Canva, From a document (`document-start.md`), ZIP file, Pasted markup.
- Blank, ZIP file and Pasted markup ask "Built on": two choice cards, "Bootstrap 5" (chosen first; "Recommended" only on Blank's) and "Custom", one line and three ticks each, a grey lock line under them; a press anywhere on a card chooses it.
- Each radio carries the address the foot's "Create design" takes (`pg_designer_start_go_href()`: `add_system_style.php?framework=…`, plus `&start=import|paste`, plus `&from=pages`); the editor then shows its file box or paste box.
- Template: the gallery (`pg_designer_template_gallery()`, the theme picker and the cards, three a row). No card has a button: a press chooses a card (`.is-chosen`, the ring), the foot's "Use this template" (grey until a card is chosen) leads to the card's `data-use`; a double press or Enter uses it at once. "Preview" stays a link. The theme picker keeps `data-use` on its choice.
- A disabled row shows its kind's `off` line, `why` as its title (Template without templates, Describe it without Pinegrap AI); `?start=` on it falls back to Blank (`pg_designer_start_key()`).

## How is a new start added (Sketch it, Figma, Canva, Describe it)?
- One entry in `pg_designer_start_kinds()` at its place and group (Start new: Blank, Template, Sketch it, Describe it; From a design tool: Figma, Canva; From what you have: From a document, ZIP file, Pasted markup), its icon in `pg_designer_start_icon()`, and a case in `pg_designer_start_pane()`.
- The foot's button reads the pane's `data-go` words and the checked radio's `data-href`; a pane with no such radio hides it.
- The start questions come after a Bootstrap 5 start's own form, before the design opens (`design-variables-questions.md`).

## Where is the script?
- `assets/js/designer_home.js`: tab choice, Up/Down/Home/End, the foot's button, `?start=`. On open the chosen start has the focus; below 768 px the window is a list, then the pane (`designer-home-narrow.md`).
- A start's own foot (`.sd-ns-part-foot`) draws no Cancel: the window's stays beside the filled button, and the part's `.me-auto` words come first. After "Turn into a template" the gallery printed with the page is stale: choosing the Template start reloads `?start=template`.

## Tests?
- `tests/designer_home_test.php`: the starts' order, groups, icons and foot words, the start key, the button's address, the rows, panes and heads, the framework and template cards.
