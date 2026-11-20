<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what a channel shows about one of its registry agents: the
 * state its dot draws (paused, working, not seen, waiting), the requests
 * waiting for it, the usage mark of a window at WS_AGENT_USAGE_HIGH or more,
 * and the agent card opened on its name or picture (role, reported model and
 * windows, last seen, today's answered and failed requests, its last message
 * in the channel, the channels the reader may read it in, its tier and what
 * wakes it). Every fact is the agent's own report or a stored row; a
 * guest is never sent any of it.
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
 * A reported usage window at this percent or more gets the mark beside the
 * agent's name. A mark only: nothing is held back.
 */
define('WS_AGENT_USAGE_HIGH', 90);

/**
 * The state an agent's dot draws, the first that holds: paused (not
 * active), working (a request it took or was handed), not seen (late:
 * a poll agent unseen for ten minutes, a push door in error), waiting (a
 * request queued for it), else idle. Not seen goes before waiting: a queued
 * request of an agent that is down tells the reader nothing new. Pure.
 *
 * @param bool   $active     ws_agents.status is active
 * @param string $seen_state ws_channel_member_agent() seen_state
 * @param int    $working    requests sent to it or running
 * @param int    $queued     requests queued for it
 * @return string paused | working | late | waiting | idle
 */
function ws_agent_card_state($active, $seen_state, $working, $queued)
{
    if (!$active) {
        return 'paused';
    }

    if ((int) $working > 0) {
        return 'working';
    }

    if ((string) $seen_state === 'late') {
        return 'late';
    }

    return ((int) $queued > 0) ? 'waiting' : 'idle';
}

/**
 * The words of a state, for the card and the dot's name.
 *
 * @param string $state ws_agent_card_state()
 * @return string
 */
function ws_agent_card_state_words($state)
{
    switch ((string) $state) {
        case 'paused':
            return lang('Paused');
        case 'working':
            return lang('Working');
        case 'late':
            return lang('Not seen');
        case 'waiting':
            return lang('Waiting');
    }

    return lang('Ready');
}

/**
 * Whether the agent's last report has a window at WS_AGENT_USAGE_HIGH or
 * more. A stale report (WS_AGENT_REPORT_STALE_AFTER) or none gives no mark.
 * Pure.
 *
 * @param array[] $windows   ws_agent_report_windows()
 * @param int     $report_at ws_agents.report_at
 * @param int     $now
 * @return bool
 */
function ws_agent_card_usage_high($windows, $report_at, $now)
{
    if (((int) $report_at <= 0) || (((int) $now - (int) $report_at) >= WS_AGENT_REPORT_STALE_AFTER)) {
        return false;
    }

    foreach ((array) $windows as $window) {
        if ((int) ($window['percent'] ?? 0) >= WS_AGENT_USAGE_HIGH) {
            return true;
        }
    }

    return false;
}

/**
 * The windows of a report as the card draws them: label, percent, reset
 * time in words, and the mark. Pure but for lang() and date().
 *
 * @param array[] $windows ws_agent_report_windows()
 * @return array[] name, label, percent, resets_at, resets, high
 */
function ws_agent_card_windows($windows)
{
    $labels = ws_agent_report_window_labels();
    $out = array();

    foreach ((array) $windows as $window) {
        $out[] = array(
            'name'      => (string) $window['name'],
            'label'     => (string) ($labels[$window['name']] ?? $window['name']),
            'percent'   => (int) $window['percent'],
            'resets_at' => (int) $window['resets_at'],
            'resets'    => lang(array('string' => 'resets {var:1}', 'vars' => date('d.m H:i', (int) $window['resets_at']))),
            'high'      => ((int) $window['percent'] >= WS_AGENT_USAGE_HIGH),
        );
    }

    return $out;
}

/**
 * The counts of a set of rows of ws_ai_requests, by the agent value they
 * carry: queued, working (sent or running), and answered and failed since
 * the day began. Pure.
 *
 * @param array[] $rows agent, status, answered_at
 * @param int     $day_start
 * @return array agent value => queued, working, answered, failed
 */
function ws_agent_request_counts_of($rows, $day_start)
{
    $out = array();

    foreach ((array) $rows as $row) {
        $key = (string) $row['agent'];

        if (!isset($out[$key])) {
            $out[$key] = array('queued' => 0, 'working' => 0, 'answered' => 0, 'failed' => 0);
        }

        $status = (string) $row['status'];

        if ($status === 'queued') {
            $out[$key]['queued']++;
        } elseif (($status === 'sent') || ($status === 'running')) {
            $out[$key]['working']++;
        } elseif ((($status === 'answered') || ($status === 'failed')) && ((int) $row['answered_at'] >= (int) $day_start)) {
            $out[$key][$status]++;
        }
    }

    return $out;
}

/**
 * The open requests and today's closed ones of a set of agents, in one
 * query: agent value => queued, working, answered, failed. An agent with
 * none has no key.
 *
 * @param array[] $agents ws_agent_shape() rows
 * @param int     $now
 * @return array
 */
function ws_agent_request_counts($agents, $now)
{
    $values = array();

    foreach ((array) $agents as $agent) {
        $value = ws_agent_request_value($agent);

        if ($value !== '') {
            $values[$value] = "'" . e($value) . "'";
        }
    }

    if (empty($values)) {
        return array();
    }

    $day_start = (int) strtotime('today', (int) $now);

    // A request is closed on the day it was made or the next, so the
    // closed ones are read from yesterday's start on, through idx_status.
    $rows = db_items("SELECT agent, status, answered_at FROM ws_ai_requests
        WHERE agent IN (" . implode(',', $values) . ")
        AND (status IN ('queued', 'sent', 'running')
            OR (status IN ('answered', 'failed') AND created_at >= '" . ($day_start - 86400) . "' AND answered_at >= '" . $day_start . "'))");

    return ws_agent_request_counts_of((array) $rows, $day_start);
}

/**
 * The light part every member row of an agent carries, for the dot, the
 * role badge and the mark: role key, state, waiting, usage_high. Pure.
 *
 * @param array  $agent      ws_agent_shape()
 * @param string $seen_state ws_channel_member_agent() seen_state
 * @param array  $counts     ws_agent_request_counts() of this agent, or empty
 * @param int    $now
 * @return array role_key, state, state_words, waiting, usage_high
 */
function ws_agent_card_brief($agent, $seen_state, $counts, $now)
{
    $counts = (array) $counts;
    $state = ws_agent_card_state((string) ($agent['status'] ?? '') === 'active', $seen_state, (int) ($counts['working'] ?? 0), (int) ($counts['queued'] ?? 0));

    return array(
        'role_key'    => ws_agent_role_key($agent['role'] ?? ''),
        'state'       => $state,
        'state_words' => ws_agent_card_state_words($state),
        'waiting'     => (int) ($counts['queued'] ?? 0),
        'usage_high'  => ws_agent_card_usage_high(ws_agent_report_windows($agent['report_usage'] ?? ''), (int) ($agent['report_at'] ?? 0), $now),
    );
}

/**
 * The agent's last message in a channel: id and time, or null.
 *
 * @param array $agent ws_agent_shape()
 * @param int   $channel_id
 * @return array|null id, at
 */
function ws_agent_last_message($agent, $channel_id)
{
    $app_id = (int) ($agent['app_id'] ?? 0);

    if ($app_id <= 0) {
        return null;
    }

    $row = db_item("SELECT id, created_at FROM ws_messages
        WHERE channel_id = '" . (int) $channel_id . "' AND sender_kind = 'app' AND sender_id = '" . $app_id . "' AND deleted_at = 0
        ORDER BY id DESC LIMIT 1");

    return is_array($row) ? array('id' => (int) $row['id'], 'at' => (int) $row['created_at']) : null;
}

/**
 * The channels an agent is in that the reader may read, by name.
 *
 * @param array $viewer
 * @param int   $agent_id
 * @return array[] id, name
 */
function ws_agent_reader_channels($viewer, $agent_id)
{
    $out = array();

    foreach ((array) db_values("SELECT channel_id FROM ws_channel_agents WHERE agent_id = '" . (int) $agent_id . "' AND allowed = 1 ORDER BY channel_id") as $channel_id) {
        $channel = ws_channel((int) $channel_id);

        if (is_array($channel) && ws_can_read_channel($viewer, $channel)) {
            $out[] = array('id' => (int) $channel['id'], 'name' => (string) $channel['name']);
        }
    }

    return $out;
}

/**
 * The agent's own picture for the card: an https address or a path of
 * the site, else '' (the card draws the robot mark in a quiet tile). Pure.
 *
 * @param string $avatar ws_agents.avatar
 * @return string
 */
function ws_agent_card_avatar($avatar)
{
    $avatar = trim((string) $avatar);

    return preg_match('~^(https://|/(?!/))[^\s"\'<>]+$~i', $avatar) ? $avatar : '';
}

/**
 * The texts of the agent card in workspace.js.
 *
 * @return array key => text
 */
function ws_agent_card_js_strings()
{
    return array(
        'ac_state'        => lang('State now'),
        'ac_waiting'      => lang('Waiting for it'),
        'ac_requests'     => ws_js_template('{var:1} requests', 1),
        'ac_model'        => lang('Model'),
        'ac_seen'         => lang('Last seen'),
        'ac_today'        => lang('Today'),
        'ac_today_counts' => ws_js_template('{var:1} answered · {var:2} failed', 2),
        'ac_feedback'     => lang('Right or Wrong'),
        'ac_high'         => lang('This window is at 90% or more of its limit.'),
        'ac_wakes'        => lang('Wakes'),
        'ac_duty'         => lang('Duty'),
        'ac_last'         => lang('Last answer here'),
        'ac_channels'     => lang('Channels'),
        'ac_mention'      => lang('Mention'),
    );
}

/**
 * The agent card of one agent of a channel the reader reads. Null when the
 * agent is not in the channel.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $agent_id
 * @param int   $now
 * @return array|null
 */
function ws_agent_card($viewer, $channel, $agent_id, $now)
{
    $row = null;

    // In a discussion, the channel's agents asked there by name too
    // (ws_channel_member_agent_list()).
    foreach (ws_channel_member_agent_list($channel) as $candidate) {
        if ((int) $candidate['agent']['id'] === (int) $agent_id) {
            $row = $candidate;
            break;
        }
    }

    if (!$row) {
        return null;
    }

    $agent = (array) $row['agent'];
    $member = ws_channel_member_agent($row, $now);
    $counts = ws_agent_request_counts(array($agent), $now);
    $mine = $counts[ws_agent_request_value($agent)] ?? array();
    $report_at = (int) ($agent['report_at'] ?? 0);
    $reported = ((string) ($agent['kind'] ?? '') === 'app') && ($report_at > 0);
    $windows = $reported ? ws_agent_report_windows($agent['report_usage'] ?? '') : array();
    $last = ws_agent_last_message($agent, $channel['id']);

    return array(
        'id'           => (int) $agent['id'],
        'app_id'       => (int) ($agent['app_id'] ?? 0),
        'handle'       => (string) $agent['handle'],
        'name'         => (string) $agent['name'],
        'kind'         => (string) $agent['kind'],
        'avatar'       => ws_agent_card_avatar($agent['avatar'] ?? ''),
        'role'         => ws_agent_role_label($agent['role'] ?? ''),
        'duty'         => ws_agent_duty_clean($agent['duty'] ?? ''),
        'seen'         => $member['seen'],
        'seen_state'   => $member['seen_state'],
        'model'        => $reported ? (string) ($agent['report_model'] ?? '') : '',
        'windows'      => ws_agent_card_windows($windows),
        'reported'     => $reported ? lang(array('string' => 'As the agent reported it {var:1}; the site cannot check it.', 'vars' => ws_seen_ago_words($report_at, $now))) : '',
        'stale'        => $reported && (((int) $now - $report_at) >= WS_AGENT_REPORT_STALE_AFTER),
        'today'        => array('answered' => (int) ($mine['answered'] ?? 0), 'failed' => (int) ($mine['failed'] ?? 0)),
        // How people marked its answers, a line for 7 and one for 30 days;
        // empty while none was in 30 days.
        'feedback'     => function_exists('ws_agent_feedback_counts_by_agent') ? ws_agent_feedback_counts_lines(ws_agent_feedback_counts_by_agent($now)[ws_agent_request_value($agent)] ?? null) : array(),
        'last_message' => $last ? array('id' => $last['id'], 'when' => ws_seen_ago_words($last['at'], $now)) : null,
        'channels'     => ws_agent_reader_channels($viewer, $agent['id']),
        'tier'         => (int) $row['tier'],
        'wake'         => $member['wake'],
    ) + ws_agent_card_brief($agent, $member['seen_state'], $mine, $now);
}
