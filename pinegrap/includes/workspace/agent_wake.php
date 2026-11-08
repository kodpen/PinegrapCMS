<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an agent's scheduled wakes: every N minutes, or once at a set
 * time, with a note. The schedule sits on the agent's row (wake_every,
 * wake_next_at, wake_note) and is set on the Agents card, with
 * "/wake @agent every 30m note" in a channel, or through the API by the agent
 * for itself. The general job (job.php, every minute) makes a wake request
 * when the time has come: a ws_ai_requests row with no message, the note in
 * its state, and knocks the agent's push door as a message's request does.
 *
 * A wake has no message to answer under. Its channel is the agent's only
 * channel when it has one; an agent in several channels is told them in its
 * brief and writes where it needs with workspace.messages.create.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest wait between two scheduled wakes, in minutes (the column holds a SMALLINT). */
define('WS_AGENT_WAKE_EVERY_MAX', 1440);

/** The longest note a wake carries. */
define('WS_AGENT_WAKE_NOTE_MAX', 250);

/** How many due wakes one tick of the general job makes. */
define('WS_AGENT_WAKE_BATCH', 20);

/**
 * An interval word in minutes: "30m", "45min", "20dk", "2h", "3sa", or a
 * number and its unit as two words ("30 minutes" is read by the caller
 * joining them). 0 when it is none. Pure.
 *
 * @param string $word
 * @return int
 */
function ws_agent_wake_interval($word)
{
    if (!preg_match('/^([0-9]{1,4})\s*(m|min|mins|minute|minutes|dk|dakika|h|hr|hour|hours|sa|saat)$/u', mb_strtolower(trim((string) $word), 'UTF-8'), $match)) {
        return 0;
    }

    $hours = in_array($match[2], array('h', 'hr', 'hour', 'hours', 'sa', 'saat'), true);

    return (int) $match[1] * ($hours ? 60 : 1);
}

/**
 * Reads what follows /wake: the agent (its @handle or its application's
 * tag), then every <interval>, at [day] <HH:MM>, off, or nothing (show the
 * schedule), then the note, quotes taken off. Pure: the clock and the day
 * are given.
 *
 *   @ci every 30m "look at the queue"     every 30 minutes
 *   @ci at 21:00 nightly check            once, today at 21:00 or tomorrow
 *   @ci at tomorrow 09:00                 once, on that day
 *   @ci off                               no schedule
 *   @ci                                   says the schedule
 *
 * @param string $text the line after the command's name
 * @param int    $now
 * @return array ok, error, handle, app_id, action (show | every | at | off),
 *               every (minutes), at (time), note
 */
function ws_agent_wake_parse($text, $now)
{
    $out = array('ok' => false, 'error' => '', 'handle' => '', 'app_id' => 0, 'action' => 'show', 'every' => 0, 'at' => 0, 'note' => '');
    $fail = function ($error) use ($out) {
        $out['error'] = $error;

        return $out;
    };

    $words = preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY);
    $first = (string) array_shift($words);

    if (preg_match('/^<@app:([0-9]{1,10})>$/', $first, $match)) {
        $out['app_id'] = (int) $match[1];
    } elseif (preg_match('/^@([a-z0-9][a-z0-9_-]{0,31})$/i', $first, $match)) {
        $out['handle'] = mb_strtolower($match[1], 'UTF-8');
    } else {
        return $fail(lang('Name the agent first: /wake @agent every 30m, at 21:00 or off.'));
    }

    $action = mb_strtolower((string) array_shift($words), 'UTF-8');

    if ($action === '') {
        $out['ok'] = true;
        return $out;
    }

    if (in_array($action, array('off', 'kapat', 'dur', 'stop'), true)) {
        $out['action'] = 'off';
        $out['ok'] = true;
        return $out;
    }

    if (in_array($action, array('every', 'her'), true)) {
        $word = (string) array_shift($words);
        $minutes = ws_agent_wake_interval($word);

        // "every 30 minutes": the number and its unit as two words.
        if (($minutes === 0) && ctype_digit($word) && isset($words[0])) {
            $minutes = ws_agent_wake_interval($word . $words[0]);

            if ($minutes > 0) {
                array_shift($words);
            }
        }

        if (($minutes < 1) || ($minutes > WS_AGENT_WAKE_EVERY_MAX)) {
            return $fail(lang('Say how often: every 30m, every 2h, from 1 minute to 24 hours.'));
        }

        $out['action'] = 'every';
        $out['every'] = $minutes;
    } elseif (in_array($action, array('at', 'saat'), true)) {
        $today = date('Y-m-d', (int) $now);
        $day = '';
        $word = (string) array_shift($words);

        if (ws_parse_time_word($word) === '') {
            $day = ws_parse_date_word($word, $today);
            $word = (string) array_shift($words);
        }

        $time = ws_parse_time_word($word);

        if ($time === '') {
            return $fail(lang('Say when: at 21:00, or at tomorrow 09:00.'));
        }

        $at = strtotime((($day !== '') ? $day : $today) . ' ' . $time . ':00');

        // A time already gone today means tomorrow; a day named and gone is a mistake.
        if (($day === '') && ($at <= (int) $now)) {
            $at = strtotime('+1 day', $at);
        }

        if ($at <= (int) $now) {
            return $fail(lang('That time has passed. Choose a time to come.'));
        }

        $out['action'] = 'at';
        $out['at'] = (int) $at;
    } else {
        return $fail(lang('After the agent: every 30m, at 21:00, or off.'));
    }

    $note = trim(implode(' ', $words));

    if (preg_match('/^["“”«](.*)["“”»]$/su', $note, $match)) {
        $note = trim($match[1]);
    }

    if (mb_strlen($note) > WS_AGENT_WAKE_NOTE_MAX) {
        return $fail(lang('The wake note is at most 250 characters.'));
    }

    $out['note'] = $note;
    $out['ok'] = true;

    return $out;
}

/**
 * The schedule columns a /wake line or the API writes: every N minutes
 * starts N minutes from now; at is once, at that time; off clears all.
 * Pure.
 *
 * @param array $parsed ws_agent_wake_parse() (action every | at | off)
 * @param int   $now
 * @return array wake_every, wake_next_at, wake_note
 */
function ws_agent_wake_values($parsed, $now)
{
    switch ((string) ($parsed['action'] ?? '')) {
        case 'every':
            return array('wake_every' => (int) $parsed['every'], 'wake_next_at' => (int) $now + 60 * (int) $parsed['every'], 'wake_note' => (string) $parsed['note']);

        case 'at':
            return array('wake_every' => 0, 'wake_next_at' => (int) $parsed['at'], 'wake_note' => (string) $parsed['note']);
    }

    return array('wake_every' => 0, 'wake_next_at' => 0, 'wake_note' => '');
}

/**
 * The schedule after a wake was made: every N minutes moves on by N minutes
 * from the time it was due (from now when the job was away longer than
 * that, so a missed hour makes one wake, not sixty); a one-time wake is
 * over, its note with it. Pure.
 *
 * @param array $agent wake_every, wake_next_at, wake_note
 * @param int   $now
 * @return array wake_every, wake_next_at, wake_note
 */
function ws_agent_wake_after($agent, $now)
{
    $every = (int) ($agent['wake_every'] ?? 0);

    if ($every <= 0) {
        return array('wake_every' => 0, 'wake_next_at' => 0, 'wake_note' => '');
    }

    $next = (int) ($agent['wake_next_at'] ?? 0) + 60 * $every;

    if ($next <= (int) $now) {
        $next = (int) $now + 60 * $every;
    }

    return array('wake_every' => $every, 'wake_next_at' => $next, 'wake_note' => (string) ($agent['wake_note'] ?? ''));
}

/**
 * The channel a wake request is made in: the agent's only channel, or none
 * when it has several or none. Pure.
 *
 * @param int[] $channel_ids the channels the agent is allowed in
 * @return int
 */
function ws_agent_wake_channel($channel_ids)
{
    $ids = array_values(array_unique(array_map('intval', (array) $channel_ids)));

    return (count($ids) === 1) ? $ids[0] : 0;
}

/**
 * The state a wake request carries in ws_ai_requests.ai_state. Pure.
 *
 * @param string $kind every | at
 * @param string $note
 * @param int    $due  the time it was due
 * @return string JSON
 */
function ws_agent_wake_state($kind, $note, $due)
{
    return (string) json_encode(array('wake' => array('kind' => (string) $kind, 'note' => (string) $note, 'due' => (int) $due)), JSON_UNESCAPED_UNICODE);
}

/**
 * The wake a request was made for, or null for a request a message made.
 * Pure.
 *
 * @param array $row ws_ai_requests
 * @return array|null kind, note, due
 */
function ws_agent_wake_of($row)
{
    if (((int) ($row['message_id'] ?? 0) > 0) || (strpos((string) ($row['agent'] ?? ''), 'app:') !== 0)) {
        return null;
    }

    $state = json_decode((string) ($row['ai_state'] ?? ''), true);

    if (!is_array($state) || !isset($state['wake']) || !is_array($state['wake'])) {
        return null;
    }

    return array(
        'kind' => (string) ($state['wake']['kind'] ?? ''),
        'note' => (string) ($state['wake']['note'] ?? ''),
        'due'  => (int) ($state['wake']['due'] ?? 0),
    );
}

/**
 * An agent's schedule in one sentence, for the channel and the card.
 *
 * @param array $agent wake_every, wake_next_at, wake_note, handle
 * @return string
 */
function ws_agent_wake_sentence($agent)
{
    $handle = (string) ($agent['handle'] ?? '');
    $every = (int) ($agent['wake_every'] ?? 0);
    $next = (int) ($agent['wake_next_at'] ?? 0);
    $note = trim((string) ($agent['wake_note'] ?? ''));

    if ($next <= 0) {
        return lang(array('string' => '@{var:1} has no scheduled wake.', 'vars' => $handle));
    }

    $when = date('Y-m-d H:i', $next);
    $text = ($every > 0)
        ? lang(array('string' => '@{var:1} is woken every {var:2} minutes, next at {var:3}.', 'vars' => array($handle, $every, $when)))
        : lang(array('string' => '@{var:1} is woken once, at {var:2}.', 'vars' => array($handle, $when)));

    return ($note !== '') ? $text . ' ' . lang(array('string' => 'Note: {var:1}', 'vars' => $note)) : $text;
}

/* ---------------------------------------------------------------------------
   Setting a schedule
   --------------------------------------------------------------------------- */

/**
 * The app agent a /wake line names, by its handle or its application's tag.
 *
 * @param array $parsed ws_agent_wake_parse()
 * @return array|null the raw ws_agents row
 */
function ws_agent_wake_target($parsed)
{
    if (!ws_agents_ready()) {
        return null;
    }

    $where = ((int) ($parsed['app_id'] ?? 0) > 0)
        ? "app_id = '" . (int) $parsed['app_id'] . "'"
        : "handle = '" . e((string) ($parsed['handle'] ?? '')) . "'";
    $row = db_item("SELECT * FROM ws_agents WHERE kind = 'app' AND " . $where . " ORDER BY id LIMIT 1");

    return $row ? (array) $row : null;
}

/**
 * What stands in the way of a person setting an agent's schedule from a
 * channel: '' when nothing. They must be able to post there (a member, for a
 * private channel) and the agent must be allowed in it, as for adding it.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $agent the raw ws_agents row
 * @return string the sentence to show
 */
function ws_agent_wake_problem($viewer, $channel, $agent)
{
    if (ws_channel_agent_add_problem($viewer, $channel) !== '') {
        return lang('Only someone who may write in this channel can set an agent\'s wakes here.');
    }

    $row = ws_channel_agent_row((int) $channel['id'], (int) $agent['id']);

    if (!$row || ((int) $row['allowed'] !== 1)) {
        return lang(array('string' => '@{var:1} is not one of this channel\'s agents.', 'vars' => (string) $agent['handle']));
    }

    if ((string) $agent['status'] !== 'active') {
        return lang(array('string' => '@{var:1} is paused on the Agents card.', 'vars' => (string) $agent['handle']));
    }

    return '';
}

/**
 * Writes an agent's schedule.
 *
 * @param int   $agent_id
 * @param array $values ws_agent_wake_values()
 */
function ws_agent_wake_save($agent_id, $values)
{
    db("UPDATE ws_agents SET wake_every = '" . (int) $values['wake_every'] . "', wake_next_at = '" . (int) $values['wake_next_at'] . "',
            wake_note = '" . e(mb_substr((string) $values['wake_note'], 0, WS_AGENT_WAKE_NOTE_MAX)) . "', updated_at = '" . time() . "'
        WHERE id = '" . (int) $agent_id . "'");
}

/**
 * /wake in a channel: says an agent's schedule, sets it or clears it. A
 * change leaves a system line naming who made it, so the channel keeps the
 * trail.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text the line after the command's name
 * @return array a ws_command_run() result
 */
function ws_command_wake($viewer, $channel, $text)
{
    if (!ws_agents_ready()) {
        return array('type' => 'error', 'error' => lang('Agents are not set up on this site yet.'));
    }

    $parsed = ws_agent_wake_parse($text, time());

    if (!$parsed['ok']) {
        return array('type' => 'error', 'error' => $parsed['error']);
    }

    $agent = ws_agent_wake_target($parsed);

    if (!$agent) {
        return array('type' => 'error', 'error' => lang('No application agent has that handle.'));
    }

    $problem = ws_agent_wake_problem($viewer, $channel, $agent);

    if ($problem !== '') {
        return array('type' => 'error', 'error' => $problem);
    }

    if ($parsed['action'] === 'show') {
        return array('type' => 'ephemeral', 'html' => h(ws_agent_wake_sentence($agent)));
    }

    $values = ws_agent_wake_values($parsed, time());
    ws_agent_wake_save((int) $agent['id'], $values);

    $id = ws_message_system((int) $channel['id'], lang(array(
        'string' => '{var:1} set the wakes: {var:2}',
        'vars'   => array('<@user:' . (int) $viewer['id'] . '>', ws_agent_wake_sentence(array('handle' => $agent['handle']) + $values)),
    )));

    return array('type' => 'done', 'message_id' => $id);
}

/* ---------------------------------------------------------------------------
   Making the wakes
   --------------------------------------------------------------------------- */

/**
 * Is a scheduled wake due? One small read, for the general job's tick.
 *
 * @return bool
 */
function ws_agent_wakes_due()
{
    return ws_agents_ready() && ws_ai_schema_ready()
        && (bool) db_value("SELECT id FROM ws_agents
            WHERE kind = 'app' AND status = 'active' AND wake_next_at > 0 AND wake_next_at <= '" . time() . "' LIMIT 1");
}

/**
 * Makes the wake requests whose time has come: each agent's schedule is
 * moved on first, by a conditional UPDATE, so two ticks that overlap make
 * one request. The hourly brake holds for wakes as for messages.
 *
 * @param int|null $now
 * @return int the requests made
 */
function ws_agent_wakes_run($now = null)
{
    if (!ws_agents_ready() || !ws_ai_schema_ready()) {
        return 0;
    }

    $now = ($now === null) ? time() : (int) $now;
    $made = 0;

    foreach ((array) db_items("SELECT * FROM ws_agents
        WHERE kind = 'app' AND status = 'active' AND wake_next_at > 0 AND wake_next_at <= '" . $now . "'
        ORDER BY wake_next_at LIMIT " . WS_AGENT_WAKE_BATCH) as $agent) {
        $after = ws_agent_wake_after($agent, $now);

        db("UPDATE ws_agents SET wake_next_at = '" . (int) $after['wake_next_at'] . "', wake_note = '" . e($after['wake_note']) . "'
            WHERE id = '" . (int) $agent['id'] . "' AND wake_next_at = '" . (int) $agent['wake_next_at'] . "'");

        if (mysqli_affected_rows(db::$con) < 1) {
            continue;
        }

        if (ws_agent_wake_request($agent, $now) > 0) {
            $made++;
        }
    }

    return $made;
}

/**
 * One wake request for an agent whose schedule is due, and a knock on its
 * push door. requested_by is whoever registered the agent: no person asked.
 *
 * @param array $agent the raw ws_agents row, before its schedule moved on
 * @param int   $now
 * @return int the request's id, 0 when none was made
 */
function ws_agent_wake_request($agent, $now)
{
    $value = ws_agent_request_value($agent);

    if ($value === '') {
        return 0;
    }

    $last_hour = (int) db_value("SELECT COUNT(*) FROM ws_ai_requests WHERE agent = '" . e($value) . "' AND created_at > '" . ((int) $now - 3600) . "'");

    if (ws_agent_brake(0, $last_hour) !== '') {
        return 0;
    }

    $channels = array_map(function ($row) {
        return (int) $row['channel_id'];
    }, (array) db_items("SELECT channel_id FROM ws_channel_agents WHERE agent_id = '" . (int) $agent['id'] . "' AND allowed = 1 LIMIT 2"));
    $state = ws_agent_wake_state(((int) $agent['wake_every'] > 0) ? 'every' : 'at', (string) $agent['wake_note'], (int) $agent['wake_next_at']);

    list($pv_column, $pv_value) = pg_ai_prompt_version_insert();

    db("INSERT INTO ws_ai_requests (channel_id, message_id, requested_by, status, agent, ai_state, created_at" . $pv_column . ")
        VALUES ('" . ws_agent_wake_channel($channels) . "', 0, '" . (int) $agent['created_by'] . "', 'queued', '" . e($value) . "', '" . e($state) . "', '" . (int) $now . "'" . $pv_value . ")");

    $request_id = (int) mysqli_insert_id(db::$con);

    if (($request_id > 0) && ((string) $agent['door_kind'] === 'push')) {
        ws_agent_knock($agent, $request_id);
    }

    return $request_id;
}
