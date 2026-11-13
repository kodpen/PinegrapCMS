<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the tables bound to a canvas (ws_canvas_tables, 2026.11.0
 * step 11.0.190). A canvas is the message that carries a live page; it
 * names the workflow tables its page may read, or read and write, and the
 * page's bridge (page_bridge.php) answers for those tables and no other,
 * with the rights of the person looking.
 *
 * Who binds: a person who may write in the canvas's channel and may write
 * the table (ws_wtable_may(…, 'write'), for a read binding too, so nobody
 * binds a table they could only look at to a page others run).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most tables one canvas binds.
define('WS_CANVAS_MAX_TABLES', 20);

/**
 * Has the step that brings the bindings run?
 *
 * @return bool
 */
function ws_canvas_tables_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_canvas_tables', 'bound_by_kind') && function_exists('ws_wtables_ready') && ws_wtables_ready();
    }

    return $ready;
}

/**
 * The kinds of binding.
 *
 * @return string[]
 */
function ws_canvas_table_accesses()
{
    return array('read', 'write');
}

/**
 * Why a binding may not be written, '' when it may. Pure.
 *
 * @param array $facts access (read|write), count (bindings the canvas has),
 *                     bound (the table is bound already), may_post,
 *                     table_write (may the person write the table), table
 *                     (the table is there and reached)
 * @return string '' or access_invalid, forbidden, not_found, too_many
 */
function ws_canvas_table_bind_refusal($facts)
{
    if (!in_array((string) ($facts['access'] ?? ''), ws_canvas_table_accesses(), true)) {
        return 'access_invalid';
    }

    if (empty($facts['table'])) {
        return 'not_found';
    }

    if (empty($facts['may_post']) || empty($facts['table_write'])) {
        return 'forbidden';
    }

    if (empty($facts['bound']) && ((int) ($facts['count'] ?? 0) >= WS_CANVAS_MAX_TABLES)) {
        return 'too_many';
    }

    return '';
}

/**
 * The words of a refusal.
 *
 * @param string $code ws_canvas_table_bind_refusal()
 * @return string
 */
function ws_canvas_table_refusal_text($code)
{
    $texts = array(
        'access_invalid' => lang('Choose read, or read and write.'),
        'not_found'      => lang('That table could not be found.'),
        'forbidden'      => lang('Only someone who may write in this channel and change the table may bind it to the page.'),
        'too_many'       => sprintf(lang('A canvas uses at most %d tables.'), WS_CANVAS_MAX_TABLES),
        'not_page'       => lang('That message could not be found.'),
    );

    return $texts[$code] ?? $code;
}

/**
 * The bindings of a canvas, oldest first.
 *
 * @param int $message_id
 * @return array[] table_id, access, bound_by_kind, bound_by_id, created_at
 */
function ws_canvas_tables($message_id)
{
    if (!ws_canvas_tables_ready() || ((int) $message_id <= 0)) {
        return array();
    }

    $rows = (array) db_items("SELECT table_id, access, bound_by_kind, bound_by_id, created_at FROM ws_canvas_tables
        WHERE message_id = '" . (int) $message_id . "' ORDER BY id");

    foreach ($rows as $i => $row) {
        $rows[$i]['table_id'] = (int) $row['table_id'];
        $rows[$i]['bound_by_id'] = (int) $row['bound_by_id'];
        $rows[$i]['created_at'] = (int) $row['created_at'];
    }

    return $rows;
}

/**
 * One binding, or null when the canvas does not bind the table.
 *
 * @param int $message_id
 * @param int $table_id
 * @return array|null
 */
function ws_canvas_table_binding($message_id, $table_id)
{
    foreach (ws_canvas_tables($message_id) as $row) {
        if ($row['table_id'] === (int) $table_id) {
            return $row;
        }
    }

    return null;
}

/**
 * Is the message a canvas with a live page the person reads, and may they
 * write in its channel?
 *
 * @param array      $viewer
 * @param array|null $message
 * @return array|null read (bool), may_post (bool); null when it is no page
 *                    the person reads
 */
function ws_canvas_page_reach($viewer, $message)
{
    if (!is_array($message) || ((int) ($message['deleted_at'] ?? 0) > 0) || ((int) ($message['file_id'] ?? 0) <= 0) || empty($viewer['member'])) {
        return null;
    }

    $channel = ws_channel($message['channel_id']);

    if (!$channel || !ws_can_read_channel($viewer, $channel) || !ws_page_is_page((int) $message['file_id'])) {
        return null;
    }

    return array('read' => true, 'may_post' => (bool) ws_can_post_channel($viewer, $channel));
}

/**
 * Binds a table to a canvas, or changes the access of a binding.
 *
 * @param array      $viewer
 * @param array|null $message
 * @param int        $table_id
 * @param string     $access read | write
 * @return array ok, error (code), text
 */
function ws_canvas_table_bind($viewer, $message, $table_id, $access)
{
    $reach = ws_canvas_tables_ready() ? ws_canvas_page_reach($viewer, $message) : null;

    if (!$reach) {
        return array('ok' => false, 'error' => 'not_page', 'text' => ws_canvas_table_refusal_text('not_page'));
    }

    $table = ws_wtable_for($viewer, (int) $table_id, 'view');
    $bound = ws_canvas_table_binding((int) $message['id'], (int) $table_id);
    $code = ws_canvas_table_bind_refusal(array(
        'access'      => (string) $access,
        'table'       => (bool) $table,
        'may_post'    => $reach['may_post'],
        'table_write' => $table && ws_wtable_may($table['access'], 'write'),
        'bound'       => (bool) $bound,
        'count'       => count(ws_canvas_tables((int) $message['id'])),
    ));

    if ($code !== '') {
        return array('ok' => false, 'error' => $code, 'text' => ws_canvas_table_refusal_text($code));
    }

    if ($bound) {
        db("UPDATE ws_canvas_tables SET access = '" . escape($access) . "', bound_by_kind = 'user', bound_by_id = '" . (int) $viewer['id'] . "'
            WHERE message_id = '" . (int) $message['id'] . "' AND table_id = '" . (int) $table_id . "'");
    } else {
        // INSERT IGNORE: two presses at once write one row (the unique key).
        db("INSERT IGNORE INTO ws_canvas_tables (message_id, table_id, access, bound_by_kind, bound_by_id, created_at)
            VALUES ('" . (int) $message['id'] . "', '" . (int) $table_id . "', '" . escape($access) . "', 'user', '" . (int) $viewer['id'] . "', UNIX_TIMESTAMP())");
    }

    return array('ok' => true, 'error' => '', 'text' => '');
}

/**
 * Takes a table off a canvas. Whoever may bind it may take it off; a table
 * the person no longer reaches may be taken off by anyone who may write in
 * the channel, so a binding never outlives the reach of everybody.
 *
 * @param array      $viewer
 * @param array|null $message
 * @param int        $table_id
 * @return array ok, error, text
 */
function ws_canvas_table_unbind($viewer, $message, $table_id)
{
    $reach = ws_canvas_tables_ready() ? ws_canvas_page_reach($viewer, $message) : null;

    if (!$reach) {
        return array('ok' => false, 'error' => 'not_page', 'text' => ws_canvas_table_refusal_text('not_page'));
    }

    $table = ws_wtable_for($viewer, (int) $table_id, 'view');

    if (!$reach['may_post'] || ($table && !ws_wtable_may($table['access'], 'write'))) {
        return array('ok' => false, 'error' => 'forbidden', 'text' => ws_canvas_table_refusal_text('forbidden'));
    }

    db("DELETE FROM ws_canvas_tables WHERE message_id = '" . (int) $message['id'] . "' AND table_id = '" . (int) $table_id . "'");

    return array('ok' => true, 'error' => '', 'text' => '');
}

/**
 * Removes every binding of a table (its final delete).
 *
 * @param int $table_id
 */
function ws_canvas_tables_forget_table($table_id)
{
    if (ws_canvas_tables_ready() && ((int) $table_id > 0)) {
        db("DELETE FROM ws_canvas_tables WHERE table_id = '" . (int) $table_id . "'");
    }
}

/**
 * The card's line of tables for the person looking: each bound table with
 * its name when they reach it (a table they do not reach shows nameless,
 * so the line still says how many the page uses), and whether they may
 * bind more.
 *
 * @param array $viewer
 * @param array $message
 * @param bool  $may_post
 * @return array tables [{id, name, access, reached}], can_bind
 */
function ws_canvas_tables_card($viewer, $message, $may_post)
{
    $out = array();

    foreach (ws_canvas_tables((int) $message['id']) as $row) {
        $table = ws_wtable_for($viewer, $row['table_id'], 'view');
        $out[] = array(
            'id'      => $row['table_id'],
            'name'    => $table ? (string) $table['name'] : '',
            'access'  => (string) $row['access'],
            'reached' => (bool) $table,
        );
    }

    return array('tables' => $out, 'can_bind' => ws_canvas_tables_ready() && (bool) $may_post);
}

/**
 * The tables the person may bind: those they may write, with the access a
 * binding of each has now ('' for none).
 *
 * @param array $viewer
 * @param array $message
 * @return array[] id, name, bound
 */
function ws_canvas_tables_choices($viewer, $message)
{
    $bound = array();

    foreach (ws_canvas_tables((int) $message['id']) as $row) {
        $bound[$row['table_id']] = (string) $row['access'];
    }

    $out = array();

    foreach (ws_wtables_reached($viewer) as $table) {
        if (ws_wtable_may((string) $table['access'], 'write')) {
            $out[] = array('id' => (int) $table['id'], 'name' => (string) $table['name'], 'bound' => $bound[(int) $table['id']] ?? '');
        }
    }

    return $out;
}

/**
 * The texts of the card's tables.
 *
 * @return array key => text
 */
function ws_canvas_tables_js_strings()
{
    return array(
        'page_tables'       => lang('Tables it uses'),
        'page_table_read'   => lang('reads only'),
        'page_table_write'  => lang('reads, writes'),
        'page_table_hidden' => lang('A table you do not reach'),
        'page_bind'         => lang('Bind a table'),
        'page_bind_none'    => lang('You have no table you may change.'),
        'page_bind_read'    => lang('Read only'),
        'page_bind_write'   => lang('Read and write'),
        'page_unbind'       => lang('Remove'),
    );
}
