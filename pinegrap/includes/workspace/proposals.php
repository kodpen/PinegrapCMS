<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - record changes an application proposes.
 *
 * An application connected to the site - an AI app that reaches it over MCP
 * through an OAuth connection (ChatGPT, Cursor and the like), or an
 * integration with an application key - writes no record through the
 * Workspace: it proposes the change, in a channel or on a task, and the
 * change waits on a card (ws_ai_changes, changes.php) for the person the
 * connection or the key acts for, who applies it with their own rights or
 * sets it aside, as a change Claude proposed. The changes are checked as
 * Claude's answers are (ws_changes_input()), kept the same way
 * (ws_changes_store()) and applied by the same path; nothing here writes a
 * record, and no application applies or sets aside what an application
 * proposed: only the card and the person's own team devices session do
 * (ws_api_proposal_decision_gate()).
 *
 * Each proposal is a row of ws_ai_requests, so its cards hang off one place
 * as they do under an assistant's answer. Its agent names the application:
 * oauth:<oauth_clients.id> for an OAuth connection (every OAuth client writes
 * through the one OAuth application, so the client is what tells ChatGPT
 * from Cursor), app:<api_apps.id> for an application key. The row is written
 * answered and asks nothing: the queues of Claude (agent claude), Pinegrap AI
 * (agent ai) and an MCP server's own agent (agent mcp) each read their own
 * agent, and only rows still waiting, so none takes a proposal and none
 * answers it again. In a channel the words are a message of the application
 * (reply_message_id; message_id stays 0, since no message asked) with the
 * cards under it; on a task they are kept on the row as a run's answer is
 * (note_reply), with the cards on the task (delegation.php).
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
 * The most proposals one application makes for one person in an hour. An
 * application's messages are not held to a person's twenty a minute
 * (ws_rate_limited()), and each proposal carries up to WS_CHANGES_PER_ANSWER
 * cards: an app caught in a loop stops here instead of burying a channel in
 * cards nobody asked for.
 */
define('WS_PROPOSALS_PER_HOUR', 30);

/**
 * The agent a proposal is kept under: oauth:<client id> for an OAuth
 * connection, app:<application id> for an application key, '' for neither.
 *
 * @param int $oauth_client_id oauth_clients.id, 0 for none
 * @param int $app_id          api_apps.id, 0 for none
 * @return string
 */
function ws_proposal_agent_key($oauth_client_id, $app_id)
{
    if ((int) $oauth_client_id > 0) {
        return 'oauth:' . (int) $oauth_client_id;
    }

    return ((int) $app_id > 0) ? 'app:' . (int) $app_id : '';
}

/**
 * The agent the caller of an external API call proposes under: an OAuth
 * connection by its client, an application key by its application. A team
 * devices session has none: the person on their own device changes the
 * record themselves and proposes nothing to themselves.
 *
 * @param array|null $device api_current_device(), null for an application key
 * @param int        $app_id the current application
 * @return string
 */
function ws_proposal_caller_key($device, $app_id)
{
    if (is_array($device)) {
        return ws_proposal_agent_key((int) ($device['oauth_client_id'] ?? 0), 0);
    }

    return ws_proposal_agent_key(0, $app_id);
}

/**
 * The application a proposal's agent names.
 *
 * @param string $agent ws_ai_requests.agent
 * @return array|null kind (oauth | app), id; null for an assistant's agent
 */
function ws_proposal_agent_parse($agent)
{
    if (!preg_match('/^(oauth|app):([1-9][0-9]{0,9})$/', (string) $agent, $match)) {
        return null;
    }

    return array('kind' => $match[1], 'id' => (int) $match[2]);
}

/**
 * The proposals among the request rows, or every other row, as SQL over
 * ws_ai_requests.
 *
 * @param string $alias the table's alias in the query
 * @param bool   $is    true for the proposals, false for the rest
 * @return string
 */
function ws_proposal_where($alias = '', $is = true)
{
    $column = (($alias !== '') ? $alias . '.' : '') . 'agent';
    $sql = "(" . $column . " LIKE 'oauth:%' OR " . $column . " LIKE 'app:%')";

    return $is ? $sql : 'NOT ' . $sql;
}

/**
 * The agent a proposed change is shown under in the API: ai or mcp for
 * Pinegrap AI and an MCP server's agent, the application's agent for a
 * change an application proposed, claude otherwise (every row from before
 * the column was Claude's).
 *
 * @param string $agent ws_ai_requests.agent, '' when the row has none
 * @return string
 */
function ws_change_agent_key($agent)
{
    $agent = (string) $agent;

    if (in_array($agent, array('ai', 'mcp'), true) || (ws_proposal_agent_parse($agent) !== null)) {
        return $agent;
    }

    return 'claude';
}

/**
 * The read scopes the kinds of record these changes name are read with
 * elsewhere in the API (ws_ai_type_scope()): a proposal answers with what
 * the record holds now, so a caller must be able to read the kind it
 * proposes for. Read from the body as sent, before any record is looked up;
 * a change that is not an object or names no known kind is left to
 * ws_changes_input(), which refuses it.
 *
 * @param mixed $changes the changes, as sent
 * @param array $types   ws_change_types()
 * @return string[]
 */
function ws_proposal_scopes($changes, $types)
{
    $out = array();

    foreach (is_array($changes) ? $changes : array() as $change) {
        $type = is_array($change) ? (string) ($change['type'] ?? '') : '';

        if (isset($types[$type])) {
            $out[] = ws_ai_type_scope((string) $types[$type]['tag']);
        }
    }

    return array_values(array_unique($out));
}

/**
 * The first change of a kind no application proposes: a user account (tag
 * user_account). No API scope reads user accounts (account:read is the
 * caller's own), and a proposal answers with what the record holds now, so
 * an application acting for an administrator would read every sign-in
 * e-mail under the Workspace's scopes alone. Read from the body as sent,
 * before any record is looked up; a change that is not an object or names
 * no known kind is left to ws_changes_input().
 *
 * @param mixed $changes the changes, as sent
 * @param array $types   ws_change_types()
 * @return int the change's index, -1 when there is none
 */
function ws_proposal_closed_change($changes, $types)
{
    foreach (is_array($changes) ? array_values($changes) : array() as $index => $change) {
        $type = is_array($change) ? (string) ($change['type'] ?? '') : '';

        if (isset($types[$type]) && ((string) $types[$type]['tag'] === 'user_account')) {
            return $index;
        }
    }

    return -1;
}

/**
 * Are the tables there for a proposal: the changes, the agent of a request
 * and, on a task, the columns that tie both to it?
 *
 * @param bool $on_task
 * @return bool
 */
function ws_proposals_ready($on_task = false)
{
    return function_exists('ws_changes_ready') && ws_changes_ready()
        && function_exists('ws_ai_schema_ready') && ws_ai_schema_ready()
        && (!$on_task || (function_exists('ws_delegation_ready') && ws_delegation_ready()));
}

/**
 * The name the application of a proposal is shown with: the OAuth client's
 * own (ChatGPT, Cursor), or the application's, marked as an application
 * (ws_proposal_name_shown()).
 *
 * @param string $agent
 * @return string '' for an assistant's agent
 */
function ws_proposal_name($agent)
{
    $parsed = ws_proposal_agent_parse($agent);

    return $parsed ? ws_proposal_name_shown(ws_proposal_own_name($agent), $parsed['kind']) : '';
}

/**
 * The name the application of a proposal gave itself, cleaned, with no
 * mark: for its initials.
 *
 * @param string $agent
 * @return string '' for an assistant's agent or an application with no name
 */
function ws_proposal_own_name($agent)
{
    static $cache = array();

    $parsed = ws_proposal_agent_parse($agent);

    if (!$parsed) {
        return '';
    }

    if (!isset($cache[$agent])) {
        $name = ($parsed['kind'] === 'oauth')
            ? (string) db_value("SELECT name FROM oauth_clients WHERE id = '" . $parsed['id'] . "'")
            : (string) db_value("SELECT name FROM api_apps WHERE id = '" . $parsed['id'] . "'");

        $cache[$agent] = ws_proposal_name_clean($name);
    }

    return $cache[$agent];
}

/**
 * An application's name as every line about its proposal carries it: the
 * Site Log, a decision, the bell, a card's title, a task's drawer. Marked as
 * an application, as its messages are (ws_app_sender()): an OAuth client
 * names itself when it registers, and one called Claude, Pinegrap AI or
 * after a colleague would otherwise read as them, also in the audit trail
 * once the change is applied.
 *
 * @param string $name ws_proposal_name_clean()
 * @param string $kind oauth | app
 * @return string
 */
function ws_proposal_name_shown($name, $kind)
{
    $name = (string) $name;

    if ($name === '') {
        return ($kind === 'oauth') ? lang('OAuth connection') : lang('Application');
    }

    return lang(array('string' => '{var:1} (application)', 'vars' => $name));
}

/**
 * An application's name as it may go into a message: the name goes into the
 * words of a decision ("... on ChatGPT's proposal"), and an OAuth client
 * names itself when it registers, so a name written as a tag (<@user:1>,
 * <#order:5>) would mention a person or point at a record there. One line,
 * no angle brackets, at most the length of the column it comes from.
 *
 * @param string $name
 * @return string
 */
function ws_proposal_name_clean($name)
{
    $name = trim((string) preg_replace('/[\s<>]+/u', ' ', (string) $name));

    return trim(mb_substr($name, 0, 100));
}

/**
 * The picture of the application of a proposal: its initials, as an
 * application's messages carry them (ws_app_sender()).
 *
 * @param string $agent
 * @return string
 */
function ws_proposal_avatar($agent)
{
    $parsed = ws_proposal_agent_parse($agent);

    if ($parsed && function_exists('pg_initials_avatar_url')) {
        $name = ws_proposal_own_name($agent);

        return pg_initials_avatar_url(pg_initials(($name !== '') ? $name : 'App', ''), 'contact', $parsed['kind'] . $parsed['id']);
    }

    return PATH . SOFTWARE_DIRECTORY . '/assets/images/ws-ai.svg';
}

/**
 * The application whose name a note on a task that an application proposed
 * is written in: the key's own application, or for an OAuth connection the
 * OAuth application every client writes through (the oldest active one, as
 * pg_oauth_app() reads it).
 *
 * @param string $agent
 * @return int 0 when there is none any more
 */
function ws_proposal_app_id($agent)
{
    $parsed = ws_proposal_agent_parse($agent);

    if (!$parsed) {
        return 0;
    }

    if ($parsed['kind'] === 'app') {
        return $parsed['id'];
    }

    return (int) db_value("SELECT id FROM api_apps WHERE kind = 'oauth' AND status = 'active' ORDER BY id ASC LIMIT 1");
}

/**
 * The agent a change was proposed under, when an application proposed it.
 *
 * @param array|null $change ws_ai_changes row
 * @return string '' for a change an assistant proposed
 */
function ws_change_proposal_agent($change)
{
    static $cache = array();

    $request_id = is_array($change) ? (int) ($change['request_id'] ?? 0) : 0;

    if (($request_id <= 0) || !ws_proposals_ready()) {
        return '';
    }

    if (!isset($cache[$request_id])) {
        $agent = (string) db_value("SELECT agent FROM ws_ai_requests WHERE id = '" . $request_id . "'");
        $cache[$request_id] = ws_proposal_agent_parse($agent) ? $agent : '';
    }

    return $cache[$request_id];
}

/**
 * The name of the application that proposed a change.
 *
 * @param array|null $change ws_ai_changes row
 * @return string '' for a change an assistant proposed
 */
function ws_change_proposer($change)
{
    $agent = ws_change_proposal_agent($change);

    return ($agent !== '') ? ws_proposal_name($agent) : '';
}

/**
 * The applications that proposed these requests, by name; the requests of
 * the assistants are left out.
 *
 * @param int[] $request_ids
 * @return array request id => name
 */
function ws_proposers_map($request_ids)
{
    $request_ids = array_values(array_unique(array_filter(array_map('intval', (array) $request_ids))));

    if (empty($request_ids) || !ws_proposals_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT id, agent FROM ws_ai_requests
        WHERE id IN (" . implode(',', $request_ids) . ") AND " . ws_proposal_where()) as $row) {
        $out[(int) $row['id']] = ws_proposal_name((string) $row['agent']);
    }

    return $out;
}

/**
 * The proposals whose words are these messages.
 *
 * @param int[] $message_ids
 * @return array message id => agent
 */
function ws_proposals_by_reply($message_ids)
{
    $message_ids = array_values(array_unique(array_filter(array_map('intval', (array) $message_ids))));

    if (empty($message_ids) || !ws_proposals_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT reply_message_id, agent FROM ws_ai_requests
        WHERE reply_message_id IN (" . implode(',', $message_ids) . ") AND " . ws_proposal_where()) as $row) {
        $out[(int) $row['reply_message_id']] = (string) $row['agent'];
    }

    return $out;
}

/**
 * The sender a message carrying an OAuth connection's proposal is drawn
 * with: the client's own name and initials, since every OAuth client writes
 * through the one OAuth application and would otherwise be shown under its
 * name. A key's message already carries its application's name.
 *
 * @param array|null $sender ws_app_sender()
 * @param string     $agent
 * @return array|null
 */
function ws_proposal_sender($sender, $agent)
{
    $parsed = ws_proposal_agent_parse($agent);

    if (!is_array($sender) || !$parsed || ($parsed['kind'] !== 'oauth')) {
        return $sender;
    }

    $sender['name'] = ws_proposal_name($agent);
    $sender['avatar'] = ws_proposal_avatar($agent);

    return $sender;
}

/**
 * Where a proposal stands, as the line under a run on a task says it: it
 * was made, and waits on its cards.
 *
 * @param array $row ws_ai_requests
 * @return array id, status, label, icon, session_url, agent
 */
function ws_proposal_request_state($row)
{
    return array(
        'id'          => (int) $row['id'],
        'status'      => (string) $row['status'],
        'label'       => lang(array('string' => 'Proposed by {var:1}', 'vars' => ws_proposal_name((string) $row['agent']))),
        'icon'        => 'bi-plug',
        'session_url' => '',
        'agent'       => (string) $row['agent'],
    );
}

/**
 * How many proposals this application made for this person in the last
 * hour.
 *
 * @param string $agent
 * @param int    $user_id
 * @return int
 */
function ws_proposal_count($agent, $user_id)
{
    return (int) db_value("SELECT COUNT(*) FROM ws_ai_requests
        WHERE agent = '" . e($agent) . "' AND requested_by = '" . (int) $user_id . "' AND created_at > '" . (time() - 3600) . "'");
}

/**
 * Has this application made as many proposals for this person as it may in
 * the last hour (WS_PROPOSALS_PER_HOUR)?
 *
 * @param string $agent
 * @param int    $user_id
 * @return bool
 */
function ws_proposal_limited($agent, $user_id)
{
    return ws_proposal_count($agent, $user_id) >= WS_PROPOSALS_PER_HOUR;
}

/**
 * Writes a proposal: the request it is kept under, its words (a message of
 * the application in the channel, or the request's answer on a task) and
 * the changes, which wait there for the person to apply them. The checks
 * are the caller's, made first: ws_message_send_check() or
 * ws_task_note_add_check(), and ws_changes_input().
 *
 * @param array  $viewer the person the application acts for, who applies the changes
 * @param string $agent  ws_proposal_agent_key()
 * @param array  $place  channel (a ws_channels row) or task (a ws_tasks row)
 * @param string $text   the words, checked
 * @param array  $rows   ws_changes_input() rows
 * @param int    $app_id the application the message is written as
 * @return array ok, error, limited (the hourly limit was reached meanwhile),
 *               request_id, message_id (0 on a task)
 */
function ws_proposal_store($viewer, $agent, $place, $text, $rows, $app_id)
{
    $channel = isset($place['channel']) ? $place['channel'] : null;
    $task = $channel ? null : (isset($place['task']) ? $place['task'] : null);
    $now = time();

    $fail = function ($error, $limited = false) {
        return array('ok' => false, 'error' => $error, 'limited' => $limited, 'request_id' => 0, 'message_id' => 0);
    };

    if (!$channel && !$task) {
        return $fail(lang('Invalid request.'));
    }

    // Written answered: no queue takes it, and its cards need it from the
    // start.
    db("INSERT INTO ws_ai_requests (channel_id, message_id, " . ($task ? 'task_id, note_text, note_reply, ' : '') . "requested_by, status, agent, created_at, answered_at)
        VALUES (
            '" . ($channel ? (int) $channel['id'] : 0) . "',
            '0',
            " . ($task ? "'" . (int) $task['id'] . "', '', '" . e($text) . "'," : '') . "
            '" . (int) $viewer['id'] . "',
            'answered',
            '" . e($agent) . "',
            '" . $now . "',
            '" . $now . "')");

    $request_id = (int) mysqli_insert_id(db::$con);

    if ($request_id <= 0) {
        return $fail(lang('The proposal could not be saved.'));
    }

    // Counted again with this row in: requests sent at the same moment all
    // passed ws_proposal_limited() before any of them was written, so one
    // that went over the limit is taken back here, before anything is sent.
    if (ws_proposal_count($agent, (int) $viewer['id']) > WS_PROPOSALS_PER_HOUR) {
        db("DELETE FROM ws_ai_requests WHERE id = '" . $request_id . "'");

        return $fail(lang(array('string' => 'This application has made {var:1} proposals for this person in the last hour. Wait before proposing more.', 'vars' => WS_PROPOSALS_PER_HOUR)), true);
    }

    $message_id = 0;

    if ($channel) {
        $sent = ws_message_send($viewer, $channel, $text, array('app_id' => (int) $app_id));

        if (!$sent['ok']) {
            db("DELETE FROM ws_ai_requests WHERE id = '" . $request_id . "'");

            return $fail($sent['error']);
        }

        $message_id = (int) $sent['message_id'];

        db("UPDATE ws_ai_requests SET reply_message_id = '" . $message_id . "' WHERE id = '" . $request_id . "'");
    }

    ws_changes_store(array(
        'id'           => $request_id,
        'channel_id'   => $channel ? (int) $channel['id'] : 0,
        'task_id'      => $task ? (int) $task['id'] : 0,
        'requested_by' => (int) $viewer['id'],
    ), $message_id, $rows);

    if ($channel) {
        // The message went out before its cards were kept: the open screens
        // draw it again with them.
        ws_message_touch($message_id);
    } else {
        // On a task nothing is mentioned: the person is told on the bell, as
        // at the end of an assistant's run on it.
        ws_notify((int) $viewer['id'], 'task_assistant', array('task_id' => (int) $task['id'], 'actor_id' => 0));
    }

    return array('ok' => true, 'error' => '', 'limited' => false, 'request_id' => $request_id, 'message_id' => $message_id);
}
