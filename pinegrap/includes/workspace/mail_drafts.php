<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: e-mail drafts kept on the server. A draft is a ws_mail_items
 * row going out (direction out) whose send_state is 'draft': nothing sends
 * it, and the readers of sent mail leave it out. A reply's draft belongs to
 * a conversation and is read by whoever reads its channel; a new mail's
 * draft has no conversation yet (conversation_id 0) and is read by its
 * writer alone. Sending one is the person's (the reply drawer or the New
 * e-mail form); the draft row is deleted once the mail is stored.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The send_state of a draft. */
define('WS_MAIL_DRAFT_STATE', 'draft');

/** The most drafts one person keeps. */
define('WS_MAIL_DRAFTS_MAX', 200);

/** The longest To, Cc or Bcc field of a draft, as typed. */
define('WS_MAIL_DRAFT_ADDRESSES_MAX', 6000);

/** The longest subject of a draft. */
define('WS_MAIL_DRAFT_SUBJECT_MAX', 200);

/**
 * Whether drafts can be kept: the send state of 11.0.185.
 *
 * @return bool
 */
function ws_mail_drafts_ready()
{
    return function_exists('ws_mail_send_ready') && ws_mail_send_ready();
}

/**
 * Whether an item is a draft. Pure.
 *
 * @param array|null $item ws_mail_items row
 * @return bool
 */
function ws_mail_item_is_draft($item)
{
    return is_array($item) && ((string) ($item['direction'] ?? '') === 'out')
        && ((string) ($item['send_state'] ?? '') === WS_MAIL_DRAFT_STATE);
}

/**
 * The SQL that leaves drafts out of a read of ws_mail_items; before the
 * send state is kept there are none, and the column is not there to name.
 *
 * @param string $alias the table's alias, '' for none
 * @param bool   $ready whether the send state is kept; null asks
 * @return string
 */
function ws_mail_drafts_excluded_sql($alias = '', $ready = null)
{
    if (!(($ready === null) ? ws_mail_drafts_ready() : $ready)) {
        return '1 = 1';
    }

    $column = (($alias !== '') ? $alias . '.' : '') . 'send_state';

    return $column . " <> '" . WS_MAIL_DRAFT_STATE . "'";
}

/**
 * What a draft asks to keep, checked without the database. A draft may be
 * unfinished: an address field is kept as typed, the subject and text may be
 * empty, but not everything at once. Pure.
 *
 * @param array $draft to, cc, bcc, subject, text, html, file_ids (only the keys sent are read)
 * @return array ok, error (too_long | addresses | subject | html | too_many_files), fields (the keys sent, cleaned)
 */
function ws_mail_draft_input($draft)
{
    $draft = is_array($draft) ? $draft : array();
    $fields = array();

    foreach (array('to', 'cc', 'bcc') as $key) {
        if (array_key_exists($key, $draft)) {
            $value = is_scalar($draft[$key]) ? trim(preg_replace('/\s+/u', ' ', (string) $draft[$key])) : '';

            if (mb_strlen($value) > WS_MAIL_DRAFT_ADDRESSES_MAX) {
                return array('ok' => false, 'error' => 'addresses', 'field' => $key, 'fields' => array());
            }

            $fields[$key] = $value;
        }
    }

    if (array_key_exists('subject', $draft)) {
        $subject = is_scalar($draft['subject']) ? trim(preg_replace('/\s+/u', ' ', (string) $draft['subject'])) : '';

        if (mb_strlen($subject) > WS_MAIL_DRAFT_SUBJECT_MAX) {
            return array('ok' => false, 'error' => 'subject', 'field' => 'subject', 'fields' => array());
        }

        $fields['subject'] = $subject;
    }

    if (array_key_exists('text', $draft)) {
        $text = is_scalar($draft['text']) ? rtrim(str_replace(array("\r\n", "\r"), "\n", (string) $draft['text'])) : '';

        if (mb_strlen($text) > WS_MAIL_REPLY_MAX) {
            return array('ok' => false, 'error' => 'too_long', 'field' => 'text', 'fields' => array());
        }

        $fields['text'] = $text;
    }

    // The HTML body, cleaned as a sent mail's is (pg_mail_html_clean());
    // HTML the cleaner refuses (over its size) is refused, not kept bare.
    if (array_key_exists('html', $draft)) {
        $html = is_string($draft['html']) ? trim($draft['html']) : '';

        if ($html !== '') {
            $html = function_exists('pg_mail_html_clean') ? pg_mail_html_clean($html) : '';

            if ($html === '') {
                return array('ok' => false, 'error' => 'html', 'field' => 'html', 'fields' => array());
            }
        }

        $fields['html'] = $html;
    }

    // Files by their Files id (files.id), a list or a comma list; whether
    // the writer may send each is the caller's check.
    if (array_key_exists('file_ids', $draft)) {
        $ids = ws_mail_compose_file_ids(is_array($draft['file_ids']) ? implode(',', array_filter($draft['file_ids'], 'is_scalar')) : $draft['file_ids']);

        if (count($ids) > WS_MAIL_SEND_FILES) {
            return array('ok' => false, 'error' => 'too_many_files', 'field' => 'file_ids', 'fields' => array());
        }

        $fields['file_ids'] = $ids;
    }

    return array('ok' => true, 'error' => '', 'field' => null, 'fields' => $fields);
}

/**
 * Whether a draft's files can be kept: ws_mail_items.draft_files (11.0.206).
 *
 * @return bool
 */
function ws_mail_draft_files_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('ws_mail_items', 'draft_files');
}

/**
 * The file ids a draft keeps (draft_files, a comma list of files.id). Pure.
 *
 * @param array $draft ws_mail_items row
 * @return int[]
 */
function ws_mail_draft_file_ids($draft)
{
    return ws_mail_compose_file_ids((string) ($draft['draft_files'] ?? ''));
}

/**
 * Keeps a draft's HTML and files, only the keys given: body_html ('' keeps
 * NULL) and draft_files (none keeps NULL). The columns come in steps of
 * their own, so each is written only once it is there.
 *
 * @param int   $id
 * @param array $fields html, file_ids (ws_mail_draft_input()'s)
 * @return bool
 */
function ws_mail_draft_keep_body($id, $fields)
{
    $sets = array();

    if (array_key_exists('html', $fields) && function_exists('ws_mail_html_ready') && ws_mail_html_ready()) {
        $sets[] = 'body_html = ' . (((string) $fields['html'] === '') ? 'NULL' : "'" . e((string) $fields['html']) . "'");
    }

    if (array_key_exists('file_ids', $fields) && ws_mail_draft_files_ready()) {
        $ids = array_map('intval', (array) $fields['file_ids']);
        $sets[] = 'draft_files = ' . ($ids ? "'" . e(implode(',', $ids)) . "'" : 'NULL');
    }

    if (!$sets) {
        return true;
    }

    return (bool) db("UPDATE ws_mail_items SET " . implode(', ', $sets) . "
        WHERE id = '" . (int) $id . "' AND send_state = '" . WS_MAIL_DRAFT_STATE . "'");
}

/**
 * Whether a draft, once its changes are made, still holds anything. Pure.
 *
 * @param array $fields to, cc, bcc, subject, text, html, file_ids
 * @return bool
 */
function ws_mail_draft_has_words($fields)
{
    foreach (array('to', 'cc', 'bcc', 'subject', 'text', 'html') as $key) {
        if (trim((string) ($fields[$key] ?? '')) !== '') {
            return true;
        }
    }

    return !empty($fields['file_ids']);
}

/**
 * The hash a draft takes in rfc_message_hash: ws_mail_items is unique on
 * (box_id, rfc_message_hash), so two drafts of one box may not share ''.
 *
 * @return string
 */
function ws_mail_draft_hash()
{
    return hash('sha256', 'draft:' . bin2hex(random_bytes(16)));
}

/**
 * A draft by id, or null for any row that is not one.
 *
 * @param int $id
 * @return array|null ws_mail_items row
 */
function ws_mail_draft($id)
{
    $row = db_item("SELECT * FROM ws_mail_items WHERE id = '" . (int) $id . "' AND direction = 'out' AND send_state = '" . WS_MAIL_DRAFT_STATE . "'");

    return (is_array($row) && $row) ? $row : null;
}

/**
 * Whether a person reads a draft (or, with $write, may change or delete
 * it): a reply's by its conversation's channel, a new mail's by its writer.
 *
 * @param array    $viewer
 * @param array    $draft   ws_mail_items row
 * @param bool     $write
 * @param callable $reached channel row => bool, the caller's own reach on top
 *                          of the channel's (the API narrows the Claude
 *                          application); null for none
 * @return bool
 */
function ws_mail_draft_reached($viewer, $draft, $write = false, $reached = null)
{
    if (!ws_mail_item_is_draft($draft)) {
        return false;
    }

    if ((int) $draft['conversation_id'] <= 0) {
        return ((int) $draft['user_id'] > 0) && ((int) $draft['user_id'] === (int) $viewer['id']);
    }

    $conversation = db_item("SELECT channel_id FROM ws_mail_conversations WHERE id = '" . (int) $draft['conversation_id'] . "'");
    $channel = is_array($conversation) ? ws_channel((int) $conversation['channel_id']) : null;

    if (!$channel || !ws_can_read_channel($viewer, $channel) || (($reached !== null) && !call_user_func($reached, $channel))) {
        return false;
    }

    return !$write || ws_mail_can_answer($viewer, $channel);
}

/**
 * How many drafts a person keeps.
 *
 * @param int $user_id
 * @return int
 */
function ws_mail_drafts_count($user_id)
{
    return (int) db_value("SELECT COUNT(*) FROM ws_mail_items
        WHERE direction = 'out' AND send_state = '" . WS_MAIL_DRAFT_STATE . "' AND user_id = '" . (int) $user_id . "'");
}

/**
 * The fields that make a reply's draft more than nothing: its To and
 * subject are the conversation's, so they do not count. Pure.
 *
 * @param array $fields
 * @return array text, cc, bcc, html, file_ids
 */
function ws_mail_draft_reply_words($fields)
{
    return array('text' => $fields['text'] ?? '', 'cc' => $fields['cc'] ?? '', 'bcc' => $fields['bcc'] ?? '',
        'html' => $fields['html'] ?? '', 'file_ids' => $fields['file_ids'] ?? array());
}

/**
 * Whether a draft's HTML and files can be kept: their columns are there,
 * and the writer may send each file (ws_mail_send_files_by_id(), sizes
 * only; Send reads them again with the sender's rights).
 *
 * @param array    $viewer
 * @param array    $fields ws_mail_draft_input()'s fields
 * @param callable $reached as ws_mail_draft_reached()
 * @return array ok, error (not_ready | too_many_files | files_too_large | file_refused), detail, field
 */
function ws_mail_draft_body_check($viewer, $fields, $reached = null)
{
    $ok = array('ok' => true, 'error' => '', 'detail' => '', 'field' => null);

    if (((string) ($fields['html'] ?? '') !== '') && !(function_exists('ws_mail_html_ready') && ws_mail_html_ready())) {
        return array('ok' => false, 'error' => 'not_ready', 'detail' => '', 'field' => null);
    }

    if (empty($fields['file_ids'])) {
        return $ok;
    }

    if (!ws_mail_draft_files_ready()) {
        return array('ok' => false, 'error' => 'not_ready', 'detail' => '', 'field' => null);
    }

    $files = ws_mail_send_files_by_id($viewer, $fields['file_ids'], $reached, false);

    return $files['ok'] ? $ok : array('ok' => false, 'error' => $files['error'], 'detail' => $files['detail'], 'field' => 'file_ids');
}

/**
 * Whether this person may keep this new draft: the conversation of a reply
 * (whoever may answer in its channel), or a box they may send from for a
 * new mail; the fields; the number they keep. Writes nothing.
 *
 * @param array    $viewer
 * @param array    $draft   conversation_id, or box_id; to, cc, bcc, subject, text
 * @param callable $reached as ws_mail_draft_reached()
 * @return array ok, error (not_ready | conversation | no_access | box_id | too_many | empty | the input's | ws_mail_draft_body_check()'s), detail, field, kind (reply | new), conversation, box, fields
 */
function ws_mail_draft_create_check($viewer, $draft, $reached = null)
{
    $out = array('ok' => false, 'error' => '', 'detail' => '', 'field' => null, 'kind' => '', 'conversation' => null, 'box' => null, 'fields' => array());

    if (!ws_mail_drafts_ready()) {
        return array_merge($out, array('error' => 'not_ready'));
    }

    $input = ws_mail_draft_input($draft);

    if (!$input['ok']) {
        return array_merge($out, array('error' => $input['error'], 'field' => $input['field']));
    }

    $fields = array_merge(array('to' => '', 'cc' => '', 'bcc' => '', 'subject' => '', 'text' => ''), $input['fields']);

    if ((int) ($draft['conversation_id'] ?? 0) > 0) {
        $conversation = db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . (int) $draft['conversation_id'] . "'");
        $channel = is_array($conversation) && $conversation ? ws_channel((int) $conversation['channel_id']) : null;

        if (!$channel || !ws_can_read_channel($viewer, $channel) || (($reached !== null) && !call_user_func($reached, $channel))) {
            return array_merge($out, array('error' => 'conversation', 'field' => 'conversation_id'));
        }

        if (!ws_mail_can_answer($viewer, $channel)) {
            return array_merge($out, array('error' => 'no_access'));
        }

        // An answer goes to the conversation's customer under its subject,
        // as the reply drawer sends it.
        if (!array_key_exists('to', $input['fields'])) {
            $fields['to'] = (string) $conversation['customer_email'];
        }

        if (!array_key_exists('subject', $input['fields'])) {
            $fields['subject'] = (string) $conversation['subject'];
        }

        $box = ws_mail_box((int) $conversation['box_id']);
        $out = array_merge($out, array('kind' => 'reply', 'conversation' => $conversation, 'box' => $box ?: array('id' => (int) $conversation['box_id'], 'address' => '')));
    } else {
        $box = null;

        foreach (ws_mail_send_boxes($viewer) as $sendable) {
            if ((int) $sendable['box']['id'] === (int) ($draft['box_id'] ?? 0)) {
                $box = $sendable['box'];
            }
        }

        if (!$box) {
            return array_merge($out, array('error' => 'box_id', 'field' => 'box_id'));
        }

        $out = array_merge($out, array('kind' => 'new', 'box' => $box));
    }

    if (!ws_mail_draft_has_words($out['kind'] === 'reply' ? ws_mail_draft_reply_words($fields) : $fields)) {
        return array_merge($out, array('error' => 'empty', 'field' => 'text', 'kind' => '', 'conversation' => null, 'box' => null));
    }

    $body = ws_mail_draft_body_check($viewer, $fields, $reached);

    if (!$body['ok']) {
        return array_merge($out, array('error' => $body['error'], 'detail' => $body['detail'], 'field' => $body['field'], 'kind' => '', 'conversation' => null, 'box' => null));
    }

    if (ws_mail_drafts_count((int) $viewer['id']) >= WS_MAIL_DRAFTS_MAX) {
        return array_merge($out, array('error' => 'too_many', 'kind' => '', 'conversation' => null, 'box' => null));
    }

    return array_merge($out, array('ok' => true, 'fields' => $fields));
}

/**
 * Keeps a checked new draft.
 *
 * @param array $viewer
 * @param array $checked ws_mail_draft_create_check()
 * @param int   $now
 * @return int the draft's id, 0 when it could not be kept
 */
function ws_mail_draft_insert($viewer, $checked, $now)
{
    $fields = $checked['fields'];

    db("INSERT INTO ws_mail_items (box_id, conversation_id, direction, rfc_message_hash, from_email, from_name,
            to_list, cc_list, bcc_list, subject, body_text, auth, user_id, send_state, created_at)
        VALUES ('" . (int) $checked['box']['id'] . "', '" . (int) ($checked['conversation']['id'] ?? 0) . "', 'out', '" . e(ws_mail_draft_hash()) . "',
            '" . e((string) ($checked['box']['address'] ?? '')) . "', '" . e(ws_person_name((int) $viewer['id'])) . "',
            '" . e($fields['to']) . "', '" . e($fields['cc']) . "', '" . e($fields['bcc']) . "',
            '" . e($fields['subject']) . "', '" . e($fields['text']) . "', 'pass', '" . (int) $viewer['id'] . "',
            '" . WS_MAIL_DRAFT_STATE . "', '" . (int) $now . "')");

    return (int) mysqli_insert_id(db::$con);
}

/**
 * The fields a change keeps, from the draft and the keys sent; a reply's
 * To and subject stay its conversation's. Pure.
 *
 * @param array $draft  ws_mail_items row
 * @param array $fields ws_mail_draft_input()'s fields
 * @return array to, cc, bcc, subject, text
 */
function ws_mail_draft_merge($draft, $fields)
{
    $merged = array(
        'to'      => (string) ($draft['to_list'] ?? ''),
        'cc'      => (string) ($draft['cc_list'] ?? ''),
        'bcc'     => (string) ($draft['bcc_list'] ?? ''),
        'subject' => (string) ($draft['subject'] ?? ''),
        'text'    => (string) ($draft['body_text'] ?? ''),
    );

    foreach ($fields as $key => $value) {
        // The HTML and the files are kept apart (ws_mail_draft_keep_body()).
        if (!array_key_exists($key, $merged) || ((int) ($draft['conversation_id'] ?? 0) > 0 && in_array($key, array('to', 'subject'), true))) {
            continue;
        }

        $merged[$key] = (string) $value;
    }

    return $merged;
}

/**
 * Writes a draft's change; created_at moves to the change, so the newest
 * change lists first.
 *
 * @param array $draft  ws_mail_items row
 * @param array $merged ws_mail_draft_merge()
 * @param int   $now
 * @return bool
 */
function ws_mail_draft_update($draft, $merged, $now)
{
    return (bool) db("UPDATE ws_mail_items SET to_list = '" . e($merged['to']) . "', cc_list = '" . e($merged['cc']) . "',
            bcc_list = '" . e($merged['bcc']) . "', subject = '" . e($merged['subject']) . "', body_text = '" . e($merged['text']) . "',
            created_at = '" . (int) $now . "'
        WHERE id = '" . (int) $draft['id'] . "' AND send_state = '" . WS_MAIL_DRAFT_STATE . "'");
}

/**
 * Deletes a draft; any other row is left alone.
 *
 * @param int $id
 * @return bool
 */
function ws_mail_draft_delete($id)
{
    return (bool) db("DELETE FROM ws_mail_items WHERE id = '" . (int) $id . "' AND direction = 'out' AND send_state = '" . WS_MAIL_DRAFT_STATE . "'");
}

/**
 * The drafts a person reads, the newest change first: their own new mails'
 * and the replies' of the conversations whose channel they read.
 *
 * @param array    $viewer
 * @param array    $filters kind (reply | new | ''), conversation_id, box_id
 * @param int      $limit
 * @param array    $cursor  v (created_at), i (id) of the last row, or null
 * @param callable $reached as ws_mail_draft_reached()
 * @return array rows (one more than $limit when there are more)
 */
function ws_mail_drafts_list($viewer, $filters, $limit, $cursor = null, $reached = null)
{
    $where = array("direction = 'out'", "send_state = '" . WS_MAIL_DRAFT_STATE . "'");
    $kind = (string) ($filters['kind'] ?? '');
    $own_new = "(conversation_id = 0 AND user_id = '" . (int) $viewer['id'] . "')";
    $conversation_ids = array();

    if ($kind !== 'new') {
        $conversations = (array) db_items("SELECT DISTINCT c.id, c.channel_id FROM ws_mail_conversations c
            INNER JOIN ws_mail_items i ON i.conversation_id = c.id
            WHERE i.direction = 'out' AND i.send_state = '" . WS_MAIL_DRAFT_STATE . "'");

        foreach ($conversations as $conversation) {
            $channel = ws_channel((int) $conversation['channel_id']);

            if ($channel && ws_can_read_channel($viewer, $channel) && (($reached === null) || call_user_func($reached, $channel))) {
                $conversation_ids[] = (int) $conversation['id'];
            }
        }
    }

    $replies = $conversation_ids ? '(conversation_id IN (' . implode(',', $conversation_ids) . '))' : '';

    if ($kind === 'new') {
        $where[] = $own_new;
    } elseif ($kind === 'reply') {
        if ($replies === '') {
            return array();
        }

        $where[] = $replies;
    } else {
        $where[] = ($replies === '') ? $own_new : '(' . $own_new . ' OR ' . $replies . ')';
    }

    if ((int) ($filters['conversation_id'] ?? 0) > 0) {
        $where[] = "conversation_id = '" . (int) $filters['conversation_id'] . "'";
    }

    if ((int) ($filters['box_id'] ?? 0) > 0) {
        $where[] = "box_id = '" . (int) $filters['box_id'] . "'";
    }

    if (is_array($cursor)) {
        $where[] = "(created_at < '" . (int) $cursor['v'] . "' OR (created_at = '" . (int) $cursor['v'] . "' AND id < '" . (int) $cursor['i'] . "'))";
    }

    return (array) db_items("SELECT * FROM ws_mail_items WHERE " . implode(' AND ', $where) . "
        ORDER BY created_at DESC, id DESC LIMIT " . ((int) $limit + 1));
}

/**
 * What a refused draft is told.
 *
 * @param string $error  ws_mail_draft_create_check() / ws_mail_draft_input() error
 * @param string $detail a refused file's words
 * @return string
 */
function ws_mail_draft_error_text($error, $detail = '')
{
    $texts = array(
        'not_ready'    => lang('The database has to be updated first.'),
        'conversation' => lang('This conversation was not found.'),
        'no_access'    => lang('You cannot write in this conversation\'s channel.'),
        'box_id'       => lang('You cannot send from this mailbox.'),
        'too_many'     => lang(array('string' => 'You keep at most {var:1} e-mail drafts; send or delete some first.', 'vars' => WS_MAIL_DRAFTS_MAX)),
        'empty'        => lang('Write something to keep as a draft.'),
        'too_long'     => lang(array('string' => 'An answer is at most {var:1} characters.', 'vars' => pg_format_number(WS_MAIL_REPLY_MAX, 0))),
        'addresses'    => lang(array('string' => 'An address field is at most {var:1} characters.', 'vars' => WS_MAIL_DRAFT_ADDRESSES_MAX)),
        'subject'      => lang(array('string' => 'A subject is at most {var:1} characters.', 'vars' => WS_MAIL_DRAFT_SUBJECT_MAX)),
        'html'         => lang('The HTML is too large to keep.'),
    );

    if (in_array($error, array('too_many_files', 'files_too_large', 'file_refused'), true)) {
        return ws_mail_send_error_text($error, $detail);
    }

    return $texts[$error] ?? $texts['conversation'];
}
