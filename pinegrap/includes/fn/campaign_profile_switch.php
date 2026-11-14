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
 * The update step only marks the conversion due, in a file of data/ named
 * after the database (pg_profile_flow_switch_file()), with the highest
 * profile id of that moment; the general job converts chain by chain within
 * its time budget and keeps the result there for the E-mail Campaign
 * Profiles screen. What is converted stays where the switch writes it (the
 * flow's start box, the head turned off): a lost file converts nothing
 * more, and the profiles keep sending.
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
 * drafts of every flow, deleted ones included: a chain listed here is never
 * switched again, even after its flow was deleted.
 *
 * @return int[]
 */
function pg_profile_flow_switched()
{
    $switched = array();
    $rows = db_items("SELECT id, draft FROM ws_flows WHERE draft LIKE '%\"profile_id\"%'");

    foreach ((is_array($rows) ? $rows : array()) as $row) {
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
        // A draft carrying the profile would count as switched while the
        // profile still sends; the never-published draft goes, and the
        // chain stays a profile with the reason.
        db("DELETE FROM ws_flows WHERE id = '" . $flow_id . "' AND version_id = 0");

        return array('ok' => false, 'flow_id' => 0, 'paused' => false, 'reason' => (string) $published['error']);
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
 * The file that marks the conversion due and keeps its result: in data/,
 * not data/temp (the clean-up sweeps that), named after the database as the
 * upgrade lock is, since sites may share one software directory. A PHP file
 * that exits first, so the web server never serves what it holds.
 *
 * @return string
 */
function pg_profile_flow_switch_file()
{
    $database = defined('DB_DATABASE') ? DB_DATABASE . '@' . (defined('DB_HOST') ? DB_HOST : '') : (string) db_value("SELECT DATABASE()");

    return PG_FUNCTIONS_DIR . '/data/campaign_profile_flows_' . md5($database) . '.php';
}

/**
 * The guard line the file starts with.
 *
 * @return string
 */
function pg_profile_flow_switch_guard()
{
    return "<?php exit; ?>\n";
}

/**
 * The state the file holds, from its text, null for a file that is not
 * one. Pure.
 *
 * @param string $text
 * @return array|null status (due, running, done), ceiling, due_at,
 *                    done_at, switched (head => flow id), failed (head =>
 *                    reason), report (plan, profiles, stats; once done)
 */
function pg_profile_flow_switch_parse($text)
{
    $guard = pg_profile_flow_switch_guard();

    if (strpos((string) $text, $guard) !== 0) {
        return null;
    }

    $state = json_decode(substr((string) $text, strlen($guard)), true);

    return (is_array($state) && in_array(($state['status'] ?? ''), array('due', 'running', 'done'), true)) ? $state : null;
}

/**
 * The state of the conversion, null when none was marked due here.
 *
 * @return array|null pg_profile_flow_switch_parse()
 */
function pg_profile_flow_switch_state()
{
    $file = pg_profile_flow_switch_file();

    return is_file($file) ? pg_profile_flow_switch_parse((string) @file_get_contents($file)) : null;
}

/**
 * Writes the state: beside the file and renamed, so a cut request never
 * leaves half of it.
 *
 * @param array $state
 * @return bool
 */
function pg_profile_flow_switch_write($state)
{
    $file = pg_profile_flow_switch_file();
    $text = pg_profile_flow_switch_guard() . json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

    if (@file_put_contents($file . '.tmp', $text) === false) {
        return false;
    }

    return @rename($file . '.tmp', $file);
}

/**
 * The state a conversion starts from. Pure.
 *
 * @param int $ceiling the highest profile id at the update
 * @param int $now
 * @return array
 */
function pg_profile_flow_switch_due($ceiling, $now)
{
    return array('status' => 'due', 'ceiling' => (int) $ceiling, 'due_at' => (int) $now, 'done_at' => 0,
        'switched' => array(), 'failed' => array(), 'report' => null);
}

/**
 * The update step's part: marks the conversion due, once. Nothing is marked
 * when a file is there already, when a flow's start box already carries a
 * profile (a conversion ran), or when the site has no profile; a run of the
 * upgrade steps again never opens a second conversion.
 *
 * @param int $now
 * @return array marked (bool), profiles (how many the job will look at),
 *               error ('' or why nothing could be marked)
 */
function pg_profile_flow_switch_mark($now)
{
    $none = array('marked' => false, 'profiles' => 0, 'error' => '');
    $ceiling = (int) db_value("SELECT MAX(id) FROM email_campaign_profiles");

    if (($ceiling <= 0) || is_file(pg_profile_flow_switch_file()) || !empty(pg_profile_flow_switched())) {
        return $none;
    }

    if (!pg_profile_flow_switch_write(pg_profile_flow_switch_due($ceiling, $now))) {
        return array('error' => 'data/ cannot be written') + $none;
    }

    return array('marked' => true, 'profiles' => (int) db_value("SELECT COUNT(*) FROM email_campaign_profiles"), 'error' => '');
}

/**
 * The plan the report is drawn from once the conversion is done: the
 * chains switched as done, a chain whose flow could not be published left
 * with its reason, its partners naming it. Pure.
 *
 * @param array $plan     pg_profile_flow_convert() with the switched chains done
 * @param array $failed   head => reason
 * @param array $profiles id => row
 * @return array
 */
function pg_profile_flow_switch_final($plan, $failed, $profiles)
{
    foreach ($plan['flows'] as $head => $flow) {
        if (!isset($failed[$head])) {
            continue;
        }

        foreach ($flow['members'] as $id) {
            // The flow code's refusal comes as a sentence in the job's
            // language; the partners' reason is kept as a spec.
            $plan['left'][$id] = ((int) $id === (int) $head) ? (string) $failed[$head]
                : array('string' => 'A profile of its chain stays: {var:1}.', 'vars' => (string) ($profiles[$head]['name'] ?? ''));
        }

        unset($plan['flows'][$head]);
    }

    return $plan;
}

/**
 * A pass of the general job: switches the chains still to switch within the
 * budget, one Site Log line each; with none left, writes the result and
 * rings the administrators' bell once.
 *
 * @param float $budget seconds
 * @return int chains switched in this pass
 */
function pg_profile_flow_switch_tick($budget)
{
    $state = pg_profile_flow_switch_state();

    if (!is_array($state) || ($state['status'] === 'done')) {
        return 0;
    }

    if (!is_file(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php')) {
        return 0;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/campaign_profile_flow.php');

    if (!function_exists('ws_flows_ready') || !ws_flows_ready()) {
        return 0;
    }

    $started = microtime(true);
    $now = time();
    $state['status'] = 'running';
    $run = pg_profile_flow_dry_run($now, pg_profile_flow_switched(), (int) $state['ceiling']);
    $turned = 0;
    $pending = false;

    foreach ($run['plan']['flows'] as $head => $flow) {
        if (isset($state['failed'][$head])) {
            continue;
        }

        if ((microtime(true) - $started) >= (float) $budget) {
            $pending = true;
            break;
        }

        $result = pg_profile_flow_switch_chain((int) $head, $flow);

        if ($result['ok']) {
            $state['switched'][$head] = (int) $result['flow_id'];
            $turned++;
            log_activity(lang(array('string' => 'e-mail campaign profile ({var:1}) was turned into flow #{var:2}', 'vars' => array($flow['name'], (int) $result['flow_id']))), 'UNKNOWN');
        } else {
            $state['failed'][$head] = (string) $result['reason'];
            log_activity(lang(array('string' => 'e-mail campaign profile ({var:1}) could not be turned into a flow: {var:2}', 'vars' => array($flow['name'], (string) $result['reason']))), 'UNKNOWN');
        }

        // Written after every chain: a pass cut short keeps what it did.
        pg_profile_flow_switch_write($state);
    }

    if ($pending) {
        pg_profile_flow_switch_write($state);

        return $turned;
    }

    $final = pg_profile_flow_dry_run($now, pg_profile_flow_switched(), (int) $state['ceiling']);
    $profiles = array();

    foreach ($final['profiles'] as $id => $row) {
        $profiles[$id] = array('name' => (string) $row['name'], 'action' => (string) $row['action'], 'enabled' => (int) $row['enabled'], 'purpose' => (string) $row['purpose']);
    }

    $state['status'] = 'done';
    $state['done_at'] = time();
    $state['report'] = array('plan' => pg_profile_flow_switch_final($final['plan'], $state['failed'], $profiles), 'profiles' => $profiles, 'stats' => $final['stats']);

    if (pg_profile_flow_switch_write($state)) {
        create_notification(array('action' => 'profile_flows', 'type' => 'info', 'title' => (string) count($state['switched']), 'user' => 'UNKNOWN'));
    }

    return $turned;
}

/**
 * The notice of the E-mail Campaign Profiles screen: while the conversion is
 * due or running, that it is under way; once done, "Profiles turned into
 * flows on <date>" opening to the report (the flows made, each linked; the
 * profiles left with their reasons), drawn in the reader's language. '' when
 * no conversion was marked here.
 *
 * @param array|null $state   pg_profile_flow_switch_state()
 * @param string     $date    the day it was done, as the reader reads it
 * @param string[]   $actions action => its name on the screen; a key
 *                            missing is shown as it is
 * @return string HTML
 */
function pg_profile_flow_switch_notice($state, $date, $actions = array())
{
    if (!is_array($state)) {
        return '';
    }

    $open = function ($tone, $icon) {
        return '<div id="profile-flows" class="alert theme-' . $tone . ' d-flex align-items-start gap-3" role="status">'
            . '<i class="bi ' . $icon . '" aria-hidden="true"></i><div class="flex-grow-1 min-w-0">';
    };

    if ($state['status'] !== 'done') {
        return $open('info', 'bi-arrow-repeat')
            . '<strong>' . h(lang('Profiles are being turned into flows')) . '</strong>'
            . '<p class="mb-0">' . h(lang(array('string' => 'The general job turns these profiles into flows over its next runs; the report shows here when it is done. Flows made so far: {var:1}.', 'vars' => count((array) $state['switched'])))) . '</p>'
            . '</div></div>';
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/campaign_profile_flow.php');

    $report = is_array($state['report'] ?? null) ? $state['report'] : array();
    $plan = (array) ($report['plan'] ?? array()) + array('flows' => array(), 'left' => array(), 'orphans' => array(), 'done' => array());
    $profiles = (array) ($report['profiles'] ?? array());
    $made = '';

    foreach ($profiles as $id => $profile) {
        $profiles[$id]['action'] = (string) ($actions[(string) ($profile['action'] ?? '')] ?? ($profile['action'] ?? ''));
    }

    foreach ((array) $plan['done'] as $head => $flow_id) {
        $made .= '<li><a href="workspace_flows.php?flow=' . (int) $flow_id . '">' . h((string) ($profiles[$head]['name'] ?? '')) . '</a></li>';
    }

    // The profiles left, each as the dry-run report words it, its name
    // leading to the profile and its counts under it.
    $left = '';

    foreach ((array) $plan['left'] as $id => $reason) {
        $lines = array_values(array_filter(pg_profile_flow_report_lines(array('flows' => array(), 'left' => array($id => $reason), 'done' => array()), $profiles, (array) ($report['stats'] ?? array())), function ($line) {
            return trim($line) !== '';
        }));
        $name = h('“' . (string) ($profiles[$id]['name'] ?? '') . '”');
        $text = h((string) ($lines[1] ?? ''));
        $at = strpos($text, $name);

        if ($at !== false) {
            $text = substr($text, 0, $at) . '<a href="edit_email_campaign_profile.php?id=' . (int) $id . '">' . $name . '</a>' . substr($text, $at + strlen($name));
        }

        $left .= '<li>' . $text . (isset($lines[2]) ? '<div class="fg-secondary">' . h(trim($lines[2])) . '</div>' : '') . '</li>';
    }

    return $open('success', 'bi-diagram-3')
        . '<strong>' . h(lang(array('string' => 'Profiles turned into flows on {var:1}', 'vars' => $date))) . '</strong>'
        . '<details class="mt-2"><summary>' . h(lang(array('string' => 'Flows made: {var:1}; profiles left: {var:2}', 'vars' => array(count((array) $plan['done']), count((array) $plan['left']))))) . '</summary>'
        . (($made !== '') ? '<h3 class="h6 mt-3">' . h(lang('Flows made')) . '</h3><ul class="mb-0">' . $made . '</ul>' : '')
        . (($left !== '') ? '<h3 class="h6 mt-3">' . h(lang('Profiles left')) . '</h3><ul class="mb-0">' . $left . '</ul>' : '')
        . '</details></div></div>';
}

/**
 * The name each profile action is shown under on the profile screens and in
 * the conversion's report.
 *
 * @return string[] action => name
 */
function pg_profile_flow_action_names()
{
    return array(
        'calendar_event_reserved'    => lang('Calendar Event Reserved'),
        'custom_form_submitted'      => lang('Custom Form Submitted'),
        'email_campaign_sent'        => lang('Auto Campaign Sent'),
        'order_abandoned'            => lang('Order Abandoned'),
        'order_completed'            => lang('Order Completed'),
        'order_review_request'       => lang('Order Review Request'),
        'order_shipped'              => lang('Order Shipped'),
        'order_delivered'            => lang('Order Delivered'),
        'subscription_renewed'       => lang('Subscription Renewed'),
        'subscription_failed'        => lang('Subscription Payment Failed'),
        'subscription_cancelled'     => lang('Subscription Cancelled'),
        'subscription_price_changed' => lang('Subscription Price Changed'),
        'product_ordered'            => lang('Product Ordered'),
        'booking_confirmed'          => lang('Booking Confirmed'),
        'booking_reminder'           => lang('Booking Reminder'),
        'booking_rescheduled'        => lang('Booking Rescheduled'),
        'booking_cancelled'          => lang('Booking Cancelled'),
        'contact_group_joined'       => lang('Joined a Contact Group'),
        'credit_issued'              => lang('Store Credit Issued'),
        'credit_expiring'            => lang('Store Credit Expiring'),
    );
}

/**
 * Every profile of a chain a flow was made of, its head and its followers,
 * with the flow. Pure.
 *
 * @param array $profiles id => row (action, action_item_id)
 * @param int[] $switched head id => flow id (pg_profile_flow_switched())
 * @return int[] profile id => flow id
 */
function pg_profile_flow_members($profiles, $switched)
{
    require_once PG_FUNCTIONS_DIR . '/includes/fn/campaign_profile_flow.php';

    $members = array();

    foreach (pg_profile_flow_chains($profiles)['chains'] as $head => $chain) {
        if (!isset($switched[$head])) {
            continue;
        }

        foreach ($chain as $place) {
            $members[(int) $place[0]] = (int) $switched[$head];
        }
    }

    return $members;
}

/**
 * The profiles a flow was made of on this site, profile id => flow id; read
 * once a request.
 *
 * @return int[]
 */
function pg_profile_flow_converted()
{
    static $converted = null;

    if ($converted === null) {
        $switched = pg_profile_flow_switched();
        $profiles = array();

        if (!empty($switched)) {
            foreach ((array) db_items("SELECT id, action, action_item_id FROM email_campaign_profiles") as $row) {
                $profiles[(int) $row['id']] = $row;
            }
        }

        $converted = empty($profiles) ? array() : pg_profile_flow_members($profiles, $switched);
    }

    return $converted;
}

/**
 * Where a profile screen sends its visitor instead of drawing, '' when it
 * draws: a profile a flow was made of to that flow; any screen, on a site
 * with no profile left, to the Automatic sends tab, where the flows and the
 * conversion's report are. Pure.
 *
 * @param int   $profile_id the profile the screen is about, 0 for none
 * @param int[] $converted  pg_profile_flow_converted()
 * @param int   $left       how many profiles are left
 * @return string address relative to the software directory
 */
function pg_profile_flow_screen_door($profile_id, $converted, $left)
{
    if (((int) $profile_id > 0) && isset($converted[(int) $profile_id])) {
        return 'workspace_flows.php?flow=' . (int) $converted[(int) $profile_id];
    }

    return ((int) $left <= 0) ? 'messages_automatic.php' : '';
}

/**
 * Sends the visitor of a profile screen on (pg_profile_flow_screen_door()),
 * or returns when the screen draws.
 *
 * @param int $profile_id
 */
function pg_profile_flow_screen_go($profile_id = 0)
{
    $converted = pg_profile_flow_converted();
    $left = (int) db_value("SELECT COUNT(*) FROM email_campaign_profiles") - count($converted);
    $door = pg_profile_flow_screen_door((int) $profile_id, $converted, $left);

    if ($door !== '') {
        go(PATH . SOFTWARE_DIRECTORY . '/' . $door);
    }
}

/**
 * The SQL that leaves out the profiles a flow was made of, '' when none
 * was. Pure.
 *
 * @param int[]  $converted pg_profile_flow_converted()
 * @param string $column
 * @return string " AND <column> NOT IN (…)"
 */
function pg_profile_flow_left_sql($converted, $column = 'email_campaign_profiles.id')
{
    if (empty($converted)) {
        return '';
    }

    return ' AND ' . $column . " NOT IN ('" . implode("', '", array_map('intval', array_keys($converted))) . "')";
}
