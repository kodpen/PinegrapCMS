<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an agent waits while the message that asked it is being
 * edited. While the writer has the edit box of a message with an open
 * request open, the browser beats (ws_edit_hold action) and every open
 * request of that message is held for two minutes from the last beat
 * (ws_ai_requests.held_until). A held request is handed to nobody: the
 * agent-requests routes leave it out and refuse its claim, no push door is
 * knocked for it and the site's own runners skip it.
 *
 * Save hands the requests on with the new words; cancel, or two minutes
 * without a beat, with the words of before. A run already working on the
 * message reads again: the site's own runners start the request over, an
 * application agent's or Claude's answer is refused (409 edited) and the
 * request queued again. An answer already given is never asked again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// How long a beat holds the requests, in seconds.
if (!defined('WS_EDIT_HOLD_SECONDS')) {
    define('WS_EDIT_HOLD_SECONDS', 120);
}

/**
 * Whether the hold's column is there: files land before the upgrade runs.
 *
 * @return bool
 */
function ws_edit_hold_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('ws_ai_requests', 'held_until');
    }

    return $ready;
}

/**
 * The statuses of a request that is still open: those a hold applies to.
 *
 * @return string[]
 */
function ws_edit_hold_statuses()
{
    return array('queued', 'sent', 'running');
}

/**
 * The SQL condition a picker adds to leave held requests out. Pure.
 *
 * @param int    $now
 * @param bool   $ready the column exists (ws_edit_hold_ready())
 * @param string $alias table alias with its dot, '' for none
 * @return string " AND …" or '' before the upgrade
 */
function ws_edit_hold_free_where($now, $ready, $alias = '')
{
    return $ready ? " AND " . $alias . "held_until <= '" . (int) $now . "'" : '';
}

/**
 * ws_edit_hold_free_where() for now.
 *
 * @param string $alias
 * @return string
 */
function ws_edit_hold_free_sql($alias = '')
{
    return ws_edit_hold_free_where(time(), ws_edit_hold_ready(), $alias);
}

/**
 * Whether a request is held now. Pure.
 *
 * @param array $row ws_ai_requests: status, held_until
 * @param int   $now
 * @return bool
 */
function ws_edit_hold_is_held($row, $now)
{
    return in_array((string) ($row['status'] ?? ''), ws_edit_hold_statuses(), true)
        && ((int) ($row['held_until'] ?? 0) > (int) $now);
}

/**
 * Whether a run's answer is refused because of an edit. Pure.
 *
 * @param array      $row     ws_ai_requests: status, held_until, claimed_at
 * @param array|null $message ws_messages: edited_at
 * @param int        $now
 * @return string '' (answer), held (the edit box is open: try again
 *                later), edited (saved after the claim: read again)
 */
function ws_edit_hold_answer_refusal($row, $message, $now)
{
    if (ws_edit_hold_is_held($row, $now)) {
        return 'held';
    }

    $claimed = (int) ($row['claimed_at'] ?? 0);

    if (((string) ($row['status'] ?? '') === 'running') && ($claimed > 0) && is_array($message)
        && ((int) ($message['edited_at'] ?? 0) > $claimed)) {
        return 'edited';
    }

    return '';
}

/**
 * Whether a run of this agent value is the site's own, started over when
 * the message is saved: Pinegrap AI and the model agents. Pure.
 *
 * @param string $agent ws_ai_requests.agent
 * @return bool
 */
function ws_edit_hold_site_run($agent)
{
    return ((string) $agent === 'ai') || (strpos((string) $agent, 'model:') === 0);
}

/**
 * The sentence of a refused answer, with its API code. Pure.
 *
 * @param string $refusal held | edited
 * @return array code (editing | edited), error
 */
function ws_edit_hold_refusal_error($refusal)
{
    return ($refusal === 'held')
        ? array('code' => 'editing', 'error' => lang('The message that asked is being edited: wait and try again in a minute.'))
        : array('code' => 'edited', 'error' => lang('The message that asked was edited after the claim: the request is waiting again; claim it and read the new words.'));
}

/**
 * The writer's edit box is open (or they typed): holds the message's open
 * requests for two minutes from now. Only the writer of the message, a
 * person, may hold it.
 *
 * @param array $viewer
 * @param array $message
 * @return int how many requests are held
 */
function ws_edit_hold_beat($viewer, $message)
{
    if (!ws_edit_hold_ready() || !is_array($message) || ((int) $message['id'] <= 0)
        || ((string) ($message['sender_kind'] ?? 'user') !== 'user') || ((int) $message['sender_id'] !== (int) $viewer['id'])
        || ((int) ($message['deleted_at'] ?? 0) > 0)) {
        return 0;
    }

    $now = time();
    $open = "message_id = '" . (int) $message['id'] . "' AND status IN ('" . implode("', '", ws_edit_hold_statuses()) . "')";
    $starts = (int) db_value("SELECT COUNT(*) FROM ws_ai_requests WHERE " . $open . " AND held_until <= '" . $now . "'");

    db("UPDATE ws_ai_requests SET held_until = '" . ($now + WS_EDIT_HOLD_SECONDS) . "' WHERE " . $open);
    $held = max(0, (int) mysqli_affected_rows(db::$con));

    // The line under the message changes only when a hold starts.
    if (($starts > 0) && function_exists('ws_message_touch')) {
        ws_message_touch((int) $message['id']);
    }

    return $held;
}

/**
 * The hold ends: on save ($saved) the runs already working start over or
 * are refused at their answer, on cancel and expiry the words stay. The
 * push doors of the requests waiting are knocked now.
 *
 * @param int  $message_id
 * @param bool $saved
 */
function ws_edit_hold_release($message_id, $saved)
{
    if (!ws_edit_hold_ready() || ((int) $message_id <= 0)) {
        return;
    }

    $where = "message_id = '" . (int) $message_id . "' AND status IN ('" . implode("', '", ws_edit_hold_statuses()) . "')";
    $rows = (array) db_items("SELECT id, agent, status, held_until FROM ws_ai_requests WHERE " . $where);

    if (empty($rows)) {
        return;
    }

    if ($saved) {
        // Pinegrap AI and the model agents run on the site: an unfinished
        // run starts over and reads the new words.
        foreach ($rows as $row) {
            if (ws_edit_hold_site_run($row['agent']) && in_array($row['status'], array('sent', 'running'), true)) {
                db("UPDATE ws_ai_requests SET status = 'queued', claimed_at = 0, sent_at = 0, ai_state = NULL, ai_steps = 0, ai_attempts = 0, error = ''
                    WHERE id = '" . (int) $row['id'] . "' AND status IN ('sent', 'running')");
            }
        }
    }

    db("UPDATE ws_ai_requests SET held_until = 0 WHERE " . $where);

    if (function_exists('ws_message_touch')) {
        ws_message_touch((int) $message_id);
    }

    ws_edit_hold_knock($rows);
}

/**
 * Knocks the push doors of an application agent's requests that waited
 * through a hold.
 *
 * @param array[] $rows ws_ai_requests: id, agent, status
 */
function ws_edit_hold_knock($rows)
{
    if (!function_exists('ws_agent_request_parse') || !function_exists('ws_agent_knock')) {
        return;
    }

    foreach ((array) $rows as $row) {
        $parsed = ws_agent_request_parse((string) $row['agent']);

        if (!$parsed || ($parsed['kind'] !== 'app') || !in_array($row['status'], array('queued', 'sent'), true)) {
            continue;
        }

        $agent = db_item("SELECT * FROM ws_agents WHERE id = '" . (int) $parsed['id'] . "' AND kind = 'app' AND status = 'active' AND door_kind = 'push'");

        if (is_array($agent)) {
            ws_agent_knock($agent, (int) $row['id']);
        }
    }
}

/**
 * Ends the holds whose two minutes passed without a beat: the line under
 * the message is drawn again and the push doors are knocked. Called with
 * the queue's other work (ws_agent_knocks_retry()).
 */
function ws_edit_hold_expire()
{
    if (!ws_edit_hold_ready()) {
        return;
    }

    $now = time();
    $statuses = "status IN ('" . implode("', '", ws_edit_hold_statuses()) . "')";

    foreach ((array) db_values("SELECT DISTINCT message_id FROM ws_ai_requests
        WHERE held_until > 0 AND held_until <= '" . $now . "' AND " . $statuses . " LIMIT 50") as $message_id) {
        ws_edit_hold_release((int) $message_id, false);
    }

    // A closed request keeps no hold.
    db("UPDATE ws_ai_requests SET held_until = 0 WHERE held_until > 0 AND held_until <= '" . $now . "' AND NOT " . $statuses);
}

/**
 * Whether a request is held now, read from the database.
 *
 * @param int $request_id
 * @return bool
 */
function ws_edit_hold_request_held($request_id)
{
    if (!ws_edit_hold_ready()) {
        return false;
    }

    $row = db_item("SELECT status, held_until FROM ws_ai_requests WHERE id = '" . (int) $request_id . "'");

    return is_array($row) && ws_edit_hold_is_held($row, time());
}

/**
 * Puts a request whose answer was refused as edited back in the queue:
 * the next claim reads the new words.
 *
 * @param array $row ws_ai_requests
 */
function ws_edit_hold_requeue($row)
{
    $device = (function_exists('ws_claude_sessions_ready') && ws_claude_sessions_ready()) ? ', claimed_device_id = 0' : '';

    db("UPDATE ws_ai_requests SET status = 'queued', claimed_at = 0, error = ''" . $device . "
        WHERE id = '" . (int) $row['id'] . "' AND status = 'running'");

    if (function_exists('ws_message_touch')) {
        ws_message_touch((int) $row['message_id']);
    }
}

/**
 * Refuses an API answer while the message is held or once it was edited
 * after the claim (then the request waits again, but for a dry run).
 * Answers 409 and stops.
 *
 * @param array         $row    ws_ai_requests
 * @param callable|null $unmark takes the run's eye off the message when the
 *                              request waits again
 */
function ws_edit_hold_answer_guard($row, $unmark = null)
{
    if (!ws_edit_hold_ready() || ((int) $row['message_id'] <= 0)) {
        return;
    }

    $message = db_item("SELECT id, edited_at FROM ws_messages WHERE id = '" . (int) $row['message_id'] . "'");
    $refusal = ws_edit_hold_answer_refusal($row, $message, time());

    if ($refusal === '') {
        return;
    }

    if (($refusal === 'edited') && !(function_exists('api_dry_run_requested') && api_dry_run_requested())) {
        ws_edit_hold_requeue($row);

        if (is_callable($unmark)) {
            $unmark();
        }
    }

    $said = ws_edit_hold_refusal_error($refusal);
    api_fail(409, $said['code'], $said['error']);
}

/**
 * The name of the agent a request waits for, as the line under the message
 * says it.
 *
 * @param array $row ws_ai_requests
 * @return string
 */
function ws_edit_hold_agent_name($row)
{
    $agent = (string) ($row['agent'] ?? 'claude');
    $parsed = function_exists('ws_agent_request_parse') ? ws_agent_request_parse($agent) : null;

    if ($parsed && function_exists('ws_agents_ready') && ws_agents_ready()) {
        $handle = (string) db_value("SELECT handle FROM ws_agents WHERE id = '" . (int) $parsed['id'] . "'");

        return ($handle !== '') ? '@' . $handle : lang('The agent');
    }

    if (($agent === 'mcp') && ((int) ($row['mcp_server_id'] ?? 0) > 0) && function_exists('ws_mcp_server')) {
        $server = ws_mcp_server((int) $row['mcp_server_id']);

        return $server ? '@' . $server['alias'] : lang('The agent');
    }

    if (function_exists('ws_assistant_name') && in_array($agent, array('ai', 'claude'), true)) {
        return ws_assistant_name($agent);
    }

    return lang('The agent');
}

/**
 * The line under a held message, put over the request's own line. Pure
 * but for the name.
 *
 * @param array $state the request's line (ws_claude_request_state())
 * @param array $row   ws_ai_requests
 * @param int   $now
 * @return array the line
 */
function ws_edit_hold_state($state, $row, $now)
{
    if (!is_array($state) || !ws_edit_hold_is_held($row, $now)) {
        return $state;
    }

    $state['label'] = lang(array('string' => 'Being edited, {var:1} is waiting', 'vars' => ws_edit_hold_agent_name($row)));
    $state['icon'] = 'bi-pencil';
    $state['held'] = true;

    return $state;
}
