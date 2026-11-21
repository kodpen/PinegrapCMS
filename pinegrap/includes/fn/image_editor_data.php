<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Artifact Canvas's Data menu on the server: the tables a person may
 * read for its picker, and the rows of one, read with their rights. The
 * editor reaches them as the actions data_tables and data_rows
 * (pg_image_editor_api()); a data layer writes nothing to its table.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The Workspace's functions and the person as the Workspace sees them, or
 * null when it is off or the person is not one of its people.
 *
 * @param array $user
 * @return array|null
 */
function pg_image_editor_data_viewer($user)
{
    if (!defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_chart.php');

    if (!ws_enabled() || !ws_wtables_ready()) {
        return null;
    }

    $viewer = ws_viewer($user);

    return !empty($viewer['member']) ? $viewer : null;
}

/**
 * The picker's rows of the tables a person reaches (ws_wtables_reached()),
 * in that order: id, name, the channel's name ('' for a table of no
 * channel), the row count and the channel's colour as a one-series chart
 * takes it on each board (ws_canvas_chart_tone()). Pure.
 *
 * @param array    $tables   ws_wtables rows
 * @param callable $channel  channel id => channel row or null
 * @param array    $palettes theme => palette (ws_canvas_guide_values())
 * @return array[]
 */
function pg_image_editor_data_tables_shape($tables, $channel, $palettes)
{
    $out = array();

    foreach ((array) $tables as $table) {
        $home = ((int) ($table['channel_id'] ?? 0) > 0) ? $channel((int) $table['channel_id']) : null;
        $colour = is_array($home) ? ws_canvas_chart_channel_tone($home) : '';
        $tone = null;

        if ($colour !== '') {
            $tone = array();

            foreach ($palettes as $theme => $palette) {
                $tone[$theme] = ws_canvas_chart_tone($colour, $palette);
            }
        }

        $out[] = array(
            'id'      => (int) $table['id'],
            'name'    => (string) ($table['name'] ?? ''),
            'channel' => is_array($home) ? (string) ($home['name'] ?? '') : '',
            'rows'    => (int) ($table['row_count'] ?? 0),
            'tone'    => $tone,
        );
    }

    return $out;
}

/**
 * Action data_tables: the tables the person may read, 200 at most; none
 * without the Workspace.
 *
 * @param array $request
 * @param array $user
 * @return array payload, status
 */
function pg_image_editor_action_data_tables($request, $user)
{
    $viewer = pg_image_editor_data_viewer($user);

    if ($viewer === null) {
        return array(array('status' => 'success', 'tables' => array()), 200);
    }

    $tables = array_values(array_filter(ws_wtables_reached($viewer, 'all'), function ($table) {
        return ws_wtable_access_rank((string) ($table['access'] ?? '')) >= ws_wtable_access_rank('view');
    }));

    return array(array(
        'status' => 'success',
        'tables' => pg_image_editor_data_tables_shape(array_slice($tables, 0, 200), 'ws_wtable_home_channel', ws_canvas_guide_values()['palettes']),
    ), 200);
}

/**
 * Action data_rows: one table's rows with the person's rights
 * (ws_canvas_data_rows()), as editor.dataRead() takes them; a table they
 * may not read reads as state refused.
 *
 * @param array $request table (id)
 * @param array $user
 * @return array payload, status
 */
function pg_image_editor_action_data_rows($request, $user)
{
    $viewer = pg_image_editor_data_viewer($user);
    $table = (int) ($request['table'] ?? 0);
    $read = (($viewer !== null) && ($table > 0)) ? ws_canvas_data_rows($viewer, $table) : array('state' => 'refused');

    // An empty list of columns or cells stays an object in JSON.
    if (isset($read['columns'])) {
        $read['columns'] = (object) $read['columns'];

        foreach ($read['rows'] as $i => $row) {
            $read['rows'][$i]['cells'] = (object) $row['cells'];
        }
    }

    return array(array('status' => 'success', 'read' => $read), 200);
}

/* ---------------------------------------------------------------------------
   "Show on a canvas" on the Data screen: a new canvas in the table's
   channel holding one data layer of the table, the table bound to it for
   reading only (ws_canvas_tables).
   --------------------------------------------------------------------------- */

/**
 * Why a person may not show a table on a new canvas in its channel, ''
 * when they may. Pure.
 *
 * @param array $facts table (they may read it), channel (it belongs to a
 *                     channel, the one the canvas goes to), may_post (they
 *                     may post in that channel)
 * @return string '' or table, channel, post
 */
function pg_image_editor_data_show_refusal($facts)
{
    if (empty($facts['table'])) {
        return 'table';
    }

    if (empty($facts['channel'])) {
        return 'channel';
    }

    if (empty($facts['may_post'])) {
        return 'post';
    }

    return '';
}

/**
 * The words of a refusal: the ones the data layer and the channel already
 * say.
 *
 * @param string $code pg_image_editor_data_show_refusal()
 * @return string
 */
function pg_image_editor_data_show_text($code)
{
    $texts = array(
        'table'   => lang('You cannot read this table.'),
        'channel' => lang('That channel could not be found.'),
        'post'    => lang('You cannot post in that channel.'),
    );

    return $texts[$code] ?? $code;
}

/**
 * May this person show the table on a new canvas, and where does it go?
 * With a channel id, the canvas is asked to go there: any channel but the
 * table's own is refused.
 *
 * @param array    $viewer
 * @param int      $table_id
 * @param int|null $channel_id
 * @return array code ('' when they may), table, channel (the table's)
 */
function pg_image_editor_data_show_check($viewer, $table_id, $channel_id = null)
{
    $table = (!empty($viewer['member']) && ((int) $table_id > 0) && ws_wtables_ready()) ? ws_wtable_for($viewer, (int) $table_id, 'view') : null;
    $home = $table ? ws_wtable_home($table) : null;

    if (is_array($home) && ($channel_id !== null) && ((int) $home['id'] !== (int) $channel_id)) {
        $home = null;
    }

    $code = pg_image_editor_data_show_refusal(array(
        'table'    => (bool) $table,
        'channel'  => is_array($home),
        'may_post' => is_array($home) && ws_can_post_channel($viewer, $home),
    ));

    return array('code' => $code, 'table' => $table, 'channel' => $home);
}

/**
 * Does a saved canvas document hold a data layer of the table? Only then
 * does the save bind the table. Pure.
 *
 * @param array|object|null $doc the editor's document
 * @param int               $table_id
 * @return bool
 */
function pg_image_editor_data_doc_shows($doc, $table_id)
{
    $doc = json_decode((string) json_encode($doc), true);

    foreach ((is_array($doc) && is_array($doc['layers'] ?? null)) ? $doc['layers'] : array() as $layer) {
        if (is_array($layer) && (($layer['type'] ?? '') === 'data') && is_array($layer['data'] ?? null)
            && ((int) ($layer['data']['table'] ?? 0) === (int) $table_id) && ((int) $table_id > 0)) {
            return true;
        }
    }

    return false;
}

/**
 * The binding a shown table gets: read only, in the person's name. Pure.
 *
 * @param int $message_id the new canvas
 * @param int $table_id
 * @param int $user_id
 * @return array the ws_canvas_tables row's fields
 */
function pg_image_editor_data_show_binding($message_id, $table_id, $user_id)
{
    return array(
        'message_id'    => (int) $message_id,
        'table_id'      => (int) $table_id,
        'access'        => 'read',
        'bound_by_kind' => 'user',
        'bound_by_id'   => (int) $user_id,
    );
}

/**
 * Binds the shown table to the new canvas for reading. The canvas is new,
 * so the limit of tables a canvas binds is never met; a second press
 * writes one row (the unique key).
 *
 * @param int $message_id
 * @param int $table_id
 * @param int $user_id
 * @return bool written
 */
function pg_image_editor_data_show_bind($message_id, $table_id, $user_id)
{
    $row = pg_image_editor_data_show_binding($message_id, $table_id, $user_id);

    if (!ws_canvas_tables_ready() || ($row['message_id'] <= 0) || ($row['table_id'] <= 0)) {
        return false;
    }

    return (bool) db("INSERT IGNORE INTO ws_canvas_tables (message_id, table_id, access, bound_by_kind, bound_by_id, created_at)
        VALUES ('" . $row['message_id'] . "', '" . $row['table_id'] . "', '" . escape($row['access']) . "', '" . escape($row['bound_by_kind']) . "', '" . $row['bound_by_id'] . "', UNIX_TIMESTAMP())");
}

/**
 * What the Data screen's "Show on a canvas" hands the editor: the table as
 * the picker lists it (pg_image_editor_data_tables_shape()) and where the
 * canvas is sent.
 *
 * @param array $table   ws_wtable_for() row
 * @param array $channel the table's channel
 * @return array table, channel (id), save (URL)
 */
function pg_image_editor_data_show_config($table, $channel)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_chart.php');

    $shape = pg_image_editor_data_tables_shape(array($table), function () use ($channel) {
        return $channel;
    }, ws_canvas_guide_values()['palettes']);

    return array(
        'table'   => $shape[0],
        'channel' => (int) $channel['id'],
        'save'    => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_file_save.php?channel=' . (int) $channel['id'] . '&table=' . (int) $table['id'],
        'open'    => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php?channel=' . (int) $channel['id'],
    );
}

/**
 * "Show on a canvas" on a table's head: the Menus pattern of the Design
 * examples screen (a quiet small pg-button opening .menu) with "As a chart"
 * and "As a table", what the editor is handed, and the editor's loader.
 * Each entry opens the Data picker's second step on the table
 * (workspace_wtables.php's script, dataShow in pg_image_editor_data.js).
 *
 * @param array $config pg_image_editor_data_show_config()
 * @return string HTML
 */
function pg_image_editor_data_show_menu($config)
{
    $config['label'] = lang('Send the canvas');
    $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    return '<span class="pg-menu-wrap" id="ws_wt_canvas_wrap">'
        . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" id="ws_wt_canvas_button" data-bs-toggle="menu" aria-expanded="false">'
        . pg_design_canvas_icon_one_colour('me-1') . h(lang('Show on a canvas')) . '<i class="bi bi-chevron-down ms-1" aria-hidden="true"></i></button>'
        . '<ul class="menu">'
        . '<li><button type="button" class="menu-item" data-ws-wt-canvas="chart"><i class="bi bi-bar-chart me-3" aria-hidden="true"></i>' . h(lang('As a chart')) . '</button></li>'
        . '<li><button type="button" class="menu-item" data-ws-wt-canvas="table"><i class="bi bi-table me-3" aria-hidden="true"></i>' . h(lang('As a table')) . '</button></li>'
        . '</ul></span>'
        . '<script type="application/json" id="ws_wt_canvas_config">' . $json . '</script>'
        . pg_image_editor_includes();
}
