---
name: layout-type-converter
description: A page type's layout file into its widget's design elements (LE6): pg_layout_convert(), the piece map, form list and item view pieces, the dropped list, the run; other types in their own files
module: layout_convert
---

## What does it do?
- `pg_layout_convert($type, $code, $ctx)` (`includes/fn/layout_convert.php`): the custom form converter ([[layout-form-converter]]) made general. Parts by `pg_di_php_parts()`, markup by the HTML converter, so the theme's wrappers and classes stay. Pure.
- Answers `ok` (false for a type with no map), `tree`, `cfg` (settings the layout sets: `empty_message`), `dropped`: `piece` (the PHP source), `line`, `code` (`condition`, `value`, `loop`, `code`, or a map's own code such as `browse`).
- `ctx`: `state` (`pg_layout_convert_state()`, in `layouts.php`, from the type row), `row` (the starter's row template, `pg_layout_convert_row()`: the loop area's one column's content), `header`, `footer`.

## What is the piece map?
- `pg_layout_piece_map($type)`: `echo` (a printed value: `none`, `token:<name>[,<name>]`, `submit:<token>`, `ctx:<key>`, `row`), `if` (a condition: `widget` skipped, `flag` (one or nested flags) shown under the widget's flag, `empty` the widget's empty message, `section`, `keep`, `drop`; `state` = settings without which it is never drawn, `drop_when`; a key `elseif:<cond>` read only after an "else").
- `else` (the flag of an "otherwise", or `keep`), `loop` (`list`, or `list:<row flag>` for one kind of records of the widget's one list: [[layout-type-converter-photo-gallery]]).
- Options `forms`, `flag_list`, `controls`, `submits`, `media`: [[layout-type-converter-order-form]]. Types: `pg_layout_convert_types()`; LE8b to LE10 add theirs.
- A condition whose settings are off is left out unreported: the visitor never sees it. Anything the map does not name is dropped with what it holds.
- A flag never goes on a node holding the list (the widget's empty message sits in the list's place), unless the map says `flag_list`.
- An address, text or image source that is one widget value alone is bound (`_bindings.href` / `text` / `src`), never typed.

## What do the form list pieces become?
- Messages, `<form>` attributes and hidden fields, the pagination: nothing, the widget draws them. The browse and search form: the widget's search box; with browse on it is dropped (`browse`).
- `$browse_or_search_required` (browse or search on, results not shown by default): dropped. `elseif ($number_of_forms == 0)`: its words become `empty_message`; its `else`: shown under `has_records`. "Found N results" (`$browse_active or $search_active`): dropped when search or browse is on (no widget flag for a search in force). `foreach ($forms …)`: the loop area, `$form['content']` the starter's row.
- Starting sites: three texts; with browse and search off nothing drops, with search on only "Found N results" does.

## And the form item view?
- `$content`: the loop area with the row. `$auto_registration` → `has_new_account`, `$edit_button_url` → `can_edit` with `__edit_url`. `$auto_registration_for_submitter` (both branches) and `$back_button_url` drop. No starting-site page has a file.

## How does the move run use it?
- The dry run reads each file page of a mapped type (`pg_ltd_type_file_dropped()`); nothing dropped and nothing held makes the target `convert` with `file` true: listed under "Page types", its kind's starter posted, not counted under "Pages with a custom layout".
- The types step reads the file again and builds the widget with `pg_ltd_type_file_widget()`; a drop since then or no starter leaves the System region, with a report line.
## And in the editor?
- The Region panel's dropped list and its Convert: [[layout-type-converter-editor]].

## Tests?
- `tests/layout_convert_test.php`: the map, the templates, all starting-site form list texts under each setting, lines, the target, the widget, the dry run count.

Related: docs/memory/designer/legacy-types-step.md, docs/memory/designer/legacy-stock-types.md
