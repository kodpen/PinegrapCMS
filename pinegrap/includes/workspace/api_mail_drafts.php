<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace API: e-mail drafts kept on the server (mail_drafts.php). Every
 * caller that reads a conversation may keep a draft of its answer, and a
 * person a draft of a new mail; a draft is never sent from here. Sending one
 * is the person's, from the E-mails screen.
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
 * The drafts' route rows, merged into ws_mail_api_routes().
 *
 * @return array
 */
function ws_mail_draft_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $never = ' A draft is never sent by itself: the person sends it from the E-mails screen.';
    $fields = array(
        array('name' => 'to', 'in' => 'body', 'type' => 'string', 'max_length' => WS_MAIL_DRAFT_ADDRESSES_MAX, 'description' => 'Addresses, separated by commas, kept as written; checked when the mail is sent. A reply goes to its conversation\'s customer: to is not kept for it.'),
        array('name' => 'cc', 'in' => 'body', 'type' => 'string', 'max_length' => WS_MAIL_DRAFT_ADDRESSES_MAX, 'description' => 'Addresses, separated by commas.'),
        array('name' => 'bcc', 'in' => 'body', 'type' => 'string', 'max_length' => WS_MAIL_DRAFT_ADDRESSES_MAX, 'description' => 'Addresses, separated by commas; not shown to the other recipients.'),
        array('name' => 'subject', 'in' => 'body', 'type' => 'string', 'max_length' => WS_MAIL_DRAFT_SUBJECT_MAX, 'description' => 'A new mail\'s subject. A reply keeps its conversation\'s: subject is not kept for it.'),
        array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => WS_MAIL_REPLY_MAX, 'description' => 'The mail as plain text, without a greeting from the person\'s name or a Signature: the name, the mailbox\'s Signature and, for a reply, the customer\'s last mail quoted are added when it is sent.'),
    );

    return array(
        array(
            'id'          => 'workspace.mail_drafts.list',
            'method'      => 'GET',
            'path'        => '/workspace/mail/drafts',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_mail_drafts_list',
            'returns'     => array('list' => 'WorkspaceMailDraft'),
            'summary'     => 'List e-mail drafts',
            'description' => 'The e-mail drafts kept on the server that the owner of the application reads, the newest change first: the drafts of answers in the conversations of channels the owner reads (kind reply), and the owner\'s own drafts of new mails (kind new), which nobody else sees.' . $never,
            'params'      => array(
                array('name' => 'kind', 'in' => 'query', 'type' => 'enum', 'values' => array('all', 'reply', 'new'), 'default' => 'all', 'description' => 'reply: drafts of answers; new: drafts of new mails.'),
                array('name' => 'conversation_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only the drafts answering this conversation.'),
                array('name' => 'box_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only the drafts of this mailbox.'),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 50, 'description' => 'Drafts per page.'),
                array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200, 'description' => 'next_cursor from the previous page.'),
            ),
        ),

        array(
            'id'          => 'workspace.mail_drafts.get',
            'method'      => 'GET',
            'path'        => '/workspace/mail/drafts/{id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_mail_drafts_get',
            'returns'     => 'WorkspaceMailDraft',
            'summary'     => 'One e-mail draft',
            'description' => 'A draft the owner reads, as the list gives it; 404 otherwise.' . $never,
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.mail_drafts.create',
            'method'      => 'POST',
            'path'        => '/workspace/mail/drafts',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_mail_drafts_create',
            'dry_run'     => true,
            'returns'     => 'WorkspaceMailDraft',
            'status'      => 201,
            'summary'     => 'Keep an e-mail draft',
            'description' => 'Keeps a draft for the person to send. conversation_id makes it an answer to that conversation, by whoever may write in its channel (403 otherwise, 404 on conversation_id for one the owner does not read). Without it, box_id makes it a new mail from a mailbox the owner may send from (422 on box_id otherwise), seen by the owner alone. A draft may be unfinished, but not empty; a person keeps at most ' . WS_MAIL_DRAFTS_MAX . ' (422). Send an Idempotency-Key so a retry does not keep it twice.' . $never,
            'params'      => array_merge(array(
                array('name' => 'conversation_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The conversation the draft answers (GET /workspace/mail/conversations).'),
                array('name' => 'box_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'For a new mail: the mailbox it is to be sent from.'),
            ), $fields),
        ),

        array(
            'id'           => 'workspace.mail_drafts.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/mail/drafts/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_mail_drafts_update',
            'dry_run'      => true,
            'returns'      => 'WorkspaceMailDraft',
            'summary'      => 'Change an e-mail draft',
            'description'  => 'Changes only the fields sent: an answer\'s by whoever may write in its conversation\'s channel, a new mail\'s by its writer (404 for a draft the owner does not read, 403 for one they may only read). A change that would leave it empty is refused with 422; delete it instead.' . $never,
            'params'       => array_merge(array($id), $fields),
        ),

        array(
            'id'           => 'workspace.mail_drafts.delete',
            'method'       => 'DELETE',
            'path'         => '/workspace/mail/drafts/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_mail_drafts_delete',
            'dry_run'      => true,
            'returns'      => 'WorkspaceMailDraft',
            'summary'      => 'Delete an e-mail draft',
            'description'  => 'Deletes a draft, by those who may change it (404, 403 as for a change), and answers it as it was. POST /workspace/mail/drafts/{id}/delete does the same where DELETE is not let through.',
            'params'       => array($id),
        ),

        array(
            'id'           => 'workspace.mail_drafts.delete_post',
            'method'       => 'POST',
            'path'         => '/workspace/mail/drafts/{id}/delete',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_mail_drafts_delete',
            'dry_run'      => true,
            'returns'      => 'WorkspaceMailDraft',
            'mcp'          => false,
            'summary'      => 'Delete an e-mail draft (POST)',
            'description'  => 'The same as DELETE /workspace/mail/drafts/{id}, for servers that do not let DELETE through.',
            'params'       => array($id),
        ),
    );
}

/**
 * A draft as the API gives it. Pure.
 *
 * @param array $draft ws_mail_items row
 * @return array
 */
function ws_api_mail_draft_present($draft)
{
    return array(
        'id'              => (int) $draft['id'],
        'kind'            => ((int) $draft['conversation_id'] > 0) ? 'reply' : 'new',
        'conversation_id' => ((int) $draft['conversation_id'] > 0) ? (int) $draft['conversation_id'] : null,
        'box_id'          => (int) $draft['box_id'],
        'to'              => (string) ($draft['to_list'] ?? ''),
        'cc'              => (string) ($draft['cc_list'] ?? ''),
        'bcc'             => (string) ($draft['bcc_list'] ?? ''),
        'subject'         => (string) $draft['subject'],
        'text'            => (string) $draft['body_text'],
        'user_id'         => ((int) $draft['user_id'] > 0) ? (int) $draft['user_id'] : null,
        'updated_at'      => api_time($draft['created_at']),
    );
}

// What ws_api_mail_draft_present() returns.
function ws_api_mail_draft_schema()
{
    return array(
        'id'              => 'integer',
        'kind'            => 'string',
        'conversation_id' => 'integer?',
        'box_id'          => 'integer',
        'to'              => 'string',
        'cc'              => 'string',
        'bcc'             => 'string',
        'subject'         => 'string',
        'text'            => 'string',
        'user_id'         => 'integer?',
        'updated_at'      => 'string?',
    );
}

/**
 * How a refused draft is told. Pure.
 *
 * @param string $error ws_mail_draft_create_check() error
 * @return array status, code
 */
function ws_api_mail_draft_refusal($error)
{
    switch ((string) $error) {
        case 'not_ready':
            return array('status' => 503, 'code' => 'service_unavailable');

        case 'conversation':
            return array('status' => 404, 'code' => 'not_found');

        case 'no_access':
            return array('status' => 403, 'code' => 'forbidden');
    }

    return array('status' => 422, 'code' => 'validation_failed');
}

/**
 * The application's own reach on top of a channel's: the Claude application
 * only where Claude may be asked.
 *
 * @return callable channel row => bool
 */
function ws_api_mail_draft_reach()
{
    return function ($channel) {
        return !ws_api_is_claude() || ws_claude_channel_allowed($channel);
    };
}

/**
 * A draft the application reaches, or a 404; with $write a 403 for one it
 * may only read.
 *
 * @param array $viewer
 * @param int   $id
 * @param bool  $write
 * @return array ws_mail_items row
 */
function ws_api_mail_draft_or_404($viewer, $id, $write = false)
{
    if (!ws_mail_drafts_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $draft = ws_mail_draft((int) $id);

    if (!$draft || !ws_mail_draft_reached($viewer, $draft, false, ws_api_mail_draft_reach())) {
        api_fail_not_found(lang('E-mail draft'));
    }

    if ($write && !ws_mail_draft_reached($viewer, $draft, true, ws_api_mail_draft_reach())) {
        api_fail(403, 'forbidden', ws_mail_draft_error_text('no_access'));
    }

    return $draft;
}

function ws_api_mail_drafts_list($params)
{
    $viewer = ws_api_viewer();

    if (!ws_mail_drafts_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $cursor = null;

    if (isset($params['cursor']) && ($params['cursor'] !== '')) {
        $cursor = api_cursor_decode($params['cursor']);

        if ($cursor === null) {
            api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
        }
    }

    $limit = (int) ($params['limit'] ?? 50);
    $kind = (string) ($params['kind'] ?? 'all');
    $rows = ws_mail_drafts_list($viewer, array(
        'kind'            => ($kind === 'all') ? '' : $kind,
        'conversation_id' => (int) ($params['conversation_id'] ?? 0),
        'box_id'          => (int) ($params['box_id'] ?? 0),
    ), $limit, $cursor, ws_api_mail_draft_reach());
    $next = null;

    if (count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
        $end = end($rows);
        $next = api_cursor_encode((int) $end['created_at'], (int) $end['id']);
    }

    api_ok_list(array_values(array_map('ws_api_mail_draft_present', $rows)), $limit, $next);
}

function ws_api_mail_drafts_get($params)
{
    api_ok(ws_api_mail_draft_present(ws_api_mail_draft_or_404(ws_api_viewer(), $params['id'])));
}

function ws_api_mail_drafts_create($params)
{
    $viewer = ws_api_viewer();
    $checked = ws_mail_draft_create_check($viewer, $params, ws_api_mail_draft_reach());

    if (!$checked['ok']) {
        $refusal = ws_api_mail_draft_refusal($checked['error']);
        api_fail($refusal['status'], $refusal['code'], ws_mail_draft_error_text($checked['error']), $checked['field']);
    }

    api_dry_run_stop('created', 'workspace_mail_draft', array('kind' => $checked['kind'], 'box_id' => (int) $checked['box']['id'],
        'conversation_id' => (int) ($checked['conversation']['id'] ?? 0)));

    $id = ws_mail_draft_insert($viewer, $checked, time());
    $draft = ($id > 0) ? ws_mail_draft($id) : null;

    if (!$draft) {
        api_fail(500, 'server_error', lang('The draft could not be kept.'));
    }

    api_ok(ws_api_mail_draft_present($draft), 201);
}

function ws_api_mail_drafts_update($params)
{
    $viewer = ws_api_viewer();
    $draft = ws_api_mail_draft_or_404($viewer, $params['id'], true);
    $input = ws_mail_draft_input($params);

    if (!$input['ok']) {
        api_fail_validation(ws_mail_draft_error_text($input['error']), $input['field']);
    }

    $merged = ws_mail_draft_merge($draft, $input['fields']);

    if (!ws_mail_draft_has_words(((int) $draft['conversation_id'] > 0) ? array('text' => $merged['text'], 'cc' => $merged['cc'], 'bcc' => $merged['bcc']) : $merged)) {
        api_fail_validation(ws_mail_draft_error_text('empty'), 'text');
    }

    api_dry_run_stop('updated', 'workspace_mail_draft', array('id' => (int) $draft['id'], 'fields' => array_keys($input['fields'])));

    ws_mail_draft_update($draft, $merged, time());

    api_ok(ws_api_mail_draft_present(ws_mail_draft((int) $draft['id']) ?: $draft));
}

function ws_api_mail_drafts_delete($params)
{
    $viewer = ws_api_viewer();
    $draft = ws_api_mail_draft_or_404($viewer, $params['id'], true);

    api_dry_run_stop('deleted', 'workspace_mail_draft', array('id' => (int) $draft['id']));

    ws_mail_draft_delete((int) $draft['id']);

    api_ok(ws_api_mail_draft_present($draft));
}
