<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the site's end of a live page's data bridge. A page asks its
 * shell (pg_guarded_frame_bridge_script()), the shell asks the panel, and
 * the page card (assets/js/workspace_page_card.js) sends the panel action
 * ws_page_call with the id of the canvas whose card made that shell: the
 * page never names a canvas. Here a call is checked and answered with the
 * rights of the person looking.
 *
 * The calls: who (the reader's display name), tables (the tables the canvas
 * binds that the reader reaches) and rows (a bound table's rows with the
 * conditions, sort and totals of the table screen, workspace/
 * workflow-tables-query.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/guarded_frame.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/page.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_tables.php');

// The most rows one read hands a page, and the most it asks for when it
// names no limit.
define('WS_PAGE_BRIDGE_MAX_ROWS', 500);
define('WS_PAGE_BRIDGE_ROWS', 100);

/**
 * The calls a page may make.
 *
 * @return string[]
 */
function ws_page_bridge_calls()
{
    return array('who', 'tables', 'rows');
}

/**
 * A refused call as the bridge answers it.
 *
 * @param string $code invalid, limit, unknown_call, not_found, not_bound,
 *                     forbidden
 * @param string $text
 * @return array ok false, error {code, text}
 */
function ws_page_bridge_error($code, $text = '')
{
    return array('ok' => false, 'error' => array('code' => (string) $code, 'text' => (string) $text));
}

/**
 * Checks a call before it is answered: its name and its arguments, sent by
 * the panel as JSON text (the shell already bounded them; the site checks
 * again, as it trusts no browser). Pure.
 *
 * @param mixed $call
 * @param mixed $args_json
 * @return array ok true with call and args (array), or ws_page_bridge_error()
 */
function ws_page_bridge_request($call, $args_json)
{
    if (!is_string($call) || !preg_match('/^[a-z][a-z_]{0,23}\z/', $call)) {
        return ws_page_bridge_error('invalid', lang('The request is not valid.'));
    }

    $args_json = is_string($args_json) ? $args_json : '';

    if ($args_json === '') {
        $args_json = '{}';
    }

    if (strlen($args_json) > PG_GUARDED_FRAME_BRIDGE_MAX_ARGS) {
        return ws_page_bridge_error('limit', lang('The request is too large.'));
    }

    $args = json_decode($args_json, true);

    // A JSON object, possibly empty; a list or a scalar is refused.
    if (!is_array($args) || (substr(ltrim($args_json), 0, 1) !== '{')) {
        return ws_page_bridge_error('invalid', lang('The request is not valid.'));
    }

    if (!in_array($call, ws_page_bridge_calls(), true)) {
        return ws_page_bridge_error('unknown_call', lang('A live page cannot ask for that.'));
    }

    return array('ok' => true, 'call' => $call, 'args' => $args);
}

/**
 * Answers one call of a live page for the person looking, after
 * ws_page_bridge_request(). The message is the canvas the panel names,
 * never one the page names.
 *
 * @param array $viewer  ws_rights()
 * @param array $message ws_message()
 * @param mixed $call
 * @param mixed $args_json
 * @return array ok true with data, or ws_page_bridge_error()
 */
function ws_page_bridge_answer($viewer, $message, $call, $args_json)
{
    if (!is_array($message) || ((int) ($message['deleted_at'] ?? 0) > 0) || ((int) ($message['file_id'] ?? 0) <= 0)
        || !$viewer['member'] || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))
        || !ws_page_is_page((int) $message['file_id'])) {
        return ws_page_bridge_error('not_found', lang('That message could not be found.'));
    }

    $request = ws_page_bridge_request($call, $args_json);

    if (!$request['ok']) {
        return $request;
    }

    if ($request['call'] === 'tables') {
        return array('ok' => true, 'data' => array('tables' => ws_page_bridge_tables($viewer, $message)));
    }

    if ($request['call'] === 'rows') {
        return ws_page_bridge_rows($viewer, $message, $request['args']);
    }

    // who: the name the reader is shown by, nothing else of them.
    return array('ok' => true, 'data' => array('name' => ws_person_name((int) $viewer['id'])));
}

/**
 * A bound table as a page sees it: its id, name, the binding's access and
 * its columns (id, name, type).
 *
 * @param array  $table   ws_wtable_for()
 * @param array  $columns ws_wtable_columns()
 * @param string $access  the binding's read | write
 * @return array
 */
function ws_page_bridge_table_present($table, $columns, $access)
{
    $list = array();

    foreach ((array) $columns as $id => $column) {
        $list[] = array('id' => (int) $id, 'name' => (string) $column['name'], 'type' => (string) $column['type']);
    }

    return array('id' => (int) $table['id'], 'name' => (string) $table['name'], 'access' => (string) $access, 'columns' => $list);
}

/**
 * The tables the canvas binds that the reader reaches; a table they do not
 * reach is left out, as on its own screen.
 *
 * @param array $viewer
 * @param array $message
 * @return array[]
 */
function ws_page_bridge_tables($viewer, $message)
{
    $out = array();

    foreach (ws_canvas_tables((int) $message['id']) as $binding) {
        $table = ws_wtable_for($viewer, $binding['table_id'], 'view');

        if ($table) {
            $out[] = ws_page_bridge_table_present($table, ws_wtable_columns((int) $table['id']), $binding['access']);
        }
    }

    return $out;
}

/**
 * The read arguments of rows, bounded. Pure.
 *
 * @param array $args table, where (list), join, sort, dir, limit, from, totals
 * @return array table, query (ws_wtable_query()), limit, from, totals; or
 *               ws_page_bridge_error() when the table or where is unusable
 */
function ws_page_bridge_rows_args($args)
{
    $table = $args['table'] ?? 0;

    if ((!is_int($table) && !(is_string($table) && ctype_digit($table))) || ((int) $table <= 0)) {
        return ws_page_bridge_error('invalid', lang('Name the table by its id.'));
    }

    $where = $args['where'] ?? array();
    $query = ws_wtable_query(is_array($where) ? $where : null, (string) ($args['join'] ?? 'all'), $args['sort'] ?? 0, (string) ($args['dir'] ?? 'asc'));

    if ($query['where'] === null) {
        return ws_page_bridge_error('invalid', lang('The conditions are not a list.'));
    }

    $limit = (int) ($args['limit'] ?? WS_PAGE_BRIDGE_ROWS);

    return array(
        'ok'     => true,
        'table'  => (int) $table,
        'query'  => $query,
        'limit'  => max(1, min(WS_PAGE_BRIDGE_MAX_ROWS, $limit)),
        'from'   => max(0, (int) ($args['from'] ?? 0)),
        'totals' => !empty($args['totals']),
    );
}

/**
 * A page of a bound table's rows for the reader: only a table the canvas
 * binds (not_bound), only when the reader may read it (forbidden), with
 * the conditions, sort and totals of the table screen; lookups and rollups
 * worked out for the reader.
 *
 * @param array $viewer
 * @param array $message
 * @param array $args
 * @return array ok, data {table, rows, total, next, totals} or an error
 */
function ws_page_bridge_rows($viewer, $message, $args)
{
    $read = ws_page_bridge_rows_args($args);

    if (!$read['ok']) {
        return $read;
    }

    $binding = ws_canvas_table_binding((int) $message['id'], $read['table']);

    if (!$binding) {
        return ws_page_bridge_error('not_bound', lang('This canvas does not use that table.'));
    }

    $table = ws_wtable_for($viewer, $read['table'], 'view');

    if (!$table) {
        return ws_page_bridge_error('forbidden', lang('You cannot see that table.'));
    }

    $columns = ws_wtable_columns((int) $table['id']);
    $refusal = ws_wtable_query_refusal($columns, $read['query']);

    if ($refusal !== '') {
        return ws_page_bridge_error('invalid', $refusal);
    }

    $kept = ws_wtable_rows((int) $table['id'], $columns);

    if (ws_wtable_query_active($read['query'])) {
        $kept = ws_wtable_query_rows($columns, $kept, $read['query']);
    }

    $page = ws_wtable_query_page($kept, $read['from'], $read['limit']);

    return array('ok' => true, 'data' => array(
        'table'  => ws_page_bridge_table_present($table, $columns, $binding['access']),
        'rows'   => array_map('ws_wtable_row_present', ws_wtable_joined_fill($viewer, $columns, $page['rows'])),
        'total'  => count($kept),
        'next'   => $page['next'],
        'totals' => $read['totals'] ? (object) ws_wtable_rows_totals($columns, $kept) : null,
    ));
}

/**
 * The texts of the bridge on the page card.
 *
 * @return array key => text
 */
function ws_page_bridge_js_strings()
{
    return array(
        'page_stopped'    => lang('The page was stopped: it asked the site too often.'),
        'page_open_again' => lang('Open again'),
    );
}
