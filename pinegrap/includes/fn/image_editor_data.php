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
