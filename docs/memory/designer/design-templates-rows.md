---
name: design-templates-rows
description: The shared component and widget rows a template or a multi-page import makes on opening: origin markers, the pagehide discard, reuse after 12 hours, and how to test what Publish resolves inside them.
---

## How is a template's row recognised?
- A widget row carries `template_origin = '<template>/<key>'` in `system_region_config`; a shared part, which has no settings, carries `category = 'template:<template>/<key>'`. A multi-page import's shared blocks carry `category = 'import:<project>'` (the `shared_component` create accepts only that prefix).
- Templates never take each other's rows: the marker holds the template id.

## What happens when the operator leaves without publishing?
- The editor sends the row ids as a beacon on `pagehide` to `designer/template_discard` → `pg_design_template_discard($ids)` (at most 100). A row a template or an import made is deleted when nothing uses it: `_pg_tpl_row_in_use()` looks at every page tree (recycle bin included) and every other component tree. A row freed by deleting its container goes in the next pass; passes repeat until one deletes nothing (at most 8).
- A row left behind anyway (closed browser) is reused the next time the same template opens, once it has been idle 12 hours (`_pg_tpl_reuse_cutoff()`) and is used nowhere: it is brought back to the template's layout instead of piling up. A younger row may belong to the same template open in another tab.
- Deleting a template made from a design discards its unused rows the same way. Leftovers can be deleted on the designs home (`shared-components-panel.md`).

## How do settings inside a shared part get page ids?
- Publish resolves `tab:` references with `pg_designer_resolve_tab_refs()`, walking into nested components (a widget inside the header) and reading the stored tree of a page sent unchanged.
- Testing only `pg_design_template_prepare()` does not show this: install the template (`pg_design_template_install()`) and read the widget's settings row.
