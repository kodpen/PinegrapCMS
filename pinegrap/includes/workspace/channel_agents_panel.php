<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the agents of a channel in the channel settings
 * (assets/js/workspace_channel_agents.js): the panel actions that list a
 * channel's agents with the registered ones it can still take, add one,
 * change what wakes it there and take it out. The same rules as the
 * workspace.channels.agents.* routes (api_agents.php): whoever may post in a
 * channel changes its agents, in a private channel only a member; a new
 * agent in a private channel and the tier are the channel manager's.
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
 * The panel actions this file answers.
 *
 * @return string[]
 */
function ws_channel_agents_actions()
{
    return array('ws_channel_agents_list', 'ws_channel_agents_set', 'ws_channel_agents_remove');
}

/**
 * A channel-agent row as the settings draw it: the agent's last seen
 * (ws_agent_seen_words()), its role, picture and state, and in a few words
 * what wakes it and what it may do by itself. Pure but for lang() and the
 * clock.
 *
 * @param array    $row ws_channel_agent_shape()
 * @param int|null $now
 * @return array
 */
function ws_channel_agent_panel_row($row, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $seen = ws_agent_seen_words($row['agent'], $now);
    $member = ws_channel_member_agent($row, $now);
    $active = ((string) $row['agent']['status'] === 'active');

    return array(
        'agent_id'    => (int) $row['agent']['id'],
        'kind'        => (string) $row['agent']['kind'],
        'handle'      => (string) $row['agent']['handle'],
        'name'        => (string) $row['agent']['name'],
        'role'        => $member['role'],
        'avatar'      => ws_channel_agent_avatar_url($row['agent']['avatar'] ?? ''),
        'active'      => $active,
        'state'       => ws_channel_agent_panel_state($active, $member['seen_state']),
        'wake_rule'   => (string) $row['wake_rule'],
        'wake_prefix' => (string) $row['wake_prefix'],
        'wake_words'  => $member['wake'],
        'tier'        => (int) $row['tier'],
        'tier_words'  => ws_channel_agent_tier_words($row['tier']),
        'loop_guard'  => (bool) $row['loop_guard'],
        'seen'        => $seen['label'],
        'not_seen'    => $seen['not_seen'],
        // The member list's short words for the state's pill: a poll
        // agent's last call, a push agent's last knock, "Always ready".
        'seen_words'  => $member['seen'],
    );
}

/**
 * The state the agents panel shows on a row: 'paused' for an agent not
 * active, first of all; else its last-seen state as the member list reads
 * it ('on', 'off', 'late', 'ready'). The agent card's own state (working,
 * waiting) replaces the second part once the server sends it. Pure.
 *
 * @param bool   $active
 * @param string $seen_state ws_channel_member_agent()'s seen_state
 * @return string
 */
function ws_channel_agent_panel_state($active, $seen_state)
{
    if (!$active) {
        return 'paused';
    }

    $seen_state = (string) $seen_state;

    return in_array($seen_state, array('on', 'off', 'late', 'ready'), true) ? $seen_state : 'off';
}

/**
 * What an agent may do by itself in a channel, in the few words of its row:
 * tier 1 asks before every change of a record, 2 makes the changes that
 * can be undone, 3 every change. A value out of range counts as 1, as
 * ws_agent_tier_allows() reads it. Pure but for lang().
 *
 * @param int $tier
 * @return string
 */
function ws_channel_agent_tier_words($tier)
{
    switch ((int) $tier) {
        case 2:
            return lang('makes changes that can be undone');
        case 3:
            return lang('makes every change by itself');
    }

    return lang('asks before any change');
}

/**
 * What wakes an agent in a channel, in the short words of its row and of
 * Channel info. Pure but for lang().
 *
 * @param string $rule   mention, all, prefix, replies
 * @param string $prefix the word of the prefix rule
 * @return string
 */
function ws_channel_agent_wake_words($rule, $prefix = '')
{
    switch ((string) $rule) {
        case 'all':
            return lang('wakes on every message');
        case 'prefix':
            return lang(array('string' => 'wakes on {var:1}', 'vars' => (string) $prefix));
        case 'replies':
            return lang('wakes on replies to it');
    }

    return lang('wakes when mentioned');
}

/**
 * An agent's own picture for the panel: a web address or a path of the
 * site, else '' and the screen draws the robot tile. Pure.
 *
 * @param string $avatar ws_agents.avatar
 * @return string
 */
function ws_channel_agent_avatar_url($avatar)
{
    $avatar = trim((string) $avatar);

    if (preg_match('~^(https://|/(?!/))[^\s"\'<>]+$~i', $avatar)) {
        return $avatar;
    }

    return '';
}

/**
 * The registered agents a channel does not have yet, for the Add list: only
 * what the list shows, never the door or the key. Claude is no channel agent
 * (ws_channel_agent_kind_problem()) and is not offered. Pure.
 *
 * @param array[] $agents ws_agent_shape() rows
 * @param int[]   $in     ids of the agents already in the channel
 * @return array[] agent_id, kind, handle, name, active
 */
function ws_channel_agents_addable($agents, $in)
{
    $in = array_map('intval', (array) $in);
    $out = array();

    foreach ((array) $agents as $agent) {
        if (in_array((int) $agent['id'], $in, true) || (ws_channel_agent_kind_problem($agent['kind']) !== '')) {
            continue;
        }

        $out[] = array(
            'agent_id' => (int) $agent['id'],
            'kind'     => (string) $agent['kind'],
            'handle'   => (string) $agent['handle'],
            'name'     => (string) $agent['name'],
            'active'   => ((string) $agent['status'] === 'active'),
        );
    }

    return $out;
}

/**
 * What the settings section needs about one channel.
 *
 * @param array $viewer
 * @param array $channel
 * @return array
 */
function ws_channel_agents_panel_state($viewer, $channel)
{
    if (!ws_agents_ready()) {
        return array('ready' => false, 'agents' => array(), 'addable' => array(), 'can_edit' => false, 'can_tier' => false);
    }

    $rows = ws_channel_agents($channel['id']);
    $in = array();

    foreach ($rows as $row) {
        $in[] = (int) $row['agent']['id'];
    }

    return array(
        'ready'    => true,
        'agents'   => array_map(function ($row) {
            return ws_channel_agent_panel_row($row);
        }, $rows),
        'addable'  => ws_channel_agents_addable(ws_agents_list(), $in),
        'can_edit' => (ws_channel_agent_add_problem($viewer, $channel) === ''),
        'can_tier' => ws_can_manage_channel($viewer, $channel),
    );
}

/**
 * Answers a ws_channel_agents_* action. Who may read the channel is checked
 * by ws_action_channel() (actions.php), who may change its agents here.
 *
 * @param string $action
 * @param array  $request channel_id; agent_id, wake_rule, wake_prefix, tier, loop_guard for a write
 * @return array
 */
function ws_channel_agents_action($action, $request)
{
    if (!ws_enabled()) {
        return ws_action_error(lang('The workspace is not switched on.'));
    }

    $viewer = ws_viewer(validate_user());

    if (!$viewer['member']) {
        return ws_action_error(lang('Access denied.'));
    }

    session_write_close();

    $request = is_array($request) ? $request : array();
    $channel = ws_action_channel($viewer, $request);

    if (!is_array($channel)) {
        return ws_action_error($channel);
    }

    if ($action === 'ws_channel_agents_list') {
        return ws_action_ok(ws_channel_agents_panel_state($viewer, $channel));
    }

    if (!ws_agents_ready()) {
        return ws_action_error(lang('The database has to be updated first.'));
    }

    $problem = ws_channel_agent_add_problem($viewer, $channel);

    if ($problem !== '') {
        return ws_action_error(($problem === 'forbidden') ? lang('You cannot post in that channel.') : ws_channel_agent_error_text($problem));
    }

    $agent = ws_agent((int) ($request['agent_id'] ?? 0));

    if (!$agent) {
        return ws_action_error(lang('That agent could not be found.'));
    }

    if ($action === 'ws_channel_agents_remove') {
        ws_channel_agent_remove($channel['id'], $agent['id']);

        return ws_action_ok(ws_channel_agents_panel_state($viewer, $channel));
    }

    $kind_problem = ws_channel_agent_kind_problem($agent['kind']);

    if ($kind_problem !== '') {
        return ws_action_error(ws_channel_agent_error_text($kind_problem));
    }

    $input = array();

    foreach (array('wake_rule', 'wake_prefix', 'tier', 'loop_guard') as $field) {
        if (array_key_exists($field, $request)) {
            $input[$field] = $request[$field];
        }
    }

    // The settings open only for whoever manages the channel, and a
    // discussion has none; the request can still be made without them.
    $current = ws_channel_agent_row($channel['id'], $agent['id']);
    $problem = ws_channel_agent_enter_problem($viewer, $channel, $current);

    if ($problem !== '') {
        return ws_action_error(ws_channel_agent_error_text($problem));
    }

    $check = ws_channel_agent_check($input, $current, ws_can_manage_channel($viewer, $channel));

    if (!$check['ok']) {
        return ws_action_error(ws_channel_agent_error_text($check['error']), $check['field']);
    }

    ws_channel_agent_save($viewer, $channel['id'], $agent['id'], $check['values']);

    return ws_action_ok(ws_channel_agents_panel_state($viewer, $channel));
}

/**
 * The words of the channel settings' Agents section.
 *
 * @return array key => text
 */
function ws_channel_agents_js_strings()
{
    return array(
        'cha_title'       => lang('Agents in this channel'),
        'cha_not_seen'    => lang('Not seen'),
        'cha_help'        => lang('Open an agent to choose when it is woken here and what it may do by itself. Adding it changes only when it wakes, never what it may read.'),
        'cha_empty'       => lang('No agent in this channel yet.'),
        'cha_add'         => lang('Add an agent'),
        'cha_choose'      => lang('Choose an agent'),
        'cha_remove'      => lang('Remove from the channel'),
        'cha_remove_ask'  => lang('Take this agent out of the channel? It is no longer woken here.'),
        'cha_save'        => lang('Save'),
        'cha_saved'       => lang('Saved.'),
        'cha_paused'      => lang('Paused'),
        'cha_state_working' => lang('Working'),
        'cha_state_waiting' => lang('Waiting'),
        'cha_wake'        => lang('When is it woken?'),
        'cha_wake_mention' => lang('When its name is written'),
        'cha_wake_all'    => lang('On every message'),
        'cha_wake_prefix' => lang('On messages starting with a word'),
        'cha_wake_replies' => lang('On answers to its messages'),
        'cha_wake_mention_help' => lang('It reads a message only when someone writes @{1} in it.'),
        'cha_wake_all_help' => lang('Every message written here wakes it, which can be many requests in a busy channel. Writing its name always wakes it too.'),
        'cha_wake_prefix_help' => lang('A message that starts with the word below wakes it. Writing its name always wakes it too.'),
        'cha_wake_replies_help' => lang('An answer to one of its messages wakes it, also when another agent writes it. Writing its name always wakes it too.'),
        'cha_prefix'      => lang('The word a message starts with'),
        'cha_tier'        => lang('What may it do by itself?'),
        'cha_tier_1'      => lang('Only read and write messages'),
        'cha_tier_1_help' => lang('Every change of a record waits for a person\'s approval.'),
        'cha_tier_2'      => lang('Make changes that can be undone'),
        'cha_tier_2_help' => lang('It applies changes that can be undone, for seven days; the rest wait for approval.'),
        'cha_tier_3'      => lang('Make every change'),
        'cha_tier_3_help' => lang('It applies every change, deletions and money included; these cannot be undone.'),
        'cha_tier_help'   => lang('Only the manager of the channel changes what it may do by itself.'),
        'cha_loop_guard'  => lang('Stop long agent conversations'),
        'cha_loop_guard_help' => lang('Agents answering one another are told to stop after a few turns and wait for a person.'),
        'cha_readonly'    => lang('Only whoever may post here, and in a private channel only a member, changes the agents.'),
        // Channel info and the channel head: people and agents.
        'cm_people'       => lang('People here'),
        'cm_agents'       => lang('Agents here'),
        'cm_agent'        => lang('AI agent'),
        'cm_assistant'    => lang('Assistant'),
        'cm_manage'       => lang('Manage'),
        'cm_manage_label' => lang('Manage the agents in this channel'),
        'cm_ready'        => lang('Always ready'),
    );
}

/**
 * How long ago a moment was, in the short words of the channel's member
 * list: "a moment ago", "12 min ago", "3 h ago", then the date. Pure but
 * for lang().
 *
 * @param int $timestamp 0 for never
 * @param int $now
 * @return string
 */
function ws_seen_ago_words($timestamp, $now)
{
    $timestamp = (int) $timestamp;

    if ($timestamp <= 0) {
        return lang('Not seen yet');
    }

    $age = max(0, (int) $now - $timestamp);

    if ($age < 60) {
        return lang('a moment ago');
    }

    if ($age < 3600) {
        return lang(array('string' => '{var:1} min ago', 'vars' => (int) floor($age / 60)));
    }

    if ($age < 86400) {
        return lang(array('string' => '{var:1} h ago', 'vars' => (int) floor($age / 3600)));
    }

    return date('d.m.Y H:i', $timestamp);
}

/**
 * A person's last seen in the channel's member list: "Online" while the
 * panel's heartbeat is fresh (ws_presence()), else how long ago it beat.
 * Sent only to a channel manager (ws_channel_member_seen()). Pure but for
 * lang() and ws_presence()'s clock.
 *
 * @param int $online_timestamp user.user_online_timestamp
 * @param int $now
 * @return string
 */
function ws_person_seen_words($online_timestamp, $now)
{
    return (ws_presence($online_timestamp) === 'online') ? lang('Online') : ws_seen_ago_words($online_timestamp, $now);
}

/**
 * The people's last seen of a channel, for its manager: user id => words.
 * Empty for everyone else, who see only the online dot.
 *
 * @param bool  $can_manage ws_can_manage_channel() of the reader
 * @param int[] $user_ids
 * @param int   $now
 * @return array
 */
function ws_channel_member_seen($can_manage, $user_ids, $now)
{
    $user_ids = array_filter(array_map('intval', (array) $user_ids));

    if (!$can_manage || empty($user_ids)) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT user_id, user_online_timestamp FROM user WHERE user_id IN (" . implode(',', $user_ids) . ")") as $row) {
        $out[(int) $row['user_id']] = ws_person_seen_words($row['user_online_timestamp'], $now);
    }

    return $out;
}

/**
 * One registry agent of a channel as its member list draws it: name,
 * handle, badge, what wakes it here and its last seen, which everyone in
 * the channel reads. A poll agent's last call with its key
 * (ws_agent_seen_words()'s rule: amber once unseen for ten minutes), a push
 * agent's last knock, a model assistant always ready. Pure but for lang().
 *
 * @param array $row ws_channel_agent_shape()
 * @param int   $now
 * @return array id, handle, name, role (its label, '' for none), kind, assistant, active, wake, seen, seen_state ('on', 'off', 'late', 'ready')
 */
function ws_channel_member_agent($row, $now)
{
    $agent = (array) $row['agent'];
    $kind = (string) ($agent['kind'] ?? '');
    $state = 'off';

    if ($kind !== 'app') {
        $seen = lang('Always ready');
        $state = 'ready';
    } elseif ((string) ($agent['door_kind'] ?? '') === 'poll') {
        $at = (int) ($agent['seen_at'] ?? 0);
        $seen = ws_seen_ago_words($at, $now);
        $state = (($at <= 0) || (((int) $now - $at) >= WS_AGENT_NOT_SEEN_AFTER)) ? 'late' : 'on';
    } else {
        $at = (int) ($agent['door_last_knock'] ?? 0);
        $seen = ($at > 0) ? lang(array('string' => 'Last knock {var:1}', 'vars' => ws_seen_ago_words($at, $now))) : lang('Not knocked yet');
        $state = ((string) ($agent['door_error'] ?? '') !== '') ? 'late' : 'off';
    }

    $wake = ws_channel_agent_wake_words($row['wake_rule'] ?? 'mention', $row['wake_prefix'] ?? '');

    return array(
        'id'         => (int) ($agent['id'] ?? 0),
        // The application it writes as, so the screen can tell its
        // messages in this channel (workspace.js speaksAnswer()).
        'app_id'     => (int) ($agent['app_id'] ?? 0),
        'handle'     => (string) ($agent['handle'] ?? ''),
        'name'       => (string) ($agent['name'] ?? ''),
        'role'       => ws_agent_role_label($agent['role'] ?? ''),
        'kind'       => $kind,
        'assistant'  => ($kind === 'model'),
        'active'     => ((string) ($agent['status'] ?? '') === 'active'),
        'wake'       => $wake,
        'seen'       => $seen,
        'seen_state' => $state,
    );
}

/**
 * A channel's registry agents for its head and Channel info, read by
 * everyone who reads the channel. Empty where the registry is not there.
 *
 * @param array $channel
 * @param int   $now
 * @return array[] ws_channel_member_agent()
 */
function ws_channel_member_agents($channel, $now)
{
    if (!ws_agents_ready()) {
        return array();
    }

    return array_map(function ($row) use ($now) {
        return ws_channel_member_agent($row, $now);
    }, ws_channel_agents($channel['id']));
}
