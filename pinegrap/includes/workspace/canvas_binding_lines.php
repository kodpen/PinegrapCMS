<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an application's binding of a table to a canvas, shown as a
 * line in the canvas's channel with Undo. The line is a system message; a
 * few ws_refs rows tie it to the canvas, the table, the access set, the
 * access before and the application, so nothing new is stored. Undo puts
 * the binding back as it was: taken off when it was new, its earlier
 * access when it was changed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_tables.php');

/**
 * The ws_refs types a binding line keeps, by their meaning.
 *
 * @return array meaning => ref_type
 */
function ws_canvas_binding_ref_types()
{
    return array(
        'canvas' => 'canvas_bind',
        'table'  => 'canvas_bind_table',
        'now'    => 'canvas_bind_now',
        'was'    => 'canvas_bind_was',
        'app'    => 'canvas_bind_app',
        'undone' => 'canvas_bind_undone',
    );
}

/**
 * An access as the number a ref keeps (ws_refs keeps no id of 0). Pure.
 *
 * @param string $access '' (no binding), read or write
 * @return int 1, 2 or 3
 */
function ws_canvas_binding_access_code($access)
{
    $codes = array('read' => 2, 'write' => 3);

    return $codes[(string) $access] ?? 1;
}

/**
 * The access a ref's number names. Pure.
 *
 * @param int $code
 * @return string '', read or write
 */
function ws_canvas_binding_access_of($code)
{
    $names = array(2 => 'read', 3 => 'write');

    return $names[(int) $code] ?? '';
}

/**
 * How the binding application is named in the line: its agent's @handle,
 * else its own name. Pure.
 *
 * @param int    $app_id
 * @param array  $tags     ws_agent_tags(): app_id => handle, name
 * @param string $app_name the application's name in api_apps
 * @return string
 */
function ws_canvas_binding_agent_name($app_id, $tags, $app_name)
{
    $handle = trim((string) ($tags[(int) $app_id]['handle'] ?? ''));

    if ($handle !== '') {
        return '@' . $handle;
    }

    $name = trim(str_replace(array('<', '>', '*', '_', '`', '[', ']', '#', '~'), '', (string) $app_name));

    return ($name !== '') ? $name : lang('An application');
}

/**
 * The line's words. Pure but for lang().
 *
 * @param string $agent  ws_canvas_binding_agent_name()
 * @param string $table  the table's name, '' when the reader does not reach it
 * @param string $canvas the canvas's name
 * @param string $access read | write
 * @return string
 */
function ws_canvas_binding_line_text($agent, $table, $canvas, $access)
{
    // The words the card's line of tables uses.
    $does = ((string) $access === 'write') ? lang('reads, writes') : lang('reads only');

    if ((string) $table === '') {
        return lang(array('string' => '{var:1} bound a table to {var:2} · {var:3}', 'vars' => array($agent, $canvas, $does)));
    }

    return lang(array('string' => '{var:1} bound {var:2} to {var:3} · {var:4}', 'vars' => array($agent, $table, $canvas, $does)));
}

/**
 * Does the binding still stand as the line's application set it, so Undo
 * puts back what it changed and nothing else? Pure.
 *
 * @param array      $line    ws_canvas_binding_line_refs() of one line
 * @param array|null $binding ws_canvas_table_binding() now
 * @return bool
 */
function ws_canvas_binding_line_current($line, $binding)
{
    if (!is_array($binding) || ((int) ($line['undone'] ?? 0) > 0)) {
        return false;
    }

    return ((string) $binding['access'] === (string) $line['now'])
        && ((string) $binding['bound_by_kind'] === 'app')
        && ((int) $binding['bound_by_id'] === (int) $line['app']);
}

/**
 * The application's name for the line, read now.
 *
 * @param int $app_id
 * @return string
 */
function ws_canvas_binding_app_name($app_id)
{
    static $tags = null;

    if ($tags === null) {
        $tags = function_exists('ws_agent_tags') ? ws_agent_tags() : array();
    }

    return ws_canvas_binding_agent_name((int) $app_id, $tags, (string) db_value("SELECT name FROM api_apps WHERE id = '" . (int) $app_id . "'"));
}

/**
 * Writes the line an application's binding shows in the canvas's channel.
 * Its stored words never name the table: a reader of the channel may not
 * reach it; each reader's own words come with the payload.
 *
 * @param array  $message the canvas's message
 * @param int    $table_id
 * @param string $access  read | write, as set now
 * @param string $was     the access before, '' when it was not bound
 * @param int    $app_id
 * @return int the line's message id, 0 when it could not be written
 */
function ws_canvas_binding_line_post($message, $table_id, $access, $was, $app_id)
{
    if (((int) $app_id <= 0) || !is_array($message)) {
        return 0;
    }

    $channel_id = (int) $message['channel_id'];
    $line_id = ws_message_system($channel_id, ws_canvas_binding_line_text(ws_canvas_binding_app_name($app_id), '', ws_canvas_name($message), $access));

    if ($line_id > 0) {
        $types = ws_canvas_binding_ref_types();

        ws_refs_store('message', $line_id, $channel_id, array(
            array('type' => $types['canvas'], 'id' => (int) $message['id']),
            array('type' => $types['table'], 'id' => (int) $table_id),
            array('type' => $types['now'], 'id' => ws_canvas_binding_access_code($access)),
            array('type' => $types['was'], 'id' => ws_canvas_binding_access_code($was)),
            array('type' => $types['app'], 'id' => (int) $app_id),
        ));
    }

    return $line_id;
}

/**
 * What a line's refs say, by line. Pure.
 *
 * @param array[] $rows ws_refs rows: source_id, ref_type, ref_id
 * @return array line id => canvas, table, now, was, app, undone (the
 *               person who undid it, 0 for none); only complete lines
 */
function ws_canvas_binding_lines_from_refs($rows)
{
    $meaning = array_flip(ws_canvas_binding_ref_types());
    $lines = array();

    foreach ((array) $rows as $row) {
        $type = (string) ($row['ref_type'] ?? '');

        if (isset($meaning[$type])) {
            $lines[(int) $row['source_id']][$meaning[$type]] = (int) $row['ref_id'];
        }
    }

    $out = array();

    foreach ($lines as $id => $line) {
        if (empty($line['canvas']) || empty($line['table']) || empty($line['now']) || empty($line['app'])) {
            continue;
        }

        $out[$id] = array(
            'canvas' => (int) $line['canvas'],
            'table'  => (int) $line['table'],
            'now'    => ws_canvas_binding_access_of($line['now']),
            'was'    => ws_canvas_binding_access_of($line['was'] ?? 1),
            'app'    => (int) $line['app'],
            'undone' => (int) ($line['undone'] ?? 0),
        );
    }

    return $out;
}

/**
 * The binding lines among some messages.
 *
 * @param int[] $ids message ids
 * @return array line id => ws_canvas_binding_lines_from_refs() entry
 */
function ws_canvas_binding_line_refs($ids)
{
    $ids = array_filter(array_map('intval', (array) $ids));

    if (empty($ids)) {
        return array();
    }

    $types = array_map(function ($type) {
        return "'" . $type . "'";
    }, ws_canvas_binding_ref_types());

    return ws_canvas_binding_lines_from_refs(db_items("SELECT source_id, ref_type, ref_id FROM ws_refs
        WHERE source_type = 'message' AND ref_type IN (" . implode(',', $types) . ") AND source_id IN (" . implode(',', $ids) . ")"));
}

/**
 * Why this person may not undo the line now, '' when they may: the
 * binding must still be the application's, and the person must be one who
 * may take the table off (a new binding) or bind it as it was.
 *
 * @param array $viewer
 * @param array $line    ws_canvas_binding_line_refs() entry
 * @param array|null $canvas the canvas's message
 * @return string '' or done, changed, or a bind/unbind refusal code
 */
function ws_canvas_binding_undo_check($viewer, $line, $canvas)
{
    if ((int) $line['undone'] > 0) {
        return 'done';
    }

    if (!ws_canvas_binding_line_current($line, ws_canvas_table_binding((int) $line['canvas'], (int) $line['table']))) {
        return 'changed';
    }

    return ($line['was'] === '')
        ? ws_canvas_table_unbind_check($viewer, $canvas, (int) $line['table'])
        : ws_canvas_table_bind_check($viewer, $canvas, (int) $line['table'], $line['was']);
}

/**
 * What the screen draws for each binding line the reader reads: the line
 * in their words (the table named when they reach it) and Undo or who
 * undid it.
 *
 * @param array $viewer
 * @param int[] $ids message ids
 * @return array message id => text, can_undo, undone_by ('' for none)
 */
function ws_canvas_binding_lines_map($viewer, $ids)
{
    if (!ws_canvas_tables_ready()) {
        return array();
    }

    $out = array();

    foreach (ws_canvas_binding_line_refs($ids) as $line_id => $line) {
        $canvas = ws_message($line['canvas']);

        if (!$canvas || ((int) ($canvas['deleted_at'] ?? 0) > 0)) {
            continue;
        }

        $table = ws_wtable_for($viewer, $line['table'], 'view');

        $out[$line_id] = array(
            'text'      => ws_canvas_binding_line_text(ws_canvas_binding_app_name($line['app']), $table ? (string) $table['name'] : '', ws_canvas_name($canvas), $line['now']),
            'can_undo'  => ws_canvas_binding_undo_check($viewer, $line, $canvas) === '',
            'undone_by' => ($line['undone'] > 0) ? (string) ws_person_name($line['undone']) : '',
        );
    }

    return $out;
}

/**
 * Undo of an application's binding, from its line: the table is taken off
 * the canvas when the binding was new, or bound again with the access it
 * had; the line then says who undid it.
 *
 * @param array $viewer
 * @param int   $line_id the line's message id
 * @return array ok, error (words)
 */
function ws_canvas_binding_undo($viewer, $line_id)
{
    $lines = ws_canvas_binding_line_refs(array((int) $line_id));
    $message = ws_message((int) $line_id);

    if (empty($lines[(int) $line_id]) || !$message || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        return array('ok' => false, 'error' => lang('That message could not be found.'));
    }

    $line = $lines[(int) $line_id];
    $canvas = ws_message($line['canvas']);
    $code = ws_canvas_binding_undo_check($viewer, $line, $canvas);

    if ($code === 'done') {
        return array('ok' => false, 'error' => lang('This binding was undone already.'));
    }

    if ($code === 'changed') {
        return array('ok' => false, 'error' => lang('The binding changed since, so it is not undone.'));
    }

    if ($code !== '') {
        return array('ok' => false, 'error' => ws_canvas_table_refusal_text($code));
    }

    $result = ($line['was'] === '')
        ? ws_canvas_table_unbind($viewer, $canvas, $line['table'])
        : ws_canvas_table_bind($viewer, $canvas, $line['table'], $line['was']);

    if (!$result['ok']) {
        return array('ok' => false, 'error' => $result['text']);
    }

    $types = ws_canvas_binding_ref_types();

    db("INSERT INTO ws_refs (source_type, source_id, channel_id, ref_type, ref_id, created_at)
        VALUES ('message', '" . (int) $line_id . "', '" . (int) $message['channel_id'] . "', '" . $types['undone'] . "', '" . (int) $viewer['id'] . "', '" . time() . "')");

    // Open conversations draw the canvas's line of tables and this line again.
    ws_message_touch((int) $canvas['id']);
    ws_message_touch((int) $line_id);

    return array('ok' => true, 'error' => '');
}

/**
 * The texts of a binding line.
 *
 * @return array key => text
 */
function ws_canvas_binding_js_strings()
{
    return array(
        'page_bind_undo'      => lang('Undo'),
        'page_bind_undone_by' => ws_js_template('Undone by {var:1}', 1),
    );
}
