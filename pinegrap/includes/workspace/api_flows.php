<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - flows in the external API: the owner's flows and the live ones
 * of the channels they read, drafting a flow (create and change its draft),
 * publishing, pausing and deleting it; and its runs: starting one, reading
 * them and cancelling one. The checks are the store's and the runner's own
 * (includes/workspace/flows.php, flow_runs.php), so a dry run meets the
 * refusals the real call meets.
 *
 * An application key or an OAuth connection may draft, since a draft never
 * runs: an assistant can lay a process out for the person to look at.
 * Publishing is the person's own decision, made from their team devices
 * session: a published flow later opens tasks in their name.
 * Moving a published flow to another home channel is the person's own too:
 * the home decides at once who may start the flow and where its new runs
 * open their tasks. Starting and cancelling a run are REST calls only for
 * now, refused to the application an assistant works through: an AI app is
 * to ask the person first, which the MCP server cannot do yet. The
 * application Claude works through reads the flows and runs it reaches only
 * through a channel where Claude may be asked.
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

function ws_api_flow_version_present($version)
{
    return array(
        'id'           => (int) $version['id'],
        'n'            => (int) $version['n'],
        'published_by' => (int) $version['published_by'],
        'published_at' => api_time($version['published_at']),
    );
}

// What ws_api_flow_version_present() returns.
function ws_api_flow_version_schema()
{
    return array(
        'id'           => 'integer',
        'n'            => 'integer',
        'published_by' => 'integer',
        'published_at' => 'string?',
    );
}

/**
 * A graph as the API hands it out: a box's settings always an object, even
 * an end's empty ones.
 *
 * @param array $graph ws_flow_graph_normalize()
 * @return array
 */
function ws_api_flow_graph_present($graph)
{
    return ws_flow_graph_present($graph);
}

// What ws_api_flow_graph_present() returns.
function ws_api_flow_graph_schema()
{
    return array(
        'v'     => 'integer',
        'nodes' => array(array('id' => 'string', 'kind' => 'string', 'label' => 'string', 'x' => 'integer', 'y' => 'integer', 'config' => 'object')),
        'edges' => array(array('from' => 'string', 'to' => 'string', 'on' => 'string')),
        'notes' => array(array('id' => 'string', 'text' => 'string', 'x' => 'integer', 'y' => 'integer', 'w' => 'integer', 'h' => 'integer')),
    );
}

function ws_api_flow_problem_present($problem)
{
    return array(
        'code' => (string) $problem['code'],
        'node' => ($problem['node'] !== null) ? (string) $problem['node'] : null,
        'edge' => ($problem['edge'] !== null) ? (int) $problem['edge'] : null,
        'text' => (string) $problem['text'],
    );
}

// What ws_api_flow_problem_present() returns.
function ws_api_flow_problem_schema()
{
    return array(
        'code' => 'string',
        'node' => 'string?',
        'edge' => 'integer?',
        'text' => 'string',
    );
}

/**
 * One flow. A single flow read by its owner carries the draft and what stops
 * it from being published; whoever may run it sees the live graph only. The
 * listing carries neither.
 *
 * @param array      $flow    ws_flows row with access
 * @param array|null $version its live ws_flow_versions row
 * @param bool       $full    the graphs and problems too
 * @return array
 */
function ws_api_flow_present($flow, $version, $full)
{
    $edit = ((string) $flow['access'] === 'edit');
    $draft = ($full && $edit) ? ws_flow_graph_of($flow) : null;

    return array(
        'id'         => (int) $flow['id'],
        'name'       => (string) $flow['name'],
        'status'     => (string) $flow['status'],
        'access'     => (string) $flow['access'],
        'owner_id'   => (int) $flow['owner_id'],
        'channel_id' => ((int) $flow['channel_id'] > 0) ? (int) $flow['channel_id'] : null,
        'draft_rev'  => (int) $flow['draft_rev'],
        'version'    => is_array($version) ? ws_api_flow_version_present($version) : null,
        'draft'      => ($draft !== null) ? ws_api_flow_graph_present($draft) : null,
        'graph'      => ($full && is_array($version)) ? ws_api_flow_graph_present(ws_flow_graph_of($version)) : null,
        'problems'   => ($draft !== null) ? array_map('ws_api_flow_problem_present', ws_flow_problems($draft)) : null,
        'created_at' => api_time($flow['created_at']),
        'updated_at' => api_time($flow['updated_at']),
    );
}

// What ws_api_flow_present() returns.
function ws_api_flow_schema()
{
    return array(
        'id'         => 'integer',
        'name'       => 'string',
        'status'     => 'string',
        'access'     => 'string',
        'owner_id'   => 'integer',
        'channel_id' => 'integer?',
        'draft_rev'  => 'integer',
        'version'    => 'WorkspaceFlowVersion?',
        'draft'      => 'WorkspaceFlowGraph?',
        'graph'      => 'WorkspaceFlowGraph?',
        'problems'   => 'WorkspaceFlowProblem[]?',
        'created_at' => 'string?',
        'updated_at' => 'string?',
    );
}

/**
 * The flow tables are there, or the call is answered 503.
 */
function ws_api_flows_ready()
{
    if (!ws_flows_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }
}

/**
 * Whether the current application reaches a flow or a run the owner reaches
 * through a channel: always, except for the application Claude works
 * through and a channel where Claude may not be asked (as a note shared
 * through such a channel, ws_api_note_reachable()). What the owner of its
 * key owns (access edit) stays readable.
 *
 * @param string $access     edit, or run / read through the channel
 * @param int    $channel_id the flow's home, or the channel the run was started in
 * @return bool
 */
function ws_api_flow_reachable($access, $channel_id)
{
    if (((string) $access === 'edit') || !ws_api_is_claude()) {
        return true;
    }

    $channel = ((int) $channel_id > 0) ? ws_channel((int) $channel_id) : null;

    return $channel && ws_claude_channel_allowed($channel);
}

/**
 * A flow the owner of the application reaches, read again from the table,
 * with access; anything else is a 404.
 *
 * @param array $viewer
 * @param int   $id
 * @return array
 */
function ws_api_flow_or_404($viewer, $id)
{
    $flow = ws_flow_for($viewer, (int) $id, 'run');

    if (!$flow || !ws_api_flow_reachable($flow['access'], $flow['channel_id'])) {
        api_fail_not_found(lang('Flow'));
    }

    return $flow;
}

/**
 * A flow the owner of the application may change; one they may only run is
 * a 403.
 *
 * @param array $viewer
 * @param int   $id
 * @return array
 */
function ws_api_flow_editable($viewer, $id)
{
    $flow = ws_api_flow_or_404($viewer, $id);

    if ($flow['access'] !== 'edit') {
        api_fail(403, 'forbidden', lang('Only the owner of a flow can change it.'));
    }

    return $flow;
}

/**
 * A refused flow write, as the API answers it.
 *
 * @param array $result a ws_flow_*_check() or write result
 */
function ws_api_flow_refused($result)
{
    $field = ((string) $result['field'] === 'graph') ? 'nodes' : (string) $result['field'];

    switch ((int) $result['status']) {
        case 403:
            api_fail(403, 'forbidden', $result['error']);
            break;

        case 404:
            api_fail_not_found(lang('Flow'));
            break;

        case 409:
            api_fail(409, 'changed_meanwhile', $result['error'], ($field !== '') ? $field : null);
            break;

        case 503:
            api_fail(503, 'service_unavailable', $result['error']);
            break;
    }

    api_fail_validation($result['error'], ($field !== '') ? $field : null);
}

/**
 * The flow as it is now, with its live version.
 *
 * @param array $viewer
 * @param int   $id
 * @param bool  $full
 * @return array
 */
function ws_api_flow_answer($viewer, $id, $full = true)
{
    $flow = ws_api_flow_or_404($viewer, $id);

    return ws_api_flow_present($flow, ws_flow_version($flow['version_id']), $full);
}

/**
 * The fields of a flow write, in the shape ws_flow_save() reads; only those
 * sent. nodes, edges and notes are the three parts of the draft: a part not
 * sent is kept from the draft as it is ($current), or empty on a new flow.
 *
 * @param array      $params
 * @param array|null $current ws_flows row for a change
 * @return array
 */
function ws_api_flow_input($params, $current = null)
{
    $data = array();

    foreach (array('name', 'channel_id', 'draft_rev') as $field) {
        if (array_key_exists($field, $params)) {
            $data[$field] = $params[$field];
        }
    }

    $sent = array_intersect(array('nodes', 'edges', 'notes'), array_keys($params));

    if (($current === null) || !empty($sent)) {
        $graph = ($current !== null) ? ws_flow_graph_of($current) : ws_flow_graph_empty();

        foreach ($sent as $part) {
            $graph[$part] = (array) $params[$part];
        }

        $data['graph'] = $graph;
    }

    return $data;
}

/**
 * What a rehearsed draft write answers in detail: the draft as it would be
 * kept and what would stop it from being published.
 *
 * @param array $checked ws_flow_save_check()
 * @return array
 */
function ws_api_flow_draft_detail($checked)
{
    return array(
        'name'     => $checked['sets']['name'] ?? null,
        'fields'   => array_keys($checked['sets']),
        'boxes'    => count($checked['graph']['nodes']),
        'arrows'   => count($checked['graph']['edges']),
        'problems' => array_map('ws_api_flow_problem_present', $checked['problems']),
    );
}

function ws_api_flows_list($params)
{
    $viewer = ws_api_viewer();

    ws_api_flows_ready();

    $rows = array_values(array_filter(ws_flows_list($viewer, (string) ($params['scope'] ?? 'all')), function ($row) {
        return ws_api_flow_reachable($row['access'], $row['channel_id']);
    }));
    $version_ids = array();

    foreach ($rows as $row) {
        if ((int) $row['version_id'] > 0) {
            $version_ids[] = (int) $row['version_id'];
        }
    }

    $versions = array();

    if (!empty($version_ids)) {
        foreach ((array) db_items("SELECT id, flow_id, n, published_by, published_at FROM ws_flow_versions WHERE id IN (" . implode(',', $version_ids) . ")") as $version) {
            $versions[(int) $version['id']] = $version;
        }
    }

    $out = array();

    foreach ($rows as $row) {
        $out[] = ws_api_flow_present($row, $versions[(int) $row['version_id']] ?? null, false);
    }

    api_ok_list($out, count($out));
}

function ws_api_flows_get($params)
{
    $viewer = ws_api_viewer();

    ws_api_flows_ready();

    api_ok(ws_api_flow_answer($viewer, $params['id']));
}

function ws_api_flows_create($params)
{
    $viewer = ws_api_viewer();

    ws_api_flows_ready();

    $data = ws_api_flow_input($params);

    // The checks ws_flow_save() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_flow_save_check($viewer, $data);

    if (!$checked['ok']) {
        ws_api_flow_refused($checked);
    }

    api_dry_run_stop('created', 'workspace_flow', ws_api_flow_draft_detail($checked));

    $result = ws_api_as_owner(function () use ($viewer, $data) {
        return ws_flow_save($viewer, $data);
    });

    if (!$result['ok']) {
        ws_api_flow_refused($result);
    }

    api_ok(ws_api_flow_answer($viewer, $result['flow_id']), 201);
}

/**
 * A published flow moved to another home channel is the person's own
 * decision, made from their team devices session as a publish is: the home
 * decides at once who may start the flow and in which channel its new runs
 * open their tasks, so a draft change from an application key or an OAuth
 * connection (an assistant drafting) may not make it. Everything else in a
 * draft stays open to them, and so does the home of a flow never published.
 *
 * @param array $flow ws_flows row
 * @param array $data ws_api_flow_input()
 */
function ws_api_flow_home_move_gate($flow, $data)
{
    if (!array_key_exists('channel_id', $data) || (max(0, (int) $data['channel_id']) === (int) $flow['channel_id']) || ((int) $flow['version_id'] <= 0)) {
        return;
    }

    if (!ws_api_device_writes_as_person(function_exists('api_current_device') ? api_current_device() : null)) {
        api_fail(403, 'forbidden', lang('Only the person\'s own team devices session (POST /auth/login) can move a published flow to another home channel; an application key or an OAuth connection cannot do it for them.'), 'channel_id');
    }
}

function ws_api_flows_update($params)
{
    $viewer = ws_api_viewer();

    ws_api_flows_ready();

    $flow = ws_api_flow_editable($viewer, $params['id']);
    $data = ws_api_flow_input($params, $flow);

    ws_api_flow_home_move_gate($flow, $data);

    // The checks ws_flow_save() makes, the draft saved meanwhile among them.
    $checked = ws_flow_save_check($viewer, $data, $flow);

    if (!$checked['ok']) {
        ws_api_flow_refused($checked);
    }

    api_dry_run_stop('updated', 'workspace_flow', array('id' => (int) $flow['id']) + ws_api_flow_draft_detail($checked));

    $result = ws_api_as_owner(function () use ($viewer, $data, $flow) {
        return ws_flow_save($viewer, $data, $flow);
    });

    if (!$result['ok']) {
        ws_api_flow_refused($result);
    }

    api_ok(ws_api_flow_answer($viewer, $flow['id']));
}

// Published, a flow later opens tasks in the person's own name, so it is
// published from their team devices session only, and offered to no MCP
// client as a tool.
function ws_api_flows_publish($params)
{
    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can publish a flow; an application key or an OAuth connection cannot do it for them.'));

    $viewer = ws_api_viewer();

    ws_api_flows_ready();

    $flow = ws_api_flow_editable($viewer, $params['id']);
    $data = array_key_exists('draft_rev', $params) ? array('draft_rev' => (int) $params['draft_rev']) : array();

    $checked = ws_flow_publish_check($viewer, $flow, $data);

    if (!$checked['ok']) {
        ws_api_flow_refused($checked);
    }

    api_dry_run_stop('published', 'workspace_flow', array(
        'id'     => (int) $flow['id'],
        'boxes'  => count($checked['graph']['nodes']),
        'arrows' => count($checked['graph']['edges']),
    ));

    $result = ws_api_as_owner(function () use ($viewer, $flow, $data) {
        return ws_flow_publish($viewer, $flow, $data);
    });

    if (!$result['ok']) {
        ws_api_flow_refused($result);
    }

    api_ok(ws_api_flow_answer($viewer, $flow['id']));
}

function ws_api_flows_pause($params)
{
    $viewer = ws_api_viewer();

    ws_api_flows_ready();

    $flow = ws_api_flow_editable($viewer, $params['id']);
    $checked = ws_flow_pause_check($viewer, $flow);

    if (!$checked['ok']) {
        ws_api_flow_refused($checked);
    }

    api_dry_run_stop('paused', 'workspace_flow', array('id' => (int) $flow['id']));

    $result = ws_api_as_owner(function () use ($viewer, $flow) {
        return ws_flow_pause($viewer, $flow);
    });

    if (!$result['ok']) {
        ws_api_flow_refused($result);
    }

    api_ok(ws_api_flow_answer($viewer, $flow['id']));
}

function ws_api_flows_delete($params)
{
    $viewer = ws_api_viewer();

    ws_api_flows_ready();

    $flow = ws_api_flow_editable($viewer, $params['id']);
    $checked = ws_flow_bin_check($viewer, $flow);

    if (!$checked['ok']) {
        ws_api_flow_refused($checked);
    }

    api_dry_run_stop('deleted', 'workspace_flow', array('id' => (int) $flow['id'], 'name' => (string) $flow['name']));

    $result = ws_api_as_owner(function () use ($viewer, $flow) {
        return ws_flow_bin($viewer, $flow);
    });

    if (!$result['ok']) {
        ws_api_flow_refused($result);
    }

    // Nobody reaches it any more: the answer is the flow as it was, marked
    // deleted.
    $flow['status'] = 'binned';
    $flow['updated_at'] = time();

    api_ok(ws_api_flow_present($flow, ws_flow_version($flow['version_id']), false));
}

/* ---------------------------------------------------------------------------
   Runs (includes/workspace/flow_runs.php)
   --------------------------------------------------------------------------- */

/**
 * The most characters of a box's result an answer carries.
 */
define('WS_API_FLOW_RESULT_CHARS', 1000);

/**
 * One box of a run as the API hands it out: which box, how it stands, the
 * task it opened, its times and how it went, in short.
 *
 * @param array $step  ws_flow_run_steps row
 * @param array $graph the version the run follows
 * @return array
 */
function ws_api_flow_run_step_present($step, $graph)
{
    $node = ws_flow_graph_node($graph, $step['node_id']);
    $task_id = ws_flow_effect_task_id((string) $step['effect']);

    return array(
        'node_id'     => (string) $step['node_id'],
        'kind'        => $node ? (string) $node['kind'] : '',
        'name'        => $node ? ws_flow_box_name($node) : (string) $step['node_id'],
        'status'      => (string) $step['status'],
        'outcome'     => ((string) $step['outcome'] !== '') ? (string) $step['outcome'] : null,
        'task_id'     => ($task_id > 0) ? $task_id : null,
        'attempt'     => (int) $step['attempt'],
        'ready_at'    => ws_flow_time($step['ready_at']),
        'started_at'  => ws_flow_time($step['claimed_at']),
        'finished_at' => ws_flow_time($step['finished_at']),
        'result'      => mb_substr(ws_flow_result_text($step['result']), 0, WS_API_FLOW_RESULT_CHARS),
    );
}

// What ws_api_flow_run_step_present() returns.
function ws_api_flow_run_step_schema()
{
    return array(
        'node_id'     => 'string',
        'kind'        => 'string',
        'name'        => 'string',
        'status'      => 'string',
        'outcome'     => 'string?',
        'task_id'     => 'integer?',
        'attempt'     => 'integer',
        'ready_at'    => 'string?',
        'started_at'  => 'string?',
        'finished_at' => 'string?',
        'result'      => 'string',
    );
}

/**
 * One run. A single run read carries its boxes and the graph of the version
 * it follows; the listing carries neither.
 *
 * The canvas's panel actions (ws_flow_run_*, actions.php) answer with this
 * presenter and the one of a box too, so the screen and an application read
 * a run alike; both therefore write their times with ws_flow_time(), which
 * the panel has, rather than api_time(), which only the API loads.
 *
 * @param array      $viewer
 * @param array      $run   ws_flow_runs row with access (edit or read)
 * @param int        $n     the number of the version it follows
 * @param array|null $steps its ws_flow_run_steps rows, for a single read
 * @param array|null $graph the version's graph, with the steps
 * @return array
 */
function ws_api_flow_run_present($viewer, $run, $n, $steps = null, $graph = null)
{
    $starter = ((int) $run['started_by'] === (int) $viewer['id']);
    $full = ($steps !== null) && ($graph !== null);

    return array(
        'id'          => (int) $run['id'],
        'flow_id'     => (int) $run['flow_id'],
        'version_id'  => (int) $run['version_id'],
        'version'     => (int) $n,
        'status'      => (string) $run['status'],
        'access'      => (string) $run['access'],
        'can_cancel'  => ws_flow_run_open($run['status']) && ws_flow_run_cancel_allowed((string) $run['access'], $starter),
        'started_by'  => (int) $run['started_by'],
        'actor_id'    => (int) $run['actor_id'],
        'channel_id'  => (ws_flow_run_channel_id($run) > 0) ? ws_flow_run_channel_id($run) : null,
        'boxes_run'   => (int) $run['boxes_run'],
        'started_at'  => ws_flow_time($run['started_at']),
        'updated_at'  => ws_flow_time($run['updated_at']),
        'finished_at' => ws_flow_time($run['finished_at']),
        'steps'       => $full ? array_map(function ($step) use ($graph) {
            return ws_api_flow_run_step_present($step, $graph);
        }, $steps) : null,
        'graph'       => $full ? ws_api_flow_graph_present($graph) : null,
    );
}

// What ws_api_flow_run_present() returns.
function ws_api_flow_run_schema()
{
    return array(
        'id'          => 'integer',
        'flow_id'     => 'integer',
        'version_id'  => 'integer',
        'version'     => 'integer',
        'status'      => 'string',
        'access'      => 'string',
        'can_cancel'  => 'boolean',
        'started_by'  => 'integer',
        'actor_id'    => 'integer',
        'channel_id'  => 'integer?',
        'boxes_run'   => 'integer',
        'started_at'  => 'string?',
        'updated_at'  => 'string?',
        'finished_at' => 'string?',
        'steps'       => 'WorkspaceFlowRunStep[]?',
        'graph'       => 'WorkspaceFlowGraph?',
    );
}

/**
 * A run the owner of the application reaches, with access and its flow;
 * anything else is a 404.
 *
 * @param array $viewer
 * @param int   $id
 * @return array
 */
function ws_api_flow_run_or_404($viewer, $id)
{
    $run = ws_flow_run_for($viewer, (int) $id);

    if (!$run || !ws_api_flow_reachable($run['access'], ws_flow_run_channel_id($run))) {
        api_fail_not_found(lang('Flow run'));
    }

    return $run;
}

/**
 * A refused run write, as the API answers it.
 *
 * @param array $result a ws_flow_run_*_check() or write result
 */
function ws_api_flow_run_refused($result)
{
    if ((int) $result['status'] === 404) {
        api_fail_not_found(lang('Flow run'));
    }

    ws_api_flow_refused($result);
}

/**
 * The run as it is now, with its boxes and its version's graph.
 *
 * @param array $viewer
 * @param int   $id
 * @return array
 */
function ws_api_flow_run_answer($viewer, $id)
{
    $run = ws_api_flow_run_or_404($viewer, $id);
    $version = ws_flow_version($run['version_id']);

    return ws_api_flow_run_present($viewer, $run, is_array($version) ? (int) $version['n'] : 0, ws_flow_run_steps($run['id']), ws_flow_run_graph($run));
}

function ws_api_flow_runs_list($params)
{
    $viewer = ws_api_viewer();

    ws_api_flows_ready();

    $flow = ws_api_flow_or_404($viewer, $params['id']);
    $before = 0;

    if (isset($params['cursor']) && ($params['cursor'] !== '')) {
        $cursor = api_cursor_decode($params['cursor']);

        if ($cursor === null) {
            api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
        }

        $before = (int) $cursor['i'];
    }

    $limit = max(1, min(100, (int) ($params['limit'] ?? 50)));

    // Each run with its own access: its flow's owner reads them all, anybody
    // else those started in a channel they read.
    $page = ws_flow_runs_list($viewer, $flow, array('status' => (string) ($params['status'] ?? ''), 'before_id' => $before, 'limit' => $limit), function ($run) {
        return ws_api_flow_reachable($run['access'], ws_flow_run_channel_id($run));
    });
    $version_ids = array();

    foreach ($page['rows'] as $run) {
        $version_ids[(int) $run['version_id']] = (int) $run['version_id'];
    }

    $numbers = array();

    if (!empty($version_ids)) {
        foreach ((array) db_items("SELECT id, n FROM ws_flow_versions WHERE id IN (" . implode(',', $version_ids) . ")") as $version) {
            $numbers[(int) $version['id']] = (int) $version['n'];
        }
    }

    $out = array();

    foreach ($page['rows'] as $run) {
        $out[] = ws_api_flow_run_present($viewer, $run, $numbers[(int) $run['version_id']] ?? 0);
    }

    api_ok_list($out, $limit, ($page['next'] !== null) ? api_cursor_encode($page['next'], $page['next']) : null);
}

function ws_api_flow_runs_get($params)
{
    $viewer = ws_api_viewer();

    ws_api_flows_ready();

    api_ok(ws_api_flow_run_answer($viewer, $params['id']));
}

// A run opens tasks in the flow owner's name; an AI app is to ask the person
// before it starts one, which the MCP server cannot do yet, so this route is
// offered to no MCP client as a tool, and the application an assistant works
// through is refused here as well: Claude's routine calls REST with its key.
function ws_api_flow_runs_start($params)
{
    $viewer = ws_api_viewer();

    if (ws_api_is_assistant()) {
        api_fail(403, 'forbidden', lang('An assistant does not start or cancel the runs of a flow; the person does.'));
    }

    ws_api_flows_ready();

    $data = array_key_exists('token', $params) ? array('token' => (string) $params['token']) : array();

    // A start retried with its token after the flow was paused: the person
    // may no longer run the flow, but the run the token started is answered
    // to them while they may read it, as before the pause, so the flow is
    // taken as it is and the start check below answers that run. Any other
    // flow they may not run is a 404, as always.
    $retried = (ws_flow_for($viewer, (int) $params['id'], 'run') === null) ? ws_flow_run_for_token($viewer, (int) $params['id'], $data['token'] ?? '') : null;
    $flow = $retried ? $retried['flow'] : ws_api_flow_or_404($viewer, $params['id']);

    // The checks ws_flow_run_start() makes, made first so that a dry run
    // meets the same refusals.
    $checked = ws_flow_run_start_check($viewer, $flow, $data);

    if (!$checked['ok']) {
        ws_api_flow_refused($checked);
    }

    api_dry_run_stop('started', 'workspace_flow_run', array(
        'flow_id'  => (int) $flow['id'],
        'version'  => (int) $checked['version']['n'],
        'boxes'    => count($checked['graph']['nodes']),
        'existing' => ($checked['existing'] > 0) ? (int) $checked['existing'] : null,
    ));

    $result = ws_api_as_owner(function () use ($viewer, $flow, $data) {
        return ws_flow_run_start($viewer, $flow, $data);
    });

    if (!$result['ok']) {
        ws_api_flow_refused($result);
    }

    api_ok(ws_api_flow_run_answer($viewer, $result['run_id']), $result['started'] ? 201 : 200);
}

// Cancelled, a run stops acting in the owner's name; an AI app is to ask the
// person first, as for a start, so this is no MCP tool either, nor a call an
// assistant's application may make.
function ws_api_flow_runs_cancel($params)
{
    $viewer = ws_api_viewer();

    if (ws_api_is_assistant()) {
        api_fail(403, 'forbidden', lang('An assistant does not start or cancel the runs of a flow; the person does.'));
    }

    ws_api_flows_ready();

    $run = ws_api_flow_run_or_404($viewer, $params['id']);
    $checked = ws_flow_run_cancel_check($viewer, $run);

    if (!$checked['ok']) {
        ws_api_flow_run_refused($checked);
    }

    api_dry_run_stop('cancelled', 'workspace_flow_run', array(
        'id'         => (int) $run['id'],
        'open_boxes' => (int) $checked['open'],
        'tasks_kept' => $checked['task_ids'],
    ));

    $result = ws_api_as_owner(function () use ($viewer, $run) {
        return ws_flow_run_cancel($viewer, $run);
    });

    if (!$result['ok']) {
        ws_api_flow_run_refused($result);
    }

    api_ok(ws_api_flow_run_answer($viewer, $run['id']));
}
