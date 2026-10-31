---
name: widget-layout-gallery
description: W5 layout gallery of a system widget: sources (Default, Starters, From the template, On this site), the filters, Try/Keep/Cancel, undo, saves and rights
---

## What does the gallery offer?
- The widget panel's "Layout Template" section lists layouts for the widget's kind: Default (the starter tree; a form view with a form chosen gets the layout built from that form's fields), Starters (layouts the release ships, [[widget-starters]]), From the template (only in a design made from a template) and On this site (every other widget of the same kind, `_swKindOf()`).

## Which widgets on this site are offered?
- Same kind; a stored tree that is not empty and not a template placeholder (`_swIsTemplatePlaceholder()`); not the same tree as this widget's; identical trees once.
- A form layout only where it works: form views when both widgets' forms have the same field names (same form, or `list_form_fields` compared); a custom form when both trees have the same control names (`_cfControls()`).
- Read with `shared_component` `list` and `prefetch` (read-only sub-actions), never through `_sharedCache`: the other widget is not touched.

## How do Try, Keep and Cancel work?
- Try puts a re-ided copy (`gid()`) on the canvas; nothing is marked unsaved. A bar at the foot of the window says what is tried, with Keep and Cancel. Another Try swaps the copy.
- Keep puts back the original for one `saveState()`, then the copy, marks the widget dirty: one undo step (Ctrl+Z) brings the old layout back. Copied, never linked: the copy has its own node ids.
- Cancel, Ctrl+Z and Ctrl+Y put the original back. Editing the tried layout keeps it first (`saveState()` hook). A save cancels a try before it serialises (`_saveAjaxImpl()`, `prepareFormData()`).
- The widget's settings (form, product group) are never changed. The section replaced the old "Load the Default Layout" button (it asked, then replaced at once); the express order panel keeps its own server-tree button.

## Who may use it?
- Shown only to an operator who may edit (`_collab.mayEdit`) and is not content-level (widgets never open to content editors); Try checks the same and nudges.

## Which template layouts are offered?
- Only the design's own template (`style.style_template`, else `sdDesign.template.id` while it is being opened): its layouts carry that template's styles. `shared_component` `template_layouts` reads the template from the design's row, then `pg_sw_template_layouts()` (`includes/fn/widgets_gallery.php`).
- Explicit trees of the kind (not `'tree' => 'starter'`, the Default already), whose page the site has. `{{page:}}` and `{{tab:}}` resolve to this design's pages (`pg_sw_template_page_map()`: the template page's name, or the lowest `-2`… suffix opening gave it); site name, e-mail, year are filled.
- Left out: a page the design lacks; folders, product or contact groups (`_pg_tpl_fill()` would turn them into 0 silently); nested `templateWidget`/`templateShared` parts (they need rows).
- A form view carries the field names of the template form it was made for (the custom form widget on the page its setting names); offered only when this widget's form has the same names. A custom form: same control names, as on this site.

## What is not in W5?
- Other templates' layouts with their styles, and an online library (owner, 2026-10-26: [[widget-designability-decisions]]).
