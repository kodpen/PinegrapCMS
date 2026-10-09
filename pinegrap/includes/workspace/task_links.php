<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - tasks that wait for other tasks. A link says a task cannot
 * start until another one is done (ws_task_links: task_id waits for
 * blocked_by_task_id). A task with an open task in front of it is blocked:
 * its card and its row carry a lock and say what it waits for, and moving it
 * to In progress asks first - it does not refuse, the people doing the work
 * may know better. When the last task in front of it is done, the people on
 * it hear that it can start.
 *
 * A chain of links may not come back to where it started (A waits for B,
 * which waits for A): every new link walks the chain in front of it, fifty
 * steps at most, and is refused if it meets the task it was added to - or if
 * the chain is longer than that.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** How far along a chain of links a new link is checked. */
if (!defined('WS_TASK_LINK_DEPTH')) {
    define('WS_TASK_LINK_DEPTH', 50);
}

/** The most tasks one task may wait for. */
if (!defined('WS_TASK_LINK_MAX')) {
    define('WS_TASK_LINK_MAX', 20);
}

/**
 * Has the database the links between tasks (2026.4.8, 8.110)?
 *
 * @return bool
 */
function ws_task_links_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('ws_task_links', 'blocked_by_task_id');
    }

    return $ready;
}

/**
 * Would "task waits for blocker" close a loop? Pure: walks the links in
 * front of the blocker as the map gives them, level by level.
 *
 * @param array $map      task id => int[] the tasks it waits for
 * @param int   $task_id
 * @param int   $blocker_id
 * @param int   $depth    how many levels to walk
 * @return string '' when the link may be made; self | cycle | too_deep
 */
function ws_task_link_cycle($map, $task_id, $blocker_id, $depth = WS_TASK_LINK_DEPTH)
{
    $task_id = (int) $task_id;
    $blocker_id = (int) $blocker_id;

    if ($task_id === $blocker_id) {
        return 'self';
    }

    $seen = array($blocker_id => true);
    $frontier = array($blocker_id);

    for ($step = 0; $step < (int) $depth; $step++) {
        $next = array();

        foreach ($frontier as $id) {
            foreach ((array) ($map[$id] ?? array()) as $ahead) {
                $ahead = (int) $ahead;

                if ($ahead === $task_id) {
                    return 'cycle';
                }

                if (!isset($seen[$ahead])) {
                    $seen[$ahead] = true;
                    $next[] = $ahead;
                }
            }
        }

        if (empty($next)) {
            return '';
        }

        $frontier = $next;
    }

    return 'too_deep';
}

/**
 * The links in front of a task, read level by level as far as the check
 * walks, in the shape ws_task_link_cycle() takes.
 *
 * @param int $start_id
 * @param int $depth
 * @return array task id => int[]
 */
function ws_task_link_map_from($start_id, $depth = WS_TASK_LINK_DEPTH)
{
    $map = array();
    $frontier = array((int) $start_id);
    $seen = array((int) $start_id => true);

    // One level more than the check walks, so a chain that is too long is
    // seen to be.
    for ($step = 0; ($step <= (int) $depth) && !empty($frontier); $step++) {
        $next = array();

        foreach ((array) db_items("SELECT task_id, blocked_by_task_id FROM ws_task_links WHERE task_id IN (" . implode(',', $frontier) . ")") as $row) {
            $ahead = (int) $row['blocked_by_task_id'];
            $map[(int) $row['task_id']][] = $ahead;

            if (!isset($seen[$ahead])) {
                $seen[$ahead] = true;
                $next[] = $ahead;
            }
        }

        $frontier = $next;
    }

    return $map;
}

/**
 * Makes a task wait for another. The reader must be able to change the task
 * and to see the one it waits for.
 *
 * @param array $viewer
 * @param array $task
 * @param int   $blocker_task_id
 * @return array ok, error
 */
function ws_task_link_add($viewer, $task, $blocker_task_id)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error);
    };

    if (!ws_task_links_ready() || !is_array($task) || !ws_can_edit_task($viewer, $task)) {
        return $fail(lang('You cannot change this task.'));
    }

    $blocker = ws_task((int) $blocker_task_id);

    if (!$blocker || !ws_can_see_task($viewer, $blocker)) {
        return $fail(lang('That task could not be found.'));
    }

    if ((int) db_value("SELECT COUNT(*) FROM ws_task_links WHERE task_id = '" . (int) $task['id'] . "' AND blocked_by_task_id = '" . (int) $blocker['id'] . "'") > 0) {
        return array('ok' => true, 'error' => '');
    }

    if ((int) db_value("SELECT COUNT(*) FROM ws_task_links WHERE task_id = '" . (int) $task['id'] . "'") >= WS_TASK_LINK_MAX) {
        return $fail(lang(array('string' => 'A task can wait for {var:1} tasks at most.', 'vars' => WS_TASK_LINK_MAX)));
    }

    switch (ws_task_link_cycle(ws_task_link_map_from($blocker['id']), $task['id'], $blocker['id'])) {
        case 'self':
            return $fail(lang('A task cannot wait for itself.'));

        case 'cycle':
            return $fail(lang(array('string' => '{var:1} already waits for {var:2}, so {var:2} cannot wait for it.', 'vars' => array(ws_task_number($blocker['id']), ws_task_number($task['id'])))));

        case 'too_deep':
            return $fail(lang(array('string' => 'The chain of tasks in front of {var:1} is longer than {var:2} steps.', 'vars' => array(ws_task_number($blocker['id']), WS_TASK_LINK_DEPTH))));
    }

    db("INSERT IGNORE INTO ws_task_links (task_id, blocked_by_task_id, created_by, created_at)
        VALUES ('" . (int) $task['id'] . "', '" . (int) $blocker['id'] . "', '" . (int) $viewer['id'] . "', '" . time() . "')");

    db("UPDATE ws_tasks SET updated_at = '" . time() . "' WHERE id = '" . (int) $task['id'] . "'");

    return array('ok' => true, 'error' => '');
}

/**
 * Takes a link off.
 *
 * @param array $viewer
 * @param array $task
 * @param int   $blocker_task_id
 * @return array ok, error
 */
function ws_task_link_remove($viewer, $task, $blocker_task_id)
{
    if (!ws_task_links_ready() || !is_array($task) || !ws_can_edit_task($viewer, $task)) {
        return array('ok' => false, 'error' => lang('You cannot change this task.'));
    }

    db("DELETE FROM ws_task_links WHERE task_id = '" . (int) $task['id'] . "' AND blocked_by_task_id = '" . (int) $blocker_task_id . "'");
    db("UPDATE ws_tasks SET updated_at = '" . time() . "' WHERE id = '" . (int) $task['id'] . "'");

    return array('ok' => true, 'error' => '');
}

/**
 * Replaces the tasks a task waits for with a list (the API's blocked_by).
 * Links to tasks the reader cannot see are kept as they are.
 *
 * @param array $viewer
 * @param array $task
 * @param int[] $ids
 * @return array ok, error
 */
function ws_task_links_set($viewer, $task, $ids)
{
    if (!ws_task_links_ready()) {
        return array('ok' => false, 'error' => lang('The database has to be updated first.'));
    }

    $wanted = array();

    foreach ((array) $ids as $id) {
        if ((int) $id > 0) {
            $wanted[(int) $id] = (int) $id;
        }
    }

    foreach (ws_task_link_ids($task['id']) as $current) {
        $blocker = ws_task($current);

        if (!isset($wanted[$current]) && $blocker && ws_can_see_task($viewer, $blocker)) {
            $result = ws_task_link_remove($viewer, $task, $current);

            if (!$result['ok']) {
                return $result;
            }
        }
    }

    foreach ($wanted as $id) {
        $result = ws_task_link_add($viewer, $task, $id);

        if (!$result['ok']) {
            return $result;
        }
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Checks a list of tasks to wait for before anything is written (the API's
 * blocked_by): each one there and seen by the reader, not too many, no loop.
 *
 * @param array $viewer
 * @param int   $task_id 0 for a task about to be created
 * @param int[] $ids
 * @return string '' when the list may be set, else why not
 */
function ws_task_links_check($viewer, $task_id, $ids)
{
    if (!ws_task_links_ready()) {
        return lang('The database has to be updated first.');
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));

    if (count($ids) > WS_TASK_LINK_MAX) {
        return lang(array('string' => 'A task can wait for {var:1} tasks at most.', 'vars' => WS_TASK_LINK_MAX));
    }

    foreach ($ids as $id) {
        $blocker = ws_task($id);

        if (!$blocker || !ws_can_see_task($viewer, $blocker)) {
            return lang(array('string' => 'Task {var:1} could not be found.', 'vars' => ws_task_number($id)));
        }

        if (((int) $task_id > 0) && (ws_task_link_cycle(ws_task_link_map_from($id), $task_id, $id) !== '')) {
            return lang(array('string' => '{var:1} cannot wait for {var:2}: the tasks would wait for each other.', 'vars' => array(ws_task_number($task_id), ws_task_number($id))));
        }
    }

    return '';
}

/**
 * The tasks a task waits for.
 *
 * @param int $task_id
 * @return int[]
 */
function ws_task_link_ids($task_id)
{
    if (!ws_task_links_ready()) {
        return array();
    }

    return array_map('intval', (array) db_values("SELECT blocked_by_task_id FROM ws_task_links WHERE task_id = '" . (int) $task_id . "' ORDER BY created_at, blocked_by_task_id"));
}

/**
 * The tasks that wait for a task.
 *
 * @param int $task_id
 * @return int[]
 */
function ws_task_link_waiting_ids($task_id)
{
    if (!ws_task_links_ready()) {
        return array();
    }

    return array_map('intval', (array) db_values("SELECT task_id FROM ws_task_links WHERE blocked_by_task_id = '" . (int) $task_id . "' ORDER BY created_at, task_id"));
}

/**
 * The open tasks in front of a set of tasks, in one query: what makes each
 * of them blocked.
 *
 * @param int[] $task_ids
 * @return array task id => array[] id, number
 */
function ws_task_links_open_map($task_ids)
{
    $ids = array();

    foreach ((array) $task_ids as $task_id) {
        if ((int) $task_id > 0) {
            $ids[(int) $task_id] = (int) $task_id;
        }
    }

    $out = array();

    if (empty($ids) || !ws_task_links_ready()) {
        return $out;
    }

    foreach ((array) db_items("SELECT l.task_id, l.blocked_by_task_id FROM ws_task_links l
        INNER JOIN ws_tasks b ON b.id = l.blocked_by_task_id AND b.status IN ('todo', 'doing', 'waiting')
        WHERE l.task_id IN (" . implode(',', $ids) . ") ORDER BY l.created_at, l.blocked_by_task_id") as $row) {
        $out[(int) $row['task_id']][] = array('id' => (int) $row['blocked_by_task_id'], 'number' => ws_task_number($row['blocked_by_task_id']));
    }

    return $out;
}

/**
 * The Dependencies part of the task drawer.
 *
 * @param array $viewer
 * @param array $task
 * @param bool  $can_edit
 * @return array|null null when the schema is not there
 */
function ws_task_links_detail($viewer, $task, $can_edit)
{
    if (!ws_task_links_ready()) {
        return null;
    }

    $item = function ($id) use ($viewer) {
        $other = ws_task($id);
        $visible = $other && ws_can_see_task($viewer, $other);

        return array(
            'id'           => (int) $id,
            'number'       => ws_task_number($id),
            'title'        => $visible ? (string) $other['title'] : '',
            'status'       => $other ? (string) $other['status'] : '',
            'status_label' => $other ? ws_task_status_label($other['status']) : '',
            'open'         => $other ? ws_task_is_open($other['status']) : false,
            'visible'      => $visible,
        );
    };

    return array(
        'blocked_by' => array_map($item, ws_task_link_ids($task['id'])),
        'blocks'     => array_map($item, ws_task_link_waiting_ids($task['id'])),
        'can_edit'   => (bool) $can_edit,
        'max'        => WS_TASK_LINK_MAX,
    );
}

/**
 * A task was done: the people on each task that waited for it and now
 * waits for nothing open hear that it can start.
 *
 * @param array $viewer
 * @param array $task the task that was done
 */
function ws_task_links_done($viewer, $task)
{
    if (!ws_task_links_ready()) {
        return;
    }

    $waiting = ws_task_link_waiting_ids($task['id']);

    if (empty($waiting)) {
        return;
    }

    $still = ws_task_links_open_map($waiting);

    foreach ($waiting as $waiting_id) {
        $other = ws_task($waiting_id);

        if (!$other || !ws_task_is_open($other['status']) || !empty($still[$waiting_id])) {
            continue;
        }

        foreach (ws_task_assignee_ids($waiting_id) as $user_id) {
            if ((int) $user_id !== (int) $viewer['id']) {
                ws_notify($user_id, 'unblocked', array('task_id' => $waiting_id, 'actor_id' => $viewer['id']));
            }
        }
    }
}

/**
 * The task in front of this one that was done last: what an "it can start"
 * line names.
 *
 * @param int $task_id
 * @return int 0 when there is none
 */
function ws_task_links_last_done($task_id)
{
    if (!ws_task_links_ready()) {
        return 0;
    }

    return (int) db_value("SELECT b.id FROM ws_task_links l
        INNER JOIN ws_tasks b ON b.id = l.blocked_by_task_id AND b.status = 'done'
        WHERE l.task_id = '" . (int) $task_id . "' ORDER BY b.completed_at DESC, b.id DESC LIMIT 1");
}

/**
 * Every text assets/js/workspace_task_links.js shows.
 *
 * @return array
 */
function ws_task_links_js_strings()
{
    return array(
        'tl_title'          => lang('Dependencies'),
        'tl_blocked_by'     => lang('Cannot start until these are done'),
        'tl_blocks'         => lang('Waiting for this task'),
        'tl_none'           => lang('None'),
        'tl_add'            => lang('Find a task by its number or title'),
        'tl_remove'         => lang('Take the link off'),
        'tl_task_hidden'    => lang('A task you cannot see'),
        'tl_waits'          => ws_js_template('Waiting for {var:1}', 1),
        'tl_start_confirm'  => ws_js_template('This task is waiting for {var:1}, which is not done yet. Start it anyway?', 1),
        'tl_start_anyway'   => lang('Start anyway'),
    );
}
