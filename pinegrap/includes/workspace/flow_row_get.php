<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the box of a flow that reads one row of a workflow table
 * (kind row_get): the row named by its id or by one value of the run, read
 * with the flow owner's rights as they stand when the box runs. It gives the
 * boxes after it the row's id, whether the row was found (1 or 0) and one
 * value per column of the table, as a row start box gives its cells
 * (ws_flow_row_cell_text()). A row that is not there is no failure: the box
 * is done, found is 0 and every cell is empty, so a later condition decides.
 * A table whose owner has left the team pauses the box until they are back,
 * as the boxes that write rows do (ws_step_row_wait()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Checks a box that reads a row with somebody's rights, the owner as they
 * publish and again each time the box runs: a table they may read
 * (ws_wtable_flow_check() at view) whose owner is in the team (else the box
 * waits for them: paused), and a row named by its id or by one value alone.
 * A row id that names no row is checked as the box runs: it reads no row.
 *
 * @param array $viewer the flow's owner
 * @param array $config ws_step_config_normalize('row_get')
 * @return array ok, error, paused, action (type, table_id, table_name, row)
 */
function ws_step_row_get_check($viewer, $config)
{
    $refuse = function ($error, $paused = false) {
        return array('ok' => false, 'error' => $error, 'paused' => $paused, 'action' => null);
    };

    if (!function_exists('ws_wtable_flow_check') || !ws_wtables_ready()) {
        return $refuse(lang('The database has to be updated first.'));
    }

    $table = ws_wtable((int) ($config['table_id'] ?? 0));
    $gate = $table ? ws_wtable_flow_check($viewer, (int) $table['id'], 'view') : 'refused';

    if ($gate === 'paused') {
        return $refuse(lang(array('string' => 'The owner of the table “{var:1}” has left the team: no row is read from it until they are back.', 'vars' => (string) $table['name'])), true);
    }

    if ($gate !== '') {
        return $refuse(lang('Choose a table you may read.'));
    }

    $row = (string) ($config['row'] ?? '');

    if (!ws_step_row_names_row($row)) {
        return $refuse(lang('Name the row by its id or by one value, such as {{trigger.row_id}}.'));
    }

    return array('ok' => true, 'error' => '', 'paused' => false, 'action' => array(
        'type'       => 'row_get',
        'table_id'   => (int) $table['id'],
        'table_name' => (string) $table['name'],
        'row'        => $row,
    ));
}

/**
 * What a box that reads a row gives, from the row read: its id, found 1,
 * and each column's cell as {{node.*}} fills it in; for no row, no id,
 * found 0 and every cell empty. Pure.
 *
 * @param array      $columns column id => column (ws_wtable_columns())
 * @param array|null $cells   column id => value of the row read, null for none
 * @param int        $row_id
 * @return array row_id, found, cell_<column id>
 */
function ws_step_row_get_data($columns, $cells, $row_id)
{
    $found = is_array($cells);
    $out = array('row_id' => $found ? (int) $row_id : 0, 'found' => $found ? 1 : 0);

    foreach ((array) $columns as $column) {
        $id = (int) ($column['id'] ?? 0);

        if ($id > 0) {
            $out['cell_' . $id] = $found ? ws_flow_row_cell_text($cells[(string) $id] ?? ($cells[$id] ?? null)) : '';
        }
    }

    return $out;
}

/**
 * Reads the row a box names, with the flow owner's rights checked
 * (ws_step_row_get_check()), its formulas worked out as a read does
 * (ws_wtable_row_out()). The row text is filled in already: one that names
 * no row of the table, or no id at all, reads nothing, and the box is done
 * with found 0. A table gone meanwhile fails the box.
 *
 * @param array $owner  the flow's owner, checked
 * @param array $action ws_step_row_get_check(), filled
 * @return array ok, text, row_id, data
 */
function ws_step_row_get_do($owner, $action)
{
    $table = ws_wtable((int) $action['table_id']);
    $name = $table ? (string) $table['name'] : (string) $action['table_name'];

    if (!$table) {
        return array('ok' => false, 'text' => lang(array('string' => 'The table “{var:1}” is gone.', 'vars' => $name)), 'row_id' => 0, 'data' => array());
    }

    $columns = ws_wtable_columns((int) $table['id']);
    $row_id = ws_step_row_id($action['row']);
    $stored = ($row_id > 0) ? ws_wtable_row((int) $table['id'], $row_id) : null;
    $cells = null;

    if ($stored) {
        $presented = ws_wtable_row_present(ws_wtable_row_out($stored, $columns));
        $cells = (array) $presented['cells'];
    }

    $data = ws_step_row_get_data($columns, $cells, $row_id);

    return array(
        'ok'     => true,
        'text'   => ($cells !== null)
            ? lang(array('string' => 'Read the row {var:1} of the table “{var:2}”.', 'vars' => array($row_id, $name)))
            : lang(array('string' => 'The table “{var:1}” has no such row: nothing was read.', 'vars' => $name)),
        'row_id' => (int) $data['row_id'],
        'data'   => $data,
    );
}

/**
 * What a box that reads a row does, in words. Pure but for lang().
 *
 * @param array $action ws_step_row_get_check()
 * @return string
 */
function ws_step_row_get_text($action)
{
    return lang(array('string' => 'Reads a row of the table “{var:1}”.', 'vars' => (string) ($action['table_name'] ?? '')));
}
