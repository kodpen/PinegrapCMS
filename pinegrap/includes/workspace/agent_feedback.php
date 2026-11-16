<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - Right or Wrong on an agent's reply: one mark per reply
 * (ws_agent_feedback), set by the person who asked or a manager of the
 * channel, with a short reason. A new mark replaces the last; taking it back
 * deletes it. The agent reads its own marks and the counts of the last 7 and
 * 30 days with its own key (GET /workspace/agent-feedback). A mark on a reply
 * that was deleted is neither listed nor counted.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The longest reason a mark carries.
if (!defined('WS_AGENT_FEEDBACK_REASON_MAX')) {
    define('WS_AGENT_FEEDBACK_REASON_MAX', 300);
}

// The most marks the agent's read lists, newest first.
if (!defined('WS_AGENT_FEEDBACK_LIST_MAX')) {
    define('WS_AGENT_FEEDBACK_LIST_MAX', 100);
}

/**
 * Whether the marks table is there: on a site whose update has not run,
 * nothing can be marked and everything else works as before.
 *
 * @return bool
 */
function ws_agent_feedback_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_agent_feedback', 'verdict');
    }

    return $ready;
}

/**
 * Whether a request is a registry agent's (application or model agent), the
 * only kind whose replies are marked. Pure.
 *
 * @param string $value ws_ai_requests.agent
 * @return bool
 */
function ws_agent_feedback_agent_value($value)
{
    return preg_match('/^(agent|model):[1-9][0-9]*$/', (string) $value) === 1;
}

/**
 * Whether a person may mark a reply, and whether the mark itself is good.
 * Pure: the caller reads the records and the rights.
 *
 * @param array      $viewer     ws_viewer(): id, member
 * @param array|null $request    ws_ai_requests row the reply answered
 * @param array|null $message    ws_messages row of the reply
 * @param bool       $can_read   the person reads the reply's channel
 * @param bool       $can_manage the person manages the reply's channel
 * @param string     $verdict    right | wrong; null when taking the mark back
 * @param mixed      $reason
 * @return array ok, reason; or error, field, status
 */
function ws_agent_feedback_check($viewer, $request, $message, $can_read, $can_manage, $verdict, $reason)
{
    $refuse = function ($status, $error, $field = null) {
        return array('ok' => false, 'status' => $status, 'error' => $error, 'field' => $field);
    };

    if (!is_array($request) || !is_array($message) || ((int) ($message['deleted_at'] ?? 0) > 0)
        || !ws_agent_feedback_agent_value($request['agent'] ?? '')
        || ((int) ($request['reply_message_id'] ?? 0) !== (int) ($message['id'] ?? -1))
        || ((string) ($request['status'] ?? '') !== 'answered')) {
        return $refuse(404, lang('Only an agent\'s answer can be marked right or wrong.'));
    }

    if (empty($viewer['member']) || !$can_read) {
        return $refuse(404, lang('Only an agent\'s answer can be marked right or wrong.'));
    }

    if (((int) $viewer['id'] !== (int) $request['requested_by']) && !$can_manage) {
        return $refuse(403, lang('Only the person who asked and the managers of the channel can mark this answer.'));
    }

    if ($verdict === null) {
        return array('ok' => true, 'reason' => '');
    }

    if (!in_array($verdict, array('right', 'wrong'), true)) {
        return $refuse(422, lang('Mark the answer right or wrong.'), 'verdict');
    }

    $reason = trim(preg_replace('/\s+/u', ' ', is_string($reason) ? $reason : ''));

    if (mb_strlen($reason) > WS_AGENT_FEEDBACK_REASON_MAX) {
        return $refuse(422, lang(array('string' => 'The reason is at most {var:1} characters.', 'vars' => WS_AGENT_FEEDBACK_REASON_MAX)), 'reason');
    }

    return array('ok' => true, 'reason' => $reason);
}

/**
 * The records a mark on a reply is checked against, read with the person's
 * rights. Null parts when there is nothing to mark.
 *
 * @param array $viewer
 * @param int   $message_id
 * @return array request, message, can_read, can_manage
 */
function ws_agent_feedback_place($viewer, $message_id)
{
    $message = ((int) $message_id > 0) ? ws_message((int) $message_id) : null;
    $request = $message ? db_item("SELECT * FROM ws_ai_requests WHERE reply_message_id = '" . (int) $message_id . "' ORDER BY id DESC LIMIT 1") : null;
    $channel = $message ? ws_channel((int) $message['channel_id']) : null;

    return array(
        'request'    => is_array($request) ? $request : null,
        'message'    => $message ?: null,
        'can_read'   => $channel ? ws_can_read_channel($viewer, $channel) : false,
        'can_manage' => $channel ? ws_can_manage_channel($viewer, $channel) : false,
    );
}

/**
 * Marks an agent's reply right or wrong, or takes the mark back ($verdict
 * null). The panel's action and any later route call this one writer.
 *
 * @param array       $viewer
 * @param int         $message_id the agent's reply
 * @param string|null $verdict    right | wrong; null takes the mark back
 * @param string      $reason
 * @param int         $now
 * @return array ok, reason (as stored); or error, field, status
 */
function ws_agent_feedback_mark($viewer, $message_id, $verdict, $reason = '', $now = 0)
{
    if (!ws_agent_feedback_ready()) {
        return array('ok' => false, 'status' => 503, 'error' => lang('The database has to be updated first.'), 'field' => null);
    }

    $place = ws_agent_feedback_place($viewer, $message_id);
    $check = ws_agent_feedback_check($viewer, $place['request'], $place['message'], $place['can_read'], $place['can_manage'], $verdict, $reason);

    if (!$check['ok']) {
        return $check;
    }

    if ($verdict === null) {
        db("DELETE FROM ws_agent_feedback WHERE message_id = '" . (int) $message_id . "'");

        return array('ok' => true);
    }

    $now = ((int) $now > 0) ? (int) $now : time();
    $request = $place['request'];

    // One row per reply: the last mark stands.
    db("INSERT INTO ws_agent_feedback (message_id, request_id, channel_id, agent, verdict, reason, marked_by, marked_at)
        VALUES ('" . (int) $message_id . "', '" . (int) $request['id'] . "', '" . (int) $place['message']['channel_id'] . "', '" . e((string) $request['agent']) . "',
            '" . e($verdict) . "', '" . e($check['reason']) . "', '" . (int) $viewer['id'] . "', '" . $now . "')
        ON DUPLICATE KEY UPDATE verdict = VALUES(verdict), reason = VALUES(reason), marked_by = VALUES(marked_by), marked_at = VALUES(marked_at)");

    return array('ok' => true, 'reason' => $check['reason']);
}

/**
 * The counts of marks over the last 7 and 30 days, by the time of the mark.
 * Pure.
 *
 * @param array $rows marked_at, verdict
 * @param int   $now
 * @return array days_7, days_30: right, wrong
 */
function ws_agent_feedback_tally($rows, $now)
{
    $out = array();

    foreach (array(7, 30) as $days) {
        $since = (int) $now - $days * 86400;
        $count = array('right' => 0, 'wrong' => 0);

        foreach ((array) $rows as $row) {
            $verdict = (string) ($row['verdict'] ?? '');

            if (((int) ($row['marked_at'] ?? 0) > $since) && isset($count[$verdict])) {
                $count[$verdict]++;
            }
        }

        $out['days_' . $days] = $count;
    }

    return $out;
}

/**
 * An agent's marks, newest first, on replies that are still there.
 *
 * @param string $agent_value ws_ai_requests.agent
 * @param int    $since       0 for any time
 * @param int    $limit
 * @return array[]
 */
function ws_agent_feedback_rows($agent_value, $since = 0, $limit = WS_AGENT_FEEDBACK_LIST_MAX)
{
    if (!ws_agent_feedback_ready()) {
        return array();
    }

    return (array) db_items("SELECT f.message_id, f.request_id, f.channel_id, f.verdict, f.reason, f.marked_at
        FROM ws_agent_feedback f
        INNER JOIN ws_messages m ON m.id = f.message_id AND m.deleted_at = 0
        WHERE f.agent = '" . e((string) $agent_value) . "'" . (((int) $since > 0) ? " AND f.marked_at > '" . (int) $since . "'" : '') . "
        ORDER BY f.marked_at DESC
        LIMIT " . max(1, (int) $limit));
}

/**
 * What the reader of a channel sees of the mark on an agent's reply: null
 * for everybody but the person who asked and the channel's managers, who get
 * the current mark (verdict '' while unmarked). Pure.
 *
 * @param int        $viewer_id
 * @param bool       $can_manage the reader manages the reply's channel
 * @param array      $request    ws_ai_requests row whose reply it is
 * @param array|null $mark       ws_agent_feedback row, null while unmarked
 * @return array|null verdict, reason
 */
function ws_agent_feedback_offer($viewer_id, $can_manage, $request, $mark)
{
    if (!ws_agent_feedback_agent_value($request['agent'] ?? '') || ((string) ($request['status'] ?? '') !== 'answered')
        || (((int) $viewer_id !== (int) ($request['requested_by'] ?? 0)) && !$can_manage)) {
        return null;
    }

    return array(
        'verdict' => is_array($mark) ? (string) $mark['verdict'] : '',
        'reason'  => is_array($mark) ? (string) $mark['reason'] : '',
    );
}

/**
 * The offers of a page of messages: message id => offer, only for agents'
 * replies the reader may mark (ws_agent_feedback_offer()).
 *
 * @param array $viewer
 * @param int[] $message_ids
 * @return array
 */
function ws_agent_feedback_map($viewer, $message_ids)
{
    $ids = array_values(array_filter(array_map('intval', (array) $message_ids)));

    if (!$ids || !ws_agent_feedback_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT r.id, r.agent, r.status, r.requested_by, r.reply_message_id, r.channel_id, f.verdict, f.reason
        FROM ws_ai_requests r
        LEFT JOIN ws_agent_feedback f ON f.message_id = r.reply_message_id
        WHERE r.reply_message_id IN (" . implode(',', $ids) . ") AND r.status = 'answered' AND (r.agent LIKE 'agent:%' OR r.agent LIKE 'model:%')
        ORDER BY r.id");

    $out = array();
    $manages = array();

    foreach ($rows as $row) {
        $channel_id = (int) $row['channel_id'];

        if (!isset($manages[$channel_id])) {
            $channel = ws_channel($channel_id);
            $manages[$channel_id] = $channel ? ws_can_manage_channel($viewer, $channel) : false;
        }

        $offer = ws_agent_feedback_offer((int) $viewer['id'], $manages[$channel_id], $row, ($row['verdict'] !== null) ? $row : null);

        if ($offer !== null) {
            $out[(int) $row['reply_message_id']] = $offer;
        }
    }

    return $out;
}

/**
 * The 7- and 30-day counts of every agent from the grouped rows (agent,
 * verdict, d7, d30). Pure.
 *
 * @param array $rows
 * @return array agent value => ws_agent_feedback_tally() shape
 */
function ws_agent_feedback_tally_rows($rows)
{
    $out = array();

    foreach ((array) $rows as $row) {
        $agent = (string) ($row['agent'] ?? '');
        $verdict = (string) ($row['verdict'] ?? '');

        if (!in_array($verdict, array('right', 'wrong'), true)) {
            continue;
        }

        if (!isset($out[$agent])) {
            $out[$agent] = array('days_7' => array('right' => 0, 'wrong' => 0), 'days_30' => array('right' => 0, 'wrong' => 0));
        }

        $out[$agent]['days_7'][$verdict] += (int) ($row['d7'] ?? 0);
        $out[$agent]['days_30'][$verdict] += (int) ($row['d30'] ?? 0);
    }

    return $out;
}

/**
 * Every agent's marks of the last 7 and 30 days on replies still there, in
 * one grouped query.
 *
 * @param int $now
 * @return array agent value => counts
 */
function ws_agent_feedback_counts_by_agent($now)
{
    static $cache = array();

    if (!ws_agent_feedback_ready()) {
        return array();
    }

    if (!isset($cache[(int) $now])) {
        $cache[(int) $now] = ws_agent_feedback_tally_rows(db_items("SELECT f.agent, f.verdict,
                SUM(f.marked_at > '" . ((int) $now - 7 * 86400) . "') AS d7, COUNT(*) AS d30
            FROM ws_agent_feedback f
            INNER JOIN ws_messages m ON m.id = f.message_id AND m.deleted_at = 0
            WHERE f.marked_at > '" . ((int) $now - 30 * 86400) . "'
            GROUP BY f.agent, f.verdict"));
    }

    return $cache[(int) $now];
}

/**
 * The counts in words, as the Agents card and the agent card show them: ''
 * when nothing was marked in 30 days. Pure.
 *
 * @param array|null $counts ws_agent_feedback_tally() shape
 * @return string
 */
function ws_agent_feedback_counts_text($counts)
{
    $d7 = (array) ($counts['days_7'] ?? array());
    $d30 = (array) ($counts['days_30'] ?? array());

    if (((int) ($d30['right'] ?? 0) + (int) ($d30['wrong'] ?? 0)) === 0) {
        return '';
    }

    return lang(array('string' => '7 days: {var:1} right · {var:2} wrong; 30 days: {var:3} right · {var:4} wrong', 'vars' => array(
        (int) ($d7['right'] ?? 0), (int) ($d7['wrong'] ?? 0), (int) ($d30['right'] ?? 0), (int) ($d30['wrong'] ?? 0),
    )));
}

/**
 * The texts of the marks under a reply in workspace.js.
 *
 * @return array key => text
 */
function ws_agent_feedback_js_strings()
{
    return array(
        'af_ask'         => lang('Was this answer right?'),
        'af_right'       => lang('Correct'),
        'af_wrong'       => lang('Wrong'),
        'af_right_help'  => lang('Mark this answer right'),
        'af_wrong_help'  => lang('Mark this answer wrong'),
        'af_undo_help'   => lang('Take the mark back'),
        'af_reason'      => lang('What was wrong? (optional)'),
        'af_save'        => lang('Save'),
        'af_cancel'      => lang('Cancel'),
        'af_marked'      => lang('Marked'),
    );
}
