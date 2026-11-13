---
name: form-list-view-template-texts
description: Form list view built-in layouts print their visitor texts through lang(); the Turkish starter theme's file field label; why their Bootstrap 3 markup stays
---

## Which texts of a form list view are translated?
- `includes/templates/form_list_view.php` (custom layout) and `form_list_view_system.php` (system layout) print through `lang()`: the toggles' titles (Remove Browse, Add / Remove Advanced Search, Clear), "You may browse or search above to find results." and its browse-only and search-only sentences, "There are no results.", "Found {var:1} result{suffix:1}.", and in the custom layout Previous, Next and "Page {var:1} of {var:2}" (#9026). They printed English literals on a Turkish site.
- A page whose layout was edited (`data/layouts/<id>.php`, the starter sites' layouts) keeps its own text; the template change reaches new and unedited layouts only.

## Why did a Turkish site's file field read "Attach a File"?
- The starter sites' theme script (`data/backups/<starter>/files/scripts.js`) wraps every `input[type=file]` with a fixed label. The Turkish starter, Turkish everywhere else, now says "Dosya Ekle"; the English one keeps English. An installed site keeps its own copy in its File Manager.

## Why do the templates keep Bootstrap 3 names?
- The same template serves a classic style (the starter sites load Bootstrap 3.3.5) and a Bootstrap 5.3.8 design. A visual design with framework `bootstrap5` gets 5.3.8 names from the output map (`plans/legacy-layouts-output-map.md`, steps LL3 and LL4 for `input-group-btn` and `glyphicon`), so the template is not rewritten; the squeezed Browse select and the empty toggle button on such a page (#9034) are that plan's work.
