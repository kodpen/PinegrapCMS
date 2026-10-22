---
name: shared-components-panel
description: Deleting shared components and system widgets without opening a design (designs home panel), how usage is counted, and why deleting a design leaves those rows alone (pg_designer_delete_design()).
---

## Where are components deleted without opening a design?
- Designs home (`view_system_styles.php`): the button "Shared components and system widgets" above the list opens an offcanvas built by `pg_designer_components_card($user)` (`includes/designer_screen.php`); designers only, absent before the table exists or when it is empty. It lists and deletes; editing stays in the editor.
- These rows belong to no design: a template's header and widgets outlive the design that opened them, and the next template opened names its own "[1]".
- Columns: name; kind (widget label from `pg_sw_type_labels()`, the PHP mirror of `SW_TYPES` in `style_designer.js`, kept in step by `tests/designer_components_test.php`); source (template, from a `template:` category or `template_origin`; import, from `import:<project>`); where it is used; last change.
- Delete goes through `shared_component` sub-action `delete_many` (at most 500 ids): a row a saved page still places is kept unless the request says `force` and comes back in `kept` with its usage; content editor grants go with the row.

## How is usage counted?
- `pg_shared_component_usage($ids)` (`includes/fn/designer.php`): every page tree referencing the id, through any number of nesting levels, the recycle bin included (`binned`: a page brought back from the bin must find its widget where it left it).
- A binned page shows the name it had before the bin. If its design was deleted, `style_name` is `''`: print the page alone, never "page ()".

## What does deleting a design do?
- One path: `pg_designer_delete_design($style_id, $user, $collab_key)` behind the toolbar and the designs list (`designer/design_delete`). Designers only; refused while another editor tab has the design open (its next save would write pages of a design that no longer exists).
- Live pages go to the recycle bin; pages already there stay. Folders using the design as default and pages using it as mobile style are set to 0 (`get_style()` then asks the parent folder). Once the bin can hold styles (`pg_recycle_style_ready()`) the design goes there with its pages and comes back with them; before that the style row is deleted.
- It never touches `shared_components`: leftovers are cleared from the panel above.

Related: docs/memory/designer/designer-home-screen.md, docs/memory/designer/shared-component-model.md
