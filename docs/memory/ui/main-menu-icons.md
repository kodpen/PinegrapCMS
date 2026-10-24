---
name: main-menu-icons
description: The panel main menu's two-colour SVG icons (pg_menu_icon()): when a drawing is needed, the 24-unit grid, module colour and accent colour classes, hover motion, what stays a Bootstrap icon.
---

## Which icons are not Bootstrap Icons?
- Only the rows of the panel's main menu (`output_menu()`): each item names its drawing with `'menu_icon' => '<name>'`, drawn by `pg_menu_icon($name, $color_class)` (`includes/fn/output.php`). Everything else, the entries in the panels those rows open included, stays `bi`.
- A new main menu item gets its own drawing in `pg_menu_icon()`. Without one the row falls back to a `bi` icon (`'icon'`), then an svg, then a letter in a circle.

## Which icons do the panel entries carry?
- Bootstrap Icons, always as `bi bi-<name> bi-me-2` (a bare `bi-<name>` draws too, but reads as a mistake). A list or screen: the outline icon of what it lists, one icon per subject across the menu and never the same one for two subjects. Create/Add: always `bi-plus-lg`. Reports: one chart shape each (`graph-up-arrow` order reports, `bar-chart` shipping, `clipboard2-data` visitors; the ERP's aging, P&L, VAT, cash flow and stock each their own). No `-fill` or half-filled glyphs. A link that leaves the panel: `box-arrow-up-right`. A module's own settings: `sliders`. `bi-stars` stays the AI mark.
- Site Settings' categories and tools take their icons from `includes/settings/registry.php`, so the modal's sidebar and the search box show the same ones; its sections carry a neutral `bi-dot`.
- Settings is the gear (`pg-mi-settings`, `settings-color`), turning one tooth on hover.

## How is a menu icon drawn?
- On a 24-unit grid. First colour: the module's colour class on the `<svg>` sets `currentColor`, which draws the outline; the same colour as a tint (`.pg-mi-2`) fills the body.
- Second colour: the one detail the icon is about (the clock's hands, the tick, the sun, the per cent sign), as an outline (`.pg-mi-a`) and a tint (`.pg-mi-at`). Its hue, `--pg-mi-a`, is picked per icon in `assets/css/backend.src.css`, block "Main menu icons", to stand apart from the module colour. Without a second colour the operator sees the icon as single-coloured.
- The `fill-opacity` attributes are the fallback until the stylesheet has loaded; the light and dark themes swap the module's two shades.

## Where does the hover movement go?
- Into the same CSS block, inside its `prefers-reduced-motion: no-preference` section, so a device that asks for less motion gets none: one movement per icon, under a second, on the parts that carry a class of their own (`.pg-mi-lid`, `.pg-mi-check`...). Transform origins are points on the 24-unit grid.
- The dashboard clock's hands and the calendar's day are set by the server (site time zone) and kept moving by `backend.src.js`.

Related: docs/memory/ui/main-menu-states.md
