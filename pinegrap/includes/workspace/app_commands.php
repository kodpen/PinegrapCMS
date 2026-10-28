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
 * POST /workspace/channels/{id}/messages and the writer sees "Sent to <app>".
 * An application that is disabled, expired, without workspace:commands or
 * unable to read the channel leaves its command answered as unknown.
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

    if (in_array(ws_command_fold($name), array_map('ws_command_fold', array_keys(ws_command_table())), true) || ws_command_assistant_word($typed)) {
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
 * @return array[] id, app_id, name, description, usage_text, created_at,
 *                 updated_at, app_name, status, expires_at, owner_user_id,
 *                 scopes
 */
function ws_app_commands_rows($app_id = 0)
{
    if (!ws_app_commands_ready()) {
        return array();
    }

    return db_items("SELECT ws_app_commands.id, ws_app_commands.app_id, ws_app_commands.name, ws_app_commands.description,
            ws_app_commands.usage_text, ws_app_commands.created_at, ws_app_commands.updated_at,
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
 * A registered command as a ws_commands() entry: free text after the name,
 * no live line, the application's name beside it.
 *
 * @param array $row ws_app_commands_rows() row
 * @return array
 */
function ws_app_command_entry($row)
{
    $name = '/' . (string) $row['name'];
    $usage = trim((string) $row['usage_text']);

    return array(
        'key'         => 'app:' . (string) $row['name'],
        'names'       => array($name),
        'name'        => $name,
        'label'       => $name . ' · ' . (string) $row['app_name'],
        'description' => (string) $row['description'],
        'usage'       => $name . (($usage !== '') ? ' ' . $usage : ''),
        'example'     => $name . (($usage !== '') ? ' ' . $usage : ''),
        'args'        => array(),
        'live'        => false,
        'app_id'      => (int) $row['app_id'],
        'app'         => (string) $row['app_name'],
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
 * @return array
 */
function ws_app_command_payload($channel, $thread, $app_id, $name, $user_id, $text, $body, $parent_id = 0)
{
    return array(
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
}

/**
 * Sends a typed app command to its application (workspace.app.command).
 *
 * @param array  $viewer  the writer
 * @param array  $channel
 * @param array  $row     ws_app_command_find() row
 * @param string $rest    the words after the command, as stored
 * @param int    $parent_id
 * @return array|null the writer's answer; null when the application may not
 *                    read the channel, and nothing was sent
 */
function ws_app_command_send($viewer, $channel, $row, $rest, $parent_id = 0)
{
    $owner = pg_load_user_row((int) $row['owner_user_id']);
    $reader = is_array($owner) ? ws_viewer($owner) : null;

    if (!ws_app_reads_channel($reader, $channel) || !function_exists('pg_announce')) {
        return null;
    }

    $thread = ws_channel_is_thread($channel) ? ws_thread($channel['id']) : null;

    pg_announce('workspace.app.command', ws_app_command_payload($channel, $thread, (int) $row['app_id'], $row['name'], (int) $viewer['id'], ws_plain_text($reader, $rest), (string) $rest, $parent_id));

    return array(
        'type' => 'ephemeral',
        'html' => '<div>' . h(lang(array('string' => 'Sent to the application {var:1}.', 'vars' => (string) $row['app_name']))) . '</div>',
        'app_command' => '/' . (string) $row['name'],
    );
}

/**
 * Checks a registration against what is stored: the command checked
 * (ws_app_command_check()), the name not held by another application, the
 * application under its limit. A row left by an application that is gone
 * gives its name up.
 *
 * @param int   $app_id
 * @param mixed $input name, description, usage
 * @return array ok, name, description, usage, created (a new name for this
 *               application), id (the application's own row of that name, 0
 *               when created); or ok false, status (422, 409), error, field
 */
function ws_app_command_save_check($app_id, $input)
{
    $clean = ws_app_command_check($input);

    if (isset($clean['error'])) {
        return array('ok' => false, 'status' => 422, 'error' => $clean['error'], 'field' => $clean['field']);
    }

    if (!ws_app_commands_ready()) {
        return array('ok' => false, 'status' => 503, 'error' => 'not_ready', 'field' => '');
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
                return array('ok' => false, 'status' => 409, 'error' => 'name_taken', 'field' => 'name');
            }

            db("DELETE FROM ws_app_commands WHERE id = '" . (int) $row['id'] . "'");
        }
    }

    if (($own_id === 0) && ($mine >= WS_APP_COMMANDS_MAX)) {
        return array('ok' => false, 'status' => 409, 'error' => 'limit_reached', 'field' => 'name');
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

    if (!$clean['created']) {
        return (bool) db_q("UPDATE ws_app_commands SET name = ?, description = ?, usage_text = ?, updated_at = ? WHERE id = ? AND app_id = ?",
            array($clean['name'], $clean['description'], $clean['usage'], $now, (int) $clean['id'], (int) $app_id));
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
