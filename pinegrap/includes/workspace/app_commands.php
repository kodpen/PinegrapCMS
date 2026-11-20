<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the / commands an application registers for itself (for
 * example /summarize or /report), offered in the channel beside the built-in
 * ones (includes/workspace/commands.php, ws_commands()).
 *
 * A registered name is one word of letters, the shape ws_command_attempt()
 * reads as a command, so the composer sees it as one; it never takes a name a
 * built-in command or an assistant answers to, so a / word means one thing.
 * Names are the site's (ws_app_commands.name is UNIQUE): the first
 * application to register one keeps it.
 *
 * A typed app command is never posted. It goes only to the registering
 * application's own webhook subscriptions (workspace.app.command), and only
 * where the application may read the channel (ws_app_reads_channel(), as
 * workspace.app.mentioned); the application answers with
 * POST /workspace/channels/{id}/messages and the writer sees "Sent to <app>",
 * or answers the writer alone with POST /workspace/commands/answer and the
 * event's answer_token (app_answers.php).
 * An application that is disabled, expired, without workspace:commands or
 * unable to read the channel leaves its command answered as unknown.
 *
 * An application with an agent waiting on its request queue (an active app
 * agent working through it, agents.php): its commands are offered and run
 * only where that agent is allowed. Typed there, the line is posted under
 * the writer's name and one request is made for the agent on that message,
 * as a mention makes it (ws_agent_after_send(), its brakes), the command and
 * its values carried to the brief; the event is sent as for any command.
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
 * The longest description and usage line an application may register.
 */
const WS_APP_COMMAND_TEXT_MAX = 200;

/**
 * How many commands one application may register.
 */
const WS_APP_COMMANDS_MAX = 20;

/**
 * How many arguments one command's definition may hold.
 */
const WS_APP_COMMAND_ARGS_MAX = 8;

/**
 * The longest label of an argument, shown in the strip.
 */
const WS_APP_COMMAND_ARG_LABEL_MAX = 40;

/**
 * Checks one command an application wants to register and gives it back in
 * the shape it is kept: the name in Turkish lower case without its slash,
 * the texts trimmed with their line breaks folded into spaces.
 *
 * @param mixed $input name, description, optional usage (the arguments after
 *                     the name, as /help shows them)
 * @return array name, description, usage; or error (name_invalid,
 *               name_taken, description_required, description_too_long,
 *               usage_too_long) and field
 */
function ws_app_command_check($input)
{
    $input = is_array($input) ? $input : array();
    $typed = isset($input['name']) && is_string($input['name']) ? ltrim(trim($input['name']), '/') : '';
    $name = ws_lower($typed);

    if (!preg_match('/^\p{Ll}{2,20}$/u', $name)) {
        return array('error' => 'name_invalid', 'field' => 'name');
    }

    if (ws_command_shadowed($typed)) {
        return array('error' => 'name_taken', 'field' => 'name');
    }

    $description = ws_app_command_text(isset($input['description']) ? $input['description'] : '');

    if ($description === '') {
        return array('error' => 'description_required', 'field' => 'description');
    }

    if (mb_strlen($description, 'UTF-8') > WS_APP_COMMAND_TEXT_MAX) {
        return array('error' => 'description_too_long', 'field' => 'description');
    }

    $usage = ws_app_command_text(isset($input['usage']) ? $input['usage'] : '');

    if (mb_strlen($usage, 'UTF-8') > WS_APP_COMMAND_TEXT_MAX) {
        return array('error' => 'usage_too_long', 'field' => 'usage');
    }

    return array('name' => $name, 'description' => $description, 'usage' => $usage);
}

/**
 * One line of registered text: anything but a string is empty, control
 * characters and line breaks become single spaces.
 *
 * @param mixed $text
 * @return string
 */
function ws_app_command_text($text)
{
    if (!is_string($text)) {
        return '';
    }

    return trim((string) preg_replace('/[\p{Cc}\s]+/u', ' ', $text));
}

/**
 * Whether an application's commands are offered: its key active, not
 * expired, and granted workspace:commands. Its owner's reach is asked apart
 * (ws_app_commands_live(), ws_app_command_send()).
 *
 * @param array    $app api_apps row: status, expires_at, scopes (JSON)
 * @param int|null $now
 * @return bool
 */
function ws_app_command_app_ok($app, $now = null)
{
    if ((string) ($app['status'] ?? '') !== 'active') {
        return false;
    }

    $expires = (int) ($app['expires_at'] ?? 0);

    if (($expires > 0) && ($expires < (($now === null) ? time() : (int) $now))) {
        return false;
    }

    $scopes = json_decode((string) ($app['scopes'] ?? ''), true);

    return is_array($scopes) && in_array('workspace:commands', $scopes, true);
}

/**
 * Whether the table is there: the files land before the upgrade runs.
 *
 * @return bool
 */
function ws_app_commands_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('ws_app_commands');
}

/**
 * The registered commands with their application, by name.
 *
 * @param int $app_id only this application's; 0 for all
 * @return array[] id, app_id, name, description, usage_text, args (the
 *                 stored definition, JSON or null), created_at,
 *                 updated_at, app_name, status, expires_at, owner_user_id,
 *                 scopes
 */
function ws_app_commands_rows($app_id = 0)
{
    if (!ws_app_commands_ready()) {
        return array();
    }

    // The files land before the upgrade adds the column (11.0.246).
    $args = pg_schema_has('ws_app_commands', 'args') ? 'ws_app_commands.args' : 'NULL AS args';

    return db_items("SELECT ws_app_commands.id, ws_app_commands.app_id, ws_app_commands.name, ws_app_commands.description,
            ws_app_commands.usage_text, " . $args . ", ws_app_commands.created_at, ws_app_commands.updated_at,
            api_apps.name AS app_name, api_apps.status, api_apps.expires_at, api_apps.owner_user_id, api_apps.scopes
        FROM ws_app_commands
        INNER JOIN api_apps ON api_apps.id = ws_app_commands.app_id"
        . (((int) $app_id > 0) ? " WHERE ws_app_commands.app_id = '" . (int) $app_id . "'" : '')
        . " ORDER BY ws_app_commands.name");
}

/**
 * The commands offered in the channels: of applications whose key works
 * (ws_app_command_app_ok()) and whose owner may still hand on
 * workspace:commands (ws_owner_scopes()). Read once a request.
 *
 * @return array[] ws_app_commands_rows() rows
 */
function ws_app_commands_live()
{
    static $live = null;

    if ($live !== null) {
        return $live;
    }

    $live = array();
    $owners = array();

    foreach (ws_app_commands_rows() as $row) {
        if (!ws_app_command_app_ok($row)) {
            continue;
        }

        $owner_id = (int) $row['owner_user_id'];

        if (!isset($owners[$owner_id])) {
            $owner = ($owner_id > 0) ? pg_load_user_row($owner_id) : null;
            // The owner ceiling (ws_owner_scopes()) holds workspace:commands
            // for a member of the team; asked here without the API's files.
            $owners[$owner_id] = is_array($owner) && !empty(ws_rights($owner)['member']);
        }

        if ($owners[$owner_id]) {
            $live[] = $row;
        }
    }

    return $live;
}

/**
 * A registered command as a ws_commands() entry: its arguments when it has a
 * definition (free text after the name when not), its usage line written
 * from them when none is kept, no live line, the application's name beside
 * it.
 *
 * @param array $row ws_app_commands_rows() row
 * @return array
 */
function ws_app_command_entry($row)
{
    $name = '/' . (string) $row['name'];
    $usage = trim((string) $row['usage_text']);
    $args = ws_app_command_args_read($row['args'] ?? null);

    // A change that sent no usage keeps the definition but stores no line
    // (ws_app_command_save_check() writes one only beside new arguments).
    if (($usage === '') && !empty($args)) {
        $usage = ws_app_command_args_usage($args);
    }

    return array(
        'key'         => 'app:' . (string) $row['name'],
        'names'       => array($name),
        'name'        => $name,
        'label'       => $name . ' · ' . (string) $row['app_name'],
        'description' => (string) $row['description'],
        'usage'       => $name . (($usage !== '') ? ' ' . $usage : ''),
        'example'     => $name . (($usage !== '') ? ' ' . $usage : ''),
        'args'        => $args,
        'live'        => false,
        'app_id'      => (int) $row['app_id'],
        'app'         => (string) $row['app_name'],
        'shadowed'    => false,
    );
}

/**
 * The live command a typed word names, matched with Turkish letters folded
 * (ws_command_fold()) as the table's collation compares them.
 *
 * @param string $word without its slash
 * @param array[]|null $rows ws_app_commands_live() unless given
 * @return array|null the row
 */
function ws_app_command_find($word, $rows = null)
{
    $folded = ws_command_fold($word);

    if ($folded === '') {
        return null;
    }

    foreach (($rows === null) ? ws_app_commands_live() : $rows as $row) {
        if (ws_command_fold($row['name']) === $folded) {
            return $row;
        }
    }

    return null;
}

/**
 * What workspace.app.command tells the application: the command as typed,
 * where, by whom, the words after it as the owner reads them (text) and as
 * stored (body). Ids are ints, 0 for none.
 *
 * @param array      $channel the channel or discussion it was typed in
 * @param array|null $thread  ws_thread() of a discussion
 * @param int        $app_id  the registering application
 * @param string     $name    the command's registered name
 * @param int        $user_id the writer
 * @param string     $text
 * @param string     $body
 * @param int        $parent_id the message it answers, 0 for none
 * @param array|null $values    the arguments by name (ws_app_command_values());
 *                              null for a command without a definition, whose
 *                              payload has no values key
 * @param string     $answer_token the writer-only answer's token
 *                              (ws_app_answer_token()); '' before the upgrade,
 *                              whose payload has no answer_token key
 * @return array
 */
function ws_app_command_payload($channel, $thread, $app_id, $name, $user_id, $text, $body, $parent_id = 0, $values = null, $answer_token = '')
{
    $payload = array(
        'command'      => '/' . (string) $name,
        'app_id'       => (int) $app_id,
        'channel_id'   => (int) $channel['id'],
        'channel'      => (string) $channel['name'],
        'channel_kind' => (string) $channel['kind'],
        'thread'       => is_array($thread) ? array('channel_id' => (int) $thread['parent_channel_id'], 'message_id' => (int) $thread['message_id']) : null,
        'parent_id'    => (int) $parent_id,
        'user_id'      => (int) $user_id,
        'text'         => (string) $text,
        'body'         => (string) $body,
    );

    if (is_array($values)) {
        $payload['values'] = $values;
    }

    if ((string) $answer_token !== '') {
        $payload['answer_token'] = (string) $answer_token;
    }

    return $payload;
}

/**
 * Sends a typed app command to its application (workspace.app.command).
 *
 * @param array  $viewer  the writer
 * @param array  $channel
 * @param array  $row     ws_app_command_find() row
 * @param string $rest    the words after the command, as stored
 * @param int    $parent_id
 * @return array|null the writer's answer (sent, or which required argument
 *                    could not be read and nothing was sent; for an agent's
 *                    command the posted line, ws_app_command_agent_send());
 *                    null when the application may not read the channel or
 *                    its agent is not allowed there, and nothing was sent
 */
function ws_app_command_send($viewer, $channel, $row, $rest, $parent_id = 0)
{
    $owner = pg_load_user_row((int) $row['owner_user_id']);
    $reader = is_array($owner) ? ws_viewer($owner) : null;

    if (!ws_app_reads_channel($reader, $channel) || !function_exists('pg_announce')) {
        return null;
    }

    // An agent's command runs only where the agent is allowed.
    $agent = ws_app_command_agent_of(ws_app_command_agents(), (int) $row['app_id']);
    $route = ws_app_command_route($agent, ($agent !== null) ? ws_agent_reach_rows($channel) : array());

    if ($route === 'none') {
        return null;
    }

    $args = ws_app_command_args_read($row['args'] ?? null);
    $values = null;

    if (!empty($args)) {
        $read = ws_app_command_values($args, (string) $rest);

        if ($read['missing'] !== '') {
            return array(
                'type' => 'ephemeral',
                'html' => '<div>' . h(lang(array('string' => '/{var:1} needs {var:2}. Nothing was sent to the application.', 'vars' => array((string) $row['name'], $read['missing'])))) . '</div>',
                'app_command' => '/' . (string) $row['name'],
            );
        }

        $values = $read['values'];
    }

    $thread = ws_channel_is_thread($channel) ? ws_thread($channel['id']) : null;
    $announce = function () use ($channel, $thread, $row, $viewer, $reader, $rest, $parent_id, $values) {
        $token = function_exists('ws_app_answer_token_for') ? ws_app_answer_token_for((int) $viewer['id'], (int) $channel['id'], (int) $row['app_id'], (string) $row['name']) : '';
        pg_announce('workspace.app.command', ws_app_command_payload($channel, $thread, (int) $row['app_id'], $row['name'], (int) $viewer['id'], ws_plain_text($reader, $rest), (string) $rest, $parent_id, $values, $token));
    };

    if ($agent !== null) {
        return ws_app_command_agent_send($viewer, $channel, $row, $agent, $rest, $values, $parent_id, array('announce' => $announce));
    }

    $announce();

    return array(
        'type' => 'ephemeral',
        'html' => '<div>' . h(lang(array('string' => 'Sent to the application {var:1}.', 'vars' => (string) $row['app_name']))) . '</div>',
        'app_command' => '/' . (string) $row['name'],
    );
}

/**
 * The agent an application's commands belong to: the first active app agent
 * (in id order) working through it, as an application's key resolves to one
 * agent (ws_agent_apps_taken()). Pure.
 *
 * @param array[] $agents ws_agents rows: id, kind, status, app_id
 * @param int     $app_id
 * @return array|null the agent row
 */
function ws_app_command_agent_of($agents, $app_id)
{
    $found = null;

    foreach ((array) $agents as $agent) {
        if (((string) ($agent['kind'] ?? '') !== 'app') || ((string) ($agent['status'] ?? '') !== 'active')
            || ((int) ($agent['app_id'] ?? 0) !== (int) $app_id) || ((int) $app_id <= 0)) {
            continue;
        }

        if (($found === null) || ((int) $agent['id'] < (int) $found['id'])) {
            $found = $agent;
        }
    }

    return $found;
}

/**
 * Where an application's command goes in a channel. Pure.
 *
 * @param array|null $agent      ws_app_command_agent_of()
 * @param array[]    $reach_rows ws_agent_reach_rows() of the channel: the
 *                               allowed rows of the agents it reaches
 * @return string app (no agent: the application alone hears it), agent (its
 *                agent is allowed here: the line is posted and the agent
 *                asked), none (its agent is not allowed here: not offered,
 *                answered as unknown)
 */
function ws_app_command_route($agent, $reach_rows)
{
    if ($agent === null) {
        return 'app';
    }

    foreach ((array) $reach_rows as $row) {
        if ((int) $row['id'] === (int) $agent['id']) {
            return 'agent';
        }
    }

    return 'none';
}

/**
 * The active app agents with the channels that allow them, read once a
 * request.
 *
 * @return array[] id, kind, status, app_id, handle, channels (ids of the
 *                 channels and discussions whose row allows it)
 */
function ws_app_command_agents()
{
    static $agents = null;

    if ($agents !== null) {
        return $agents;
    }

    $agents = array();

    if (!function_exists('ws_agents_ready') || !ws_agents_ready()) {
        return $agents;
    }

    foreach ((array) db_items("SELECT id, kind, status, app_id, handle FROM ws_agents
        WHERE kind = 'app' AND status = 'active' AND app_id > 0 ORDER BY id") as $agent) {
        $agent['channels'] = array_map('intval', (array) db_values("SELECT channel_id FROM ws_channel_agents
            WHERE agent_id = '" . (int) $agent['id'] . "' AND allowed = 1"));
        $agents[] = $agent;
    }

    return $agents;
}

/**
 * An application command's ws_commands() entry with the channels its agent
 * is allowed in (agent_channels), for the composer's "/" list; null when
 * the application has no agent and the command is offered wherever the
 * application reads. A discussion reaches its channel's agents, so the list
 * asks for the discussion's own id and its channel's. Pure.
 *
 * @param array   $entry  ws_app_command_entry()
 * @param array[] $agents ws_app_command_agents()
 * @return array
 */
function ws_app_command_entry_reach($entry, $agents)
{
    $agent = ws_app_command_agent_of($agents, (int) ($entry['app_id'] ?? 0));
    $entry['agent_channels'] = ($agent === null) ? null : array_values(array_map('intval', (array) ($agent['channels'] ?? array())));

    return $entry;
}

/**
 * The ai_state a request made by an agent's command carries: the command's
 * name and its values by name (null for a command without a definition).
 * Pure.
 *
 * @param string     $name without its slash
 * @param array|null $values ws_app_command_values()
 * @return string JSON
 */
function ws_app_command_request_state($name, $values)
{
    return (string) json_encode(array('command' => array('name' => '/' . (string) $name, 'values' => is_array($values) ? $values : null)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * The command a request was made for, or null for any other request. Pure.
 *
 * @param array $row ws_ai_requests: ai_state
 * @return array|null name, values
 */
function ws_app_command_request_of($row)
{
    $state = json_decode((string) ($row['ai_state'] ?? ''), true);

    if (!is_array($state) || !isset($state['command']['name']) || !is_string($state['command']['name'])) {
        return null;
    }

    return array('name' => $state['command']['name'], 'values' => is_array($state['command']['values'] ?? null) ? $state['command']['values'] : null);
}

/**
 * What an agent's brief says of the command it was asked with. Pure.
 *
 * @param array|null $command ws_app_command_request_of()
 * @return string[]
 */
function ws_app_command_brief_lines($command)
{
    if ($command === null) {
        return array();
    }

    $lines = array('It was typed as your application\'s command ' . $command['name'] . '. Do what the command is for.');

    if ($command['values'] !== null) {
        $lines[] = 'Its values by name, as workspace.app.command sends them:';
        $lines[] = pg_ai_data_block('values', (string) json_encode($command['values'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    return $lines;
}

/**
 * Runs a command whose application has an agent allowed in the channel: the
 * line posted under the writer's name, one request for the agent on it (its
 * brakes, its door knocked), the event sent once.
 *
 * @param array      $viewer
 * @param array      $channel
 * @param array      $row     ws_app_command_find() row
 * @param array      $agent   ws_app_command_agent_of()
 * @param string     $rest    the words after the command, as stored
 * @param array|null $values  the arguments by name
 * @param int        $parent_id
 * @param array      $calls   post, ask, announce: the writes, given apart by
 *                            the tests (ws_message_send(),
 *                            ws_agent_after_send(), the event)
 * @return array type done and message_id, app_command, agents (the requests
 *               made, [] when a brake held it); or type error
 */
function ws_app_command_agent_send($viewer, $channel, $row, $agent, $rest, $values, $parent_id, $calls = array())
{
    $post = $calls['post'] ?? function ($viewer, $channel, $body, $parent_id) {
        return ws_message_send($viewer, $channel, $body, array('parent_id' => (int) $parent_id));
    };
    $ask = $calls['ask'] ?? function ($viewer, $channel, $message_id, $body, $agent_value, $state) {
        return ws_agent_after_send($viewer, $channel, $message_id, ws_tokens_normalise(trim((string) $body)), $agent_value, false, array('state' => $state));
    };
    $announce = $calls['announce'] ?? function () {
    };

    $line = '/' . (string) $row['name'] . ((trim((string) $rest) !== '') ? ' ' . trim((string) $rest) : '');
    $posted = $post($viewer, $channel, $line, (int) $parent_id);

    if (empty($posted['ok'])) {
        return array('type' => 'error', 'error' => (string) ($posted['error'] ?? lang('The message could not be saved.')));
    }

    $message_id = (int) $posted['message_id'];
    // Followed mode: only this agent, without its name written; the own-message
    // rule and the brakes stay.
    $made = (array) $ask($viewer, $channel, $message_id, $line, ws_agent_request_value($agent), ws_app_command_request_state((string) $row['name'], $values));

    $announce();

    return array(
        'type'        => 'done',
        'message_id'  => $message_id,
        'app_command' => '/' . (string) $row['name'],
        'agents'      => array_values($made),
    );
}

/**
 * Checks a registration against what is stored: the command checked
 * (ws_app_command_check()), the name not held by another application, the
 * application under its limit. A row left by an application that is gone
 * gives its name up.
 *
 * @param int   $app_id
 * @param mixed $input name, description, usage, args (a list: the new
 *                     definition; [] or '' removes it; left out keeps the
 *                     stored one)
 * @return array ok, name, description, usage, args (the definition as kept,
 *               [] for none, null to keep what is stored), created (a new
 *               name for this application), id (the application's own row of
 *               that name, 0 when created); or ok false, status (422, 409),
 *               error, field, index (the refused argument, -1 for none)
 */
function ws_app_command_save_check($app_id, $input)
{
    $clean = ws_app_command_check($input);

    if (isset($clean['error'])) {
        return array('ok' => false, 'status' => 422, 'error' => $clean['error'], 'field' => $clean['field'], 'index' => -1);
    }

    $clean['args'] = null;

    if (is_array($input) && array_key_exists('args', $input)) {
        $args = ws_app_command_args_check($input['args']);

        if (isset($args['error'])) {
            return array('ok' => false, 'status' => 422, 'error' => $args['error'], 'field' => 'args', 'index' => $args['index']);
        }

        $clean['args'] = $args['args'];

        // A definition without a usage line gets one from its labels.
        if (($clean['usage'] === '') && !empty($clean['args'])) {
            $clean['usage'] = ws_app_command_args_usage($clean['args']);
        }
    }

    if (!ws_app_commands_ready()) {
        return array('ok' => false, 'status' => 503, 'error' => 'not_ready', 'field' => '', 'index' => -1);
    }

    $mine = 0;
    $own_id = 0;
    $folded = ws_command_fold($clean['name']);

    foreach (db_items("SELECT ws_app_commands.id, ws_app_commands.app_id, ws_app_commands.name, api_apps.id AS present
            FROM ws_app_commands LEFT JOIN api_apps ON api_apps.id = ws_app_commands.app_id") as $row) {
        $same = (ws_command_fold($row['name']) === $folded);

        if ((int) $row['app_id'] === (int) $app_id) {
            $mine++;
            $own_id = $same ? (int) $row['id'] : $own_id;
        } elseif ($same) {
            if ($row['present'] !== null) {
                return array('ok' => false, 'status' => 409, 'error' => 'name_taken', 'field' => 'name', 'index' => -1);
            }

            db("DELETE FROM ws_app_commands WHERE id = '" . (int) $row['id'] . "'");
        }
    }

    if (($own_id === 0) && ($mine >= WS_APP_COMMANDS_MAX)) {
        return array('ok' => false, 'status' => 409, 'error' => 'limit_reached', 'field' => 'name', 'index' => -1);
    }

    return array('ok' => true, 'created' => ($own_id === 0), 'id' => $own_id) + $clean;
}

/**
 * Writes a checked registration (ws_app_command_save_check()): a new row, or
 * the application's own row of that name changed. Two applications taking a
 * free name at once: the database keeps the first.
 *
 * @param int   $app_id
 * @param array $clean ws_app_command_save_check() answer
 * @return bool false when the name went to another application meanwhile
 */
function ws_app_command_write($app_id, $clean)
{
    $now = time();
    $args = $clean['args'] ?? null;
    // Before the upgrade adds the column a definition cannot be kept; the
    // command is kept without one.
    $column = pg_schema_has('ws_app_commands', 'args');
    $stored = (is_array($args) && !empty($args)) ? json_encode($args, JSON_UNESCAPED_UNICODE) : null;

    if (!$clean['created']) {
        if ($column && is_array($args)) {
            return (bool) db_q("UPDATE ws_app_commands SET name = ?, description = ?, usage_text = ?, args = ?, updated_at = ? WHERE id = ? AND app_id = ?",
                array($clean['name'], $clean['description'], $clean['usage'], $stored, $now, (int) $clean['id'], (int) $app_id));
        }

        return (bool) db_q("UPDATE ws_app_commands SET name = ?, description = ?, usage_text = ?, updated_at = ? WHERE id = ? AND app_id = ?",
            array($clean['name'], $clean['description'], $clean['usage'], $now, (int) $clean['id'], (int) $app_id));
    }

    if ($column) {
        return (bool) db_q("INSERT INTO ws_app_commands (app_id, name, description, usage_text, args, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
            array((int) $app_id, $clean['name'], $clean['description'], $clean['usage'], $stored, $now, $now));
    }

    return (bool) db_q("INSERT INTO ws_app_commands (app_id, name, description, usage_text, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
        array((int) $app_id, $clean['name'], $clean['description'], $clean['usage'], $now, $now));
}

/**
 * Removes one of an application's commands.
 *
 * @param int    $app_id
 * @param string $name with or without its slash
 * @return bool whether it was there
 */
function ws_app_command_delete($app_id, $name)
{
    if (!ws_app_commands_ready()) {
        return false;
    }

    $row = ws_app_command_find(ltrim((string) $name, '/'), ws_app_commands_rows($app_id));

    if ($row === null) {
        return false;
    }

    db("DELETE FROM ws_app_commands WHERE id = '" . (int) $row['id'] . "'");

    return true;
}

/**
 * Where the composer finds values for an argument of a kind, as the built-in
 * commands use it; the application never sends it.
 *
 * @param string $kind a ws_command_argument_kinds() value
 * @return string a suggest source, or '' for none
 */
function ws_app_command_arg_suggest($kind)
{
    $sources = array(
        'person'      => 'people',
        'people'      => 'people',
        'department'  => 'people_departments',
        'task'        => 'tasks',
        'record'      => 'records',
        'date'        => 'dates',
        'estimate'    => 'estimates',
        'priority'    => 'priorities',
        'status'      => 'statuses',
        'record_kind' => 'kinds',
        'health'      => 'healths',
        'notify'      => 'notify',
    );

    return $sources[(string) $kind] ?? '';
}

/**
 * Checks the arguments an application defines for its command and gives them
 * back in the registry's shape (ws_command_registry_arg()), in the order they
 * are typed.
 *
 * @param mixed $args a list of name, kind, label, optional, multiple,
 *                    anywhere; '', null or [] for no definition
 * @return array args (the list, [] for none); or error (too_many_args,
 *               invalid_kind, invalid_args) and index (the refused item, -1
 *               when the whole value is)
 */
function ws_app_command_args_check($args)
{
    if (($args === null) || ($args === '') || ($args === array())) {
        return array('args' => array());
    }

    if (!is_array($args) || (array_values($args) !== $args)) {
        return array('error' => 'invalid_args', 'index' => -1);
    }

    if (count($args) > WS_APP_COMMAND_ARGS_MAX) {
        return array('error' => 'too_many_args', 'index' => WS_APP_COMMAND_ARGS_MAX);
    }

    $out = array();
    $names = array();

    foreach ($args as $index => $item) {
        if (!is_array($item) || !isset($item['name']) || !is_string($item['name']) || !preg_match('/^[a-z][a-z0-9_]{0,19}$/', $item['name'])
            || isset($names[$item['name']])) {
            return array('error' => 'invalid_args', 'index' => $index);
        }

        $kind = $item['kind'] ?? null;

        if (!is_string($kind) || !in_array($kind, ws_command_argument_kinds(), true)) {
            return array('error' => 'invalid_kind', 'index' => $index);
        }

        $label = array_key_exists('label', $item) ? $item['label'] : $item['name'];

        if (!is_string($label) || preg_match('/[\p{Cc}]/u', $label)) {
            return array('error' => 'invalid_args', 'index' => $index);
        }

        $label = trim($label);
        $length = mb_strlen($label, 'UTF-8');

        if (($length < 1) || ($length > WS_APP_COMMAND_ARG_LABEL_MAX)) {
            return array('error' => 'invalid_args', 'index' => $index);
        }

        $flags = array();

        foreach (array('optional', 'multiple', 'anywhere') as $flag) {
            if (array_key_exists($flag, $item) && !is_bool($item[$flag])) {
                return array('error' => 'invalid_args', 'index' => $index);
            }

            $flags[$flag] = !empty($item[$flag]);
        }

        $names[$item['name']] = true;
        $out[] = ws_command_registry_arg($item['name'], $kind, $label, $flags['optional'], ws_app_command_arg_suggest($kind),
            array('multiple' => $flags['multiple'], 'anywhere' => $flags['anywhere']));
    }

    return array('args' => $out);
}

/**
 * A stored definition read back. One that cannot be resolved (not JSON, not
 * a list, an unknown kind, a bad item) is no definition: the command takes
 * free text as one without, never an error for the writer.
 *
 * @param mixed $stored ws_app_commands.args
 * @return array[] the arguments, [] for none
 */
function ws_app_command_args_read($stored)
{
    if (!is_string($stored) || (trim($stored) === '')) {
        return array();
    }

    $read = ws_app_command_args_check(json_decode($stored, true));

    return isset($read['error']) ? array() : $read['args'];
}

/**
 * The usage line a definition writes when the application gave none:
 * <label> for a required argument, [label] for an optional one.
 *
 * @param array[] $args checked arguments
 * @return string
 */
function ws_app_command_args_usage($args)
{
    $parts = array();

    foreach ($args as $arg) {
        $parts[] = $arg['optional'] ? '[' . $arg['label'] . ']' : '<' . $arg['label'] . '>';
    }

    return ws_app_command_text(implode(' ', $parts));
}

/**
 * The value one word gives an argument of a word kind, or null when the word
 * is none of that kind.
 *
 * @param string $kind
 * @param string $word
 * @param string $today Y-m-d
 * @return mixed
 */
function ws_app_command_word_value($kind, $word, $today)
{
    switch ($kind) {
        case 'date':
            $date = ws_parse_date_word($word, $today);
            return ($date !== '') ? $date : null;

        case 'time':
            $time = ws_parse_time_word($word);
            return ($time !== '') ? $time : null;

        case 'estimate':
            $minutes = ws_parse_estimate((substr($word, 0, 1) === '~') ? $word : '~' . $word);
            return ($minutes > 0) ? $minutes : null;

        case 'priority':
            $priority = ws_parse_priority((substr($word, 0, 1) === '!') ? $word : '!' . $word);
            return ($priority !== '') ? $priority : null;

        case 'status':
            $status = ws_parse_status_word($word);
            return ($status !== '') ? $status : null;

        case 'health':
            $health = ws_command_health_word($word);
            return ($health !== '') ? $health : null;

        case 'notify':
            $notify = ws_command_notify_word($word);
            return ($notify !== '') ? $notify : null;

        case 'record_kind':
            $kinds = array(
                'urun' => 'product', 'product' => 'product', 'grup' => 'product_group', 'group' => 'product_group',
                'kisi' => 'contact', 'contact' => 'contact', 'satir' => 'wtable_row', 'row' => 'wtable_row',
                'cari' => 'erp_account', 'account' => 'erp_account', 'etkinlik' => 'calendar_event', 'event' => 'calendar_event',
            );
            return $kinds[ws_command_fold(trim($word, " \t.,;:!"))] ?? null;

        case 'amount':
            return ws_command_parse_amount($word);

        case 'quantity':
            $quantity = ws_command_parse_quantity($word);
            return is_array($quantity) ? $quantity['value'] : null;
    }

    return null;
}

/**
 * Reads the words after a command with a definition into values by argument
 * name: a person, department or task is its id, people a list of ids, a
 * record {type, id}, a date Y-m-d, a time H:i, an estimate in minutes, an
 * amount in minor units, a quantity an integer, text and a flow a string,
 * options a list of strings; an argument not typed is null (a list for a
 * multiple one). Tagged kinds take their tags, the other typed kinds the
 * first word of their kind anywhere on the line, in the order of the
 * definition; the words left over go to the text arguments in turn, the
 * last one taking the rest.
 *
 * @param array[]     $args  ws_app_command_args_read()
 * @param string      $body  the words after the command, as stored
 * @param string|null $today Y-m-d, the server's day when not given
 * @return array values (name => value), missing (the label of the first
 *               required argument that could not be read, '' for none)
 */
function ws_app_command_values($args, $body, $today = null)
{
    $today = ($today === null) ? date('Y-m-d') : (string) $today;
    $tokens = ws_tokens($body);
    $used_tokens = array();
    $words = array_values(array_filter(preg_split('/\s+/u', trim((string) preg_replace('/<[@#][a-z_]+:[0-9]{1,10}>/', ' ', (string) $body))), 'strlen'));
    $used_words = array();
    $values = array();
    $texts = array();
    $records = function_exists('ws_record_type_keys') ? ws_record_type_keys() : array();
    $tagged = array('person' => 'user', 'people' => 'user', 'department' => 'dept', 'task' => 'task');

    foreach ($args as $arg) {
        $kind = $arg['kind'];
        $many = $arg['multiple'] || ($kind === 'people');
        $found = array();

        if (isset($tagged[$kind]) || ($kind === 'record')) {
            foreach ($tokens as $index => $token) {
                $fits = ($kind === 'record') ? in_array($token['type'], $records, true) : ($token['type'] === $tagged[$kind]);

                if (!$fits || isset($used_tokens[$index])) {
                    continue;
                }

                $used_tokens[$index] = true;
                $found[] = ($kind === 'record') ? array('type' => $token['type'], 'id' => $token['id']) : $token['id'];

                if (!$many) {
                    break;
                }
            }
        } elseif (!in_array($kind, array('text', 'options', 'flow'), true)) {
            foreach ($words as $index => $word) {
                if (isset($used_words[$index])) {
                    continue;
                }

                $value = ws_app_command_word_value($kind, $word, $today);

                if ($value === null) {
                    continue;
                }

                $used_words[$index] = true;
                $found[] = $value;

                if (!$many) {
                    break;
                }
            }
        } else {
            $texts[] = $arg['name'];
            $values[$arg['name']] = null;
            continue;
        }

        $values[$arg['name']] = $many ? (empty($found) ? null : $found) : ($found[0] ?? null);
    }

    // The words no typed argument took, in the order they were written.
    $left = array();

    foreach ($words as $index => $word) {
        if (!isset($used_words[$index])) {
            $left[] = $word;
        }
    }

    $last = count($texts) - 1;

    foreach ($texts as $position => $name) {
        $share = ($position === $last) ? $left : array_slice($left, 0, 1);
        $left = ($position === $last) ? array() : array_slice($left, 1);
        $text = trim(implode(' ', $share));

        foreach ($args as $arg) {
            if ($arg['name'] !== $name) {
                continue;
            }

            if ($text === '') {
                $values[$name] = null;
            } elseif ($arg['kind'] === 'options') {
                $parts = (strpos($text, ',') !== false) ? explode(',', $text) : preg_split('/\s+/u', $text);
                $values[$name] = array_values(array_filter(array_map('trim', $parts), 'strlen'));
            } else {
                $values[$name] = $text;
            }
        }
    }

    $missing = '';

    foreach ($args as $arg) {
        if (!$arg['optional'] && ($values[$arg['name']] === null)) {
            $missing = $arg['label'];
            break;
        }
    }

    return array('values' => $values, 'missing' => $missing);
}
