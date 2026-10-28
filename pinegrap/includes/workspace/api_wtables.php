<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - workflow tables in the external API: the tables the owner of
 * the application reaches, their columns and rows. The checks are the
 * store's own (includes/workspace/wtables.php, wtable_rows.php), so a dry
 * run meets the refusals the real call meets.
 *
 * Reading and writing tables, columns and rows are MCP tools, as notes are.
 * Deleting a table or a column cannot be undone, so neither is a tool. The
 * application Claude works through reads a table that reaches the owner only
 * through a channel just where Claude may be asked, as it reads the channel
 * itself.
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
        array('name' => 'options', 'in' => 'body', 'type' => 'object', 'description' => 'What the type takes: number and money decimals (0-6; money 2 when left out) and money currency (three letters); date time (true to keep a time HH:MM too); status choices (1-' . $limits['statuses'] . ' objects of key [a-z0-9_-], label and color #rrggbb); link kinds (the record kinds it may point to, all when empty); formula expression (other columns by name, as Price * Quantity). Sent on a change, it replaces the options.'),
    );

    $errors = ' A refused value answers 422 validation_failed naming the field, with the reason code in the message (name_empty, name_too_long, description_too_long, type_unknown, type_fixed, decimals_invalid, currency_invalid, choices_empty, choice_key_invalid, kinds_invalid, formula_too_long and the like).';
    $cells = 'Cells by column id: text a string, number a number, money an integer in minor units, date YYYY-MM-DD (with HH:MM on a column that keeps a time), person a user id, status a choice key, checkbox true or false, link an object of {kind, id}; null empties a cell. A formula column takes no cell: its value is worked out when the row is read.';

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
            'description' => 'Deletes a table of the owner\'s own for good, with its columns, rows and shares; the answer is the table as it was. Only its owner may (403 for a person it is shared with). It cannot be undone, so it is no MCP tool. POST /workspace/tables/{id}/delete does the same for servers that refuse DELETE.',
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
            'description' => 'The rows of a table the owner reaches, in their order. ' . $cells . ' A formula\'s error is in formula_errors by column id (ref, cycle and the like), its cell null.',
            'params'      => array(
                $id,
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 100),
                array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200),
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
            'description' => 'Deletes a row of a table the owner may change; the answer is the row as it was, and the rows after it move up one. With revision, a row changed since is refused with 409 changed_meanwhile. POST /workspace/tables/{id}/rows/{row_id}/delete does the same for servers that refuse DELETE.',
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
 * A row as the API hands it out (ws_wtable_row_present()).
 *
 * @param array $row ws_wtable_row_out()
 * @return array
 */
function ws_api_wtable_row_present($row)
{
    return ws_wtable_row_present($row);
}

// What ws_api_wtable_row_present() returns.
function ws_api_wtable_row_schema()
{
    return array(
        'id'             => 'integer',
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
            return lang(array('string' => 'The limit is reached: {var:1}.', 'vars' => (string) $code));
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
            api_fail_not_found($what);
            break;

        case 409:
            $message = ws_api_wtable_message($code);

            if (isset($result['revision'])) {
                $message .= ' ' . lang(array('string' => 'Revision now: {var:1}.', 'vars' => (string) (int) $result['revision']));
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

    if (isset($params['cursor']) && ($params['cursor'] !== '')) {
        $cursor = api_cursor_decode($params['cursor']);

        if ($cursor === null) {
            api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
        }

        $after = (int) $cursor['v'];
    }

    $limit = (int) ($params['limit'] ?? 100);
    $page = ws_wtable_rows_page((int) $table['id'], ws_wtable_columns((int) $table['id']), $after, $limit);
    $last = end($page['rows']);

    api_ok_list(array_map('ws_api_wtable_row_present', $page['rows']), $limit,
        ($page['next'] !== null) ? api_cursor_encode($page['next'], (int) $last['id']) : null, (int) $table['row_count']);
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
