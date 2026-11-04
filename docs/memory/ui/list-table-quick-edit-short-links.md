---
name: list-table-quick-edit-short-links
description: Short links list quick edit (includes/fn/short_link_list_edit.php): which cells are fields, the split destination columns, how a change runs the edit screen's own checks and save
---

## Which cells change?
- Name, destination type, page, destination (product group, product, URL or file), tracking code and how it answers: every value `edit_short_link.php` changes.

## How is a change checked and written?
- Through the edit screen's own checks (`includes/forms/short_link_form.php`).

Related: docs/memory/ui/list-table-quick-edit-values.md
