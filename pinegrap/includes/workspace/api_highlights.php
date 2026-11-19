<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - readers' highlights on a message in the external API: the
 * highlights, highlight_text and can_highlight_all keys of WorkspaceMessage,
 * and the person's own writes (POST /workspace/messages/{id}/highlights,
 * POST /workspace/highlights/{id}/note, POST /workspace/highlights/{id}/delete)
 * over highlights.php. A "For me" row reaches only the person's own team
 * devices session, never an application key or an OAuth connection.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Whether the caller is the person's own team devices session, the only
 * caller that receives their "For me" highlights.
 *
 * @return bool
 */
function ws_api_highlights_personal()
{
    return function_exists('api_current_device') && ws_api_device_writes_as_person(api_current_device());
}

/**
 * One message's highlights as the API gives them: every 'all' row, and the
 * reader's own 'me' rows only to their own session ($personal). Pure.
 *
 * @param array[] $rows      ws_highlights_map_rows() rows of one message
 * @param int     $viewer_id
 * @param bool    $can_react whether the reader may react to the message
 * @param bool    $personal  ws_api_highlights_personal()
 * @return array[] id, quote, occurrence, scope, note, user_id, name, mine,
 *                 can_remove, can_note
 */
function ws_api_message_highlights_rows($rows, $viewer_id, $can_react, $personal)
{
    $out = array();

    foreach ((array) $rows as $row) {
        $scope = ws_highlight_scope($row['scope'] ?? '');
        $mine = ((int) $row['user_id'] === (int) $viewer_id);

        if (($scope === 'me') && (!$mine || !$personal)) {
            continue;
        }

        $out[] = array(
            'id'         => (int) $row['id'],
            'quote'      => (string) $row['quote'],
            'occurrence' => (int) $row['occurrence'],
            'scope'      => $scope,
            'note'       => (string) $row['note'],
            'user_id'    => (int) $row['user_id'],
            'name'       => (string) ($row['name'] ?? ''),
            'mine'       => $mine,
            'can_remove' => !empty($row['can_remove']),
            'can_note'   => ws_highlight_can_note($viewer_id, array('scope' => $scope) + $row, $can_react),
        );
    }

    return $out;
}

/**
 * The highlight keys of a set of messages, read once for the set: the plain
 * text the panel draws for this reader (what quote and occurrence index),
 * whether the reader may highlight for everyone, and the rows. A deleted
 * message, a line the workspace wrote and one the person deleted for
 * themselves carry none, as does every message before the upgrade.
 *
 * @param array   $viewer
 * @param array[] $rows ws_messages rows
 * @return array message id => text, can_all, rows
 */
function ws_api_message_highlights_map($viewer, $rows)
{
    if (!function_exists('ws_highlights_ready') || !ws_highlights_ready()) {
        return array();
    }

    $alive = array();

    foreach ((array) $rows as $row) {
        if (((int) $row['deleted_at'] === 0) && ($row['kind'] !== 'system')) {
            $alive[(int) $row['id']] = $row;
        }
    }

    if (empty($alive)) {
        return array();
    }

    // The panel's own payload: its body as drawn for this reader, and the
    // highlights it carries (ws_highlights_map()), so both match the panel.
    // A message hidden by the person is left out of it.
    $payloads = ws_message_payloads($viewer, array_values($alive));
    $personal = ws_api_highlights_personal();
    $posts = array();
    $out = array();

    foreach ($payloads as $payload) {
        $id = (int) $payload['id'];

        if (!isset($alive[$id]) || !empty($payload['deleted'])) {
            continue;
        }

        $row = $alive[$id];
        $channel_id = (int) $row['channel_id'];

        // ws_highlight_rights()'s can_react, with the channel read once.
        if (!isset($posts[$channel_id])) {
            $channel = ws_channel($channel_id);
            $posts[$channel_id] = is_array($channel) && ws_can_post_channel($viewer, $channel);
        }

        $can_react = $posts[$channel_id] && !(function_exists('ws_message_in_past') && ws_message_in_past($row));

        $out[$id] = array(
            'text'    => ws_highlight_plain((string) ($payload['html'] ?? '')),
            'can_all' => $can_react,
            'rows'    => ws_api_message_highlights_rows($payload['highlights'] ?? array(), $viewer['id'], $can_react, $personal),
        );
    }

    return $out;
}

// One highlight on WorkspaceMessage (ws_api_message_highlights_rows()).
function ws_api_message_highlight_schema()
{
    return array(
        'id'         => 'integer',
        'quote'      => 'string',
        'occurrence' => 'integer',
        'scope'      => 'string',
        'note'       => 'string',
        'user_id'    => 'integer',
        'name'       => 'string',
        'mine'       => 'boolean',
        'can_remove' => 'boolean',
        'can_note'   => 'boolean',
    );
}

/**
 * The writes are the person's own, from their team devices session, and
 * wait for the upgrade that makes the table.
 */
function ws_api_highlights_write_gate()
{
    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can highlight a message; an application key or an OAuth connection cannot do it for them.'));

    if (!ws_highlights_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }
}

/**
 * A refusal of ws_highlight_make_check() as the API answers it: a limit
 * with its own code, a field with a 422 on it, anything else a 404.
 *
 * @param array $checked ok, error, field, limit
 */
function ws_api_highlight_refuse($checked)
{
    if ((string) ($checked['limit'] ?? '') !== '') {
        api_fail(422, (string) $checked['limit'], (string) $checked['error']);
    }

    if ((string) ($checked['field'] ?? '') !== '') {
        api_fail_validation((string) $checked['error'], (string) $checked['field']);
    }

    api_fail_not_found(lang('Message'));
}

/**
 * A highlight the person may see: an 'all' one or their own, on a message
 * they can read; anything else a 404.
 *
 * @param array $viewer
 * @param int   $id
 * @return array ws_highlights row, message (ws_messages row), rights
 */
function ws_api_highlight_or_404($viewer, $id)
{
    $row = ws_highlight_row((int) $id);
    $message = $row ? ws_message((int) $row['message_id']) : null;
    $rights = $message ? ws_highlight_rights($viewer, $message) : array();
    $visible = $row && ((ws_highlight_scope($row['scope']) === 'all') || ((int) $row['user_id'] === (int) $viewer['id']));

    if (!$visible || empty($rights['can_read'])) {
        api_fail_not_found(lang('Highlight'));
    }

    // The limits of the application Claude works through.
    ws_api_channel_or_404($viewer, $message['channel_id']);

    return array('row' => $row, 'message' => $message, 'rights' => $rights);
}

// The message again, as every read gives it now.
function ws_api_highlight_message_ok($viewer, $message_id)
{
    $row = ws_message((int) $message_id);

    return ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body'])));
}

function ws_api_messages_highlight($params)
{
    $viewer = ws_api_viewer();
    $message = ws_message((int) $params['id']);

    if (!$message || ((int) $message['deleted_at'] > 0) || ($message['kind'] === 'system') || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        api_fail_not_found(lang('Message'));
    }

    // The limits of the application Claude works through.
    ws_api_channel_or_404($viewer, $message['channel_id']);

    ws_api_highlights_write_gate();

    $input = api_request_input();
    $quote = (string) ($input['quote'] ?? '');
    $occurrence = (int) ($input['occurrence'] ?? 0);
    $scope = (string) ($input['scope'] ?? 'me');
    $note = (array_key_exists('note', $input) && ($input['note'] !== null)) ? (string) $input['note'] : null;

    // The checks ws_highlight_set() makes, made first so that a dry run
    // meets the same refusals.
    $rights = ws_highlight_rights($viewer, $message);
    $mine = (array) db_items("SELECT * FROM ws_highlights WHERE message_id = '" . (int) $message['id'] . "' AND user_id = '" . (int) $viewer['id'] . "'");
    $same = ws_highlight_same($mine, $quote, $occurrence);
    $checked = ws_highlight_make_check(array(
        'message'    => $message,
        'can_read'   => $rights['can_read'],
        'can_react'  => $rights['can_react'],
        'plain'      => $rights['can_read'] ? ws_highlight_message_plain($viewer, $message) : '',
        'quote'      => $quote,
        'occurrence' => $occurrence,
        'scope'      => $scope,
        'same'       => $same,
        'counts'     => array(
            'mine_here' => count($mine),
            'here'      => (int) db_value("SELECT COUNT(*) FROM ws_highlights WHERE message_id = '" . (int) $message['id'] . "'"),
            'mine'      => (int) db_value("SELECT COUNT(*) FROM ws_highlights WHERE user_id = '" . (int) $viewer['id'] . "'"),
        ),
    ));

    if (!$checked['ok']) {
        ws_api_highlight_refuse($checked);
    }

    if ($note !== null) {
        $cleaned = ws_highlight_note_clean($note);

        if (!$cleaned['ok']) {
            api_fail_validation($cleaned['error'], 'note');
        }
    }

    api_dry_run_stop(is_array($same) ? 'updated' : 'created', 'workspace_highlight', array(
        'id'         => is_array($same) ? (int) $same['id'] : null,
        'message_id' => (int) $message['id'],
        'scope'      => $checked['scope'],
        'quote'      => $checked['quote'],
        'occurrence' => $checked['occurrence'],
    ));

    $result = ws_highlight_set($viewer, $message, $quote, $occurrence, $scope, $note);

    if (!$result['ok']) {
        ws_api_highlight_refuse($result);
    }

    api_ok(array('id' => (int) $result['id'], 'message' => ws_api_highlight_message_ok($viewer, $message['id'])));
}

function ws_api_highlights_note($params)
{
    $viewer = ws_api_viewer();
    ws_api_highlights_write_gate();

    $found = ws_api_highlight_or_404($viewer, $params['id']);

    if (!ws_highlight_can_note($viewer['id'], $found['row'], $found['rights']['can_react'])) {
        api_fail(403, 'forbidden', lang('Only the person who made a highlight can write its note.'));
    }

    $input = api_request_input();
    $cleaned = ws_highlight_note_clean((string) ($input['note'] ?? ''));

    if (!$cleaned['ok']) {
        api_fail_validation($cleaned['error'], 'note');
    }

    api_dry_run_stop('updated', 'workspace_highlight', array('id' => (int) $found['row']['id'], 'message_id' => (int) $found['message']['id'], 'note' => $cleaned['note']));

    $result = ws_highlight_note($viewer, $found['row']['id'], $cleaned['note']);

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'note');
    }

    api_ok(ws_api_highlight_message_ok($viewer, $found['message']['id']));
}

function ws_api_highlights_delete($params)
{
    $viewer = ws_api_viewer();
    ws_api_highlights_write_gate();

    $found = ws_api_highlight_or_404($viewer, $params['id']);

    if (!ws_highlight_can_remove($viewer['id'], $found['row'], $found['rights'])) {
        api_fail(403, 'forbidden', lang('You cannot remove this highlight.'));
    }

    api_dry_run_stop('deleted', 'workspace_highlight', array('id' => (int) $found['row']['id'], 'message_id' => (int) $found['message']['id']));

    $result = ws_highlight_remove($viewer, $found['row']['id']);

    if (!$result['ok']) {
        api_fail(403, 'forbidden', $result['error']);
    }

    api_ok(ws_api_highlight_message_ok($viewer, $found['message']['id']));
}
