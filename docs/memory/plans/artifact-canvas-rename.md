---
name: artifact-canvas-rename
description: Plan (owner 2026-11-20): the Design Canvas is renamed Artifact Canvas ("Yapıt Tuvali") and gets a new icon, two sheets and a wave; the name and the icon on every screen that shows them; AC1-AC5
---

## What did the owner ask?
- Owner (2026-11-20), with two pictures he liked: "tasarım tuvali logosunu değiştirmeyi düşünüyorum", then "Adını ayrıca "Artifact Canvas" yani Yapıt Tuvali olarak güncellemek istiyorum."
- On a sheet of four drawings made from his two pictures he chose drawing A: "bunu yapalım. plana işle lütfen ve özellikle dosya yönetici, kanallar ve sayfa tasarımcısında olmak üzere ikonun ve adının geçtiği alanlarda adı ve ikonu güncellensin".
- Record: `workflow/decisions-made-panel-10.md`. The sheet: `plans/artifact-canvas-rename/icon-candidates.png`.

## What is the new drawing?
- Two round-cornered sheets: the back one up and left, hidden behind the front one, with the module's tint; on the front sheet a wave in the accent hue. No crop marks, no anchors.
- It replaces drawing B in `pg_menu_icon()`, case `design_canvas` (`ui/design-canvas-icon.md`). The two forms (two colours, one colour), their classes and the places that draw them stay.
- On the 24-unit grid, the menu icons' stroke of 1.7:
  - Back sheet, outline: `M17 7V6.1A3.6 3.6 0 0 0 13.4 2.5H6.1A3.6 3.6 0 0 0 2.5 6.1v7.3A3.6 3.6 0 0 0 6.1 17H7`.
  - Back sheet, tint (`.pg-mi-2`): the same path closed with `v-6.4A3.6 3.6 0 0 1 10.6 7z`.
  - Front sheet, outline only: `<rect x="7" y="7" width="14.5" height="14.5" rx="3.6"/>`.
  - Wave, in the accent group (`.pg-mi-a`): `M9.9 18.7C10.8 15.2 11.8 13 13.2 13C14.6 13 14.4 14.9 15.8 14.9C17.3 14.9 18 12.4 18.7 9.8`.
- Not chosen: his first picture as it is (see-through sheets blur at 16 px), the wave with drawing B's pen anchors, portrait cards, one line that turns into the wave.

## What is renamed and what stays?
- Every text that names the Design Canvas says Artifact Canvas, in Turkish "Yapıt Tuvali" ("Yapıt Tuvali'nde"). A sentence is rewritten around the name (`designer/editor-turkish-terms.md`).
- Only texts change, as in #8894: no address, file, function, class, setting or API name (`image_editor`, `pg_design_canvas_icon()`, `.pg-dc-icon`, the icon's key `design_canvas`).
- The short word "canvas" stays ("New canvas", "Send the canvas"): the plan writer's reading, told to the owner.
- The site's edit button on an image keeps the Pinegrap mascot (owner 2026-11-04, `ui/brand-logo.md`); its title and alt text take the name.
- `changelog.txt` gets no `[NEW]` line and its earlier lines keep the old name.

## Where are the steps?
- `plans/artifact-canvas-rename-steps.md`.

## How does the plan close?
- Step AC5: `ui/design-canvas-icon.md`, `designer/editor-turkish-terms.md` and `ui/image-editor-overview.md` say the new state; the plan's two files and its sheet are deleted.
