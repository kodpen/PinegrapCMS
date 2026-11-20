<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an application's answer to a / command it registered, shown to
 * the command's writer alone (includes/workspace/app_commands.php).
 *
 * workspace.app.command carries answer_token: signed with the site's key
 * over the writer, the channel, the application, the command and the time,
 * valid WS_APP_ANSWER_TOKEN_LIFE seconds, nothing stored for it. The
 * application answers with POST /workspace/commands/answer and the token; the
 * answer is kept as a ws_app_answers row for the writer and that channel,
 * at most WS_APP_ANSWER_MAX a token, so an application can answer only the
 * writer of a command it received, never anyone at any time.
 *
 * ws_sync reads the open channel's rows of the reader after the last one the
 * window has (one indexed read); every window and the phone draw them as a
 * box under the conversation with the application's name. No inbox line, no
 * push, no bell. Rows go with their channel, application or account and are
 * swept after WS_APP_ANSWER_KEEP seconds (workspace_recurring_job.php).
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
 * How long a command's answer_token is accepted, in seconds.
 */
const WS_APP_ANSWER_TOKEN_LIFE = 900;

/**
 * How many answers one token may give.
 */
const WS_APP_ANSWER_MAX = 3;

/**
 * How long an answer is kept, in seconds.
 */
const WS_APP_ANSWER_KEEP = 86400;

/**
 * How many answers ws_sync gives at once.
 */
const WS_APP_ANSWER_SYNC_LIMIT = 10;

/**
 * Whether the table is there: the files land before the upgrade runs.
 *
 * @return bool
 */
function ws_app_answers_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('ws_app_answers');
}

/**
 * The site's signing key, '' without one.
 *
 * @return string
 */
function ws_app_answer_key()
{
    return defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
}

/**
 * The signature of a token's claims: 32 hex characters, the value
 * ws_app_answers.token keeps to count the answers. Pure.
 *
 * @param int    $user_id
 * @param int    $channel_id
 * @param int    $app_id
 * @param string $command   the registered name, without its slash
 * @param int    $time      when the command was typed
 * @param string $key
 * @return string
 */
function ws_app_answer_signature($user_id, $channel_id, $app_id, $command, $time, $key)
{
    return substr(hash_hmac('sha256', 'ws-app-answer|' . (int) $user_id . '|' . (int) $channel_id . '|' . (int) $app_id . '|' . (string) $command . '|' . (int) $time, (string) $key), 0, 32);
}

/**
 * The answer_token of a typed command: the claims in the clear, the command
 * as hex so the token stays ASCII, then their signature. Pure.
 *
 * @param int    $user_id    the writer
 * @param int    $channel_id the channel or discussion it was typed in
 * @param int    $app_id     the registering application
 * @param string $command    the registered name, without its slash
 * @param int    $time
 * @param string $key        the site's key
 * @return string '' without a key, a writer, a channel, an application or a name
 */
function ws_app_answer_token($user_id, $channel_id, $app_id, $command, $time, $key)
{
    $command = (string) $command;

    if (((string) $key === '') || ((int) $user_id <= 0) || ((int) $channel_id <= 0) || ((int) $app_id <= 0) || ($command === '') || ((int) $time <= 0)) {
        return '';
    }

    return (int) $user_id . '.' . (int) $channel_id . '.' . (int) $app_id . '.' . (int) $time . '.' . bin2hex($command) . '.'
        . ws_app_answer_signature($user_id, $channel_id, $app_id, $command, $time, $key);
}

/**
 * Reads an answer_token back. Pure.
 *
 * @param mixed  $token
 * @param string $key
 * @param int    $now
 * @return array ok, error (token_invalid, token_expired), user_id,
 *               channel_id, app_id, command, time, signature
 */
function ws_app_answer_token_read($token, $key, $now)
{
    $fail = array('ok' => false, 'error' => 'token_invalid');

    if (!is_string($token) || ((string) $key === '') || !preg_match('/^([1-9][0-9]{0,9})\.([1-9][0-9]{0,9})\.([1-9][0-9]{0,9})\.([1-9][0-9]{0,10})\.((?:[0-9a-f]{2}){1,80})\.([0-9a-f]{32})$/', $token, $m)) {
        return $fail;
    }

    $command = (string) hex2bin($m[5]);
    $claims = array(
        'user_id'    => (int) $m[1],
        'channel_id' => (int) $m[2],
        'app_id'     => (int) $m[3],
        'command'    => $command,
        'time'       => (int) $m[4],
        'signature'  => $m[6],
    );

    if (!hash_equals(ws_app_answer_signature($claims['user_id'], $claims['channel_id'], $claims['app_id'], $command, $claims['time'], $key), $m[6])) {
        return $fail;
    }

    // A token from the future is not one this site signed for now; a minute
    // of clock difference between servers is allowed.
    if ($claims['time'] > ((int) $now + 60)) {
        return $fail;
    }

    if (((int) $now - $claims['time']) > WS_APP_ANSWER_TOKEN_LIFE) {
        return array('ok' => false, 'error' => 'token_expired') + $claims;
    }

    return array('ok' => true, 'error' => '') + $claims;
}

/**
 * Whether an application may answer with a token it read. Pure.
 *
 * @param array $claims   ws_app_answer_token_read() that was ok
 * @param int   $app_id   the calling application
 * @param bool  $command_is_its_own the command is still registered by it
 * @param int   $used     the answers the token gave already
 * @return string '' or the refusal: wrong_app, command_gone, limit_reached
 */
function ws_app_answer_admit($claims, $app_id, $command_is_its_own, $used)
{
    if (((int) $app_id <= 0) || ((int) ($claims['app_id'] ?? 0) !== (int) $app_id)) {
        return 'wrong_app';
    }

    if (!$command_is_its_own) {
        return 'command_gone';
    }

    if ((int) $used >= WS_APP_ANSWER_MAX) {
        return 'limit_reached';
    }

    return '';
}

/**
 * An answer's text, cleaned as a message's body is. Pure.
 *
 * @param mixed $text
 * @return array ok, body, error (text_required, text_too_long)
 */
function ws_app_answer_body($text)
{
    $body = is_string($text) ? trim($text) : '';

    if (function_exists('ws_tokens_normalise')) {
        $body = trim((string) ws_tokens_normalise($body));
    }

    if ($body === '') {
        return array('ok' => false, 'body' => '', 'error' => 'text_required');
    }

    if (mb_strlen($body) > WS_MESSAGE_MAX) {
        return array('ok' => false, 'body' => '', 'error' => 'text_too_long');
    }

    return array('ok' => true, 'body' => $body, 'error' => '');
}

/**
 * The answer_token for a command just typed, '' before the upgrade or
 * without the site's key.
 *
 * @param int    $user_id
 * @param int    $channel_id
 * @param int    $app_id
 * @param string $command
 * @return string
 */
function ws_app_answer_token_for($user_id, $channel_id, $app_id, $command)
{
    if (!ws_app_answers_ready()) {
        return '';
    }

    return ws_app_answer_token($user_id, $channel_id, $app_id, $command, time(), ws_app_answer_key());
}

/**
 * Every check of an answer, made before anything is written so a dry run
 * meets the same refusals.
 *
 * @param int   $app_id the calling application
 * @param array $input  token, text
 * @return array ok, status, error, field; on ok claims, body, channel, used
 */
function ws_app_answer_check($app_id, $input)
{
    $fail = function ($status, $error, $field = '') {
        return array('ok' => false, 'status' => $status, 'error' => $error, 'field' => $field);
    };

    if (!ws_app_answers_ready()) {
        return $fail(503, 'not_ready');
    }

    $claims = ws_app_answer_token_read($input['token'] ?? null, ws_app_answer_key(), time());

    if (!$claims['ok']) {
        return $fail(422, $claims['error'], 'token');
    }

    $own = ws_app_command_find($claims['command'], ws_app_commands_rows((int) $app_id)) !== null;
    $used = (int) db_value("SELECT COUNT(*) FROM ws_app_answers WHERE token = '" . e($claims['signature']) . "'");
    $refused = ws_app_answer_admit($claims, $app_id, $own, $used);

    if ($refused !== '') {
        return $fail(($refused === 'limit_reached') ? 409 : 403, $refused, ($refused === 'limit_reached') ? 'token' : '');
    }

    $body = ws_app_answer_body($input['text'] ?? null);

    if (!$body['ok']) {
        return $fail(422, $body['error'], 'text');
    }

    // The writer must still read the channel: a removed member is not sent
    // the answer.
    $channel = ws_channel($claims['channel_id']);
    $writer = pg_load_user_row($claims['user_id']);

    if (!$channel || !is_array($writer) || !ws_can_read_channel(ws_viewer($writer), $channel)) {
        return $fail(404, 'writer_gone');
    }

    return array('ok' => true, 'status' => 200, 'error' => '', 'field' => '', 'claims' => $claims, 'body' => $body['body'], 'channel' => $channel, 'used' => $used);
}

/**
 * Keeps an answer that ws_app_answer_check() let through.
 *
 * @param array $check
 * @return int the row's id, 0 when it was not written
 */
function ws_app_answer_write($check)
{
    $claims = $check['claims'];
    $done = db_q("INSERT INTO ws_app_answers (user_id, channel_id, app_id, command, token, body, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)", array(
        (int) $claims['user_id'],
        (int) $claims['channel_id'],
        (int) $claims['app_id'],
        mb_substr((string) $claims['command'], 0, 20),
        (string) $claims['signature'],
        (string) $check['body'],
        time(),
    ));

    return $done ? (int) db_value("SELECT LAST_INSERT_ID()") : 0;
}

/**
 * The reader's answers in a channel after the last one the window has, for
 * ws_sync: one indexed read, the newest WS_APP_ANSWER_SYNC_LIMIT, oldest
 * first. The caller has checked that the reader reads the channel.
 *
 * @param array $viewer
 * @param int   $channel_id
 * @param int   $after
 * @return array[] id, app_id, app, command, html, created_at
 */
function ws_app_answers_for($viewer, $channel_id, $after)
{
    if (!ws_app_answers_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT x.id, x.app_id, x.command, x.body, x.created_at, a.name AS app_name
        FROM ws_app_answers x
        LEFT JOIN api_apps a ON a.id = x.app_id
        WHERE x.user_id = '" . (int) $viewer['id'] . "' AND x.channel_id = '" . (int) $channel_id . "' AND x.id > '" . max(0, (int) $after) . "'
            AND x.created_at > '" . (time() - WS_APP_ANSWER_KEEP) . "'
        ORDER BY x.id DESC
        LIMIT " . WS_APP_ANSWER_SYNC_LIMIT);

    $out = array();

    foreach (array_reverse($rows) as $row) {
        $body = (string) $row['body'];
        $out[] = array(
            'id'         => (int) $row['id'],
            'app_id'     => (int) $row['app_id'],
            'app'        => (string) ($row['app_name'] ?? ''),
            'command'    => '/' . (string) $row['command'],
            'html'       => ws_render_body($body, ws_refs_resolve($viewer, ws_tokens($body))),
            'created_at' => (int) $row['created_at'],
        );
    }

    return $out;
}

/**
 * Deletes the answers of a channel, an application or an account that is
 * deleted.
 *
 * @param string $column channel_id, app_id or user_id
 * @param int    $id
 */
function ws_app_answers_delete_for($column, $id)
{
    if (!in_array($column, array('channel_id', 'app_id', 'user_id'), true) || ((int) $id <= 0) || !ws_app_answers_ready()) {
        return;
    }

    db("DELETE FROM ws_app_answers WHERE " . $column . " = '" . (int) $id . "'");
}

/**
 * Deletes the answers older than WS_APP_ANSWER_KEEP (the Workspace recurring
 * job).
 *
 * @return void
 */
function ws_app_answers_sweep()
{
    if (!ws_app_answers_ready()) {
        return;
    }

    db("DELETE FROM ws_app_answers WHERE created_at < '" . (time() - WS_APP_ANSWER_KEEP) . "'");
}
