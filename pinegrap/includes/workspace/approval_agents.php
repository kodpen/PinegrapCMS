<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - agents asked to approve. A request a person makes by hand may
 * ask the channel's active application agents and model assistants of the
 * registry, and Pinegrap AI, beside its people. An application agent answers
 * with its own key; a model assistant and Pinegrap AI answer in the site's
 * own run (ws_ai_step()) with the decide tool. Claude is not offered yet.
 * Each agent asked is one ws_approval_agents
 * row and one request in its queue (ws_ai_requests), made at once with the
 * approval told in its brief; it answers Approve or Reject with a note, and
 * its answer counts in the request's rule as a person's does
 * (ws_approval_outcome()).
 *
 * The bound: a flow's request (its approval box, a tool that asks first, a
 * member's change), a scheduled action's and an application's ask people
 * only, and no agent answers a request its own application wrote. A removed
 * or paused agent stays asked and counts as not answering.
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
 * Is the table there (2026.11.0, 11.0.210)?
 *
 * @return bool
 */
function ws_approval_agents_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_approvals_ready() && pg_schema_has('ws_approval_agents', 'request_id');
    }

    return $ready;
}

/**
 * May this request ask agents? Only one a person makes by hand: never a
 * flow's, a scheduled action's or an application's. Pure.
 *
 * @param bool $by_hand the panel's form, or the API in the person's own session
 * @param int  $app_id  the application asking (0 = the person)
 * @return bool
 */
function ws_approval_agents_allowed($by_hand, $app_id)
{
    return ($by_hand === true) && ((int) $app_id === 0);
}

/**
 * The agents a form or the API names, kept as their request values
 * (agent:<id>, model:<id>, ai), each once, in the order given; anything of
 * another shape is left out. Pure.
 *
 * @param mixed $given
 * @return string[]
 */
function ws_approval_agents_clean($given)
{
    $out = array();

    foreach ((array) $given as $value) {
        $value = strtolower(trim((string) $value));

        if ((preg_match('/^((agent|model):[1-9][0-9]{0,9}|ai)$/', $value) === 1) && !in_array($value, $out, true)) {
            $out[] = $value;
        }
    }

    return $out;
}

/**
 * The request value an agent asked answers under: agent:<id>, model:<id>,
 * or ai for Pinegrap AI (ws_approval_agent_ai_row()). Pure.
 *
 * @param array $agent ws_agents row, or Pinegrap AI's row
 * @return string '' for any other kind
 */
function ws_approval_agent_value($agent)
{
    return ((string) ($agent['kind'] ?? '') === 'ai') ? 'ai' : ws_agent_request_value($agent);
}

/**
 * Pinegrap AI as an agent asked: it has no registry row, so this one stands
 * for it, with the application it writes through.
 *
 * @return array id, kind, handle, name, app_id, status, avatar
 */
function ws_approval_agent_ai_row()
{
    return array(
        'id'     => 0,
        'kind'   => 'ai',
        'handle' => '',
        'name'   => 'Pinegrap AI',
        'app_id' => function_exists('ws_ai_config') ? (int) ws_ai_config()['app_id'] : 0,
        'status' => 'active',
        'avatar' => '',
    );
}

/**
 * May an agent answer this request? Not one its own application wrote: an
 * agent never approves what it asked for. Pure.
 *
 * @param int $agent_app_id   the agent's application (0 for a model agent)
 * @param int $message_app_id the application that wrote the request (0 = a person)
 * @return bool
 */
function ws_approval_agent_may_answer($agent_app_id, $message_app_id)
{
    return ((int) $agent_app_id === 0) || ((int) $agent_app_id !== (int) $message_app_id);
}

/**
 * The agents of a channel that may be asked: its active application agents
 * and model assistants the channel allows (a discussion: its own and its
 * channel's), then Pinegrap AI where it may be asked, as the form lists
 * them. An application agent answers with its own key, the others in the
 * site's run.
 *
 * @param array $channel
 * @return array[] value, id, kind, handle, name, app_id
 */
function ws_approval_agent_options($channel)
{
    $out = array();
    $rows = (function_exists('ws_agent_reach_rows') && function_exists('ws_agents_ready') && ws_agents_ready()) ? ws_agent_reach_rows($channel) : array();

    foreach ($rows as $row) {
        $value = ws_agent_request_value($row);
        $kind = (string) $row['kind'];

        if (($value === '') || !in_array($kind, array('app', 'model'), true) || ((string) ($row['status'] ?? 'active') !== 'active') || isset($out[$value])) {
            continue;
        }

        // A model assistant is never asked where a guest reads along.
        if (($kind === 'model') && function_exists('ws_model_channel_allowed') && !ws_model_channel_allowed($row, $channel)) {
            continue;
        }

        $out[$value] = array(
            'value'  => $value,
            'id'     => (int) $row['id'],
            'kind'   => (string) $row['kind'],
            'handle' => (string) $row['handle'],
            'name'   => (string) $row['name'],
            'app_id' => (int) $row['app_id'],
        );
    }

    if (function_exists('ws_ai_ready') && ws_ai_ready() && ws_ai_channel_allowed($channel)) {
        $ai = ws_approval_agent_ai_row();

        $out['ai'] = array('value' => 'ai', 'id' => 0, 'kind' => 'ai', 'handle' => '', 'name' => $ai['name'], 'app_id' => $ai['app_id']);
    }

    return array_values($out);
}

/**
 * The agents the form offers: value, @handle, name and picture, nothing a
 * reader of the channel may not see.
 *
 * @param array $channel
 * @return array[] value, handle, name, avatar
 */
function ws_approval_agent_choices($channel)
{
    if (!ws_approval_agents_ready()) {
        return array();
    }

    $out = array();

    foreach (ws_approval_agent_options($channel) as $option) {
        $row = ws_approval_agent_row($option['value']);
        $out[] = array(
            'value'  => $option['value'],
            'handle' => '@' . $option['handle'],
            'name'   => $option['name'],
            'avatar' => $row ? (string) $row['avatar'] : '',
        );
    }

    return $out;
}

/**
 * The agents asked by one request, in the order they answered, the ones
 * still to answer last.
 *
 * @param int $approval_id
 * @return array[] agent, request_id, decision, note, decided_at
 */
function ws_approval_agents_of($approval_id)
{
    if (!ws_approval_agents_ready()) {
        return array();
    }

    return (array) db_items("SELECT agent, request_id, decision, note, decided_at FROM ws_approval_agents
        WHERE approval_id = '" . (int) $approval_id . "' ORDER BY decided_at = 0, decided_at, agent");
}

/**
 * The registry row behind a request value, Pinegrap AI's for ai, or null.
 *
 * @param string $value agent:<id> | model:<id> | ai
 * @return array|null
 */
function ws_approval_agent_row($value)
{
    if ((string) $value === 'ai') {
        return ws_approval_agent_ai_row();
    }

    $parts = explode(':', (string) $value);

    if ((count($parts) !== 2) || !function_exists('ws_agent')) {
        return null;
    }

    $row = ws_agent((int) $parts[1]);

    return (is_array($row) && (ws_agent_request_value($row) === (string) $value)) ? $row : null;
}

/**
 * How an agent asked is named in the result and on the card: its @handle,
 * or the value when its row is gone.
 *
 * @param string $value
 * @return string
 */
function ws_approval_agent_label($value)
{
    $row = ws_approval_agent_row($value);

    if ($row && ((string) $row['handle'] === '')) {
        return (string) $row['name'];
    }

    return $row ? '@' . (string) $row['handle'] : (string) $value;
}

/**
 * What an agent asked to approve is told in its brief, in English as the
 * rest of the brief. Pure.
 *
 * @param array $approval id, title, rule, closes_at
 * @param bool  $tool     answered in the site's run with the decide tool,
 *                        not over the API
 * @return string[]
 */
function ws_approval_agent_brief_lines($approval, $tool = false)
{
    $closes = (int) ($approval['closes_at'] ?? 0);
    $lines = array();

    $lines[] = 'You were asked to approve: "' . (string) ($approval['title'] ?? '') . '" (approval request ' . (int) ($approval['id'] ?? 0) . ').';
    $lines[] = ((string) ($approval['rule'] ?? 'any') === 'all')
        ? 'Everybody asked has to approve it; one refusal settles it.'
        : 'The first approval settles it; one refusal settles it too.';

    if ($closes > 0) {
        $lines[] = 'It closes on ' . gmdate('Y-m-d H:i', $closes) . ' UTC.';
    }

    $lines[] = $tool
        ? 'Answer with the decide tool, decision approve or reject, and a note saying why. Do not answer it with a message.'
        : 'Answer with POST /workspace/approvals/' . (int) ($approval['id'] ?? 0) . '/decide, decision approve or reject, and a note saying why. Do not answer it with a message.';

    return $lines;
}

/**
 * The ai_state a request that asks an agent to approve carries. Pure.
 *
 * @param array $approval id, title, rule, closes_at
 * @return string JSON
 */
function ws_approval_agent_request_state($approval)
{
    return (string) json_encode(array('approval' => array(
        'id'        => (int) ($approval['id'] ?? 0),
        'title'     => (string) ($approval['title'] ?? ''),
        'rule'      => (string) ($approval['rule'] ?? 'any'),
        'closes_at' => (int) ($approval['closes_at'] ?? 0),
    )), JSON_UNESCAPED_UNICODE);
}

/**
 * The approval a request asks its agent about, or null. Pure.
 *
 * @param array $row ws_ai_requests: ai_state
 * @return array|null
 */
function ws_approval_agent_request_of($row)
{
    $state = json_decode((string) ($row['ai_state'] ?? ''), true);

    return (is_array($state) && isset($state['approval']) && is_array($state['approval'])) ? $state['approval'] : null;
}

/**
 * Asks the agents of a new request: one row each, and a request in its
 * queue made at once under the brakes, hung on the request's message.
 *
 * @param array    $viewer
 * @param array    $channel
 * @param array    $approval the ws_approvals row
 * @param string[] $agents   ws_approval_agents_clean() values, checked already
 */
function ws_approval_agents_ask($viewer, $channel, $approval, $agents)
{
    if (empty($agents) || !ws_approval_agents_ready()) {
        return;
    }

    $state = ws_approval_agent_request_state($approval);
    $message = ws_message($approval['message_id']);
    $body = $message ? ws_tokens_normalise((string) $message['body']) : '';

    foreach ($agents as $value) {
        db("INSERT IGNORE INTO ws_approval_agents (approval_id, agent) VALUES ('" . (int) $approval['id'] . "', '" . e($value) . "')");

        // Followed mode: the agent is asked by name, whatever its wake rule.
        // Pinegrap AI has a queue of its own; its request is given the
        // approval once made.
        if ($value === 'ai') {
            $made = ws_ai_after_send($viewer, $channel, (int) $approval['message_id'], $body, true) ? array('ai') : array();

            if (!empty($made)) {
                db("UPDATE ws_ai_requests SET ai_state = '" . e($state) . "'
                    WHERE agent = 'ai' AND message_id = '" . (int) $approval['message_id'] . "' AND status = 'queued' ORDER BY id DESC LIMIT 1");
            }
        } else {
            $made = ws_agent_after_send($viewer, $channel, (int) $approval['message_id'], $body, $value, false, array('state' => $state));
        }

        if (in_array($value, $made, true)) {
            $request_id = (int) db_value("SELECT MAX(id) FROM ws_ai_requests
                WHERE agent = '" . e($value) . "' AND message_id = '" . (int) $approval['message_id'] . "'");

            db("UPDATE ws_approval_agents SET request_id = '" . $request_id . "'
                WHERE approval_id = '" . (int) $approval['id'] . "' AND agent = '" . e($value) . "'");
        }
    }
}

/**
 * Whether an agent may give this answer: the checks of
 * ws_approval_agent_decide(), writing nothing, so that a dry run meets the
 * same refusals.
 *
 * @param array       $agent    the ws_agents row
 * @param array       $approval
 * @param string      $decision approve | reject
 * @param string|null $note
 * @return array ok, error, expired, outcome
 */
function ws_approval_agent_decide_check($agent, $approval, $decision, $note = null)
{
    $fail = function ($error, $expired = false) {
        return array('ok' => false, 'error' => $error, 'expired' => $expired);
    };
    $value = ws_approval_agent_value($agent);

    if (!in_array($decision, array('approve', 'reject'), true)) {
        return $fail(lang('Invalid request.'));
    }

    if ((int) $approval['closed_at'] > 0) {
        return $fail(lang('The approval request is closed.'));
    }

    if (((int) $approval['closes_at'] > 0) && ((int) $approval['closes_at'] <= time())) {
        return $fail(lang('The approval request is closed.'), true);
    }

    $answer = ws_approval_agents_ready() ? db_item("SELECT decision FROM ws_approval_agents
        WHERE approval_id = '" . (int) $approval['id'] . "' AND agent = '" . e($value) . "'") : null;

    if (($value === '') || !is_array($answer)) {
        return $fail(lang('You were not asked to approve this.'));
    }

    if ((string) $answer['decision'] !== 'pending') {
        return $fail(lang('You have already answered.'));
    }

    $message = ws_message($approval['message_id']);

    if (!$message || ((int) $message['deleted_at'] > 0)) {
        return $fail(lang('That approval request could not be found.'));
    }

    if (function_exists('ws_message_in_past') && ws_message_in_past($message)) {
        return $fail(lang('This message is in an earlier version of the conversation, which is kept as it was.'));
    }

    if (!ws_approval_agent_may_answer((int) $agent['app_id'], ws_message_app_id($approval['message_id']))) {
        return $fail(lang('An agent cannot answer an approval request it asked for.'));
    }

    // Still active and still allowed in the channel.
    $channel = ws_channel($approval['channel_id']);
    $allowed = $channel ? array_column(ws_approval_agent_options($channel), 'value') : array();

    if (!in_array($value, $allowed, true)) {
        return $fail(lang('The agent may no longer answer in that channel.'));
    }

    // An approval box that asks for an answer gets it with the approval.
    if (($note !== null) && ($decision === 'approve')) {
        $asked = ws_approval_answers_asked(array((int) $approval['id']));
        $problem = ws_approval_answer_problem($asked[(int) $approval['id']]['answer'] ?? 'none', $decision, mb_substr((string) $note, 0, 255));

        if ($problem !== '') {
            return $fail($problem);
        }
    }

    $decisions = ws_approval_decisions($approval['id']);
    $decisions['agent:' . $value] = ($decision === 'approve') ? 'approved' : 'rejected';

    return array('ok' => true, 'error' => '', 'expired' => false,
        'outcome' => ws_approval_outcome($approval['rule'], array_values($decisions), time(), (int) $approval['closes_at']));
}

/**
 * One agent's answer: approve or reject, with a note. Its request in the
 * queue is closed with it.
 *
 * @param array  $agent    the ws_agents row
 * @param array  $approval
 * @param string $decision approve | reject
 * @param string $note
 * @return array ok, error
 */
function ws_approval_agent_decide($agent, $approval, $decision, $note = '')
{
    $checked = ws_approval_agent_decide_check($agent, $approval, $decision, (string) $note);

    if (!$checked['ok']) {
        if ($checked['expired']) {
            ws_approval_evaluate($approval);
        }

        return array('ok' => false, 'error' => $checked['error']);
    }

    $value = ws_approval_agent_value($agent);
    $note = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $note)), 0, 255);
    $now = time();

    // Pending first, so an answer sent twice counts once.
    db("UPDATE ws_approval_agents SET decision = '" . (($decision === 'approve') ? 'approved' : 'rejected') . "',
            note = '" . e($note) . "', decided_at = '" . $now . "'
        WHERE approval_id = '" . (int) $approval['id'] . "' AND agent = '" . e($value) . "' AND decision = 'pending'");

    if (mysqli_affected_rows(db::$con) < 1) {
        return array('ok' => false, 'error' => lang('You have already answered.'));
    }

    $request_id = (int) db_value("SELECT request_id FROM ws_approval_agents
        WHERE approval_id = '" . (int) $approval['id'] . "' AND agent = '" . e($value) . "'");

    if ($request_id > 0) {
        db("UPDATE ws_ai_requests SET status = 'answered', answered_at = '" . $now . "',
                claimed_at = IF(claimed_at = 0, '" . $now . "', claimed_at), error = ''
            WHERE id = '" . $request_id . "' AND status IN ('queued', 'sent', 'running')");
    }

    ws_message_touch($approval['message_id']);
    ws_approval_evaluate($approval, 0);

    return array('ok' => true, 'error' => '');
}

/**
 * The agents asked, as the card lists them after the people.
 *
 * @param int[] $approval_ids
 * @return array approval id => array[] (the card's entry)
 */
function ws_approval_agents_card($approval_ids)
{
    $ids = array_values(array_filter(array_map('intval', (array) $approval_ids)));

    if (empty($ids) || !ws_approval_agents_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_approval_agents WHERE approval_id IN (" . implode(',', $ids) . ")
        ORDER BY decided_at = 0, decided_at, agent") as $row) {
        $agent = ws_approval_agent_row($row['agent']);

        $out[(int) $row['approval_id']][] = array(
            'id'       => 0,
            'agent'    => true,
            'value'    => (string) $row['agent'],
            'name'     => $agent ? (string) $agent['name'] : (string) $row['agent'],
            'handle'   => ($agent && ((string) $agent['handle'] !== '')) ? '@' . (string) $agent['handle'] : '',
            'icon'     => 'bi-robot',
            'avatar'   => $agent ? (string) $agent['avatar'] : '',
            'decision' => (string) $row['decision'],
            'note'     => (string) $row['note'],
            'time'     => ((int) $row['decided_at'] > 0) ? ws_time_label($row['decided_at']) : '',
        );
    }

    return $out;
}

/**
 * The agent a request in the site's own run answers as: Pinegrap AI or a
 * model assistant; null for any other request.
 *
 * @param array $row ws_ai_requests: agent
 * @return array|null
 */
function ws_approval_agent_run_agent($row)
{
    $value = (string) ($row['agent'] ?? '');

    if (($value !== 'ai') && (strpos($value, 'model:') !== 0)) {
        return null;
    }

    return ws_approval_agent_row($value);
}

/**
 * The tool the site's run answers an approval request with, in place of
 * answer: the decision and why. Pure.
 *
 * @return array an OpenAI-style function tool
 */
function ws_approval_agent_decide_tool()
{
    return array('type' => 'function', 'function' => array(
        'name'        => 'decide',
        'description' => 'Approve or reject the approval request you were asked, once, at the end. This is your whole answer: no message is written.',
        'parameters'  => array('type' => 'object', 'properties' => (object) array(
            'decision' => array('type' => 'string', 'enum' => array('approve', 'reject')),
            'note'     => array('type' => 'string', 'description' => 'Why, in a sentence, in the language of the request. When the request asks for an answer (a text or a number), the approval carries that answer here.'),
        ), 'required' => array('decision', 'note')),
    ));
}

/**
 * The decide tool of the site's run (ws_ai_step()): records the answer of
 * the agent the request was made for through ws_approval_agent_decide(),
 * which also closes the request.
 *
 * @param array $row  ws_ai_requests
 * @param array $args decision, note
 * @return string '' once recorded, else why not
 */
function ws_approval_agent_run_decide($row, $args)
{
    $asked = ws_approval_agent_request_of($row);
    $approval = $asked ? ws_approval((int) ($asked['id'] ?? 0)) : null;
    $agent = ws_approval_agent_run_agent($row);

    if (!$approval || !$agent) {
        return lang('That approval request could not be found.');
    }

    $result = ws_approval_agent_decide($agent, $approval, strtolower(trim((string) ($args['decision'] ?? ''))), (string) ($args['note'] ?? ''));

    return $result['ok'] ? '' : (string) $result['error'];
}
