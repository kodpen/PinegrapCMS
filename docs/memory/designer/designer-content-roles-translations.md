---
name: designer-content-roles-translations
description: What a content-level editor (manager, user) may do with translations and drafts in the designer: the Translate section, translations_action.php limits for role 3, a text's page scope, draft state.
---

## Where does the Translate section show at content level?
- On a node whose text the operator may edit (`_sdCanEditNode(node, 'text')`): above the read-only panel when the rest of the node is not theirs. Changing a sentence and leaving its translation behind would put two texts on the site.
- Not inside a system widget or a shared component that is not opened to them: the text is not theirs there.

## What may a user (role 3) call in translations_action.php?
- A manager runs the whole Translations screen. A user gets only the editor's three calls, `node` (read the selected element's texts), `save` and `status` (approve), with a `page_id` that `pg_designer_page_access()` answers `edit` for (the rule that opens the page in the editor). Anything else is a 403 "Access denied." in JSON.
- A write or an approval goes through `pg_tr_string_page_scope()` (`includes/translate/store.php`). A text has one translation wherever it appears (`translation_uses`), so every other use must be on a page the user may edit; a shared component, widget, menu, form or catalog never is. Deleted pages and pages in the recycle bin do not count.
- `elsewhere` (also used where they may not edit) and `no` (not on this page) refuse the write with a message. The `node` answer marks such a text `locked` and the section draws it read-only.

## Who changes a page's draft state?
- Only full access. A content-level save keeps the stored draft state whatever it sends (`pg_designer_page_draft_plan()`, page-drafts.md) and the stored publish times (page-schedule.md); name, folder and home flag come from the stored row (designer-content-roles-save-gate.md).
- Access to a draft is decided by the folder it returns to when published (`pg_designer_page_access()` reads `pg_page_draft_publish_folder()`), not by the private drafts folder.

Related: docs/memory/designer/designer-content-roles-save-gate.md, docs/memory/designer/designer-content-translation.md
