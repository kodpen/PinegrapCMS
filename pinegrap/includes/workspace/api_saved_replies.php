<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - saved replies in the external API and MCP
 * (includes/workspace/saved_replies.php): the owner of the application lists,
 * reads, writes and deletes their own replies, reads the team's, and writes
 * the team's with the right to manage them. Nobody else's personal reply is
 * ever reached: another person's id answers 404 as a missing one does.
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
 * The saved reply routes, merged into ws_api_routes(). Reading and writing
 * are tools, as notes are; deleting cannot be undone, so it is not.
 *
 * @return array[]
 */
function ws_saved_reply_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $fields = array(
        array('name' => 'shortcut', 'in' => 'body', 'type' => 'string', 'max_length' => WS_SAVED_REPLY_SHORTCUT_MAX + 1, 'description' => 'The word typed after ; in the composer to pick the reply: letters, digits, - and _, kept in lower case without the ;.'),
        array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => WS_SAVED_REPLY_TITLE_MAX, 'description' => 'What the picker shows; the shortcut when empty.'),
        array('name' => 'body', 'in' => 'body', 'type' => 'string', 'max_length' => ws_saved_reply_body_max(), 'description' => 'The text put into the message, in Markdown. {customer} and {order} are filled from the channel\'s customer when it is put in.'),
    );
    $errors = ' A refused value answers 422 validation_failed naming the field (shortcut_invalid, title_too_long, body_empty, body_too_long); a shortcut another reply of the same scope holds is 409 shortcut_taken; a team reply written without the right to manage team replies is 403 forbidden.';
    $scope = array('name' => 'scope', 'type' => 'enum', 'values' => array(WS_SAVED_REPLY_PERSONAL, WS_SAVED_REPLY_TEAM), 'default' => WS_SAVED_REPLY_PERSONAL);

    $create_fields = $fields;
    $create_fields[0]['required'] = true;
    $create_fields[2]['required'] = true;
    $create_fields[] = array_merge($scope, array('in' => 'body', 'description' => 'personal: the owner\'s own reply; team: one every Workspace member picks, which takes the right to manage team replies.'));

    return array(
        array(
            'id'          => 'workspace.saved_replies.list',
            'method'      => 'GET',
            'path'        => '/workspace/saved-replies',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_saved_replies_list',
            'returns'     => array('list' => 'WorkspaceSavedReply'),
            'summary'     => 'The owner\'s saved replies',
            'description' => 'The saved replies of the owner of the application, by shortcut: the texts they pick with ; in the composer. A personal reply is its owner\'s alone; nobody else\'s is listed, administrators\' included. scope team lists the team\'s replies, which every Workspace member picks. One person keeps at most ' . WS_SAVED_REPLIES_MAX . ', the team ' . WS_SAVED_REPLIES_TEAM_MAX . ', all in one page. body keeps {customer} and {order} as written.',
            'params'      => array(array_merge($scope, array('in' => 'query'))),
        ),

        array(
            'id'          => 'workspace.saved_replies.get',
            'method'      => 'GET',
            'path'        => '/workspace/saved-replies/{id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_saved_replies_get',
            'returns'     => 'WorkspaceSavedReply',
            'summary'     => 'One saved reply',
            'description' => 'A saved reply of the owner of the application, or one of the team\'s; anybody else\'s personal reply is 404.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.saved_replies.create',
            'method'      => 'POST',
            'path'        => '/workspace/saved-replies',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_saved_replies_create',
            'dry_run'     => true,
            'status'      => 201,
            'returns'     => 'WorkspaceSavedReply',
            'summary'     => 'Save a reply',
            'description' => 'Saves a new reply of the owner of the application, or of the team with scope team. A person keeps at most ' . WS_SAVED_REPLIES_MAX . ', the team ' . WS_SAVED_REPLIES_TEAM_MAX . ' (409 limit_reached).' . $errors,
            'params'      => $create_fields,
        ),

        array(
            'id'           => 'workspace.saved_replies.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/saved-replies/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_saved_replies_update',
            'dry_run'      => true,
            'returns'      => 'WorkspaceSavedReply',
            'summary'      => 'Change a saved reply',
            'description'  => 'Changes only the fields sent on a saved reply of the owner\'s, or of the team\'s with the right to manage them; anybody else\'s personal reply is 404. A reply keeps its scope. body replaces the whole text. PATCH is accepted beside POST.' . $errors,
            'params'       => array_merge(array($id), $fields),
        ),

        array(
            'id'          => 'workspace.saved_replies.delete',
            'method'      => 'DELETE',
            'path'        => '/workspace/saved-replies/{id}',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_saved_replies_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => array('id' => 'integer', 'deleted' => 'boolean'),
            'summary'     => 'Delete a saved reply',
            'description' => 'Deletes a saved reply of the owner\'s, or of the team\'s with the right to manage them (403 forbidden without), for good; its shortcut is free again. Anybody else\'s personal reply is 404. It cannot be undone, so it is not offered to assistants as a tool. POST /workspace/saved-replies/{id}/delete does the same for servers that refuse DELETE.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.saved_replies.delete_post',
            'method'      => 'POST',
            'path'        => '/workspace/saved-replies/{id}/delete',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_saved_replies_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => array('id' => 'integer', 'deleted' => 'boolean'),
            'summary'     => 'Delete a saved reply (POST)',
            'description' => 'The same as DELETE /workspace/saved-replies/{id}, for servers and clients that refuse DELETE.',
            'params'      => array($id),
        ),
    );
}

// The fields of a saved reply as ws_saved_reply_present() gives them.
function ws_api_saved_reply_schema()
{
    return array(
        'id'         => 'integer',
        'scope'      => 'string',
        'shortcut'   => 'string',
        'title'      => 'string',
        'body'       => 'string',
        'created_at' => 'string?',
        'updated_at' => 'string?',
    );
}

/**
 * The refusal for what ws_saved_reply_save_check() turned down.
 *
 * @param array $check
 */
function ws_api_saved_reply_refuse($check)
{
    $error = (string) $check['error'];
    $field = ((string) ($check['field'] ?? '') !== '') ? (string) $check['field'] : null;
    $message = ws_saved_reply_error_text($error, $check['scope'] ?? WS_SAVED_REPLY_PERSONAL);

    switch ((int) $check['status']) {
        case 403:
            api_fail(403, 'forbidden', $message);
            break;

        case 503:
            api_fail(503, 'service_unavailable', $message);
            break;

        case 409:
            api_fail(409, $error, $message, $field);
            break;

        default:
            api_fail_validation($message, $field);
    }
}

/**
 * One of the owner's replies or one of the team's, or 404 (also for anybody
 * else's personal reply).
 *
 * @param array $viewer
 * @param mixed $id
 * @return array
 */
function ws_api_saved_reply_or_404($viewer, $id)
{
    if (!ws_saved_replies_ready()) {
        ws_api_saved_reply_refuse(array('status' => 503, 'error' => 'not_ready'));
    }

    $row = ws_saved_reply_find((int) $viewer['id'], (int) $id);

    if ($row === null) {
        $row = ws_saved_reply_find((int) $viewer['id'], (int) $id, WS_SAVED_REPLY_TEAM);
    }

    if ($row === null) {
        api_fail_not_found(lang('Saved reply'));
    }

    return $row;
}

/**
 * The fields of a write that were sent.
 *
 * @param array $params
 * @return array
 */
function ws_api_saved_reply_input($params)
{
    $data = array();

    foreach (array('shortcut', 'title', 'body') as $field) {
        if (array_key_exists($field, $params) && ($params[$field] !== null)) {
            $data[$field] = $params[$field];
        }
    }

    return $data;
}

function ws_api_saved_replies_list($params)
{
    $viewer = ws_api_viewer();
    $scope = ws_saved_reply_scope($params['scope'] ?? null);
    $out = array_map('ws_saved_reply_present', ws_saved_replies_rows((int) $viewer['id'], $scope));

    api_ok_list($out, ws_saved_reply_limit($scope), null, count($out));
}

function ws_api_saved_replies_get($params)
{
    $viewer = ws_api_viewer();

    api_ok(ws_saved_reply_present(ws_api_saved_reply_or_404($viewer, $params['id'])));
}

function ws_api_saved_replies_create($params)
{
    $viewer = ws_api_viewer();
    $data = ws_api_saved_reply_input($params);
    $scope = ws_saved_reply_scope($params['scope'] ?? null);
    $checked = ws_saved_reply_save_check($viewer, $data, null, $scope);

    if (!$checked['ok']) {
        ws_api_saved_reply_refuse(array_merge($checked, array('scope' => $scope)));
    }

    api_dry_run_stop('created', 'workspace_saved_reply', array('shortcut' => $checked['sets']['shortcut'], 'scope' => $scope));

    $result = ws_saved_reply_save($viewer, $data, null, $scope);

    if (!$result['ok']) {
        ws_api_saved_reply_refuse(array_merge($result, array('scope' => $scope)));
    }

    api_ok(ws_saved_reply_present(ws_api_saved_reply_or_404($viewer, $result['id'])), 201);
}

function ws_api_saved_replies_update($params)
{
    $viewer = ws_api_viewer();
    $row = ws_api_saved_reply_or_404($viewer, $params['id']);
    $data = ws_api_saved_reply_input($params);
    $checked = ws_saved_reply_save_check($viewer, $data, $row);

    if (!$checked['ok']) {
        ws_api_saved_reply_refuse(array_merge($checked, array('scope' => $row['scope'])));
    }

    api_dry_run_stop('updated', 'workspace_saved_reply', array('id' => (int) $row['id'], 'fields' => array_keys($checked['sets'])));

    $result = ws_saved_reply_save($viewer, $data, $row);

    if (!$result['ok']) {
        ws_api_saved_reply_refuse(array_merge($result, array('scope' => $row['scope'])));
    }

    api_ok(ws_saved_reply_present(ws_api_saved_reply_or_404($viewer, $row['id'])));
}

function ws_api_saved_replies_delete($params)
{
    $viewer = ws_api_viewer();
    $row = ws_api_saved_reply_or_404($viewer, $params['id']);

    if ((ws_saved_reply_scope($row['scope']) === WS_SAVED_REPLY_TEAM) && !ws_saved_reply_can_manage_team($viewer)) {
        ws_api_saved_reply_refuse(array('status' => 403, 'error' => 'not_allowed', 'scope' => WS_SAVED_REPLY_TEAM));
    }

    api_dry_run_stop('deleted', 'workspace_saved_reply', array('id' => (int) $row['id']));

    ws_saved_reply_delete($viewer, $row);

    api_ok(array('id' => (int) $row['id'], 'deleted' => true));
}
