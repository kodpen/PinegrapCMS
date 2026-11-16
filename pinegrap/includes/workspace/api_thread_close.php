<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - concluding and renaming a discussion and taking back or
 * closing an approval request, in the external API and MCP:
 * POST /workspace/threads/{id}/close, POST /workspace/threads/{id} and
 * POST /workspace/approvals/{id}/close. Each runs the panel's own function
 * (threads.php, approvals.php) after the check it shares with it, so a dry
 * run meets the same refusals; who may is that function's rule.
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
 * The routes of this file, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_thread_close_api_routes()
{
    return array(
        array(
            'id'          => 'workspace.threads.close',
            'method'      => 'POST',
            'path'        => '/workspace/threads/{id}/close',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_threads_close',
            'dry_run'     => true,
            'returns'     => 'WorkspaceThread',
            'summary'     => 'Conclude a discussion',
            'description' => 'Concludes a discussion the way Conclude in its menu does; id is thread.id of the message. It is kept, read only, for 30 days and then deleted; its decisions and tasks stay in the channel, and it gets a line saying who concluded it. Anybody in the discussion may: the owner of the application has to be in it (joined on the discussion), or 403 forbidden. One already concluded is no error: the answer is the discussion either way.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'           => 'workspace.threads.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/threads/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_threads_update',
            'dry_run'      => true,
            'returns'      => 'WorkspaceThread',
            'summary'      => 'Rename a discussion',
            'description'  => 'Gives a discussion a new title, the way Rename in its menu does; id is thread.id of the message. Anybody who may write in the discussion may, while it is open: someone not in it, or a concluded discussion, is refused with 403 forbidden; a title that is empty once its spaces are taken out with 422 on title.',
            'params'       => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => WS_THREAD_TITLE_MAX, 'required' => true, 'description' => 'The new title; runs of spaces and line breaks become one space.'),
            ),
        ),

        array(
            'id'          => 'workspace.approvals.close',
            'method'      => 'POST',
            'path'        => '/workspace/approvals/{id}/close',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_approvals_close',
            'dry_run'     => true,
            'returns'     => 'WorkspaceApproval',
            'summary'     => 'Take back or close an approval request',
            'description' => 'Closes an open approval request with nothing decided, the way Take back (for the person who asked) and Close (for staff) on its card do: its outcome is withdrawn, the agents still to answer are asked no more, and the channel gets a line saying so. The owner of the application has to be the person who asked or staff (the User role closes only their own request), or 403 forbidden; a request already closed is refused with 422.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),
    );
}

/**
 * Concludes a discussion as Conclude in its menu does (ws_thread_close()).
 */
function ws_api_threads_close($params)
{
    $viewer = ws_api_viewer();

    ws_api_threads_ready();

    list($room, $thread) = ws_api_thread_room_or_404($viewer, $params['id']);

    $checked = ws_thread_close_check($viewer, $room);

    if (!$checked['ok']) {
        api_fail(403, 'forbidden', $checked['error']);
    }

    api_dry_run_stop('closed', 'workspace_thread', array('id' => (int) $room['id'], 'channel_id' => (int) $thread['parent_channel_id'], 'concluded' => $checked['concluded']));

    $result = ws_thread_close($viewer, $room);

    if (!$result['ok']) {
        api_fail(403, 'forbidden', $result['error']);
    }

    api_ok(ws_api_thread_present($viewer, ws_thread($room['id']), ws_channel($room['id'])));
}

/**
 * Renames a discussion as Rename in its menu does (ws_thread_rename()).
 */
function ws_api_threads_update($params)
{
    $viewer = ws_api_viewer();

    ws_api_threads_ready();

    list($room, $thread) = ws_api_thread_room_or_404($viewer, $params['id']);

    $checked = ws_thread_rename_check($viewer, $room, (string) $params['title']);

    if (!$checked['ok']) {
        if ($checked['field'] === '') {
            api_fail(403, 'forbidden', $checked['error']);
        }

        api_fail_validation($checked['error'], $checked['field']);
    }

    api_dry_run_stop('updated', 'workspace_thread', array('id' => (int) $room['id'], 'channel_id' => (int) $thread['parent_channel_id'], 'title' => $checked['title']));

    $result = ws_thread_rename($viewer, $room, (string) $params['title']);

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'title');
    }

    api_ok(ws_api_thread_present($viewer, ws_thread($room['id']), ws_channel($room['id'])));
}

/**
 * Takes back or closes an approval request as its card does
 * (ws_approval_close()).
 */
function ws_api_approvals_close($params)
{
    $viewer = ws_api_viewer();
    $approval = ws_api_approval_or_404($viewer, $params['id']);
    $checked = ws_approval_close_check($viewer, $approval);

    if ($checked['refusal'] === 'missing') {
        api_fail_not_found(lang('Approval request'));
    }

    if ($checked['refusal'] === 'forbidden') {
        api_fail(403, 'forbidden', $checked['error']);
    }

    if (!$checked['ok']) {
        api_fail_validation($checked['error']);
    }

    api_dry_run_stop('closed', 'workspace_approval', array('id' => (int) $approval['id'], 'message_id' => (int) $approval['message_id'], 'outcome' => 'withdrawn'));

    $result = ws_approval_close($viewer, $approval);

    if (!$result['ok']) {
        api_fail_validation($result['error']);
    }

    api_ok(ws_api_approval_present($viewer, ws_approval($approval['id'])));
}
