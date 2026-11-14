<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * E-mail campaign profiles turned into flows: the converter, as pure
 * functions, and the dry-run report worked out from the rows with nothing
 * written. A chain (a profile started by an action and the profiles that
 * follow its e-mail, as a tree) becomes one flow: the event start box, a
 * Condition for a calendar event, a Wait for the profile's delay and send
 * time, the Send an e-mail box; each follower hangs after its leader's e-mail
 * as a Condition on the e-mail sent, a Wait and its own e-mail. A chain any
 * profile of which no box can carry stays whole as profiles, with the
 * reason. The update step that writes the flows is a step of its own.
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
 * The event a profile's action becomes, '' for an action no start box
 * covers (it keeps running as a profile) or for a follower
 * (email_campaign_sent). Pure.
 *
 * @param string $action email_campaign_profiles.action
 * @return string
 */
function pg_profile_flow_event($action)
{
    $events = array(
        'custom_form_submitted'   => 'form.submitted',
        'order_completed'         => 'order.created',
        'order_shipped'           => 'order.shipped',
        'order_delivered'         => 'order.delivered',
        'contact_group_joined'    => 'contact_group.joined',
        'calendar_event_reserved' => 'calendar_event.reserved',
        'subscription_renewed'    => 'subscription.renewed',
        'subscription_failed'     => 'subscription.failed',
        'subscription_cancelled'  => 'subscription.cancelled',
        'booking_confirmed'       => 'booking.confirmed',
        'booking_rescheduled'     => 'booking.rescheduled',
        'booking_cancelled'       => 'booking.cancelled',
        'credit_issued'           => 'credit.issued',
    );

    return $events[(string) $action] ?? '';
}

/**
 * The start box a head profile becomes and, for a calendar event, the
 * Condition that narrows it to that event. Pure.
 *
 * @param array $profile email_campaign_profiles row
 * @return array start (event box config, null without a start), condition
 *               (config or null)
 */
function pg_profile_flow_start($profile)
{
    $event = pg_profile_flow_event($profile['action'] ?? '');
    $item = (int) ($profile['action_item_id'] ?? 0);

    if ($event === '') {
        return array('start' => null, 'condition' => null);
    }

    $start = array('event' => $event, 'page_id' => 0, 'status' => '', 'channel_id' => 0);
    $condition = null;

    if ($event === 'form.submitted') {
        $start['page_id'] = $item;
    } elseif ($event === 'contact_group.joined') {
        $start['status'] = (string) $item;
    } elseif ($event === 'calendar_event.reserved') {
        $condition = array('rule' => array('type' => 'value', 'ref' => '{{trigger.record_id}}', 'op' => 'eq', 'value' => (string) $item));
    }

    return array('start' => $start, 'condition' => $condition);
}

/**
 * The Wait box a profile's delay and send time become: none for "at once,
 * any time", a wait of its length moved on to its send time, or for a
 * calendar event a wait until the event's start less the length; else why no
 * Wait box can carry it. Pure.
 *
 * @param array $profile   schedule_length, schedule_unit, schedule_time,
 *                         schedule_period, schedule_base
 * @param int   $max       the longest wait in minutes (WS_FLOW_DELAY_MAX)
 * @return array ok, config (null for no Wait box), minutes (how long it
 *               holds a run, 0 for until a date), reason
 */
function pg_profile_flow_wait($profile, $max)
{
    $length = max(0, (int) ($profile['schedule_length'] ?? 0));
    $unit = (($profile['schedule_unit'] ?? '') === 'hours') ? 'hours' : 'days';
    $minutes = $length * (($unit === 'hours') ? 60 : 1440);
    $clock = substr((string) ($profile['schedule_time'] ?? ''), 0, 5);
    $time = (preg_match('/^\d\d:\d\d$/', $clock) && ($clock !== '00:00')) ? $clock : '';
    $before = (($profile['schedule_period'] ?? '') === 'before');
    $refuse = function ($reason) {
        return array('ok' => false, 'config' => null, 'minutes' => 0, 'reason' => $reason);
    };

    if (($profile['schedule_base'] ?? '') === 'calendar_event_start_time') {
        if (!$before && ($length > 0)) {
            return $refuse(lang('It waits a time after the calendar event\'s start, which no Wait box counts from.'));
        }

        // The Wait box moves a moment on to its time of day, never back: a
        // send time before the start would land later than the profile's.
        if ($before && ($length > 0) && ($time !== '')) {
            return $refuse(lang('It sends at a time of day before the calendar event\'s start, which a Wait box would move to later.'));
        }

        return array('ok' => true, 'minutes' => 0, 'reason' => '', 'config' => array('mode' => 'until', 'at' => '{{trigger.start_at}}',
            'amount' => $length, 'unit' => $unit, 'time' => $time, 'passed' => 'end'));
    }

    if ($before && ($length > 0)) {
        return $refuse(lang('It sends before its action, which no Wait box can do.'));
    }

    if ($minutes > (int) $max) {
        return $refuse(lang('It waits longer than a flow\'s Wait box may.'));
    }

    if (($length === 0) && ($time === '')) {
        return array('ok' => true, 'config' => null, 'minutes' => 0, 'reason' => '');
    }

    return array('ok' => true, 'minutes' => $minutes + (($time !== '') ? 1440 : 0), 'reason' => '',
        'config' => array('mode' => 'for', 'at' => '', 'amount' => $length, 'unit' => $unit, 'time' => $time, 'passed' => 'go'));
}

/**
 * The Send an e-mail box a profile becomes: to the person of the run's
 * record, its purpose, its subject and plain text or its page, its sender.
 * Pure.
 *
 * @param array $profile
 * @return array the box's config
 */
function pg_profile_flow_email($profile)
{
    $html = (($profile['format'] ?? '') === 'html');

    return array(
        'to'         => '',
        'subject'    => (string) ($profile['subject'] ?? ''),
        'body'       => $html ? '' : (string) ($profile['body'] ?? ''),
        'page_id'    => $html ? (int) ($profile['page_id'] ?? 0) : 0,
        'recipient'  => 'record_person',
        'purpose'    => (($profile['purpose'] ?? '') === 'transactional') ? 'info' : 'marketing',
        'from_name'  => (string) ($profile['from_name'] ?? ''),
        'from_email' => (string) ($profile['from_email_address'] ?? ''),
        'reply_to'   => (string) ($profile['reply_email_address'] ?? ''),
        'bcc'        => (string) ($profile['bcc_email_address'] ?? ''),
    );
}

/**
 * The ^^fields^^ a profile's texts use, each once, in their order. Pure.
 *
 * @param string[] $texts subject, plain text, the page's regions
 * @return string[]
 */
function pg_profile_flow_fields_used($texts)
{
    $names = array();

    foreach ((array) $texts as $text) {
        if (preg_match_all('/\^\^([a-z0-9_]+)\^\^/i', (string) $text, $matches)) {
            foreach ($matches[1] as $name) {
                $names[$name] = $name;
            }
        }
    }

    return array_values($names);
}

/**
 * The ^^fields^^ a flow would leave unfilled: those its start's values and
 * the recipient's contact do not give (workspace-flows-email-campaign). Pure.
 *
 * @param string[] $used   pg_profile_flow_fields_used()
 * @param string[] $starts the start box's output keys
 * @return string[]
 */
function pg_profile_flow_fields_missing($used, $starts)
{
    $filled = array_merge((array) $starts, array('name', 'first_name', 'last_name', 'record_id', 'event'));

    return array_values(array_diff((array) $used, $filled));
}

/**
 * The profiles in chains: each head (a profile started by an action) with
 * the profiles below it, each after its leader; and the followers whose
 * leader is gone (or that follow each other in a ring), which send nothing
 * today. Pure.
 *
 * @param array $profiles id => row (id, action, action_item_id)
 * @return array chains (head id => list of array(id, leader id, 0 for the
 *               head)), orphans (ids)
 */
function pg_profile_flow_chains($profiles)
{
    $below = array();

    foreach ($profiles as $id => $profile) {
        if (($profile['action'] ?? '') === 'email_campaign_sent') {
            $below[(int) $profile['action_item_id']][] = (int) $id;
        }
    }

    $chains = array();
    $placed = array();

    foreach ($profiles as $id => $profile) {
        if (($profile['action'] ?? '') === 'email_campaign_sent') {
            continue;
        }

        $chain = array(array((int) $id, 0));
        $placed[(int) $id] = true;

        for ($i = 0; $i < count($chain); $i++) {
            foreach ($below[$chain[$i][0]] ?? array() as $follower) {
                if (empty($placed[$follower])) {
                    $placed[$follower] = true;
                    $chain[] = array($follower, $chain[$i][0]);
                }
            }
        }

        $chains[(int) $id] = $chain;
    }

    $orphans = array();

    foreach ($profiles as $id => $profile) {
        if (empty($placed[(int) $id])) {
            $orphans[] = (int) $id;
        }
    }

    return array('chains' => $chains, 'orphans' => $orphans);
}

/**
 * Who owns the flow a chain becomes: the head's creator while on the staff
 * and holding the start's right, else the first active administrator. Pure.
 *
 * @param array|null $creator  id, name, staff (bool), right (bool); null
 *                             when the account is gone
 * @param array      $admin    id, name of the first active administrator
 * @return array id, name, changed (bool), reason ('' when the creator keeps it)
 */
function pg_profile_flow_owner($creator, $admin)
{
    if (is_array($creator) && !empty($creator['staff']) && !empty($creator['right'])) {
        return array('id' => (int) $creator['id'], 'name' => (string) $creator['name'], 'changed' => false, 'reason' => '');
    }

    if (!is_array($creator)) {
        $reason = lang('the profile\'s creator no longer has an account');
    } elseif (empty($creator['staff'])) {
        $reason = lang(array('string' => '{var:1} is no longer on the staff', 'vars' => (string) $creator['name']));
    } else {
        $reason = lang(array('string' => '{var:1} lacks the right the start needs', 'vars' => (string) $creator['name']));
    }

    return array('id' => (int) ($admin['id'] ?? 0), 'name' => (string) ($admin['name'] ?? ''), 'changed' => true, 'reason' => $reason);
}

/**
 * Why a profile's last 30 days would pass 80% of a flow's limits, '' when
 * they would not: the starts of its busiest hour against the starts an hour
 * a flow and its start box take, the runs its waits would hold open (its
 * daily starts × the days they wait) against the open runs a flow keeps.
 * Pure.
 *
 * @param array $stats   made (campaigns in 30 days), busiest (in one hour)
 * @param int   $minutes how long its chain holds a run, waits added up
 * @param array $limits  starts_hourly, open_runs
 * @return string
 */
function pg_profile_flow_limit_reason($stats, $minutes, $limits)
{
    if ((int) ($stats['busiest'] ?? 0) > 0.8 * (int) $limits['starts_hourly']) {
        return lang(array('string' => 'Its busiest hour made {var:1} campaigns; a flow starts at most {var:2} runs an hour.', 'vars' => array((int) $stats['busiest'], (int) $limits['starts_hourly'])));
    }

    $open = pg_profile_flow_open_runs((int) ($stats['made'] ?? 0), $minutes);

    if ($open > 0.8 * (int) $limits['open_runs']) {
        return lang(array('string' => 'It would hold about {var:1} runs open; a flow keeps at most {var:2}.', 'vars' => array($open, (int) $limits['open_runs'])));
    }

    return '';
}

/**
 * About how many runs a chain holds open: its starts of 30 days spread over
 * them, times how long a run waits. Pure.
 *
 * @param int $made    campaigns in 30 days
 * @param int $minutes the chain's waits added up
 * @return int
 */
function pg_profile_flow_open_runs($made, $minutes)
{
    return (int) ceil(max(0, (int) $made) * max(0, (int) $minutes) / 43200);
}

/**
 * The flow's graph of a chain: the start, a calendar event's Condition, then
 * per profile its Wait and e-mail; a follower after its leader's e-mail as a
 * Condition on that e-mail's sent count. Box ids name the profile. Pure.
 *
 * @param array $chain    pg_profile_flow_chains() chain
 * @param array $profiles id => row
 * @param array $boxes    id => array(wait => config|null, email => config);
 *                        the head's start and condition under 'start'
 * @return array v, nodes, edges, notes
 */
function pg_profile_flow_graph($chain, $profiles, $boxes)
{
    $nodes = array();
    $edges = array();
    $column = array();
    $row = 0;
    $node = function ($id, $kind, $label, $config, $x, $y) use (&$nodes) {
        $nodes[] = array('id' => $id, 'kind' => $kind, 'label' => $label, 'x' => $x * 280, 'y' => $y * 160, 'config' => $config);
    };

    $node('start', 'event', '', $boxes['start']['start'], 0, 0);
    $from = array('start', 'next');

    if ($boxes['start']['condition'] !== null) {
        $node('calendar', 'condition', lang('The calendar event'), $boxes['start']['condition'], 1, 0);
        $edges[] = array('from' => 'start', 'to' => 'calendar', 'on' => 'next');
        $from = array('calendar', 'yes');
    }

    foreach ($chain as $place) {
        list($id, $leader) = $place;
        $name = (string) ($profiles[$id]['name'] ?? '');
        $x = ($leader > 0) ? $column[$leader] + 1 : count($nodes);
        $y = ($leader > 0) ? ++$row : 0;
        $at = ($leader > 0) ? array('mail_' . $leader, 'done') : $from;

        if ($leader > 0) {
            $node('sent_' . $id, 'condition', lang('The e-mail before was sent'), array('rule' => array('type' => 'value', 'ref' => '{{node.mail_' . $leader . '.sent}}', 'op' => 'gte', 'value' => '1')), $x++, $y);
            $edges[] = array('from' => $at[0], 'to' => 'sent_' . $id, 'on' => $at[1]);
            $at = array('sent_' . $id, 'yes');
        }

        if ($boxes[$id]['wait'] !== null) {
            $node('wait_' . $id, 'delay', '', $boxes[$id]['wait'], $x++, $y);
            $edges[] = array('from' => $at[0], 'to' => 'wait_' . $id, 'on' => $at[1]);
            $at = array('wait_' . $id, 'done');
        }

        $node('mail_' . $id, 'email', $name, $boxes[$id]['email'], $x, $y);
        $edges[] = array('from' => $at[0], 'to' => 'mail_' . $id, 'on' => $at[1]);
        $column[$id] = $x;
    }

    return array('v' => 1, 'nodes' => $nodes, 'edges' => $edges, 'notes' => array());
}

/**
 * The whole conversion worked out from what the rows say: per chain the flow
 * it becomes, or why it stays whole as profiles. Pure.
 *
 * @param array $profiles id => email_campaign_profiles row
 * @param array $facts    per profile id: texts (subject, plain text and
 *                        the page's regions), stats (made, busiest, waiting);
 *                        outputs (event => start output keys), owners
 *                        (head id => pg_profile_flow_owner()), limits
 *                        (delay_max, max_nodes, starts_hourly, open_runs)
 * @return array flows (head id => graph, name, paused, owner, members),
 *               left (id => reason), orphans (ids)
 */
function pg_profile_flow_convert($profiles, $facts)
{
    $split = pg_profile_flow_chains($profiles);
    $limits = $facts['limits'];
    $flows = array();
    $left = array();

    foreach ($split['orphans'] as $id) {
        $left[$id] = lang('It follows a profile that is gone, so it sends nothing today.');
    }

    foreach ($split['chains'] as $head => $chain) {
        $start = pg_profile_flow_start($profiles[$head]);
        $event = is_array($start['start']) ? $start['start']['event'] : '';
        $boxes = array('start' => $start);
        $reasons = array();
        $held = array();

        if ($event === '') {
            $reasons[$head] = lang('No start box covers its action yet.');
        }

        foreach ($chain as $place) {
            $id = $place[0];
            $wait = pg_profile_flow_wait($profiles[$id], $limits['delay_max']);
            $held[$id] = (($place[1] > 0) ? $held[$place[1]] : 0) + $wait['minutes'];
            $missing = pg_profile_flow_fields_missing(pg_profile_flow_fields_used($facts['texts'][$id] ?? array()), $facts['outputs'][$event] ?? array());
            $boxes[$id] = array('wait' => $wait['config'], 'email' => pg_profile_flow_email($profiles[$id]));

            if (isset($reasons[$id])) {
                continue;
            }

            if (!$wait['ok']) {
                $reasons[$id] = $wait['reason'];
            } elseif (!empty($missing) && ($event !== '')) {
                $reasons[$id] = lang(array('string' => 'No value of its start fills {var:1}.', 'vars' => '^^' . implode('^^, ^^', $missing) . '^^'));
            } elseif ((($profiles[$id]['format'] ?? '') === 'html') && ((int) ($profiles[$id]['page_id'] ?? 0) <= 0)) {
                $reasons[$id] = lang('Its page is gone.');
            } else {
                $limit = pg_profile_flow_limit_reason($facts['stats'][$head] ?? array(), $held[$id], $limits);

                if ($limit !== '') {
                    $reasons[$id] = $limit;
                }
            }
        }

        $graph = empty($reasons) ? pg_profile_flow_graph($chain, $profiles, $boxes) : null;

        if (is_array($graph) && (count($graph['nodes']) > (int) $limits['max_nodes'])) {
            $reasons[$head] = lang('The flow would hold more boxes than a flow may.');
        }

        if (!empty($reasons)) {
            foreach ($chain as $place) {
                $first = array_keys($reasons)[0];
                $left[$place[0]] = $reasons[$place[0]] ?? lang(array('string' => 'A profile of its chain stays: {var:1}.', 'vars' => (string) $profiles[$first]['name']));
            }

            continue;
        }

        $flows[$head] = array(
            'name'    => (string) $profiles[$head]['name'],
            'paused'  => ((int) ($profiles[$head]['enabled'] ?? 0) !== 1),
            'owner'   => $facts['owners'][$head] ?? array('id' => 0, 'name' => '', 'changed' => true, 'reason' => ''),
            'members' => array_map(function ($place) {
                return $place[0];
            }, $chain),
            'graph'   => $graph,
        );
    }

    return array('flows' => $flows, 'left' => $left, 'orphans' => $split['orphans']);
}

/**
 * One line in words of a box of a converted flow. Pure.
 *
 * @param array $node a graph node
 * @return string
 */
function pg_profile_flow_box_line($node)
{
    $config = $node['config'];

    switch ($node['kind']) {
        case 'event':
            $filter = ((int) $config['page_id'] > 0) ? ' · ' . lang(array('string' => 'form #{var:1}', 'vars' => (int) $config['page_id']))
                : (($config['status'] !== '') ? ' · ' . lang(array('string' => 'contact group #{var:1}', 'vars' => $config['status'])) : '');

            return lang(array('string' => 'When something happens: {var:1}', 'vars' => $config['event'])) . $filter;

        case 'condition':
            return lang(array('string' => 'Condition: {var:1} {var:2} {var:3}', 'vars' => array($config['rule']['ref'], $config['rule']['op'], $config['rule']['value'])));

        case 'delay':
            $line = ($config['mode'] === 'until')
                ? lang(array('string' => 'Wait until {var:1} less {var:2} {var:3}; if it has passed, end the run', 'vars' => array($config['at'], $config['amount'], $config['unit'])))
                : lang(array('string' => 'Wait {var:1} {var:2}', 'vars' => array($config['amount'], $config['unit'])));

            return $line . (($config['time'] !== '') ? ' · ' . lang(array('string' => 'then at {var:1}', 'vars' => $config['time'])) : '');

        case 'email':
            $what = ((int) $config['page_id'] > 0) ? lang(array('string' => 'page #{var:1}', 'vars' => (int) $config['page_id'])) : lang('its text');
            $from = implode(', ', array_filter(array($config['from_name'], $config['from_email'], ($config['reply_to'] !== '') ? 'Reply-to ' . $config['reply_to'] : '', ($config['bcc'] !== '') ? 'BCC ' . $config['bcc'] : '')));

            return lang(array('string' => 'Send an e-mail “{var:1}” to the record\'s person, {var:2}, {var:3}', 'vars' => array($config['subject'], ($config['purpose'] === 'marketing') ? lang('marketing') : lang('information'), $what)))
                . (($from !== '') ? ' · ' . lang(array('string' => 'sender: {var:1}', 'vars' => $from)) : '');
    }

    return (string) $node['kind'];
}

/**
 * The dry-run report as lines of text: per flow its name and state, owner,
 * boxes and counts; per profile left its name, action, state and reason with
 * its counts. Pure.
 *
 * @param array $plan     pg_profile_flow_convert()
 * @param array $profiles id => row
 * @param array $stats    id => made, busiest, waiting, no_contact (marketing)
 * @return string[]
 */
function pg_profile_flow_report_lines($plan, $profiles, $stats)
{
    $counts = function ($id) use ($stats, $profiles) {
        $row = $stats[$id] ?? array();
        $line = '    ' . lang(array('string' => 'Last 30 days: {var:1} campaigns, at most {var:2} in an hour; {var:3} ready or paused stay with the campaign job', 'vars' => array((int) ($row['made'] ?? 0), (int) ($row['busiest'] ?? 0), (int) ($row['waiting'] ?? 0))));

        if ((($profiles[$id]['purpose'] ?? '') === 'commercial') && ((int) ($row['no_contact'] ?? 0) > 0)) {
            $line .= '; ' . lang(array('string' => '{var:1} recipients no contact holds would no longer be mailed', 'vars' => (int) $row['no_contact']));
        }

        return $line;
    };
    $state = function ($id) use ($profiles) {
        return ((int) ($profiles[$id]['enabled'] ?? 0) === 1) ? lang('on') : lang('off');
    };
    $lines = array(lang(array('string' => 'Flows: {var:1}; profiles left: {var:2}', 'vars' => array(count($plan['flows']), count($plan['left'])))));

    foreach ($plan['flows'] as $head => $flow) {
        $lines[] = '';
        $lines[] = lang(array('string' => '“{var:1}” ({var:2}) becomes a flow, {var:3}', 'vars' => array($flow['name'], $state($head), $flow['paused'] ? lang('paused') : lang('live'))));
        $lines[] = '  ' . lang(array('string' => 'Owner: {var:1}', 'vars' => $flow['owner']['name'])) . ($flow['owner']['changed'] ? ' (' . $flow['owner']['reason'] . ')' : '');

        foreach ($flow['graph']['nodes'] as $node) {
            $lines[] = '  - ' . pg_profile_flow_box_line($node);
        }

        foreach ($flow['members'] as $id) {
            $lines[] = '  ' . lang(array('string' => 'Profile “{var:1}”', 'vars' => (string) $profiles[$id]['name']));
            $lines[] = $counts($id);
        }
    }

    foreach ($plan['left'] as $id => $reason) {
        $lines[] = '';
        $lines[] = lang(array('string' => '“{var:1}” ({var:2}, {var:3}) stays a profile: {var:4}', 'vars' => array((string) $profiles[$id]['name'], (string) $profiles[$id]['action'], $state($id), $reason)));
        $lines[] = $counts($id);
    }

    return $lines;
}

/**
 * What the conversion needs to know besides the profiles, read from the
 * rows, nothing written: each profile's texts (a page's regions), its last
 * 30 days, the start values of each event, each head's owner. Needs the
 * Workspace's flow modules (includes/workspace/bootstrap.php).
 *
 * @param array $profiles id => email_campaign_profiles row
 * @param int   $now
 * @return array pg_profile_flow_convert() facts, with stats
 */
function pg_profile_flow_facts($profiles, $now)
{
    $since = (int) $now - 30 * 86400;
    $facts = array('texts' => array(), 'stats' => array(), 'outputs' => array(), 'owners' => array(), 'limits' => array(
        'delay_max'     => WS_FLOW_DELAY_MAX,
        'max_nodes'     => WS_FLOW_MAX_NODES,
        'starts_hourly' => min(WS_FLOW_STARTS_HOURLY, WS_SCHEDULED_CHAIN_HOURLY),
        'open_runs'     => WS_FLOW_MAX_OPEN_RUNS,
    ));
    $admin = db_item("SELECT user_id AS id, user_username AS name FROM user WHERE user_role = '0'" . pg_user_enabled_sql() . " ORDER BY user_id LIMIT 1");
    $catalog = ws_events_catalog();

    foreach ($profiles as $id => $profile) {
        $texts = array((string) $profile['subject'], ($profile['format'] === 'html') ? '' : (string) $profile['body']);

        if ($profile['format'] === 'html') {
            $texts = array_merge($texts, array_map('strval', (array) db_values("SELECT pregion_content FROM pregion WHERE pregion_page = '" . (int) $profile['page_id'] . "'")));
        }

        $facts['texts'][$id] = $texts;
        $made = "FROM email_campaigns WHERE email_campaign_profile_id = '" . (int) $id . "' AND type = 'automatic' AND created_timestamp >= '" . $since . "'";
        $facts['stats'][$id] = array(
            'made'       => (int) db_value("SELECT COUNT(*) " . $made),
            'busiest'    => (int) db_value("SELECT MAX(n) FROM (SELECT COUNT(*) AS n " . $made . " GROUP BY FLOOR(created_timestamp / 3600)) AS hours"),
            'waiting'    => (int) db_value("SELECT COUNT(*) FROM email_campaigns WHERE email_campaign_profile_id = '" . (int) $id . "' AND status IN ('ready', 'paused')"),
            'no_contact' => (int) db_value("SELECT COUNT(DISTINCT r.email_address) FROM email_recipients r
                JOIN email_campaigns c ON c.id = r.email_campaign_id
                LEFT JOIN contacts k ON k.email_address = r.email_address
                WHERE c.email_campaign_profile_id = '" . (int) $id . "' AND c.created_timestamp >= '" . $since . "' AND k.id IS NULL"),
        );

        $event = pg_profile_flow_event($profile['action']);

        if ($event === '') {
            continue;
        }

        if (!isset($facts['outputs'][$event])) {
            $facts['outputs'][$event] = array_map(function ($output) {
                return (string) $output['key'];
            }, ws_flow_trigger_outputs('event', array('event' => $event)));
        }

        $user = db_item("SELECT user_id AS id, user_username AS name, user_role AS role FROM user WHERE user_id = '" . (int) $profile['created_user_id'] . "'" . pg_user_enabled_sql());
        $right = (string) ($catalog[$event]['right'] ?? '');
        $creator = is_array($user) ? array('id' => (int) $user['id'], 'name' => (string) $user['name'], 'staff' => ((int) $user['role'] <= 2),
            'right' => ($right !== '') && !empty(ws_rights_for_id((int) $user['id'])[$right])) : null;
        $facts['owners'][$id] = pg_profile_flow_owner($creator, is_array($admin) ? $admin : array());
    }

    return $facts;
}

/**
 * The dry-run report of turning this site's e-mail campaign profiles into
 * flows, from the rows as they are now; nothing is written.
 *
 * @param int $now
 * @return array plan (pg_profile_flow_convert()), lines
 */
function pg_profile_flow_dry_run($now)
{
    $profiles = array();

    foreach ((array) db_items("SELECT * FROM email_campaign_profiles ORDER BY id") as $row) {
        // An HTML profile whose page is gone sends nothing: its page_id 0
        // says so to the converter.
        if (($row['format'] === 'html') && !db_value("SELECT page_id FROM page WHERE page_id = '" . (int) $row['page_id'] . "'")) {
            $row['page_id'] = 0;
        }

        $profiles[(int) $row['id']] = $row;
    }

    $facts = pg_profile_flow_facts($profiles, $now);
    $plan = pg_profile_flow_convert($profiles, $facts);

    return array('plan' => $plan, 'lines' => pg_profile_flow_report_lines($plan, $profiles, $facts['stats']));
}
