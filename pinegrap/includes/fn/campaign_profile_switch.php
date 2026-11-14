<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * E-mail campaign profiles switched to the flows they become: per chain the
 * flow is saved and published as its owner, the head profile turned off by
 * one conditional update at second S, and the flow's event start box keeps
 * the profile and S. Around S an event starts the flow only where the
 * profile did not serve it (pg_profile_flow_switch_starts()). The followers
 * stay on while campaigns of a profile above them still wait, and are turned
 * off once none is left. A chain already switched is never switched again.
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
 * How far either side of the switch second an event is judged by the
 * profile's own campaigns, in seconds.
 */
if (!defined('PG_PROFILE_FLOW_SWITCH_WINDOW')) {
    define('PG_PROFILE_FLOW_SWITCH_WINDOW', 60);
}

/**
 * Whether an event starts the flow a profile became: one recorded more than
 * the window before the switch never does (the profile served its action),
 * one more than the window after it always does, one in between only when
 * the profile made no automatic campaign to the event's person from the
 * event's second less the window on. One request may run the profiles before
 * or after it records the event, so a boundary on either alone would lose or
 * double the mail of a request caught at the switch. Pure.
 *
 * @param int  $event_at    ws_events_in.created_at
 * @param int  $switched_at the second the head profile was turned off
 * @param bool $made        the profile made a campaign to the event's person
 * @return bool
 */
function pg_profile_flow_switch_starts($event_at, $switched_at, $made)
{
    $event_at = (int) $event_at;
    $switched_at = (int) $switched_at;

    if ($event_at < $switched_at - PG_PROFILE_FLOW_SWITCH_WINDOW) {
        return false;
    }

    if ($event_at > $switched_at + PG_PROFILE_FLOW_SWITCH_WINDOW) {
        return true;
    }

    return !$made;
}

/**
 * The e-mail address of the person an event is about, as the flow's e-mail
 * box reads it (ws_flow_mail_person_source()), lower case; '' for none.
 * Read without anybody's rights: it only compares the address with the
 * profile's campaigns.
 *
 * @param string $event
 * @param array  $payload
 * @return string
 */
function pg_profile_flow_event_email($event, $payload)
{
    if (!function_exists('ws_flow_mail_person_source')) {
        return '';
    }

    list($kind, $id) = ws_flow_mail_person_source('', 0, array('event' => (string) $event, 'event_payload' => (array) $payload));

    switch ($kind) {
        case 'order':
            $email = db_value("SELECT billing_email_address FROM orders WHERE id = '" . (int) $id . "'");
            break;

        case 'form':
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
            $email = get_submitter_email_address((int) $id);
            break;

        case 'booking':
            $email = db_value("SELECT email FROM bookings WHERE id = '" . (int) $id . "'");
            break;

        case 'contact':
            $email = db_value("SELECT email_address FROM contacts WHERE id = '" . (int) $id . "'");
            break;

        default:
            $email = '';
    }

    return strtolower(trim((string) $email));
}

/**
 * Whether an event starts the flow a profile became, from the event's row:
 * its second, and inside the window whether the profile mailed its person.
 *
 * @param array  $rule     the start rule: profile_id, switched_at
 * @param int    $event_id ws_events_in id
 * @param string $event
 * @param array  $payload
 * @return bool
 */
function pg_profile_flow_event_starts($rule, $event_id, $event, $payload)
{
    $switched_at = (int) ($rule['switched_at'] ?? 0);
    $profile_id = (int) ($rule['profile_id'] ?? 0);

    if (($switched_at <= 0) || ($profile_id <= 0)) {
        return true;
    }

    // An event with no row of its own is as new as now.
    $event_at = (int) db_value("SELECT created_at FROM ws_events_in WHERE id = '" . (int) $event_id . "'");
    $event_at = ($event_at > 0) ? $event_at : time();

    if (($event_at < $switched_at - PG_PROFILE_FLOW_SWITCH_WINDOW) || ($event_at > $switched_at + PG_PROFILE_FLOW_SWITCH_WINDOW)) {
        return pg_profile_flow_switch_starts($event_at, $switched_at, false);
    }

    $email = pg_profile_flow_event_email($event, $payload);
    $made = ($email !== '') && ((int) db_value("SELECT COUNT(*) FROM email_campaigns c
        JOIN email_recipients r ON r.email_campaign_id = c.id
        WHERE c.email_campaign_profile_id = '" . $profile_id . "' AND c.type = 'automatic'
        AND c.created_timestamp >= '" . ($event_at - PG_PROFILE_FLOW_SWITCH_WINDOW) . "'
        AND r.email_address = '" . e($email) . "'") > 0);

    return pg_profile_flow_switch_starts($event_at, $switched_at, $made);
}

/**
 * The profile id a flow's graph was switched from, 0 for a flow no profile
 * became: its event start box's profile_id. Pure.
 *
 * @param array $graph
 * @return int
 */
function pg_profile_flow_graph_profile($graph)
{
    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        if ((($node['kind'] ?? '') === 'event') && ((int) ($node['config']['profile_id'] ?? 0) > 0)) {
            return (int) $node['config']['profile_id'];
        }
    }

    return 0;
}

/**
 * The flows profiles became, head profile id => flow id, read from the
 * drafts of flows not deleted: a chain listed here is never switched again.
 *
 * @return int[]
 */
function pg_profile_flow_switched()
{
    $switched = array();

    foreach ((array) db_items("SELECT id, draft FROM ws_flows WHERE status <> 'binned' AND draft LIKE '%\"profile_id\"%'") as $row) {
        $profile_id = pg_profile_flow_graph_profile((array) json_decode((string) $row['draft'], true));

        if ($profile_id > 0) {
            $switched[$profile_id] = (int) $row['id'];
        }
    }

    return $switched;
}

/**
 * A converted graph with its start box keeping the profile and the switch
 * second. Pure.
 *
 * @param array $graph       pg_profile_flow_graph()
 * @param int   $profile_id  the head profile
 * @param int   $switched_at
 * @return array
 */
function pg_profile_flow_graph_switched($graph, $profile_id, $switched_at)
{
    foreach ($graph['nodes'] as $key => $node) {
        if ($node['kind'] === 'event') {
            $graph['nodes'][$key]['config']['profile_id'] = (int) $profile_id;
            $graph['nodes'][$key]['config']['switched_at'] = (int) $switched_at;
        }
    }

    return $graph;
}

/**
 * Switches one chain: the flow saved and published as its owner, the head
 * turned off at second S by an update that only an enabled head passes, the
 * flow paused when the head was already off (a disabled profile becomes a
 * paused flow) or was turned off meanwhile. Nothing is written when the
 * owner may not build or publish it: the chain then stays, with the reason.
 *
 * @param int   $head the head profile id
 * @param array $flow a flow of pg_profile_flow_convert()
 * @return array ok, flow_id, paused, reason ('' when switched)
 */
function pg_profile_flow_switch_chain($head, $flow)
{
    $owner = ws_change_viewer_for_id((int) $flow['owner']['id']);

    if (!is_array($owner)) {
        return array('ok' => false, 'flow_id' => 0, 'paused' => false, 'reason' => lang('The flow has no owner who could publish it.'));
    }

    $switched_at = time();
    $graph = pg_profile_flow_graph_switched($flow['graph'], (int) $head, $switched_at);
    $saved = ws_flow_save($owner, array('name' => (string) $flow['name'], 'graph' => $graph));

    if (empty($saved['ok'])) {
        return array('ok' => false, 'flow_id' => 0, 'paused' => false, 'reason' => (string) $saved['error']);
    }

    $flow_id = (int) $saved['flow_id'];
    $row = db_item("SELECT * FROM ws_flows WHERE id = '" . $flow_id . "'");
    $published = is_array($row) ? ws_flow_publish($owner, $row) : array('ok' => false, 'error' => lang('The flow could not be published.'));

    if (empty($published['ok'])) {
        // The draft stays, carrying the profile, so the chain is not
        // switched twice; the profile keeps sending until it is published.
        return array('ok' => false, 'flow_id' => $flow_id, 'paused' => false, 'reason' => (string) $published['error']);
    }

    db("UPDATE email_campaign_profiles SET enabled = 0 WHERE id = '" . (int) $head . "' AND enabled = 1");
    $turned_off = ((int) mysqli_affected_rows(db::$con) === 1);

    if (!$turned_off) {
        $row = db_item("SELECT * FROM ws_flows WHERE id = '" . $flow_id . "'");

        if (is_array($row)) {
            ws_flow_pause($owner, $row);
        }
    }

    return array('ok' => true, 'flow_id' => $flow_id, 'paused' => !$turned_off, 'reason' => '');
}

/**
 * The followers to turn off now, among the profiles of switched chains: an
 * enabled follower none of whose profiles above it still has a ready or
 * paused campaign. Pure.
 *
 * @param array $profiles id => row (action, action_item_id, enabled)
 * @param int[] $heads    the switched head profile ids
 * @param int[] $waiting  profile id => its ready or paused campaigns
 * @return int[]
 */
function pg_profile_flow_followers_done($profiles, $heads, $waiting)
{
    $done = array();

    foreach ($profiles as $id => $profile) {
        if (((string) $profile['action'] !== 'email_campaign_sent') || ((int) $profile['enabled'] !== 1)) {
            continue;
        }

        $above = array();
        $leader = (int) $profile['action_item_id'];

        // Up the chain to its head; a ring or a gone leader ends the walk.
        while (isset($profiles[$leader]) && !isset($above[$leader])) {
            $above[$leader] = true;

            if ((string) $profiles[$leader]['action'] !== 'email_campaign_sent') {
                break;
            }

            $leader = (int) $profiles[$leader]['action_item_id'];
        }

        if (!in_array($leader, array_map('intval', $heads), true)) {
            continue;
        }

        $still = false;

        foreach (array_keys($above) as $up) {
            $still = $still || ((int) ($waiting[$up] ?? 0) > 0);
        }

        if (!$still) {
            $done[] = (int) $id;
        }
    }

    return $done;
}

/**
 * Turns off the followers of switched chains whose campaigns above them have
 * all gone out (pg_profile_flow_followers_done()); a pass of the general job.
 *
 * @return int how many were turned off
 */
function pg_profile_flow_followers_drain()
{
    if (!function_exists('ws_flows_ready') || !ws_flows_ready()) {
        return 0;
    }

    $heads = array_keys(pg_profile_flow_switched());

    if (empty($heads)) {
        return 0;
    }

    $profiles = array();

    foreach ((array) db_items("SELECT id, action, action_item_id, enabled FROM email_campaign_profiles") as $row) {
        $profiles[(int) $row['id']] = $row;
    }

    $waiting = array();

    foreach ((array) db_items("SELECT email_campaign_profile_id AS id, COUNT(*) AS n FROM email_campaigns
        WHERE status IN ('ready', 'paused') AND email_campaign_profile_id > 0 GROUP BY email_campaign_profile_id") as $row) {
        $waiting[(int) $row['id']] = (int) $row['n'];
    }

    $turned = 0;

    foreach (pg_profile_flow_followers_done($profiles, $heads, $waiting) as $id) {
        db("UPDATE email_campaign_profiles SET enabled = 0 WHERE id = '" . (int) $id . "' AND enabled = 1");
        $turned += (int) mysqli_affected_rows(db::$con);
    }

    return $turned;
}

/**
 * Switches every chain that converts and was not switched before, from the
 * rows as they are now, and gives the report: the dry run's lines with each
 * flow made, each chain whose flow could not be published and why.
 *
 * @param int $now
 * @return array switched (head => flow id), failed (head => reason), lines
 */
function pg_profile_flow_switch($now)
{
    require_once PG_FUNCTIONS_DIR . '/includes/fn/campaign_profile_flow.php';

    $run = pg_profile_flow_dry_run($now, pg_profile_flow_switched());
    $switched = array();
    $failed = array();

    foreach ($run['plan']['flows'] as $head => $flow) {
        $result = pg_profile_flow_switch_chain((int) $head, $flow);

        if ($result['ok']) {
            $switched[$head] = (int) $result['flow_id'];
        } else {
            $failed[$head] = (string) $result['reason'];
        }
    }

    $lines = $run['lines'];

    foreach ($failed as $head => $reason) {
        $lines[] = '';
        $lines[] = lang(array('string' => '“{var:1}” could not become a flow and keeps running as a profile: {var:2}', 'vars' => array((string) $run['plan']['flows'][$head]['name'], $reason)));
    }

    return array('switched' => $switched, 'failed' => $failed, 'lines' => $lines);
}
