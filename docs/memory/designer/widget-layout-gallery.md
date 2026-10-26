---
name: widget-layout-gallery
description: W5 layout gallery of a system widget: sources (Default, On this site; From templates left), the filters, Try/Keep/Cancel, undo, saves and rights
---

## What does the gallery offer?
- The widget panel's "Layout Template" section lists layouts for the widget's kind: Default (the starter tree; a form view with a form chosen gets the layout built from that form's fields) and On this site (every other widget of the same kind, `_swKindOf()`).
- From templates (design templates' layouts) is not built yet; see "What is left?".

## Which widgets on this site are offered?
- Same kind; a stored tree that is not empty and not a template placeholder (`_swIsTemplatePlaceholder()`); not the same tree as this widget's; identical trees once.
- A form layout only where it works: form views when both widgets' forms have the same field names (same form, or `list_form_fields` compared); a custom form when both trees have the same control names (`_cfControls()`).
- Read with `shared_component` `list` and `prefetch` (read-only sub-actions), never through `_sharedCache`: the other widget is not touched.

## How do Try, Keep and Cancel work?
- Try puts a re-ided copy (`gid()`) on the canvas; nothing is marked unsaved. A bar at the foot of the window says what is tried, with Keep and Cancel. Another Try swaps the copy.
- Keep puts back the original for one `saveState()`, then the copy, marks the widget dirty: one undo step (Ctrl+Z) brings the old layout back. Copied, never linked: the copy has its own node ids.
- Cancel, Ctrl+Z, Ctrl+Y and closing the bar put the original back. Editing the tried layout keeps it first (`saveState()` hook). A save cancels a try before it serialises (`_saveAjaxImpl()`, `prepareFormData()`).
- The widget's settings (form, product group) are never changed.

## Who may use it?
- Shown only to an operator who may edit (`_collab.mayEdit`) and is not content-level (widgets never open to content editors); Try checks the same and nudges.

## What is left?
- From templates: the design templates' layouts for the kind (`includes/design_templates/<id>.php` starter widgets), styled ones only in a design made from that template. The design needs to know its template id (not stored today); a template's plain (unstyled) layouts could be offered everywhere. Owner decision: [[widget-designability-decisions]].
