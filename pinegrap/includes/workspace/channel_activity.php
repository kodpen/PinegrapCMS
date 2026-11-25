<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the read behind the Activity tab of Channel info: in one
 * answer, the state of a channel's agents (the summary line, a row each with
 * what it works on and what it last reported), the requests waiting, in work
 * and closed today, the polls and approvals waiting for a person, today's
 * decisions, the plan windows the agents reported (each once, with the
 * agents that reported it), the project, and the repository's numbers.
 *
 * It only tells: no setting, no action. The panel action ws_channel_activity
 * (channel_agents_panel.php) and GET /workspace/channels/{id}/activity
 * (api_channel_reads.php) both answer with ws_channel_activity(), for every
 * team reader of the channel; a guest is never sent any of it.
 *
 * An agent's context, project and branch come from its last report
 * (ws_agent_report_context(), ws_agent_report_project(),
 * ws_agent_report_branch() in agent_report.php), null where it named none;
 * the repository part from ws_channel_activity_repo()
 * (channel_activity_repo.php), null without a link the reader may see.
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
 * The polls and approvals the Activity tab lists before "n more".
 */
define('WS_CHANNEL_ACTIVITY_WAITING_SHOWN', 5);

/**
 * The decisions of today the Activity tab names.
 */
define('WS_CHANNEL_ACTIVITY_DECISIONS_SHOWN', 3);

/**
 * The agent states in the order the tab lists them. Pure.
 *
 * @return string[]
 */
function ws_channel_activity_states()
{
    return array('working', 'waiting', 'late', 'idle', 'paused');
}

/**
 * The words of one state's count in the summary line: "3 working". Pure
 * but for lang().
 *
 * @param string $state ws_channel_activity_states()
 * @param int    $count
 * @return string
 */
function ws_channel_activity_state_count($state, $count)
{
    switch ((string) $state) {
        case 'working':
            return lang(array('string' => '{var:1} working', 'vars' => (int) $count));
        case 'waiting':
            return lang(array('string' => '{var:1} waiting', 'vars' => (int) $count));
        case 'late':
            return lang(array('string' => '{var:1} not seen', 'vars' => (int) $count));
        case 'paused':
            return lang(array('string' => '{var:1} paused', 'vars' => (int) $count));
    }

    return lang(array('string' => '{var:1} ready', 'vars' => (int) $count));
}

/**
 * The summary of a channel's agents: how many are in each state, and the
 * words at the end of the agents block's head, "1 working · 1 waiting ·
 * 1 not seen": only the states that ask for a look (working, waiting, not
 * seen), a state with none left out; '' when there are none. Pure but for
 * lang().
 *
 * @param string[] $states ws_agent_card_state() of each agent
 * @return array working, waiting, late, idle, paused, words
 */
function ws_channel_activity_summary($states)
{
    $out = array_fill_keys(ws_channel_activity_states(), 0);

    foreach ((array) $states as $state) {
        if (isset($out[(string) $state])) {
            $out[(string) $state]++;
        }
    }

    $words = array();

    foreach (array('working', 'waiting', 'late') as $state) {
        if ($out[$state] > 0) {
            $words[] = ws_channel_activity_state_count($state, $out[$state]);
        }
    }

    $out['words'] = implode(' · ', $words);

    return $out;
}

/**
 * The agent rows in the tab's order: working, waiting, not seen, ready,
 * paused; by name within a state. Pure.
 *
 * @param array[] $rows each with state and name
 * @return array[]
 */
function ws_channel_activity_order($rows)
{
    $rank = array_flip(ws_channel_activity_states());
    $rows = array_values((array) $rows);

    usort($rows, function ($a, $b) use ($rank) {
        $ra = $rank[(string) ($a['state'] ?? '')] ?? count($rank);
        $rb = $rank[(string) ($b['state'] ?? '')] ?? count($rank);

        if ($ra !== $rb) {
            return $ra - $rb;
        }

        $by_name = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));

        return ($by_name !== 0) ? $by_name : ((int) ($a['id'] ?? 0) - (int) ($b['id'] ?? 0));
    });

    return $rows;
}

/**
 * How long something has waited, in short words: "under a minute",
 * "12 min", "2 h 5 min", "3 d". '' for no moment. Pure but for lang().
 *
 * @param int $since
 * @param int $now
 * @return string
 */
function ws_channel_activity_age($since, $now)
{
    if ((int) $since <= 0) {
        return '';
    }

    $age = max(0, (int) $now - (int) $since);

    if ($age < 60) {
        return lang('under a minute');
    }

    if ($age < 3600) {
        return lang(array('string' => '{var:1} min', 'vars' => (int) floor($age / 60)));
    }

    if ($age < 86400) {
        return lang(array('string' => '{var:1} h {var:2} min', 'vars' => array((int) floor($age / 3600), (int) floor(($age % 3600) / 60))));
    }

    return lang(array('string' => '{var:1} d', 'vars' => (int) floor($age / 86400)));
}

/**
 * The polls and approvals waiting for a person, oldest first, at most
 * WS_CHANNEL_ACTIVITY_WAITING_SHOWN, with each one's age; more counts the
 * rest. Pure but for lang().
 *
 * @param array[] $items kind, message_id, text, created_at
 * @param int     $now
 * @return array items (kind, message_id, text, age, created_at), more
 */
function ws_channel_activity_waiting_people($items, $now)
{
    $items = array_values((array) $items);

    usort($items, function ($a, $b) {
        $by_time = (int) $a['created_at'] - (int) $b['created_at'];

        return ($by_time !== 0) ? $by_time : ((int) $a['message_id'] - (int) $b['message_id']);
    });

    $out = array();

    foreach (array_slice($items, 0, WS_CHANNEL_ACTIVITY_WAITING_SHOWN) as $item) {
        $out[] = array(
            'kind'       => (string) $item['kind'],
            'message_id' => (int) $item['message_id'],
            'text'       => (string) $item['text'],
            'age'        => ws_channel_activity_age((int) $item['created_at'], $now),
            'created_at' => (int) $item['created_at'],
        );
    }

    return array('items' => $out, 'more' => max(0, count($items) - WS_CHANNEL_ACTIVITY_WAITING_SHOWN));
}

/**
 * The plan windows the channel's application agents reported, each once
 * (by name, percent and reset time) with the agents that reported it, in
 * the order first met. A model assistant reports none. Pure but for lang()
 * and date().
 *
 * @param array[] $agents ws_agent_shape() rows
 * @return array[] name, label, percent, resets_at, resets, high, words, agents (id, handle, name)
 */
function ws_channel_activity_windows($agents)
{
    $labels = ws_agent_report_window_labels();
    $out = array();

    foreach ((array) $agents as $agent) {
        if (((string) ($agent['kind'] ?? '') !== 'app') || ((int) ($agent['report_at'] ?? 0) <= 0)) {
            continue;
        }

        foreach (ws_agent_card_windows(ws_agent_report_windows($agent['report_usage'] ?? '')) as $window) {
            $key = $window['name'] . '|' . $window['percent'] . '|' . $window['resets_at'];

            if (!isset($out[$key])) {
                $out[$key] = $window + array(
                    'words'  => lang(array('string' => '{var:1} {var:2}%, resets {var:3}', 'vars' => array($labels[$window['name']] ?? $window['name'], $window['percent'], date('d.m H:i', $window['resets_at'])))),
                    'agents' => array(),
                );
            }

            $out[$key]['agents'][] = array('id' => (int) $agent['id'], 'handle' => (string) $agent['handle'], 'name' => (string) $agent['name']);
        }
    }

    return array_values($out);
}

/**
 * The channel's project: the newest project its agents reported, else the
 * linked repository's owner/name, else null. Pure.
 *
 * @param array[] $reports project (string|null), report_at
 * @param string  $repository owner/name of a link the reader may see, '' for none
 * @return array|null name, from (report | repository)
 */
function ws_channel_activity_project($reports, $repository)
{
    $best = null;

    foreach ((array) $reports as $report) {
        $name = (string) ($report['project'] ?? '');

        if (($name !== '') && (($best === null) || ((int) $report['report_at'] > (int) $best['report_at']))) {
            $best = array('name' => $name, 'report_at' => (int) $report['report_at']);
        }
    }

    if ($best !== null) {
        return array('name' => $best['name'], 'from' => 'report');
    }

    return ((string) $repository !== '') ? array('name' => (string) $repository, 'from' => 'repository') : null;
}

/**
 * The report's context, project and branch of one agent, null where the
 * report does not carry them.
 *
 * @param array $agent ws_agent_shape()
 * @return array context, project, branch
 */
function ws_channel_activity_agent_extra($agent)
{
    $reported = ((string) ($agent['kind'] ?? '') === 'app') && ((int) ($agent['report_at'] ?? 0) > 0);

    return array(
        'context' => ($reported && function_exists('ws_agent_report_context')) ? ws_agent_report_context($agent) : null,
        'project' => ($reported && function_exists('ws_agent_report_project')) ? ws_agent_report_project($agent) : null,
        'branch'  => ($reported && function_exists('ws_agent_report_branch')) ? ws_agent_report_branch($agent) : null,
    );
}

/**
 * The repository linked to a channel, owner/name, when the reader may see
 * the link: in a private channel only its members do. '' for none.
 *
 * @param array $viewer
 * @param array $channel
 * @return string
 */
function ws_channel_activity_link_name($viewer, $channel)
{
    if (!function_exists('ws_github_links_ready') || !ws_github_links_ready()) {
        return '';
    }

    if (((string) $channel['kind'] !== 'public') && !ws_channel_membership($channel['id'], $viewer['id'])) {
        return '';
    }

    return (string) db_value("SELECT repository FROM ws_channel_links
        WHERE channel_id = '" . (int) $channel['id'] . "' AND provider = 'github'
        ORDER BY id LIMIT 1");
}

/**
 * The request each working agent holds, its newest sent or running one:
 * agent value => ws_ai_requests row.
 *
 * @param string[] $values ws_agent_request_value() of the agents
 * @return array
 */
function ws_channel_activity_held_requests($values)
{
    if (empty($values)) {
        return array();
    }

    $quoted = array_map(function ($value) { return "'" . e($value) . "'"; }, $values);
    $out = array();

    foreach ((array) db_items("SELECT id, agent, channel_id, message_id, requested_by, status, claimed_at, ai_state FROM ws_ai_requests
        WHERE agent IN (" . implode(',', $quoted) . ") AND status IN ('sent', 'running')
        ORDER BY id DESC") as $row) {
        if (!isset($out[(string) $row['agent']])) {
            $out[(string) $row['agent']] = $row;
        }
    }

    return $out;
}

/**
 * What a working agent's row says of the request it holds: who asked and
 * the message (only for a request of this channel, so nothing of another
 * channel is told), "since HH:MM" and the progress note. Pure but for
 * lang() and date().
 *
 * @param array|null $row     ws_ai_requests
 * @param int        $channel_id
 * @param array      $people  ws_people() of the askers
 * @return array|null message_id, asked_by (user_id, name), since, note
 */
function ws_channel_activity_request_line($row, $channel_id, $people)
{
    if (!is_array($row)) {
        return null;
    }

    $here = ((int) $row['channel_id'] === (int) $channel_id);
    $asker = (int) ($row['requested_by'] ?? 0);
    $line = ws_agent_request_progress_line($row);

    return array(
        'message_id' => ($here && ((int) $row['message_id'] > 0)) ? (int) $row['message_id'] : null,
        'asked_by'   => ($here && ($asker > 0) && isset($people[$asker])) ? array('user_id' => $asker, 'name' => (string) $people[$asker]['name']) : null,
        'since'      => $line['since'],
        'note'       => $line['note'],
    );
}

/**
 * The polls (agent questions among them) and approvals of a channel still
 * waiting for a person: kind, message_id, text, created_at.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $now
 * @return array[]
 */
function ws_channel_activity_waiting_rows($viewer, $channel, $now)
{
    $channel_id = (int) $channel['id'];
    $floor = function_exists('ws_channel_floor') ? (int) ws_channel_floor($channel_id) : 0;
    $items = array();

    if (ws_interact_ready()) {
        $question_ready = ws_agent_question_ready();

        foreach ((array) db_items("SELECT p.*, m.body, m.created_at AS message_at FROM ws_polls p
            INNER JOIN ws_messages m ON m.id = p.message_id AND m.deleted_at = 0
            WHERE p.channel_id = '" . $channel_id . "' AND p.closed_at = 0
                AND (p.closes_at = 0 OR p.closes_at > '" . (int) $now . "')
                AND p.message_id > '" . $floor . "'"
            . (ws_poll_again_ready() ? ' AND p.withdrawn_at = 0' : '') . "
            ORDER BY p.id ASC LIMIT 100") as $row) {
            $items[] = array(
                'kind'       => ($question_ready && ws_agent_question_is($row)) ? 'question' : 'poll',
                'message_id' => (int) $row['message_id'],
                'text'       => ws_open_items_short(ws_plain_text($viewer, (string) $row['body'])),
                'created_at' => (int) $row['message_at'],
            );
        }
    }

    if (ws_approvals_ready()) {
        foreach ((array) db_items("SELECT a.message_id, a.title, m.created_at AS message_at FROM ws_approvals a
            INNER JOIN ws_messages m ON m.id = a.message_id AND m.deleted_at = 0
            WHERE a.channel_id = '" . $channel_id . "' AND a.closed_at = 0
                AND (a.closes_at = 0 OR a.closes_at > '" . (int) $now . "')
                AND a.message_id > '" . $floor . "'
            ORDER BY a.id ASC LIMIT 100") as $row) {
            $items[] = array(
                'kind'       => 'approval',
                'message_id' => (int) $row['message_id'],
                'text'       => ws_open_items_short((string) $row['title']),
                'created_at' => (int) $row['message_at'],
            );
        }
    }

    return $items;
}

/**
 * Today's decisions of a channel: how many, and the newest three.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $day_start
 * @return array count, items (message_id, text)
 */
function ws_channel_activity_decisions($viewer, $channel, $day_start)
{
    $where = "channel_id = '" . (int) $channel['id'] . "' AND kind = 'decision' AND deleted_at = 0 AND marked_at >= '" . (int) $day_start . "'";
    $items = array();

    foreach ((array) db_items("SELECT id, body FROM ws_messages WHERE " . $where . "
        ORDER BY marked_at DESC, id DESC LIMIT " . WS_CHANNEL_ACTIVITY_DECISIONS_SHOWN) as $row) {
        $items[] = array('message_id' => (int) $row['id'], 'text' => ws_open_items_short(ws_plain_text($viewer, (string) $row['body'])));
    }

    return array('count' => (int) db_value("SELECT COUNT(*) FROM ws_messages WHERE " . $where), 'items' => $items);
}

/**
 * The requests of the channel's agents: waiting (with the oldest's age and
 * message when it is in this channel), in work, answered and failed today.
 *
 * @param array $counts   ws_agent_request_counts()
 * @param array $values   the agents' request values
 * @param int   $channel_id
 * @param int   $now
 * @return array waiting, oldest_age, oldest_message_id, working, answered, failed
 */
function ws_channel_activity_requests($counts, $values, $channel_id, $now)
{
    $out = array('waiting' => 0, 'oldest_age' => '', 'oldest_message_id' => null, 'working' => 0, 'answered' => 0, 'failed' => 0);

    foreach ((array) $counts as $count) {
        $out['waiting'] += (int) ($count['queued'] ?? 0);
        $out['working'] += (int) ($count['working'] ?? 0);
        $out['answered'] += (int) ($count['answered'] ?? 0);
        $out['failed'] += (int) ($count['failed'] ?? 0);
    }

    if (($out['waiting'] > 0) && !empty($values)) {
        $quoted = array_map(function ($value) { return "'" . e($value) . "'"; }, $values);
        $oldest = db_item("SELECT channel_id, message_id, created_at FROM ws_ai_requests
            WHERE status = 'queued' AND agent IN (" . implode(',', $quoted) . ")
            ORDER BY created_at ASC, id ASC LIMIT 1");

        if (is_array($oldest)) {
            $out['oldest_age'] = ws_channel_activity_age((int) $oldest['created_at'], $now);
            $out['oldest_message_id'] = (((int) $oldest['channel_id'] === (int) $channel_id) && ((int) $oldest['message_id'] > 0)) ? (int) $oldest['message_id'] : null;
        }
    }

    return $out;
}

/**
 * One agent of the read, as the agents block of Channel info draws it: its
 * card's state, the request it holds, its report's model and age, and from
 * its member row how it wakes, its last seen, its queue and whether it is
 * active. Pure but for lang().
 *
 * @param array      $agent   ws_agent_shape()
 * @param array      $member  ws_channel_member_agent() with its brief
 * @param array|null $request ws_channel_activity_request_line(), null while it holds none
 * @param int        $now
 * @return array
 */
function ws_channel_activity_agent_row($agent, $member, $request, $now)
{
    $now = (int) $now;
    $report_at = (int) ($agent['report_at'] ?? 0);
    $reported = ((string) ($agent['kind'] ?? '') === 'app') && ($report_at > 0);

    return array(
        'id'           => (int) ($agent['id'] ?? 0),
        'handle'       => (string) ($agent['handle'] ?? ''),
        'name'         => (string) ($agent['name'] ?? ''),
        'kind'         => (string) ($agent['kind'] ?? ''),
        'avatar'       => ws_agent_card_avatar($agent['avatar'] ?? ''),
        'role'         => (string) ($member['role'] ?? ''),
        'role_key'     => (string) ($member['role_key'] ?? ''),
        'state'        => (string) ($member['state'] ?? 'idle'),
        'state_words'  => (string) ($member['state_words'] ?? ''),
        'usage_high'   => !empty($member['usage_high']),
        'active'       => !empty($member['active']),
        'waiting'      => (int) ($member['waiting'] ?? 0),
        'wake_fact'    => (string) ($member['wake_fact'] ?? ''),
        'seen'         => (string) ($member['seen'] ?? ''),
        'seen_state'   => (string) ($member['seen_state'] ?? ''),
        'seen_rest'    => (string) ($member['seen_rest'] ?? ''),
        'request'      => is_array($request) ? $request : null,
        'model'        => $reported ? (string) ($agent['report_model'] ?? '') : '',
        'reported_at'  => $reported ? $report_at : null,
        'reported'     => $reported ? lang(array('string' => 'reported {var:1}', 'vars' => ws_seen_ago_words($report_at, $now))) : '',
        'reported_ago' => $reported ? ws_seen_ago_words($report_at, $now) : '',
        'stale'        => $reported && (($now - $report_at) >= WS_AGENT_REPORT_STALE_AFTER),
    );
}

/**
 * The Activity tab of a channel the reader reads, in one answer. The
 * caller has checked the reader (ws_action_channel(),
 * ws_api_channel_or_404()); a guest gets nothing.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $now
 * @return array channel_id, summary, project, agents, requests, waiting,
 *               decisions, usage, usage_tip, repository
 */
function ws_channel_activity($viewer, $channel, $now)
{
    $now = (int) $now;
    $rows = ws_channel_member_agent_list($channel);
    $agents = array_map(function ($row) { return (array) $row['agent']; }, $rows);
    $values = array_values(array_filter(array_unique(array_map('ws_agent_request_value', $agents)), 'strlen'));
    $counts = $rows ? ws_agent_request_counts($agents, $now) : array();
    $held = ws_channel_activity_held_requests($values);
    $people = ws_people(array_map(function ($row) { return (int) $row['requested_by']; }, $held));
    $out_agents = array();
    $reports = array();

    foreach ($rows as $row) {
        $agent = (array) $row['agent'];
        $member = ws_channel_member_agent($row, $now, $counts);
        $value = ws_agent_request_value($agent);
        $extra = ws_channel_activity_agent_extra($agent);

        $reports[] = array('project' => $extra['project'], 'report_at' => (int) ($agent['report_at'] ?? 0));

        $request = ($member['state'] === 'working') ? ws_channel_activity_request_line($held[$value] ?? null, $channel['id'], $people) : null;
        $out_agents[] = ws_channel_activity_agent_row($agent, $member, $request, $now) + $extra;
    }

    $out_agents = ws_channel_activity_order($out_agents);

    return array(
        'channel_id' => (int) $channel['id'],
        'summary'    => ws_channel_activity_summary(array_map(function ($row) { return $row['state']; }, $out_agents)),
        'project'    => ws_channel_activity_project($reports, ws_channel_activity_link_name($viewer, $channel)),
        'agents'     => $out_agents,
        'requests'   => ws_channel_activity_requests($counts, $values, $channel['id'], $now),
        'waiting'    => ws_channel_activity_waiting_people(ws_channel_activity_waiting_rows($viewer, $channel, $now), $now),
        'decisions'  => ws_channel_activity_decisions($viewer, $channel, (int) strtotime('today', $now)),
        'usage'      => ws_channel_activity_windows($agents),
        'usage_tip'  => lang('As the agent reported it; the site cannot check it.'),
        'repository' => function_exists('ws_channel_activity_repo') ? ws_channel_activity_repo($viewer, $channel, $now) : null,
    );
}

/**
 * The words of the Activity tab (assets/js/workspace_channel_activity.js).
 *
 * @return array key => text
 */
function ws_channel_activity_js_strings()
{
    return array(
        'act_tab_info'        => lang('Info'),
        'act_tab_activity'    => lang('Activity'),
        'act_project'         => ws_js_template('Project: {var:1}', 1),
        'act_context'         => ws_js_template('{var:1} of {var:2} tokens', 2),
        // The agents block, the same under both tabs.
        'agents_title'        => lang('AI agents'),
        'agents_manage'       => lang('Manage agents'),
        'agents_no_report'    => lang('no report yet'),
        'agents_always'       => lang('always ready'),
        'agents_queue'        => ws_js_template('{var:1} requests in its queue', 1),
        'agents_queue_one'    => lang('1 request in its queue'),
        'agents_for'          => ws_js_template('for {var:1}, {var:2}', 2),
        'agents_mentioned'    => lang('When mentioned'),
        'agents_context'      => ws_js_template('{var:1} of {var:2} · {var:3}%', 3),
        'agents_ring'         => ws_js_template('Context {var:1} ({var:2}%)', 2),
        'agents_not_yet'      => lang('Not yet'),
        'agents_f_doing'      => lang('Doing'),
        'agents_f_asked'      => lang('Asked by'),
        'agents_f_queue'      => lang('Queue'),
        'agents_f_handle'     => lang('Handle'),
        'agents_f_wakes'      => lang('Wakes'),
        'agents_f_model'      => lang('Model'),
        'agents_f_context'    => lang('Context'),
        'agents_f_branch'     => lang('Git branch'),
        'agents_f_seen'       => lang('Seen'),
        'agents_f_reported'   => lang('Reported'),
        'act_requests'        => lang('Requests'),
        'act_req_waiting'     => lang('Waiting'),
        'act_oldest'          => ws_js_template('oldest {var:1}', 1),
        'act_req_working'     => lang('In work'),
        'act_req_answered'    => lang('Answered today'),
        'act_req_failed'      => lang('Failed today'),
        'act_waiting'         => lang('Waiting for a person'),
        'act_waiting_none'    => lang('Nothing is waiting for a person.'),
        'act_kind_poll'       => lang('Poll'),
        'act_kind_question'   => lang('Question'),
        'act_kind_approval'   => lang('Approval'),
        'act_more'            => ws_js_template('{var:1} more', 1),
        'act_decisions'       => lang('Decisions today'),
        'act_decisions_none'  => lang('No decision today.'),
        'act_all_decisions'   => lang('All decisions'),
        'act_repository'      => lang('Repository'),
        'act_pr_opened'       => lang('Pull requests opened'),
        'act_pr_merged'       => lang('Merged'),
        'act_pr_closed'       => lang('Closed'),
        'act_checks'          => lang('Checks'),
        'act_checks_value'    => ws_js_template('{var:1} red · {var:2} green', 2),
        'act_state_opened'    => lang('opened'),
        'act_state_reopened'  => lang('reopened'),
        'act_state_merged'    => lang('merged'),
        'act_state_closed'    => lang('closed'),
        'act_state_green'     => lang('green'),
        'act_state_red'       => lang('red'),
        'act_usage'           => lang('Plan usage'),
        'act_failed'          => lang('The activity could not be read.'),
    );
}
