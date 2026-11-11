---
name: legacy-group-design
description: Plan (owner 2026-11-11, #8671): classic styles that share a CSS/JS set move as one "Legacy" design that opens right in the visual designer; the theme and {path} fixes first; steps LG1-LG7
---

## What is it for?
- Owner (2026-11-11): "aynı css ve js leri paylaşan sayfalar bir grup halinde (gorsel tasarımlar listesinde legacy diye belirtilir.) özel tasarım olarak eklenebilir. olursa planda bir sıçrama yapacaktır ve sonrası içinde plan yapalım bugün bir tanesini düzenleyince css ve js uyumsuz olarak açıyor". His full words and answers: #8671, `workflow/decisions-made-product-43.md`.
- Today (LD1-LD4 done; `designer/legacy-to-designer-report.md`, `legacy-to-designer-run.md`): a designer moves classic styles one by one; each becomes its own `custom` design, its whole page one shared frame. Memory says "classic" for the owner's "legacy".

## Why does a moved design open wrong today?
- Seen in a sandbox on home-1 (#8694), the head's other files missing too.
- The theme was lost (LG1 fixed it): the classic head's `<stylesheet></stylesheet>` gets the style's theme, else the site's activated theme (`get_page_content.php`); the importer drops the tag (`designer/classic-style-import.md`), the move sets no theme, the canvas shows only the chosen Theme file (`_sdActiveTheme()`).
- `{path}` and `{software_directory}`: an asset row kept as written fails the renderer's address check unless it became a managed file (`designer/classic-style-design-files.md`); the canvas never fills them, in asset links, inline CSS or a node's `src` / `href`.
- The load order differs when CSS stood after the `<stylesheet>` tag.

## What is a Legacy design?
- A third key `legacy` in `pg_design_frameworks()` (`includes/fn/designer.php`): no Bootstrap, as `custom`, plus a `legacy` flag. `style.style_framework` already holds it: no schema. Code asks the registry's flags, never the key's text.
- It keeps the classic theme rule on the site and the canvas: its own Theme file, else the site's activated theme; skipped when an asset row already names that file.
- The Visual Designs list shows the badge "Legacy" (Turkish "Eski").

## How does a group move?
- One design per set: the classic styles with the same ordered external CSS and JS references, `collection` and theme. On the starting sites 49 styles make 7 sets (42 in one), 50 make 8 (40 in one).
- The design is a new `style` row. Every classic `style` row and its code stay (#6006); a page changes its `page_style` only in the run a designer starts after the dry run (#6251: the same cursor and lock).
- Each page's tree is the root and a `shared_ref` to the frame component of its origin style (category `classic_style:<style_id>`): N styles, N frames in one design. Inline CSS or JS that differs between styles becomes an asset row limited to that style's pages (`pg_design_asset_on_page()`).
- The report lists the sets, then their styles; the dry run and the run take a set.

## What follows the move?
- Page content edited in the page: the frame split into a top and a bottom shared component, the page's own content in its tree (the open end of `designer/page-region-into-page-tree.md`).
- "Back to classic": a moved set or one page returns to its classic style, which never left.
- A helper towards Bootstrap 5.3.8 and a theme: a plan of its own, written by the architect when LG7 lands.
- Not chosen: joining designs converted one by one earlier to their set's design; they get the `legacy` key and the fixes only.

## What does every step keep?
- No schema. Nothing classic is removed; cell-based system styles and PHP template styles stay classic.
- A step that changes what a visitor gets shows the classic and the moved page side by side in the sandbox, wide and phone, linked in the PR: a unit test cannot reach the cascade. The look on dev is the owner's.

## Who holds it, where does it start?
- Coordinators hand the steps out; claim as `[plan:legacy-group-design] <step> — <topic>`. Steps: `legacy-group-design-steps.md`. LG1 starts.
