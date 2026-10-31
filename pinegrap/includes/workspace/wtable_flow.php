<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - Run from a workflow table: the table's rows compiled once into
 * a draft flow. Each row with a step becomes a task box in row order; its
 * "after" column (a row number) draws the arrow into it, the row before
 * otherwise; a person column gives the assignee and a number column the days
 * the task is due after it becomes ready. The person chooses the columns in
 * a window on the table screen, pre-filled by type; nothing is kept on the
 * table. The draft is saved as a new flow of theirs through ws_flow_save()
 * and opened on the Flows canvas, where they review and publish it: running
 * it is the ordinary flow run, and role-3 reach is checked at publish.
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
 * The parts a row gives its task box, each with the column type it reads.
 *
 * @return array role => array(type, label, required)
 */
function ws_wtable_flow_roles()
{
    return array(
        'step'   => array('type' => 'text', 'label' => lang('Step'), 'required' => true),
        'person' => array('type' => 'person', 'label' => lang('Person'), 'required' => false),
        'days'   => array('type' => 'number', 'label' => lang('Days'), 'required' => false),
        'after'  => array('type' => 'number', 'label' => lang('After row'), 'required' => false),
    );
}

/**
 * The columns a window opens with (pure): each part the first column of its
 * type not taken by a part before it, so of two number columns the first
 * gives the days and the second the row it comes after.
 *
 * @param array $columns column id => column (type), in table order
 * @return array role => column id (0 for none)
 */
function ws_wtable_flow_prefill($columns)
{
    $map = array();
    $taken = array();

    foreach (ws_wtable_flow_roles() as $role => $part) {
        $map[$role] = 0;

        foreach ((array) $columns as $column_id => $column) {
            if (((string) ($column['type'] ?? '') === $part['type']) && !isset($taken[(int) $column_id])) {
                $map[$role] = (int) $column_id;
                $taken[(int) $column_id] = true;
                break;
            }
        }
    }

    return $map;
}

/**
 * The columns chosen in the window, kept only where the column is of the
 * part's type and not chosen for a part before (pure).
 *
 * @param array $columns column id => column (type)
 * @param mixed $chosen  role => column id, as posted
 * @return array role => column id (0 for none)
 */
function ws_wtable_flow_map($columns, $chosen)
{
    $chosen = is_array($chosen) ? $chosen : array();
    $map = array();
    $taken = array();

    foreach (ws_wtable_flow_roles() as $role => $part) {
        $column_id = (isset($chosen[$role]) && is_scalar($chosen[$role])) ? (int) $chosen[$role] : 0;
        $type = isset($columns[$column_id]) ? (string) ($columns[$column_id]['type'] ?? '') : '';

        if (($column_id > 0) && ($type === $part['type']) && !isset($taken[$column_id])) {
            $map[$role] = $column_id;
            $taken[$column_id] = true;
        } else {
            $map[$role] = 0;
        }
    }

    return $map;
}

/**
 * A table's rows compiled into a flow (pure): a "Started by hand" box, then
 * a task box for each row whose step is not empty, in row order. The arrow
 * into a box comes from the row its "after" cell names when that row comes
 * before it and has a box, else from the row before it (the start for the
 * first). A person cell becomes the assignee when the person is among
 * $members; days are rounded and kept within 0-3650. Boxes are laid out
 * from the start.
 *
 * @param array $rows    the rows in order (ws_wtable_row_out(): position, cells)
 * @param array $map     ws_wtable_flow_map()
 * @param int[] $members the people who may be given a task
 * @return array graph (ws_flow_graph_normalize()), tasks, empty (row positions without a step),
 *               unknown (row positions whose person may not be given a task), after_ignored (row
 *               positions whose after cell named no earlier row with a step), left_out (rows over the box limit)
 */
function ws_flow_from_wtable($rows, $map, $members)
{
    $members = array_flip(array_map('intval', (array) $members));
    $cell = function ($row, $role) use ($map) {
        $column_id = (int) ($map[$role] ?? 0);

        return ($column_id > 0) ? ($row['cells'][$column_id] ?? null) : null;
    };

    $nodes = array(array('id' => 'start', 'kind' => 'manual', 'label' => lang('Started by hand'), 'x' => 0, 'y' => 0, 'config' => array()));
    $edges = array();
    $boxes = array();
    $previous = 'start';
    $result = array('tasks' => 0, 'empty' => array(), 'unknown' => array(), 'after_ignored' => array(), 'left_out' => 0);
    $room = (defined('WS_FLOW_MAX_NODES') ? WS_FLOW_MAX_NODES : 100) - 1;

    foreach ((array) $rows as $index => $row) {
        $position = (int) ($row['position'] ?? ($index + 1));
        $step = $cell($row, 'step');
        $step = is_scalar($step) && !is_bool($step) ? trim(preg_replace('/\s+/u', ' ', (string) $step)) : '';

        if ($step === '') {
            $result['empty'][] = $position;
            continue;
        }

        if ($result['tasks'] >= $room) {
            $result['left_out']++;
            continue;
        }

        $id = 'row_' . (int) ($row['id'] ?? $position);
        $config = array('title' => $step);
        $person = $cell($row, 'person');

        if (is_numeric($person) && ((int) $person > 0)) {
            if (isset($members[(int) $person])) {
                $config['assignees'] = array((int) $person);
            } else {
                $result['unknown'][] = $position;
            }
        }

        $days = $cell($row, 'days');

        if (is_numeric($days) && !is_bool($days)) {
            $config['due_in'] = max(0, min(3650, (int) round((float) $days)));
        }

        $from = $previous;
        $after = $cell($row, 'after');

        if (is_numeric($after) && !is_bool($after) && ((int) $after > 0)) {
            // Only a row above with a box: an arrow from below could close a loop.
            if (((int) $after < $position) && isset($boxes[(int) $after])) {
                $from = $boxes[(int) $after];
            } else {
                $result['after_ignored'][] = $position;
            }
        }

        $nodes[] = array('id' => $id, 'kind' => 'task', 'label' => $step, 'x' => 0, 'y' => 0, 'config' => $config);
        $edges[] = array('from' => $from, 'to' => $id, 'on' => ($from === 'start') ? 'next' : 'done');
        $boxes[$position] = $id;
        $previous = $id;
        $result['tasks']++;
    }

    $graph = ws_flow_graph_normalize(array('v' => 1, 'nodes' => $nodes, 'edges' => $edges, 'notes' => array()));

    return array('graph' => ws_flow_auto_layout($graph, array())) + $result;
}

/**
 * Whether a person may make a flow from a table: they read it and build
 * flows (pure).
 *
 * @param string $access        ws_wtable_access()
 * @param bool   $builds_flows  ws_can_schedule()
 * @return string '' when they may, else a refusal code (not_found, forbidden)
 */
function ws_wtable_flow_refusal($access, $builds_flows)
{
    if (!ws_wtable_may((string) $access, 'read')) {
        return 'not_found';
    }

    return $builds_flows ? '' : 'forbidden';
}

/**
 * A draft flow made from a table's rows, owned by the person and never
 * published: they review it on the canvas.
 *
 * @param array      $viewer
 * @param array|null $table  ws_wtable()
 * @param mixed      $chosen role => column id, as posted
 * @return array ok, error (a message), flow_id, compiled (ws_flow_from_wtable() without its graph)
 */
function ws_wtable_flow_create($viewer, $table, $chosen)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'flow_id' => 0, 'compiled' => array());
    };

    $refusal = ws_wtable_flow_refusal($table ? ws_wtable_access($viewer, $table) : '', ws_can_schedule($viewer));

    if ($refusal === 'not_found') {
        return $fail(lang('That table could not be found.'));
    }

    if ($refusal !== '') {
        return $fail(lang('Only staff in the team can build flows.'));
    }

    if (!ws_flows_ready()) {
        return $fail(lang('The database has to be updated first.'));
    }

    $columns = ws_wtable_columns((int) $table['id']);
    $map = ws_wtable_flow_map($columns, $chosen);

    if ($map['step'] === 0) {
        return $fail(lang('Choose the column that names each step.'));
    }

    $rows = ws_wtable_rows((int) $table['id'], $columns);
    $people = array();

    if ($map['person'] > 0) {
        foreach ($rows as $row) {
            $person = $row['cells'][$map['person']] ?? null;

            if (is_numeric($person) && ((int) $person > 0)) {
                $people[(int) $person] = (int) $person;
            }
        }
    }

    $members = array();

    foreach ($people as $user_id) {
        if (!empty(ws_rights_for_id($user_id)['member'])) {
            $members[] = $user_id;
        }
    }

    $compiled = ws_flow_from_wtable($rows, $map, $members);

    if ($compiled['tasks'] === 0) {
        return $fail(lang('No row names a step: there is nothing to make a flow of.'));
    }

    $saved = ws_flow_save($viewer, array('name' => (string) $table['name'], 'graph' => $compiled['graph']));

    if (!$saved['ok']) {
        return $fail((string) $saved['error']);
    }

    unset($compiled['graph']);

    return array('ok' => true, 'error' => '', 'flow_id' => (int) $saved['flow_id'], 'compiled' => $compiled);
}

/**
 * What the rows could not give the draft, one sentence each.
 *
 * @param array $compiled ws_flow_from_wtable()
 * @return string[]
 */
function ws_wtable_flow_notes($compiled)
{
    $rows = function ($positions) {
        return implode(', ', array_map('intval', (array) $positions));
    };
    $notes = array();

    if (!empty($compiled['empty'])) {
        $notes[] = lang(array('string' => 'Rows without a step were left out: {var:1}.', 'vars' => $rows($compiled['empty'])));
    }

    if (!empty($compiled['unknown'])) {
        $notes[] = lang(array('string' => 'The person of these rows cannot be given a task, so their tasks have nobody yet: {var:1}.', 'vars' => $rows($compiled['unknown'])));
    }

    if (!empty($compiled['after_ignored'])) {
        $notes[] = lang(array('string' => 'These rows name no earlier row with a step to come after, so they follow the row before: {var:1}.', 'vars' => $rows($compiled['after_ignored'])));
    }

    if (!empty($compiled['left_out'])) {
        $notes[] = lang(array('string' => 'A flow holds at most {var:1} boxes: {var:2} rows were left out.', 'vars' => array((defined('WS_FLOW_MAX_NODES') ? WS_FLOW_MAX_NODES : 100), (int) $compiled['left_out'])));
    }

    return $notes;
}
