---
name: design-templates-rows
description: Rows a template or import makes on opening: origin markers, pagehide discard, 12-hour reuse read in one pass, page names freed on the first save, testing Publish inside them.
---

## How is a template's row recognised?
- A widget row carries `template_origin = '<template>/<key>'` in `system_region_config`; a shared part, which has no settings, carries `category = 'template:<template>/<key>'`. A multi-page import's shared blocks carry `category = 'import:<project>'` (the `shared_component` create accepts only that prefix).
- Templates never take each other's rows: the marker holds the template id.

## What happens when the operator leaves without publishing?
- The editor sends the row ids as a beacon on `pagehide` to `designer/template_discard` → `pg_design_template_discard($ids)` (at most 100). A row a template or an import made is deleted when nothing uses it: `_pg_tpl_row_in_use()` looks at every page tree (recycle bin included) and every other component tree. A row freed by deleting its container goes in the next pass; passes repeat until one deletes nothing (at most 8).
- A row left behind anyway (closed browser) is reused the next time the same template opens, once it has been idle 12 hours (`_pg_tpl_reuse_cutoff()`) and is used nowhere: it is brought back to the template's layout instead of piling up. A younger row may belong to the same template open in another tab.
- Which rows are used is read once per opening (`_pg_tpl_used_rows()`: every page tree and component tree, one query each, on the first widget that has an older row), and once per discard pass. Asking `_pg_tpl_row_in_use($sid)` per candidate made each opening of an often-published template slower with every publish (#4537: 99 used rows × two full page scans ≈ 16 s).
- Deleting a template made from a design discards its unused rows the same way. Leftovers can be deleted on the designs home (`shared-components-panel.md`).

## How do settings inside a shared part get page ids?
- Publish resolves `tab:` references with `pg_designer_resolve_tab_refs()`, walking into nested components (a widget inside the header) and reading the stored tree of a page sent unchanged.
- Testing only `pg_design_template_prepare()` does not show this: install the template (`pg_design_template_install()`) and read the widget's settings row.

## What if a page name was taken between opening and the first save?
- Names are chosen on opening (`_pg_tpl_unique_page_name()`) and held by nothing. The first save of a design opened from a template (`add` mode, `style_template` posted) moves a taken name of a new page to the next free one (`anasayfa-4` → `anasayfa-5`, `sss` → `sss-2`; `pg_design_template_free_page_names()`), rewrites links to it in the saved pages and in the template's own rows they place (`pg_design_template_first_save_names()`, `pg_design_template_relink_rows()`, after validation) and reports each rename as a warning. `tab:` references are keys and need nothing. Before, Save stopped on "page name already in use" for every such tab (#4539).
- That save redirects to the new design's editor, so its warnings and notes go through the tab's sessionStorage (`_sdCarrySaveMessages()`, shown by `_sdShowCarriedSaveMessages()` once the editor is drawn); a save's warnings stay until closed. A 7-second toast died with the page after 0.4 s (#4816). A design never saved (`_design.styleId` 0) never says "changes made to pages on the site are live" on a draft save.
