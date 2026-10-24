<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - flows in the external API: the owner's flows and the live ones
 * of the channels they read, drafting a flow (create and change its draft),
 * publishing, pausing and deleting it. The checks are the store's own
 * (includes/workspace/flows.php), so a dry run meets the refusals the real
 * call meets.
 *
 * An application key or an OAuth connection may draft, since a draft never
 * runs: an assistant can lay a process out for the person to look at.
 * Publishing is the person's own decision, made from their team devices
 * session: a published flow later opens tasks in their name.
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
    return array(
        'v'     => 1,
        'nodes' => array_map(function ($node) {
            $node['config'] = (object) $node['config'];

            return $node;
        }, $graph['nodes']),
        'edges' => $graph['edges'],
        'notes' => $graph['notes'],
    );
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

    if (!$flow) {
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

    $rows = ws_flows_list($viewer, (string) ($params['scope'] ?? 'all'));
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

function ws_api_flows_update($params)
{
    $viewer = ws_api_viewer();

    ws_api_flows_ready();

    $flow = ws_api_flow_editable($viewer, $params['id']);
    $data = ws_api_flow_input($params, $flow);

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
