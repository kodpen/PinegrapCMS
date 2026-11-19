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
        array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => WS_MAIL_REPLY_MAX, 'description' => 'The mail as plain text, without a greeting from the person\'s name or a Signature: the name, the mailbox\'s Signature and, for a reply, the customer\'s last mail quoted are added when it is sent. Left empty with html, it is made from the HTML\'s words when the draft is sent.'),
        ws_api_mail_html_param(),
        ws_api_mail_file_ids_param(true),
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
            'id'          => 'workspace.mail_drafts.send',
            'method'      => 'POST',
            'path'        => '/workspace/mail/drafts/{id}/send',
            'scope'       => 'workspace:mail_send',
            'handler'     => 'ws_api_mail_drafts_send',
            'dry_run'     => true,
            'returns'     => 'WorkspaceMailConversation',
            'status'      => 201,
            'summary'     => 'Send an e-mail draft',
            'description' => 'Sends a draft as the E-mails screen\'s Send does and deletes it once the mail is stored: an answer goes to its conversation\'s customer with the person\'s name, the mailbox\'s Signature and the customer\'s last mail quoted; a new mail becomes a new conversation whose card is in channel_id (the mailbox\'s own channel when left out). It is sent in the person\'s name, by the person\'s own team devices session (POST /auth/login) or by an OAuth connection the person allowed to send e-mail (workspace:mail_send); an application key is refused with 403. The panel\'s checks hold: the addresses (422 to), the mailbox and the channel (403), ' . WS_MAIL_SEND_HOURLY . ' mails an hour (429), and 409 unknown_domain for an address whose domain has no mail or web record, sent again with send_anyway true. The answer is the conversation with the mail, send_state queued while it waits. The site\'s activity log names the application it went through. Send an Idempotency-Key: a retry after a timeout must not send the mail twice.',
            'params'      => array(
                $id,
                array('name' => 'channel_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'For a new mail: the channel its card is kept in, the mailbox\'s own or one its routes write to. Left out: the mailbox\'s own.'),
                array('name' => 'send_anyway', 'in' => 'body', 'type' => 'bool', 'description' => 'true sends the mail after a 409 unknown_domain warning.'),
            ),
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
        // The cleaned HTML body and the Files ids it keeps (MT5).
        'html'            => ((string) ($draft['body_html'] ?? '') !== '') ? (string) $draft['body_html'] : null,
        'file_ids'        => ws_mail_draft_file_ids($draft),
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
        'html'            => 'string?',
        'file_ids'        => array('integer'),
        'user_id'         => 'integer?',
        'updated_at'      => 'datetime?',
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

    // Every other refusal, a file's too, is the caller's to correct.
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
        api_fail($refusal['status'], $refusal['code'], ws_mail_draft_error_text($checked['error'], $checked['detail']), $checked['field']);
    }

    api_dry_run_stop('created', 'workspace_mail_draft', array('kind' => $checked['kind'], 'box_id' => (int) $checked['box']['id'],
        'conversation_id' => (int) ($checked['conversation']['id'] ?? 0)));

    $id = ws_mail_draft_insert($viewer, $checked, time());

    if ($id > 0) {
        ws_mail_draft_keep_body($id, array_intersect_key($checked['fields'], array('html' => 1, 'file_ids' => 1)));
        // The application it came through, once; the Drafts view names it.
        ws_mail_draft_keep_app($id, ws_api_writer_app_id());
    }

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
    // The HTML and the files it keeps, as they are after the change.
    $body = array_merge(array('html' => (string) ($draft['body_html'] ?? ''), 'file_ids' => ws_mail_draft_file_ids($draft)),
        array_intersect_key($input['fields'], array('html' => 1, 'file_ids' => 1)));
    $words = array_merge($merged, $body);

    if (!ws_mail_draft_has_words(((int) $draft['conversation_id'] > 0) ? ws_mail_draft_reply_words($words) : $words)) {
        api_fail_validation(ws_mail_draft_error_text('empty'), 'text');
    }

    $checked = ws_mail_draft_body_check($viewer, array_intersect_key($input['fields'], array('html' => 1, 'file_ids' => 1)), ws_api_mail_draft_reach());

    if (!$checked['ok']) {
        $refusal = ws_api_mail_draft_refusal($checked['error']);
        api_fail($refusal['status'], $refusal['code'], ws_mail_draft_error_text($checked['error'], $checked['detail']), $checked['field']);
    }

    api_dry_run_stop('updated', 'workspace_mail_draft', array('id' => (int) $draft['id'], 'fields' => array_keys($input['fields'])));

    ws_mail_draft_update($draft, $merged, time());
    ws_mail_draft_keep_body((int) $draft['id'], array_intersect_key($input['fields'], array('html' => 1, 'file_ids' => 1)));

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

/**
 * The mail a draft sends, as ws_mail_send() takes it: an answer in its
 * conversation, or a new mail from its box, with its HTML (the text made
 * from it when the draft has none) and the files read for the sender. Pure.
 *
 * @param array $draft  ws_mail_items row
 * @param array $params channel_id, send_anyway
 * @param array $files  ws_mail_send_files_by_id()'s files
 * @return array
 */
function ws_api_mail_draft_mail($draft, $params, $files = array())
{
    $anyway = !empty($params['send_anyway']);
    $html = (string) ($draft['body_html'] ?? '');
    $text = ws_mail_text_or_html((string) $draft['body_text'], $html);

    if ((int) $draft['conversation_id'] > 0) {
        return array(
            'conversation_id' => (int) $draft['conversation_id'],
            'cc'              => (string) ($draft['cc_list'] ?? ''),
            'bcc'             => (string) ($draft['bcc_list'] ?? ''),
            'text'            => $text,
            'html'            => $html,
            'files'           => array_values((array) $files),
            'send_anyway'     => $anyway,
        );
    }

    return ws_api_mail_new_mail(array(
        'box_id'      => (int) $draft['box_id'],
        'channel_id'  => (int) ($params['channel_id'] ?? 0),
        'to'          => (string) ($draft['to_list'] ?? ''),
        'cc'          => (string) ($draft['cc_list'] ?? ''),
        'bcc'         => (string) ($draft['bcc_list'] ?? ''),
        'subject'     => (string) $draft['subject'],
        'text'        => $text,
        'html'        => $html,
        'send_anyway' => $anyway ? '1' : '',
    ), $files);
}

function ws_api_mail_drafts_send($params)
{
    ws_api_mail_send_gate();

    $viewer = ws_api_viewer();
    $draft = ws_api_mail_draft_or_404($viewer, $params['id'], true);
    // The sender's right to each kept file is checked again now: a file may
    // have moved, or the draft been kept by someone else.
    $files = ws_api_mail_files_or_fail($viewer, ws_mail_draft_file_ids($draft), 'file_ids');
    $sent = ws_api_mail_send_checked($viewer, ws_api_mail_draft_mail($draft, $params, $files));

    // The mail is stored; the draft it came from goes, its file list with it.
    ws_mail_draft_delete((int) $draft['id']);

    api_ok(ws_api_mail_conversation_answer(ws_api_mail_conversation_or_404($viewer, $sent['conversation_id'])), 201);
}
