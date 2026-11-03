---
name: explorer-page-type-name
description: The File Manager writes a page's type in the panel language ("Page · Custom Form"), from the payload's type_name, and sorts the Type column by it
module: forms
---

## What does the Type column show for a page?
- "Page" for a standard page, "Page · <type name>" for any other: `itemTypeLabel()` in `view_folders.php` reads `item.type_name`. The same label is used in the list, the grid caption, the preview's Type row and Quick look.
- `type_name` comes from `pg_explorer_page_type_name()` (`includes/fn/forms.php`, pure apart from `lang()`): `get_page_type_name()`'s panel-language name, `''` for a standard page, the stored code for a type with no known name. `pg_explorer_page_payload()` sends it beside the raw `type`, which other code still reads.

## How does the Type sort order pages?
- `typeSortValue()`: a page sorts by the label it shows, so standard pages ("Page") come before the named types and the order matches the column; every other item keeps sorting by its stored type.

## Why?
- #5217: the label printed the stored English code ("Sayfa (custom form)", "Sayfa (shopping cart)") in a Turkish panel.

## Tests?
- `tests/explorer_page_type_name_test.php`.
