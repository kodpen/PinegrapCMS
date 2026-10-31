<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - personal flows of a team member without a staff role (role 3).
 * Staff build flows as before (ws_can_schedule()); a role-3 member builds
 * flows of their own, which only they run: no home channel, no shares, a
 * short list of box kinds (ws_flow_member_kinds()), fewer flows and fewer
 * starts an hour. Every box is checked with the member's rights at publish
 * and again as it runs, so a right lost meanwhile stops the box; the list
 * of kinds is asked again at run too, as the owner's role stands then.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The flows a role-3 member keeps at most (staff: WS_FLOW_MAX_PER_OWNER).
define('WS_FLOW_MEMBER_MAX_PER_OWNER', 10);

// The runs a role-3 member's flows start in an hour, all of them together
// (staff: WS_FLOW_STARTS_HOURLY a flow).
define('WS_FLOW_MEMBER_STARTS_HOURLY', 20);

/**
 * Who the person is as a builder of flows, from their role alone: staff in
 * the team (Administrator, Designer, Manager), a role-3 member, or nobody (a
 * person outside the team). Pure.
 *
 * @param array|null $viewer
 * @return string staff | member | ''
 */
function ws_flow_builder_kind($viewer)
{
    if (!is_array($viewer) || empty($viewer['member'])) {
        return '';
    }

    return ((int) ($viewer['role'] ?? 3) < 3) ? 'staff' : 'member';
}

/**
 * May the person build flows, and may their published flows run? Staff and
 * role-3 members of the team, once scheduled actions are there.
 *
 * @param array|null $viewer
 * @return bool
 */
function ws_flow_builder($viewer)
{
    return (ws_flow_builder_kind($viewer) !== '') && ws_scheduled_ready();
}

/**
 * Is the person a role-3 member building their own flows (not staff)?
 *
 * @param array|null $viewer
 * @return bool
 */
function ws_flow_member($viewer)
{
    return (ws_flow_builder_kind($viewer) === 'member') && ws_scheduled_ready();
}

/**
 * The kinds of box a role-3 member's flow may hold: started by hand, at a
 * time or on an event they see; tasks, approvals, waits, conditions, value
 * boxes; posts, notices, a summary of counts and the task e-mail over their
 * own reach; rows added or changed in workflow tables they edit; a record
 * change, only as a proposal its owner approves before it is written
 * (ws_flow_change_asks()). No call to another system (webhook, web check,
 * MCP), no e-mail to addresses, no other flow or scheduled action started.
 * Pure.
 *
 * @return string[]
 */
function ws_flow_member_kinds()
{
    return array(
        'manual', 'schedule', 'event', 'no_order', 'end',
        'task', 'approval', 'delay', 'condition', 'constants', 'value',
        'post', 'notify', 'task_open', 'report', 'task_digest',
        'row_add', 'row_set', 'change',
    );
}

/**
 * Does a change box of this owner's flow propose its change instead of
 * writing it? A role-3 member's does: the box asks its owner to approve the
 * change in a channel and writes it only once approved (steps.php,
 * ws_step_change_ask()). Staff's change boxes write as publishing decided.
 * Asked with the owner as they stand now, so a flow built on the staff takes
 * this path once its owner is role 3. Pure.
 *
 * @param string $builder ws_flow_builder_kind() of the owner
 * @return bool
 */
function ws_flow_change_asks($builder)
{
    return $builder === 'member';
}

/**
 * Why a role-3 member's flow may not hold this box, '' when it may: a kind
 * outside ws_flow_member_kinds(), a summary of counts e-mailed to
 * addresses (it goes to a channel), or a change box with no channel to ask
 * its approval in. Pure.
 *
 * @param array $node a box of a normalized graph
 * @return string
 */
function ws_flow_member_node_refusal($node)
{
    $kind = (string) ($node['kind'] ?? '');

    if (!in_array($kind, ws_flow_member_kinds(), true)) {
        return lang('This kind of box is not open to your own flows.');
    }

    if (($kind === 'report') && (trim((string) ($node['config']['to'] ?? '')) !== '')) {
        return lang('In your own flows a summary of counts goes to a channel, not to e-mail addresses.');
    }

    if (($kind === 'change') && ((int) ($node['config']['channel_id'] ?? 0) <= 0)) {
        return lang('In your own flows a change is proposed for your approval first: choose the channel to ask in.');
    }

    return '';
}

/**
 * The first box of a graph a role-3 member's flow may not hold, in words
 * ("The box …: …"), '' when every box may stay. Pure.
 *
 * @param array $graph normalized
 * @return string
 */
function ws_flow_member_graph_refusal($graph)
{
    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        $refusal = ws_flow_member_node_refusal($node);

        if ($refusal !== '') {
            return lang(array('string' => 'The box {var:1}: {var:2}', 'vars' => array(ws_flow_box_name($node), $refusal)));
        }
    }

    return '';
}

/**
 * Why a flow's owner may not have it saved or published as it is, '' when
 * they may: only the flow's settings a role-3 member's flow is kept to
 * (staff: '' always). Pure.
 *
 * @param string $builder    ws_flow_builder_kind() of the owner
 * @param int    $channel_id the home channel the flow would have
 * @param array  $graph      normalized
 * @return array refusal (text, '' for none), field (channel_id | graph)
 */
function ws_flow_member_flow_refusal($builder, $channel_id, $graph)
{
    if ($builder !== 'member') {
        return array('refusal' => '', 'field' => '');
    }

    if ((int) $channel_id > 0) {
        return array('refusal' => lang('Your own flows have no home channel: only you run them.'), 'field' => 'channel_id');
    }

    return array('refusal' => ws_flow_member_graph_refusal($graph), 'field' => 'graph');
}

/**
 * The most flows the person keeps. Pure.
 *
 * @param string $builder ws_flow_builder_kind()
 * @return int
 */
function ws_flow_max_per_owner($builder)
{
    return ($builder === 'member') ? WS_FLOW_MEMBER_MAX_PER_OWNER : WS_FLOW_MAX_PER_OWNER;
}

/**
 * Why a box of a running flow does not run as its owner stands now, '' when
 * it runs: the owner is a role-3 member now, and their own flow may not hold
 * this box (a flow built while they were on the staff, say). An owner who
 * has left the team is stopped by each box (ws_flow_builder()); the reach
 * within an allowed box is asked by the box itself. A change box always
 * runs on to propose its change (ws_flow_change_asks()): one without a
 * channel to ask in fails there, never writing. Pure.
 *
 * @param string $builder ws_flow_builder_kind() of the owner now
 * @param array  $node
 * @return string
 */
function ws_flow_member_box_refusal($builder, $node)
{
    $kind = (string) ($node['kind'] ?? '');
    $refused = ($kind === 'change') ? !in_array($kind, ws_flow_member_kinds(), true) : (ws_flow_member_node_refusal($node) !== '');

    if (($builder === 'member') && $refused) {
        return lang('The owner of the flow may not use this kind of box in their own flows, so it did not run.');
    }

    return '';
}

/**
 * Why a run of a flow may not start for the owner's starts this hour, ''
 * when it may: a role-3 member's flows start WS_FLOW_MEMBER_STARTS_HOURLY
 * runs an hour together (staff: ''; their flows keep the cap of a flow,
 * ws_flow_start_refusal()). Pure.
 *
 * @param string $builder ws_flow_builder_kind() of the owner
 * @param int    $recent  the runs of the owner's flows started in the last hour
 * @return string
 */
function ws_flow_member_starts_refusal($builder, $recent)
{
    if (($builder === 'member') && ((int) $recent >= WS_FLOW_MEMBER_STARTS_HOURLY)) {
        return lang(array('string' => 'Your own flows were started {var:1} times in the last hour; try again later.', 'vars' => WS_FLOW_MEMBER_STARTS_HOURLY));
    }

    return '';
}

/**
 * The starts cap of a role-3 member's flows, read: the runs their flows
 * started in the last hour (actor_id is a run's owner).
 *
 * @param array $owner
 * @return string ws_flow_member_starts_refusal()
 */
function ws_flow_member_starts_check($owner)
{
    $builder = ws_flow_builder_kind($owner);

    if ($builder !== 'member') {
        return '';
    }

    $recent = (int) db_value("SELECT COUNT(*) FROM ws_flow_runs WHERE actor_id = '" . (int) $owner['id'] . "' AND started_at > '" . (time() - 3600) . "'");

    return ws_flow_member_starts_refusal($builder, $recent);
}

/**
 * Why a person may not start a run of this flow by hand for its owner, ''
 * when they may: a role-3 member's flow is run by its owner alone, whatever
 * home channel or share it kept from a time its owner was on the staff.
 * Pure.
 *
 * @param string $builder   ws_flow_builder_kind() of the owner
 * @param int    $owner_id
 * @param int    $viewer_id the person starting it
 * @return string
 */
function ws_flow_member_starter_refusal($builder, $owner_id, $viewer_id)
{
    if (($builder === 'member') && ((int) $owner_id !== (int) $viewer_id)) {
        return lang('Only its owner runs this flow.');
    }

    return '';
}
