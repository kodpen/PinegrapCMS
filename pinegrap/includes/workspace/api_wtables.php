<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - workflow tables in the external API: the tables the owner of
 * the application reaches, their columns, rows and shares. The checks are
 * the store's own (includes/workspace/wtables.php, wtable_rows.php,
 * wtable_shares.php), so a dry run meets the refusals the real call meets.
 *
 * Reading and writing tables, columns and rows are MCP tools, as notes are.
 * Deleting a table or a column cannot be undone, so neither is a tool.
 * Sharing widens who reads a table, so it is the person's own: from their
 * team devices session only, no MCP tool, and refused to the application an
 * assistant works through. The application Claude works through reads a
 * table that reaches the owner only through a channel just where Claude may
 * be asked, as it reads the channel itself.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The route rows.
 *
 * @return array
 */
function ws_wtable_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $column_id = array('name' => 'column_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $row_id = array('name' => 'row_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $share_id = array('name' => 'share_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $limits = ws_wtable_limits();

    $table_fields = array(
        array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => $limits['name'], 'description' => 'What the table is called.'),
        array('name' => 'description', 'in' => 'body', 'type' => 'string', 'max_length' => $limits['description'], 'nullable' => true, 'description' => 'A longer text about the table. null or empty clears it.'),
    );

    $column_fields = array(
        array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => $limits['name'], 'description' => 'What the column is called. A formula names other columns by it.'),
        array('name' => 'options', 'in' => 'body', 'type' => 'object', 'description' => 'What the type takes: number and money decimals (0-6; money 2 when left out) and money currency (three letters); date time (true to keep a time HH:MM too); status choices (1-' . $limits['statuses'] . ' objects of key [a-z0-9_-], label and color #rrggbb); link kinds (the record kinds it may point to, all when empty); list of (text, the default, or number: what its items are, fixed once made); formula expression (other columns by name, as Price * Quantity). Sent on a change, it replaces the options.'),
    );

    $errors = ' A refused value answers 422 validation_failed naming the field, with the reason code in the message (name_empty, name_too_long, description_too_long, type_unknown, type_fixed, decimals_invalid, currency_invalid, choices_empty, choice_key_invalid, kinds_invalid, formula_too_long and the like).';
    $cells = 'Cells by column id: text a string, number a number, money an integer in minor units, date YYYY-MM-DD (with HH:MM on a column that keeps a time), person a user id, status a choice key, checkbox true or false, link an object of {kind, id}, list a JSON list of strings or numbers (or a text of one item a line; at most ' . $limits['list_items'] . ' items, a text item at most ' . $limits['list_item'] . ' characters); null empties a cell. A formula column takes no cell: its value is worked out when the row is read.';

    return array(
        array(
            'id'          => 'workspace.tables.list',
            'method'      => 'GET',
            'path'        => '/workspace/tables',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_wtables_list',
            'returns'     => array('list' => 'WorkspaceTable'),
            'summary'     => 'List workflow tables',
            'description' => 'The workflow tables of the owner of the application and those shared with them, themselves or in a channel they read, the latest changed first. access says owner, edit (shared with them at edit: they change its columns and rows) or view. A table is its owner\'s alone: nobody else reaches it but through a share, administrators included, and a table whose owner left the team reaches nobody. columns is null in the listing; GET /workspace/tables/{id} carries them.',
            'params'      => array(
                array('name' => 'scope', 'in' => 'query', 'type' => 'enum', 'values' => array('all', 'mine', 'shared'), 'default' => 'all', 'description' => 'mine keeps the owner\'s own, shared the ones of others.'),
            ),
        ),

        array(
            'id'          => 'workspace.tables.get',
            'method'      => 'GET',
            'path'        => '/workspace/tables/{id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_wtables_get',
            'returns'     => 'WorkspaceTable',
            'summary'     => 'One workflow table, with its columns',
            'description' => 'A table the owner of the application reaches, with its columns in order. revision moves on with every change of the columns: a client holding rows reads the columns again when it changed. The rows are read with GET /workspace/tables/{id}/rows.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.tables.create',
            'method'      => 'POST',
            'path'        => '/workspace/tables',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtables_create',
            'dry_run'     => true,
            'returns'     => 'WorkspaceTable',
            'status'      => 201,
            'summary'     => 'Make a workflow table',
            'description' => 'Makes a new table of the owner of the application, with no columns yet: nobody else sees it until they share it. A person keeps at most ' . $limits['tables'] . ' tables: one more is refused with 409 too_many_tables.' . $errors,
            'params'      => array(array_merge($table_fields[0], array('required' => true)), $table_fields[1]),
        ),

        array(
            'id'          => 'workspace.tables.import',
            'method'      => 'POST',
            'path'        => '/workspace/tables/import',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtables_import',
            'dry_run'     => true,
            'returns'     => 'WorkspaceTableImport',
            'summary'     => 'Import rows into a workflow table',
            'description' => 'Brings the rows of a CSV, Excel (xlsx, xls) or OpenDocument file, or of a table inside a note, into a workflow table, as the Tables screen does. The source is file_id (a file of the site the application may read: it also needs files:read, and only the files GET /files lists are reached) or note_id with note_table, the table\'s number in the note counted from 0 (a note the owner may read). Its first row names the columns; at most ' . $limits['columns'] . ' columns and ' . $limits['rows'] . ' rows. The target is table_id (a table the owner may change) or name (a new table of the owner\'s). Into a table, source columns are paired with its columns by name, case and spaces aside, a formula column never; map pairs them instead (source column name => column id, 0 leaving it out). A new table gets a column per source column in the type its values suggest; types names another (source column name => text, number, money, date, status, checkbox or list, a list read one item a line, or empty to leave it out). Each row is checked as POST /workspace/tables/{id}/rows checks it: a refused row is reported (line counts the header as 1) and the others are written; no workspace.wtable.row.* event is sent. With dry_run nothing is written and the answer tells the columns it would make or fill, the rows it would add and every row refused. A source without rows or with no column to fill is refused with 422 validation_failed (no_rows, map_empty).' . $errors,
            'params'      => array(
                array('name' => 'file_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'A file of the site to read: csv, xlsx, xlsm, xls or ods.'),
                array('name' => 'note_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'A note holding the table to read, instead of a file.'),
                array('name' => 'note_table', 'in' => 'body', 'type' => 'int', 'min' => 0, 'default' => 0, 'description' => 'Which table of the note, counted from 0.'),
                array('name' => 'table_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The table the rows are added to.'),
                array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => $limits['name'], 'description' => 'The name of a new table made from the source, instead of table_id.'),
                array('name' => 'map', 'in' => 'body', 'type' => 'object', 'description' => 'With table_id: source column name => column id (0 leaves it out). Left out, columns pair by name.'),
                array('name' => 'types', 'in' => 'body', 'type' => 'object', 'description' => 'With name: source column name => the new column\'s type (text, number, money, date, status, checkbox; empty leaves it out). Left out, each type is guessed from the values.'),
            ),
        ),

        array(
            'id'           => 'workspace.tables.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/tables/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_wtables_update',
            'dry_run'      => true,
            'returns'      => 'WorkspaceTable',
            'summary'      => 'Rename a workflow table',
            'description'  => 'Changes only the fields sent, on a table the owner may change (access owner or edit); a table shared with them at view is refused with 403.' . $errors,
            'params'       => array_merge(array($id), $table_fields),
        ),

        array(
            'id'          => 'workspace.tables.delete',
            'method'      => 'DELETE',
            'path'        => '/workspace/tables/{id}',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtables_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceTable',
            'summary'     => 'Delete a workflow table',
            'description' => 'Deletes a table of the owner\'s own with its columns, rows and shares; the answer is the table as it was. It goes to the bin for the days the site\'s Recycle Bin keeps (30 unless changed), where its owner restores it whole with POST /workspace/deleted-tables/{id}/restore; then it is deleted for good. Only its owner may (403 for a person it is shared with). It takes a whole table out of everybody\'s reach, so it is no MCP tool. POST /workspace/tables/{id}/delete does the same for servers that refuse DELETE.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.tables.delete_post',
            'method'      => 'POST',
            'path'        => '/workspace/tables/{id}/delete',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtables_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceTable',
            'summary'     => 'Delete a workflow table, addressed as a POST',
            'description' => 'The same as DELETE /workspace/tables/{id}, for clients and servers that cannot send DELETE.',
            'params'      => array($id),
        ),

        // The bin of tables (wtable_bin.php): the owner's alone, and like the
        // delete no MCP tool.
        array(
            'id'          => 'workspace.tables.deleted',
            'method'      => 'GET',
            'path'        => '/workspace/deleted-tables',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_wtables_deleted',
            'mcp'         => false,
            'returns'     => array('list' => 'WorkspaceDeletedTable'),
            'summary'     => 'Deleted workflow tables',
            'description' => 'The tables of the owner of the application in the bin, the latest deleted first: deleted_at says when, expires_at when it is deleted for good. Nobody reaches a deleted table, its shares included, until it is restored.',
            'params'      => array(),
        ),

        array(
            'id'          => 'workspace.tables.restore',
            'method'      => 'POST',
            'path'        => '/workspace/deleted-tables/{id}/restore',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtables_restore',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceTable',
            'summary'     => 'Restore a deleted workflow table',
            'description' => 'Brings a table of the owner\'s own back from the bin whole, with its columns, rows and shares; the answer is the table. A person keeps at most ' . $limits['tables'] . ' tables: a restore past that is refused with 409 too_many_tables.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.table_columns.create',
            'method'      => 'POST',
            'path'        => '/workspace/tables/{id}/columns',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtable_columns_create',
            'dry_run'     => true,
            'returns'     => 'WorkspaceTableColumn',
            'status'      => 201,
            'summary'     => 'Add a column to a workflow table',
            'description' => 'Adds a column at the end of a table the owner may change. Its type is fixed once made. A table has at most ' . $limits['columns'] . ' columns: one more is refused with 409 too_many_columns.' . $errors,
            'params'      => array($id, array_merge($column_fields[0], array('required' => true)), array('name' => 'type', 'in' => 'body', 'type' => 'enum', 'values' => array_keys(ws_wtable_column_types()), 'required' => true, 'description' => 'What the column holds; fixed once made.'), $column_fields[1]),
        ),

        array(
            'id'           => 'workspace.table_columns.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/tables/{id}/columns/{column_id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_wtable_columns_update',
            'dry_run'      => true,
            'returns'      => 'WorkspaceTableColumn',
            'summary'      => 'Change a column of a workflow table',
            'description'  => 'Renames a column or changes its options; its type stays (type_fixed). Cells already written that the new options no longer take are kept until their row is next written.' . $errors,
            'params'       => array_merge(array($id, $column_id), $column_fields),
        ),

        array(
            'id'          => 'workspace.table_columns.retype',
            'method'      => 'POST',
            'path'        => '/workspace/tables/{id}/columns/{column_id}/type',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtable_columns_retype',
            'dry_run'     => true,
            'returns'     => 'WorkspaceTableColumn',
            'summary'     => 'Change the type of a workflow table column',
            'description' => 'Changes a column\'s type, as ALTER ... MODIFY does: text, number, money, date, list and checkbox turn into one another, and a list of texts into a list of numbers and back. Every cell, of binned rows too, is converted and checked as a write of the new type checks it: a text read the site\'s way (a number, an amount, a date, a tick word or 1/0, one list item a line), a number or an amount into its text, a ticked box into 1, a list of one item as that item. While any cell does not convert nothing changes and the answer is 422 validation_failed, field type, its message listing each such cell by row number (no_conversion, list_many, decimals_lost when the new column keeps fewer decimals, time_lost when a date without a time would drop one, or the cell check\'s own code); with dry_run that is the dry run. A converted row\'s revision moves on; no workspace.wtable.row.* event is sent. Other types (person, status, record link, formula) and the same type are refused with 422 validation_failed too.' . $errors,
            'params'      => array($id, $column_id,
                array('name' => 'type', 'in' => 'body', 'type' => 'enum', 'values' => ws_wtable_retype_types(), 'required' => true, 'description' => 'The new type.'),
                array('name' => 'options', 'in' => 'body', 'type' => 'object', 'description' => 'The new type\'s options, as POST /workspace/tables/{id}/columns takes them (number and money decimals, money currency, date time, list of). Left out, the type\'s defaults.')),
        ),

        array(
            'id'          => 'workspace.table_columns.delete',
            'method'      => 'DELETE',
            'path'        => '/workspace/tables/{id}/columns/{column_id}',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtable_columns_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceTableColumn',
            'summary'     => 'Delete a column of a workflow table',
            'description' => 'Deletes a column of a table the owner may change; the answer is the column as it was. Its cells are no longer read and go when each row is next written; a column id is never given again. It cannot be undone, so it is no MCP tool. POST /workspace/tables/{id}/columns/{column_id}/delete does the same for servers that refuse DELETE.',
            'params'      => array($id, $column_id),
        ),

        array(
            'id'          => 'workspace.table_columns.delete_post',
            'method'      => 'POST',
            'path'        => '/workspace/tables/{id}/columns/{column_id}/delete',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtable_columns_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceTableColumn',
            'summary'     => 'Delete a column of a workflow table, addressed as a POST',
            'description' => 'The same as DELETE /workspace/tables/{id}/columns/{column_id}, for clients and servers that cannot send DELETE.',
            'params'      => array($id, $column_id),
        ),

        array(
            'id'          => 'workspace.table_rows.list',
            'method'      => 'GET',
            'path'        => '/workspace/tables/{id}/rows',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_wtable_rows_list',
            'returns'     => array('list' => 'WorkspaceTableRow'),
            'summary'     => 'The rows of a workflow table',
            'description' => 'The rows of a table the owner reaches, in their order. ' . $cells . ' A formula\'s error is in formula_errors by column id (ref, cycle and the like), its cell null.'
                . ' With where, only the rows meeting its conditions, joined by join (all or any); with sort, sorted by that column (dir asc or desc), empty cells last. total is then the count of the rows kept, and the cursor pages through them: keep the same where and sort while paging.',
            'params'      => array(
                $id,
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 100),
                array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200),
                array('name' => 'where', 'in' => 'query', 'type' => 'string', 'max_length' => 4000,
                    'description' => 'A JSON list of at most ' . WS_WTABLE_WHERE_MAX . ' conditions, each {"column": column id, "op": eq|neq|lt|gt|lte|gte|contains|not_contains|starts|empty|not_empty, "value": text}. A column compares by its type: text without case (contains, starts), number, money and formula by size (money in minor units, 1250 for 12.50), a date as YYYY-MM-DD or YYYY-MM-DD HH:MM, a status by its key, a checkbox as 1 or 0, a person or a linked record by its number, a list by an item it holds or not (contains, not_contains). An unfit comparison or value answers 422.'),
                array('name' => 'join', 'in' => 'query', 'type' => 'enum', 'values' => ws_wtable_where_joins(), 'default' => 'all', 'description' => 'all: every condition holds; any: one does.'),
                array('name' => 'sort', 'in' => 'query', 'type' => 'int', 'min' => 0, 'default' => 0, 'description' => 'The column id the rows are sorted by; 0 keeps the table\'s order.'),
                array('name' => 'dir', 'in' => 'query', 'type' => 'enum', 'values' => ws_wtable_sort_directions(), 'default' => 'asc', 'description' => 'desc reverses the order: by no column, the last row first.'),
                array('name' => 'key', 'in' => 'query', 'type' => 'string', 'max_length' => 2000,
                    'description' => 'The value of the table\'s key column (a column whose options hold key: true, filled in and different on every row): the list holds the one row with that key, or none. A text key is matched without case, a number key by its value. A table without a key column answers 422.'),
            ),
        ),

        array(
            'id'          => 'workspace.table_rows.get',
            'method'      => 'GET',
            'path'        => '/workspace/tables/{id}/rows/{row_id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_wtable_rows_get',
            'returns'     => 'WorkspaceTableRow',
            'summary'     => 'One row of a workflow table',
            'description' => 'A row of a table the owner reaches, with its revision, the number a change sends back.',
            'params'      => array($id, $row_id),
        ),

        array(
            'id'          => 'workspace.table_rows.create',
            'method'      => 'POST',
            'path'        => '/workspace/tables/{id}/rows',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtable_rows_create',
            'dry_run'     => true,
            'returns'     => 'WorkspaceTableRow',
            'status'      => 201,
            'summary'     => 'Add a row to a workflow table',
            'description' => 'Adds a row at the end of a table the owner may change. A table has at most ' . $limits['rows'] . ' rows: one more is refused with 409 too_many_rows. Cells refused answer 422 with field cells.<column id> and each column\'s reason in the message (not_number, not_minor_units, not_date, not_status, unknown_column, computed and the like).',
            'params'      => array($id, array('name' => 'cells', 'in' => 'body', 'type' => 'object', 'description' => $cells)),
        ),

        array(
            'id'           => 'workspace.table_rows.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/tables/{id}/rows/{row_id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_wtable_rows_update',
            'dry_run'      => true,
            'returns'      => 'WorkspaceTableRow',
            'summary'      => 'Change a row of a workflow table',
            'description'  => 'Writes the cells sent over what the row has; the others stay. revision is the one the row was read at: a row changed since is refused with 409 changed_meanwhile, its revision now in the message. Cells refused answer 422 as on a new row.',
            'params'       => array(
                $id,
                $row_id,
                array('name' => 'revision', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The revision the row was read at.'),
                array('name' => 'cells', 'in' => 'body', 'type' => 'object', 'required' => true, 'description' => $cells),
            ),
        ),

        array(
            'id'          => 'workspace.table_rows.move',
            'method'      => 'POST',
            'path'        => '/workspace/tables/{id}/rows/{row_id}/move',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtable_rows_move',
            'dry_run'     => true,
            'returns'     => 'WorkspaceTableRow',
            'summary'     => 'Move a row of a workflow table',
            'description' => 'Puts a row at another place; the rows between move one place to make room. Its revision stays, its cells being what they were.',
            'params'      => array($id, $row_id, array('name' => 'position', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The place it goes to, 1 the first; past the end it goes last.')),
        ),

        array(
            'id'          => 'workspace.table_rows.delete',
            'method'      => 'DELETE',
            'path'        => '/workspace/tables/{id}/rows/{row_id}',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtable_rows_delete',
            'dry_run'     => true,
            'returns'     => 'WorkspaceTableRow',
            'summary'     => 'Delete a row of a workflow table',
            'description' => 'Deletes a row of a table the owner may change; the answer is the row as it was, and the rows after it move up one. The row goes to the table\'s bin for the days the site\'s Recycle Bin keeps (30 unless changed): GET /workspace/tables/{id}/deleted-rows lists it and POST /workspace/tables/{id}/deleted-rows/{bin_id}/restore brings it back. With revision, a row changed since is refused with 409 changed_meanwhile. POST /workspace/tables/{id}/rows/{row_id}/delete does the same for servers that refuse DELETE.',
            'params'      => array($id, $row_id, array('name' => 'revision', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'The revision the row was read at: deleted only when it is still the row\'s.')),
        ),

        array(
            'id'          => 'workspace.table_rows.delete_post',
            'method'      => 'POST',
            'path'        => '/workspace/tables/{id}/rows/{row_id}/delete',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtable_rows_delete',
            'dry_run'     => true,
            'returns'     => 'WorkspaceTableRow',
            'summary'     => 'Delete a row of a workflow table, addressed as a POST',
            'description' => 'The same as DELETE /workspace/tables/{id}/rows/{row_id}, for clients and servers that cannot send DELETE.',
            'params'      => array($id, $row_id, array('name' => 'revision', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'The revision the row was read at.')),
        ),

        // The table's bin of rows (wtable_bin.php): whoever may change the
        // table sees it and restores a row, as they delete one.
        array(
            'id'          => 'workspace.table_rows.deleted',
            'method'      => 'GET',
            'path'        => '/workspace/tables/{id}/deleted-rows',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_wtable_rows_deleted',
            'returns'     => array('list' => 'WorkspaceTableBinRow'),
            'summary'     => 'Deleted rows of a workflow table',
            'description' => 'The rows of a table the owner may change that are in its bin, the latest deleted first: bin_id names the entry, id the row\'s id when it was deleted, deleted_by and deleted_at who and when, expires_at when it is deleted for good. A table shared with them at view is refused with 403.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.table_rows.restore',
            'method'      => 'POST',
            'path'        => '/workspace/tables/{id}/deleted-rows/{bin_id}/restore',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtable_rows_restore',
            'dry_run'     => true,
            'returns'     => 'WorkspaceTableRow',
            'summary'     => 'Restore a deleted row of a workflow table',
            'description' => 'Brings a row back from the table\'s bin at the end of the table, with its cells and its links to site records; the answer is the row. It keeps its id, unless another row was given that id meanwhile: then it has a new one. A full table refuses it with 409 too_many_rows.',
            'params'      => array($id, array('name' => 'bin_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)),
        ),

        // The shares of a table (wtable_shares.php). Sharing widens who reads
        // a table, so the writes are the person's own: from their team devices
        // session only, offered to no MCP client as tools, and refused to the
        // application an assistant works through. Reading them stays a tool.
        array(
            'id'          => 'workspace.table_shares.list',
            'method'      => 'GET',
            'path'        => '/workspace/tables/{id}/shares',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_wtable_shares_list',
            'returns'     => array('list' => 'WorkspaceTableShare'),
            'summary'     => 'Whom a workflow table is shared with',
            'description' => 'The people and channels a table the owner reaches is shared with, oldest first. target_type is user (target_id a user id) or channel. effective_level is what the share gives now: a person who left the team gets \'\'. A channel the owner does not read is left out, but for the table\'s owner, who sees it without a name.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.table_shares.set',
            'method'      => 'POST',
            'path'        => '/workspace/tables/{id}/shares',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtable_shares_set',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceTableShare',
            'status'      => array(201, 200),
            'summary'     => 'Share a workflow table',
            'description' => 'Shares a table of the owner\'s own with a colleague (view or edit) or a channel they read, public or private (view only), or changes the level of the share made before with the same one (200; a new share answers 201). Only the table\'s owner shares it, administrators included in the refusal (403). Sharing widens who reads the table, so only a team devices session (a Bearer token from POST /auth/login) may share; an application key and an OAuth connection are refused with 403 forbidden, and this is no MCP tool. A person newly shared with is told in their bell (inbox kind wtable_shared). A table shared in a public channel announces its rows\' changes (workspace.wtable.row.*). At most ' . WS_WTABLE_MAX_SHARES . ' shares a table: one more is refused with 409 too_many_shares.',
            'params'      => array(
                $id,
                array('name' => 'target_type', 'in' => 'body', 'type' => 'enum', 'values' => array('user', 'channel'), 'required' => true, 'description' => 'user for a colleague, channel for a channel.'),
                array('name' => 'target_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The user id, or the channel id.'),
                array('name' => 'level', 'in' => 'body', 'type' => 'enum', 'values' => array('view', 'edit'), 'default' => 'view', 'description' => 'view or edit for a person; a channel is shared at view.'),
            ),
        ),

        array(
            'id'          => 'workspace.table_shares.delete',
            'method'      => 'DELETE',
            'path'        => '/workspace/tables/{id}/shares/{share_id}',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtable_shares_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceTableShare',
            'summary'     => 'Take a share of a workflow table back',
            'description' => 'Takes a share of a table of the owner\'s own back; the answer is the share as it was. Only the table\'s owner may (403 for anybody else); a share that is not there is a 404. From a team devices session only, as sharing is: an application key and an OAuth connection are refused with 403 forbidden, and this is no MCP tool. POST /workspace/tables/{id}/shares/{share_id}/delete does the same for servers that refuse DELETE.',
            'params'      => array($id, $share_id),
        ),

        array(
            'id'          => 'workspace.table_shares.delete_post',
            'method'      => 'POST',
            'path'        => '/workspace/tables/{id}/shares/{share_id}/delete',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_wtable_shares_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceTableShare',
            'summary'     => 'Take a share of a workflow table back, addressed as a POST',
            'description' => 'The same as DELETE /workspace/tables/{id}/shares/{share_id}, for clients and servers that cannot send DELETE.',
            'params'      => array($id, $share_id),
        ),
    );
}

/* ---------------------------------------------------------------------------
   Presenters
   --------------------------------------------------------------------------- */

/**
 * A table as the API hands it out; columns only when given (one table).
 *
 * @param array      $table   ws_wtables row with access
 * @param array|null $columns ws_wtable_columns()
 * @return array
 */
function ws_api_wtable_present($table, $columns = null)
{
    return array(
        'id'          => (int) $table['id'],
        'name'        => (string) $table['name'],
        'description' => (string) ($table['description'] ?? ''),
        'access'      => (string) $table['access'],
        'owner_id'    => (int) $table['user_id'],
        'row_count'   => (int) $table['row_count'],
        'revision'    => (int) $table['revision'],
        'columns'     => ($columns === null) ? null : array_values(array_map('ws_api_wtable_column_present', $columns)),
        'created_at'  => api_time($table['created_at']),
        'updated_at'  => api_time($table['updated_at']),
    );
}

// What ws_api_wtable_present() returns.
function ws_api_wtable_schema()
{
    return array(
        'id'          => 'integer',
        'name'        => 'string',
        'description' => 'string',
        'access'      => 'string',
        'owner_id'    => 'integer',
        'row_count'   => 'integer',
        'revision'    => 'integer',
        'columns'     => 'WorkspaceTableColumn[]?',
        'created_at'  => 'string?',
        'updated_at'  => 'string?',
    );
}

/**
 * A column, its options an object (empty for a type that takes none).
 *
 * @param array $column ws_wtable_column_from_row()
 * @return array
 */
function ws_api_wtable_column_present($column)
{
    return array(
        'id'         => (int) $column['id'],
        'table_id'   => (int) $column['table_id'],
        'name'       => (string) $column['name'],
        'type'       => (string) $column['type'],
        'options'    => (object) $column['options'],
        'position'   => (int) $column['position'],
        'created_at' => api_time($column['created_at']),
    );
}

// What ws_api_wtable_column_present() returns.
function ws_api_wtable_column_schema()
{
    return array(
        'id'         => 'integer',
        'table_id'   => 'integer',
        'name'       => 'string',
        'type'       => 'string',
        'options'    => 'object',
        'position'   => 'integer',
        'created_at' => 'string?',
    );
}

/**
 * A row as the API hands it out: as the webhook events carry it
 * (ws_wtable_row_present()).
 *
 * @param array $row ws_wtable_row_out()
 * @return array
 */
function ws_api_wtable_row_present($row)
{
    return ws_wtable_row_present($row);
}

/**
 * A row in a table's bin: the row as it was, with its entry.
 *
 * @param array $row ws_wtable_bin_rows()
 * @return array
 */
function ws_api_wtable_bin_row_present($row)
{
    return ws_wtable_row_present($row) + array(
        'bin_id'     => (int) $row['bin_id'],
        'deleted_by' => (int) $row['deleted_by'],
        'deleted_at' => api_time($row['deleted_at']),
        'expires_at' => api_time($row['expires_at']),
    );
}

// What ws_api_wtable_bin_row_present() returns.
function ws_api_wtable_bin_row_schema()
{
    return ws_api_wtable_row_schema() + array(
        'bin_id'     => 'integer',
        'deleted_by' => 'integer',
        'deleted_at' => 'string?',
        'expires_at' => 'string?',
    );
}

/**
 * A table in the bin, as its owner sees it.
 *
 * @param array $table ws_wtables_binned()
 * @return array
 */
function ws_api_wtable_deleted_present($table)
{
    return array(
        'id'         => (int) $table['id'],
        'name'       => (string) $table['name'],
        'row_count'  => (int) $table['row_count'],
        'deleted_at' => api_time($table['deleted_at']),
        'expires_at' => api_time($table['expires_at']),
    );
}

// What ws_api_wtable_deleted_present() returns.
function ws_api_wtable_deleted_schema()
{
    return array(
        'id'         => 'integer',
        'name'       => 'string',
        'row_count'  => 'integer',
        'deleted_at' => 'string?',
        'expires_at' => 'string?',
    );
}

// What ws_api_wtable_row_present() returns.
function ws_api_wtable_row_schema()
{
    return array(
        'id'             => 'integer',
        'number'         => 'integer',
        'position'       => 'integer',
        'revision'       => 'integer',
        'cells'          => 'object',
        'formula_errors' => 'object',
        'created_by'     => 'integer',
        'created_at'     => 'string?',
        'updated_by'     => 'integer',
        'updated_at'     => 'string?',
    );
}

/**
 * What an import did: the table, the rows added and those refused.
 *
 * @return array
 */
function ws_api_wtable_import_schema()
{
    return array(
        'table'         => 'WorkspaceTable',
        'added'         => 'integer',
        'updated'       => 'integer',
        'refused_count' => 'integer',
        'refused'       => 'WorkspaceTableImportRefusal[]',
        'more'          => 'boolean',
    );
}

/**
 * One refused row of an import: its line in the source (the header is 1),
 * the code, and the refused cells by column name.
 *
 * @return array
 */
function ws_api_wtable_import_refusal_schema()
{
    return array(
        'line'   => 'integer',
        'error'  => 'string',
        'errors' => 'object',
    );
}

/**
 * A share of a table: what it was given and what it gives now.
 *
 * @param array $share ws_wtable_shares_list() row
 * @return array
 */
function ws_api_wtable_share_present($share)
{
    return array(
        'id'              => (int) $share['id'],
        'table_id'        => (int) $share['table_id'],
        'target_type'     => (string) $share['target_type'],
        'target_id'       => (int) $share['target_id'],
        'level'           => (string) $share['level'],
        'effective_level' => (string) ($share['gives'] ?? ''),
        'name'            => (string) ($share['name'] ?? ''),
        'created_by'      => (int) $share['created_by'],
        'created_at'      => api_time($share['created_at']),
    );
}

// What ws_api_wtable_share_present() returns.
function ws_api_wtable_share_schema()
{
    return array(
        'id'              => 'integer',
        'table_id'        => 'integer',
        'target_type'     => 'string',
        'target_id'       => 'integer',
        'level'           => 'string',
        'effective_level' => 'string',
        'name'            => 'string',
        'created_by'      => 'integer',
        'created_at'      => 'string?',
    );
}

/* ---------------------------------------------------------------------------
   Reaching a table
   --------------------------------------------------------------------------- */

/**
 * The tables are there, or the call is answered 503.
 */
function ws_api_wtables_ready()
{
    if (!ws_wtables_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }
}

/**
 * Whether the current application reaches a table the owner reads: always,
 * except for the application Claude works through and a table that reaches
 * the owner only through a channel Claude may not be asked in.
 *
 * @param array $viewer
 * @param array $table with access
 * @return bool
 */
function ws_api_wtable_reachable($viewer, $table)
{
    if (!ws_api_is_claude() || ((string) $table['access'] !== 'view')) {
        return true;
    }

    foreach (ws_wtable_share_rows((int) $table['id']) as $share) {
        if ((string) $share['target_type'] === 'user') {
            if ((int) $share['target_id'] === (int) $viewer['id']) {
                return true;
            }

            continue;
        }

        $channel = ws_channel((int) $share['target_id']);

        if ($channel && ws_can_read_channel($viewer, $channel) && ws_claude_channel_allowed($channel)) {
            return true;
        }
    }

    return false;
}

/**
 * A table the owner reaches, with access, or a 404.
 *
 * @param array $viewer
 * @param int   $id
 * @return array
 */
function ws_api_wtable_or_404($viewer, $id)
{
    ws_api_wtables_ready();

    $table = ws_wtable_for($viewer, (int) $id, 'view');

    if (!$table || !ws_api_wtable_reachable($viewer, $table)) {
        api_fail_not_found(lang('Table'));
    }

    return $table;
}

/**
 * A table the owner may change (access owner or edit), or a 404 / 403.
 *
 * @param array $viewer
 * @param int   $id
 * @return array
 */
function ws_api_wtable_writable($viewer, $id)
{
    $table = ws_api_wtable_or_404($viewer, $id);

    if (!ws_wtable_may($table['access'], 'write')) {
        api_fail(403, 'forbidden', lang('This table was shared with you to read only.'));
    }

    return $table;
}

/**
 * The words of a refusal code: a few have a sentence, the rest name the
 * code, which the route's description explains.
 *
 * @param string $code
 * @return string
 */
function ws_api_wtable_message($code)
{
    switch ((string) $code) {
        case 'changed_meanwhile':
            return lang('The row was changed meanwhile. Read it again and send its revision.');

        case 'too_many_tables':
        case 'too_many_columns':
        case 'too_many_rows':
        case 'too_many_shares':
            return lang(array('string' => 'The limit is reached: {var:1}.', 'vars' => (string) $code));

        case 'key_taken':
            return lang('Another column is already the key: take the key off it first.');

        case 'key_not_unique':
            return lang('The column cannot be the key while rows are empty in it or repeat a value.');

        case 'key_type':
            return lang('Only a text or number column can be the key.');
    }

    return lang(array('string' => 'Refused: {var:1}.', 'vars' => (string) $code));
}

/**
 * A refused write, as the API answers it.
 *
 * @param array  $result a ws_wtable_*_check() or write result
 * @param string $what   what a 404 is about, as the person reads it
 */
function ws_api_wtable_refused($result, $what)
{
    $code = (string) $result['error'];
    $field = ((string) ($result['field'] ?? '') !== '') ? (string) $result['field'] : null;

    switch ((int) $result['status']) {
        case 403:
            api_fail(403, 'forbidden', lang('Only the owner of the table may do this.'));
            break;

        case 404:
            api_fail_not_found(($code === 'share_not_found') ? lang('Table share') : $what);
            break;

        case 409:
            $message = ws_api_wtable_message($code);

            if (isset($result['revision'])) {
                $message .= ' ' . lang(array('string' => 'Revision now: {var:1}.', 'vars' => (string) (int) $result['revision']));
            }

            // The rows at fault, by the numbers the table shows.
            if (!empty($result['rows'])) {
                $message .= ' ' . lang(array('string' => 'Rows by number: {var:1} ({var:2} in all).', 'vars' => array(implode(', ', array_map('intval', $result['rows'])), (int) ($result['rows_count'] ?? count($result['rows'])))));
            }

            api_fail(409, $code, $message, $field);
            break;

        case 500:
            api_fail_server();
            break;

        case 503:
            api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
            break;
    }

    if (($code === 'cells_invalid') && !empty($result['errors'])) {
        $parts = array();

        foreach ($result['errors'] as $column_id => $reason) {
            $parts[] = $column_id . ' ' . $reason;
        }

        api_fail_validation(lang(array('string' => 'These cells were refused: {var:1}.', 'vars' => implode(', ', $parts))), 'cells.' . key($result['errors']));
    }

    api_fail_validation(ws_api_wtable_message($code), $field);
}

/**
 * The fields sent, of those named; a nullable text sent as null reaches the
 * handler as ''.
 *
 * @param array    $params
 * @param string[] $fields
 * @return array
 */
function ws_api_wtable_input($params, $fields)
{
    $data = array();

    foreach ($fields as $field) {
        if (array_key_exists($field, $params)) {
            $data[$field] = $params[$field];
        }
    }

    return $data;
}

/* ---------------------------------------------------------------------------
   Tables
   --------------------------------------------------------------------------- */

function ws_api_wtables_list($params)
{
    $viewer = ws_api_viewer();

    ws_api_wtables_ready();

    $rows = array();

    foreach (ws_wtables_reached($viewer, (string) ($params['scope'] ?? 'all')) as $table) {
        if (ws_api_wtable_reachable($viewer, $table)) {
            $rows[] = ws_api_wtable_present($table);
        }
    }

    api_ok_list($rows, count($rows));
}

function ws_api_wtables_get($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_or_404($viewer, $params['id']);

    api_ok(ws_api_wtable_present($table, ws_wtable_columns((int) $table['id'])));
}

function ws_api_wtables_create($params)
{
    $viewer = ws_api_viewer();

    ws_api_wtables_ready();

    $data = ws_api_wtable_input($params, array('name', 'description'));
    $checked = ws_wtable_create_check($viewer, $data);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Table'));
    }

    api_dry_run_stop('created', 'workspace_table', array('name' => $checked['sets']['name']));

    $result = ws_wtable_create($viewer, $data);

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Table'));
    }

    $table = ws_api_wtable_or_404($viewer, $result['table_id']);

    api_ok(ws_api_wtable_present($table, array()), 201);
}

/**
 * The table of a site file the application may read (one GET /files
 * lists), read as the panel reads an upload, or the API's refusal.
 *
 * @param int $file_id
 * @param int $max lines to keep, header included
 * @return array ws_wtable_import_file_table()
 */
function ws_api_wtable_import_file($file_id, $max)
{
    api_require_scope('files:read');

    $row = api_row(api_file_select() . " WHERE files.id = '" . (int) $file_id . "' AND " . api_files_public_clause() . " LIMIT 1");
    $name = pg_basename((string) ($row['name'] ?? ''));
    // Through the local copy: the bytes may be kept in object storage.
    $path = ($row === null) ? '' : pg_file_local_copy((int) $file_id);

    if (($row === null) || ($name === '') || ($path === '') || !is_file($path)) {
        api_fail_not_found(lang('File'));
    }

    if ((int) filesize($path) > (int) pg_upload_limits()['file_max']) {
        api_fail_validation(lang('The file is larger than an upload may be.'), 'file_id');
    }

    return ws_wtable_import_file_table($path, $name, $max);
}

/**
 * The pairing asked for: source index => column id from map (by source
 * column name), else the columns matched by name.
 *
 * @param string[]   $names
 * @param array      $columns
 * @param array|null $map source column name => column id
 * @return array source index => column id
 */
function ws_api_wtable_import_map($names, $columns, $map)
{
    if (!is_array($map)) {
        return ws_wtable_import_match($names, $columns)['map'];
    }

    $pairs = array();

    foreach ($map as $source => $column_id) {
        $index = array_search((string) $source, $names, true);

        if ($index === false) {
            api_fail_validation(lang(array('string' => 'The source has no column {var:1}.', 'vars' => (string) $source)), 'map');
        }

        if (!is_int($column_id) && !(is_string($column_id) && ctype_digit($column_id))) {
            api_fail_validation(lang(array('string' => 'Refused: {var:1}.', 'vars' => 'column_id')), 'map');
        }

        if ((int) $column_id === 0) {
            continue;
        }

        if (!isset($columns[(int) $column_id]) || !ws_wtable_type_stores((string) $columns[(int) $column_id]['type'])) {
            api_fail_validation(lang(array('string' => 'Refused: {var:1}.', 'vars' => 'unknown_column ' . (int) $column_id)), 'map');
        }

        $pairs[(int) $index] = (int) $column_id;
    }

    return $pairs;
}

function ws_api_wtables_import($params)
{
    $viewer = ws_api_viewer();

    ws_api_wtables_ready();

    $file_id = (int) ($params['file_id'] ?? 0);
    $note_id = (int) ($params['note_id'] ?? 0);
    $table_id = (int) ($params['table_id'] ?? 0);
    $name = trim((string) ($params['name'] ?? ''));

    if (($file_id > 0) === ($note_id > 0)) {
        api_fail_validation(lang('Send file_id or note_id, one of them.'), 'file_id');
    }

    if (($table_id > 0) === ($name !== '')) {
        api_fail_validation(lang('Send table_id or name, one of them.'), 'table_id');
    }

    if (($table_id > 0) && isset($params['types'])) {
        api_fail_validation(lang('types goes with name, a new table.'), 'types');
    }

    if (($name !== '') && isset($params['map'])) {
        api_fail_validation(lang('map goes with table_id, an existing table.'), 'map');
    }

    $table = ($table_id > 0) ? ws_api_wtable_writable($viewer, $table_id) : null;
    $limits = ws_wtable_limits();

    if ($file_id > 0) {
        $read = ws_api_wtable_import_file($file_id, $limits['rows'] + 1);
    } else {
        ws_api_note_or_404($viewer, $note_id);
        $read = ws_wtable_import_note_read($viewer, $note_id, (int) ($params['note_table'] ?? 0), $limits['rows'] + 1);
    }

    if (!$read['ok']) {
        api_fail_validation($read['error'], ($file_id > 0) ? 'file_id' : 'note_table');
    }

    if (count($read['table']) < 2) {
        api_fail_validation(ws_api_wtable_message('no_rows'), 'rows');
    }

    if (count($read['table'][0]) > $limits['columns']) {
        api_fail(409, 'too_many_columns', ws_api_wtable_message('too_many_columns'));
    }

    $names = ws_wtable_import_names($read['table'][0]);
    $kept = array('names' => $names, 'rows' => array_slice($read['table'], 1));

    if ($table !== null) {
        $choice = array('map' => ws_api_wtable_import_map($names, ws_wtable_columns((int) $table['id']), isset($params['map']) ? (array) $params['map'] : null));
    } else {
        $types = isset($params['types']) ? (array) $params['types'] : array();

        foreach ($types as $source => $type) {
            if (!in_array($source, $names, true)) {
                api_fail_validation(lang(array('string' => 'The source has no column {var:1}.', 'vars' => (string) $source)), 'types');
            }

            if (!is_string($type) || (($type !== '') && !in_array($type, ws_wtable_import_types(), true))) {
                api_fail_validation(ws_api_wtable_message('type_unknown'), 'types');
            }
        }

        $choice = array('name' => $name, 'plan' => ws_wtable_import_plan($names, $kept['rows'], $types));
    }

    $checked = ws_wtable_import_check($viewer, $table, $kept, $choice);

    if (!$checked['ok']) {
        ws_api_wtable_refused(isset($checked['column']) ? array_merge($checked, array('field' => 'types')) : $checked, lang('Table'));
    }

    $detail = array(
        'table_id'      => $table ? (int) $table['id'] : null,
        'columns'       => $checked['columns'],
        'rows'          => $checked['rows'],
        'refused_count' => $checked['refused_count'],
        'refused'       => ws_api_wtable_import_refused($checked['refused']),
        'more'          => !empty($read['more']),
    );

    api_dry_run_stop($table ? 'updated' : 'created', 'workspace_table', $detail);

    @set_time_limit(300);

    $result = ws_api_as_owner(function () use ($viewer, $table, $kept, $choice) {
        if ($table !== null) {
            return array('ok' => true, 'table_id' => (int) $table['id'], 'report' => ws_wtable_import_rows($viewer, $table, $kept['rows'], $choice['map']));
        }

        return ws_wtable_import_create($viewer, $choice['name'], $choice['plan'], $kept['rows']);
    });

    if (!$result['ok']) {
        ws_api_wtable_refused($result + array('field' => '', 'status' => 422), lang('Table'));
    }

    $made = ws_api_wtable_or_404($viewer, (int) $result['table_id']);

    api_ok(array(
        'table'         => ws_api_wtable_present($made, ws_wtable_columns((int) $made['id'])),
        'added'         => (int) $result['report']['added'],
        'updated'       => (int) ($result['report']['updated'] ?? 0),
        'refused_count' => (int) $result['report']['refused_count'],
        'refused'       => ws_api_wtable_import_refused($result['report']['refused']),
        'more'          => !empty($read['more']),
    ), $table ? 200 : 201);
}

/**
 * Refused rows as the API gives them: errors an object even when empty.
 *
 * @param array $refused list of line, error, errors
 * @return array
 */
function ws_api_wtable_import_refused($refused)
{
    $out = array();

    foreach ((array) $refused as $line) {
        $out[] = array('line' => (int) $line['line'], 'error' => (string) $line['error'], 'errors' => (object) $line['errors']);
    }

    return $out;
}

function ws_api_wtables_update($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_writable($viewer, $params['id']);
    $data = ws_api_wtable_input($params, array('name', 'description'));
    $checked = ws_wtable_update_check($viewer, $table, $data);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Table'));
    }

    api_dry_run_stop('updated', 'workspace_table', array('id' => (int) $table['id'], 'fields' => array_keys($checked['sets'])));

    $result = ws_wtable_update($viewer, $table, $data);

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Table'));
    }

    $table = ws_api_wtable_or_404($viewer, $table['id']);

    api_ok(ws_api_wtable_present($table, ws_wtable_columns((int) $table['id'])));
}

function ws_api_wtables_delete($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_or_404($viewer, $params['id']);
    $checked = ws_wtable_delete_check($viewer, $table);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Table'));
    }

    $before = ws_api_wtable_present($table, ws_wtable_columns((int) $table['id']));

    api_dry_run_stop('deleted', 'workspace_table', array('id' => (int) $table['id'], 'rows' => (int) $table['row_count']));

    $result = ws_wtable_delete($viewer, $table);

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Table'));
    }

    api_ok($before);
}

function ws_api_wtables_deleted($params)
{
    $viewer = ws_api_viewer();

    ws_api_wtables_ready();

    $rows = array_map('ws_api_wtable_deleted_present', ws_wtables_binned($viewer));

    api_ok_list($rows, count($rows));
}

function ws_api_wtables_restore($params)
{
    $viewer = ws_api_viewer();

    ws_api_wtables_ready();

    $table = ws_wtable_binned((int) $params['id']);
    $checked = ws_wtable_restore_check($viewer, $table);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Deleted table'));
    }

    api_dry_run_stop('restored', 'workspace_table', array('id' => (int) $table['id'], 'rows' => (int) $table['row_count']));

    $result = ws_wtable_restore($viewer, $table);

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Deleted table'));
    }

    $table = ws_api_wtable_or_404($viewer, $table['id']);

    api_ok(ws_api_wtable_present($table, ws_wtable_columns((int) $table['id'])));
}

/* ---------------------------------------------------------------------------
   Columns
   --------------------------------------------------------------------------- */

/**
 * A column of the table, or a 404.
 *
 * @param array $table
 * @param int   $column_id
 * @return array
 */
function ws_api_wtable_column_or_404($table, $column_id)
{
    $column = ws_wtable_column((int) $table['id'], (int) $column_id);

    if (!$column) {
        api_fail_not_found(lang('Table column'));
    }

    return $column;
}

function ws_api_wtable_columns_create($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_writable($viewer, $params['id']);
    $data = ws_api_wtable_input($params, array('name', 'type', 'options'));
    $checked = ws_wtable_column_create_check($viewer, $table, $data);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Table'));
    }

    api_dry_run_stop('created', 'workspace_table_column', array('table_id' => (int) $table['id'], 'name' => $checked['sets']['name'], 'type' => $checked['sets']['type'], 'options' => (object) $checked['sets']['options']));

    $result = ws_wtable_column_create($viewer, $table, $data);

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Table'));
    }

    api_ok(ws_api_wtable_column_present(ws_api_wtable_column_or_404($table, $result['column_id'])), 201);
}

function ws_api_wtable_columns_update($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_writable($viewer, $params['id']);
    $column = ws_api_wtable_column_or_404($table, $params['column_id']);
    $data = ws_api_wtable_input($params, array('name', 'options'));
    $checked = ws_wtable_column_update_check($viewer, $table, $column, $data);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Table column'));
    }

    api_dry_run_stop('updated', 'workspace_table_column', array('id' => (int) $column['id'], 'fields' => array_keys($checked['sets'])));

    $result = ws_wtable_column_update($viewer, $table, $column, $data);

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Table column'));
    }

    api_ok(ws_api_wtable_column_present(ws_api_wtable_column_or_404($table, $column['id'])));
}

function ws_api_wtable_columns_retype($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_writable($viewer, $params['id']);
    $column = ws_api_wtable_column_or_404($table, $params['column_id']);
    $data = ws_api_wtable_input($params, array('type', 'options'));
    $checked = ws_wtable_column_retype_check($viewer, $table, $column, $data);

    if (!$checked['ok']) {
        ws_api_wtable_retype_refused($checked);
    }

    api_dry_run_stop('updated', 'workspace_table_column', array('id' => (int) $column['id'], 'type' => $checked['to']['type'], 'cells' => count($checked['changed'])));

    $result = ws_wtable_column_retype($viewer, $table, $column, $data);

    if (!$result['ok']) {
        ws_api_wtable_retype_refused($result);
    }

    api_ok(ws_api_wtable_column_present(ws_api_wtable_column_or_404($table, $column['id'])));
}

/**
 * A refused type change: the cells that do not convert listed in the
 * message (at most 50, by row number, a binned row marked), else the
 * table's usual refusal.
 *
 * @param array $result ws_wtable_column_retype_check()
 */
function ws_api_wtable_retype_refused($result)
{
    if (((string) $result['error'] === 'cells_not_converted') && !empty($result['faults'])) {
        $parts = array();

        foreach (array_slice($result['faults'], 0, 50) as $fault) {
            $parts[] = 'row ' . (int) $fault['label'] . (!empty($fault['bin']) ? ' (bin)' : '') . ' ' . (string) $fault['error'];
        }

        $more = count($result['faults']) - count($parts);

        api_fail_validation(lang(array('string' => 'These cells do not convert: {var:1}.', 'vars' => implode(', ', $parts) . (($more > 0) ? ' +' . $more : ''))), 'type');
    }

    ws_api_wtable_refused($result, lang('Table column'));
}

function ws_api_wtable_columns_delete($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_writable($viewer, $params['id']);
    $column = ws_api_wtable_column_or_404($table, $params['column_id']);
    $checked = ws_wtable_column_delete_check($viewer, $table, $column);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Table column'));
    }

    api_dry_run_stop('deleted', 'workspace_table_column', array('id' => (int) $column['id'], 'table_id' => (int) $table['id']));

    $result = ws_wtable_column_delete($viewer, $table, $column);

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Table column'));
    }

    api_ok(ws_api_wtable_column_present($column));
}

/* ---------------------------------------------------------------------------
   Rows
   --------------------------------------------------------------------------- */

/**
 * A row of the table as stored, or a 404.
 *
 * @param array $table
 * @param int   $row_id
 * @return array
 */
function ws_api_wtable_row_or_404($table, $row_id)
{
    $row = ws_wtable_row((int) $table['id'], (int) $row_id);

    if (!$row) {
        api_fail_not_found(lang('Table row'));
    }

    return $row;
}

/**
 * A row as it is now, as the API answers it.
 *
 * @param array $table
 * @param int   $row_id
 * @return array
 */
function ws_api_wtable_row_answer($table, $row_id)
{
    return ws_api_wtable_row_present(ws_wtable_row_out(ws_api_wtable_row_or_404($table, $row_id), ws_wtable_columns((int) $table['id'])));
}

function ws_api_wtable_rows_list($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_or_404($viewer, $params['id']);
    $after = 0;
    $after_id = 0;

    if (isset($params['cursor']) && ($params['cursor'] !== '')) {
        $cursor = api_cursor_decode($params['cursor']);

        if ($cursor === null) {
            api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
        }

        $after = (int) $cursor['v'];
        $after_id = (int) ($cursor['i'] ?? 0);
    }

    $limit = (int) ($params['limit'] ?? 100);
    $columns = ws_wtable_columns((int) $table['id']);

    if (isset($params['key']) && ((string) $params['key'] !== '')) {
        if (ws_wtable_key_column($columns) === null) {
            api_fail_validation(lang('The table has no key column.'), 'key');
        }

        $found = ws_wtable_row_keyed((int) $table['id'], (string) $params['key']);
        $rows = $found ? array(ws_api_wtable_row_present(ws_wtable_row_out($found, $columns))) : array();

        api_ok_list($rows, $limit, null, count($rows));

        return;
    }
    $query = ws_wtable_query((string) ($params['where'] ?? ''), (string) ($params['join'] ?? 'all'), (int) ($params['sort'] ?? 0), (string) ($params['dir'] ?? 'asc'));

    if (ws_wtable_query_active($query)) {
        ws_api_wtable_rows_query($table, $columns, $query, $limit, $after, $after_id, isset($cursor));

        return;
    }

    $page = ws_wtable_rows_page((int) $table['id'], $columns, $after, $limit, $after_id);
    $last = end($page['rows']);

    api_ok_list(array_map('ws_api_wtable_row_present', $page['rows']), $limit,
        ($page['next'] !== null) ? api_cursor_encode($page['next'], (int) $last['id']) : null, (int) $table['row_count']);
}

/**
 * The rows a query keeps, a page of them: refused with 422 when it does not
 * fit the table; worked out over every row (a table holds at most 5000), so
 * the cursor is an offset into the rows kept, its id 0. A cursor of the
 * plain listing (an id past 0) answers 400.
 *
 * @param array $table
 * @param array $columns
 * @param array $query     ws_wtable_query()
 * @param int   $limit
 * @param int   $from      the cursor's offset
 * @param int   $cursor_id the cursor's id
 * @param bool  $paging    a cursor was given
 * @return void
 */
function ws_api_wtable_rows_query($table, $columns, $query, $limit, $from, $cursor_id, $paging)
{
    if ($paging && ($cursor_id !== 0)) {
        api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
    }

    $refusal = ws_wtable_query_refusal($columns, $query);

    if ($refusal !== '') {
        // The sort is named only when the conditions alone fit.
        api_fail(422, 'validation_failed', $refusal, (ws_wtable_query_refusal($columns, array('sort' => 0) + $query) === '') ? 'sort' : 'where');
    }

    $kept = ws_wtable_query_rows($columns, ws_wtable_rows((int) $table['id'], $columns), $query);
    $page = ws_wtable_query_page($kept, $from, $limit);

    api_ok_list(array_map('ws_api_wtable_row_present', $page['rows']), $limit,
        ($page['next'] !== null) ? api_cursor_encode($page['next'], 0) : null, count($kept));
}

function ws_api_wtable_rows_get($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_or_404($viewer, $params['id']);

    api_ok(ws_api_wtable_row_answer($table, $params['row_id']));
}

function ws_api_wtable_rows_create($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_writable($viewer, $params['id']);
    $data = array('cells' => array_key_exists('cells', $params) ? $params['cells'] : array());
    $checked = ws_wtable_row_create_check($viewer, $table, $data);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Table'));
    }

    api_dry_run_stop('created', 'workspace_table_row', array('table_id' => (int) $table['id'], 'cells' => (object) $checked['cells']));

    $result = ws_wtable_row_create($viewer, $table, $data);

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Table'));
    }

    api_ok(ws_api_wtable_row_answer($table, $result['row_id']), 201);
}

function ws_api_wtable_rows_update($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_writable($viewer, $params['id']);
    $row = ws_api_wtable_row_or_404($table, $params['row_id']);
    $data = ws_api_wtable_input($params, array('cells', 'revision'));
    $checked = ws_wtable_row_update_check($viewer, $table, $row, $data);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Table row'));
    }

    api_dry_run_stop('updated', 'workspace_table_row', array('id' => (int) $row['id'], 'cells' => (object) $checked['cells']));

    $result = ws_wtable_row_update($viewer, $table, $row, $data);

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Table row'));
    }

    api_ok(ws_api_wtable_row_answer($table, $row['id']));
}

function ws_api_wtable_rows_move($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_writable($viewer, $params['id']);
    $row = ws_api_wtable_row_or_404($table, $params['row_id']);
    $data = ws_api_wtable_input($params, array('position'));
    $checked = ws_wtable_row_move_check($viewer, $table, $row, $data);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Table row'));
    }

    api_dry_run_stop('moved', 'workspace_table_row', array('id' => (int) $row['id'], 'position' => $checked['position']));

    $result = ws_wtable_row_move($viewer, $table, $row, $data);

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Table row'));
    }

    api_ok(ws_api_wtable_row_answer($table, $row['id']));
}

function ws_api_wtable_rows_delete($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_writable($viewer, $params['id']);
    $row = ws_api_wtable_row_or_404($table, $params['row_id']);
    $data = ws_api_wtable_input($params, array('revision'));
    $checked = ws_wtable_row_delete_check($viewer, $table, $row, $data);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Table row'));
    }

    $before = ws_api_wtable_row_present(ws_wtable_row_out($row, ws_wtable_columns((int) $table['id'])));

    api_dry_run_stop('deleted', 'workspace_table_row', array('id' => (int) $row['id'], 'table_id' => (int) $table['id']));

    $result = ws_wtable_row_delete($viewer, $table, $row, $data);

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Table row'));
    }

    api_ok($before);
}

function ws_api_wtable_rows_deleted($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_writable($viewer, $params['id']);
    $rows = ws_wtable_bin_rows($viewer, $table);

    api_ok_list(array_map('ws_api_wtable_bin_row_present', (array) $rows), count((array) $rows));
}

function ws_api_wtable_rows_restore($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_writable($viewer, $params['id']);
    $bin = ws_wtable_bin_row((int) $table['id'], (int) $params['bin_id']);
    $checked = ws_wtable_row_restore_check($viewer, $table, $bin);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Deleted row'));
    }

    api_dry_run_stop('restored', 'workspace_table_row', array('bin_id' => (int) $bin['id'], 'id' => (int) $bin['row_id'], 'table_id' => (int) $table['id']));

    $result = ws_wtable_row_restore($viewer, $table, $bin);

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Deleted row'));
    }

    api_ok(ws_api_wtable_row_answer(ws_api_wtable_or_404($viewer, $table['id']), $result['row_id']));
}

/* ---------------------------------------------------------------------------
   Shares
   --------------------------------------------------------------------------- */

/**
 * The shares of a table as the application may see them
 * (ws_wtable_shares_list()): to the application Claude works through, a
 * share in a channel where Claude may not be asked is left out.
 *
 * @param array $viewer
 * @param array $table
 * @return array[]
 */
function ws_api_wtable_shares_seen($viewer, $table)
{
    $rows = (array) ws_wtable_shares_list($viewer, $table);

    if (!ws_api_is_claude()) {
        return $rows;
    }

    return array_values(array_filter($rows, function ($share) {
        if ((string) $share['target_type'] !== 'channel') {
            return true;
        }

        $channel = ws_channel((int) $share['target_id']);

        return $channel && ws_claude_channel_allowed($channel);
    }));
}

/**
 * One share of a table as it is now, or a 404.
 *
 * @param array $viewer
 * @param array $table
 * @param int   $share_id
 * @return array
 */
function ws_api_wtable_share_answer($viewer, $table, $share_id)
{
    $found = null;

    foreach (ws_api_wtable_shares_seen($viewer, $table) as $share) {
        if ((int) $share['id'] === (int) $share_id) {
            $found = $share;
            break;
        }
    }

    if ($found === null) {
        api_fail_not_found(lang('Table share'));
    }

    return ws_api_wtable_share_present($found);
}

function ws_api_wtable_shares_list($params)
{
    $viewer = ws_api_viewer();
    $table = ws_api_wtable_or_404($viewer, $params['id']);
    $rows = array_map('ws_api_wtable_share_present', ws_api_wtable_shares_seen($viewer, $table));

    api_ok_list($rows, count($rows));
}

// Sharing widens who reads a table: the person's own decision, from their
// team devices session, never an application key or an OAuth connection. No
// MCP tool, and the application an assistant works through is refused too.
function ws_api_wtable_shares_set($params)
{
    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can share a table or take a share back; an application key or an OAuth connection cannot do it for them.'));

    $viewer = ws_api_viewer();

    if (ws_api_is_assistant()) {
        api_fail(403, 'forbidden', lang('An assistant does not share a table; the person does.'));
    }

    $table = ws_api_wtable_or_404($viewer, $params['id']);
    $data = array(
        'target_type' => (string) ($params['target_type'] ?? ''),
        'target_id'   => (int) ($params['target_id'] ?? 0),
        'level'       => (string) ($params['level'] ?? 'view'),
    );

    // The checks ws_wtable_share_set() makes, made first so that a dry run
    // meets the same refusals.
    $checked = ws_wtable_share_set_check($viewer, $table, $data);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Table'));
    }

    api_dry_run_stop(is_array($checked['existing']) ? 'updated' : 'created', 'workspace_table_share', array(
        'table_id'    => (int) $table['id'],
        'target_type' => $checked['target_type'],
        'target_id'   => $checked['target_id'],
        'level'       => $checked['level'],
        'existing'    => is_array($checked['existing']) ? (int) $checked['existing']['id'] : null,
    ));

    $result = ws_api_as_owner(function () use ($viewer, $table, $data) {
        return ws_wtable_share_set($viewer, $table, $data);
    });

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Table'));
    }

    api_ok(ws_api_wtable_share_answer($viewer, $table, $result['share_id']), $result['created'] ? 201 : 200);
}

// Taking a share back is the owner's own decision as sharing is.
function ws_api_wtable_shares_delete($params)
{
    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can share a table or take a share back; an application key or an OAuth connection cannot do it for them.'));

    $viewer = ws_api_viewer();

    if (ws_api_is_assistant()) {
        api_fail(403, 'forbidden', lang('An assistant does not share a table; the person does.'));
    }

    $table = ws_api_wtable_or_404($viewer, $params['id']);
    $checked = ws_wtable_share_remove_check($viewer, $table, (int) $params['share_id']);

    if (!$checked['ok']) {
        ws_api_wtable_refused($checked, lang('Table'));
    }

    $before = ws_api_wtable_share_answer($viewer, $table, (int) $checked['share']['id']);

    api_dry_run_stop('deleted', 'workspace_table_share', array(
        'id'          => (int) $checked['share']['id'],
        'table_id'    => (int) $table['id'],
        'target_type' => (string) $checked['share']['target_type'],
        'target_id'   => (int) $checked['share']['target_id'],
    ));

    $result = ws_api_as_owner(function () use ($viewer, $table, $checked) {
        return ws_wtable_share_remove($viewer, $table, (int) $checked['share']['id']);
    });

    if (!$result['ok']) {
        ws_api_wtable_refused($result, lang('Table'));
    }

    api_ok($before);
}
