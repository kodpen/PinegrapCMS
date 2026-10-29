<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - saved replies: a person's own texts, each under a short word
 * (its shortcut), picked in the composer with ; and put into the message
 * being written. The body may hold {customer} and {order}; they stay
 * placeholders in the row and are filled from the channel's customer when
 * the reply is put in, so no customer data is kept here.
 *
 * Every row has a scope; this module reads and writes 'personal' rows only,
 * and a person reaches only their own. The rows go with their user
 * (delete_users.php, edit_user.php).
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
 * How many saved replies one person may keep.
 */
const WS_SAVED_REPLIES_MAX = 200;

/**
 * The longest shortcut and title, as their columns hold them.
 */
const WS_SAVED_REPLY_SHORTCUT_MAX = 32;
const WS_SAVED_REPLY_TITLE_MAX = 100;

/**
 * The scope this module reads and writes.
 */
const WS_SAVED_REPLY_PERSONAL = 'personal';

/**
 * Whether the table is there: the files land before the upgrade runs.
 *
 * @return bool
 */
function ws_saved_replies_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('ws_saved_replies');
}

/**
 * The longest body: a reply is put into a message, so it is never longer
 * than a message may be.
 *
 * @return int
 */
function ws_saved_reply_body_max()
{
    return defined('WS_MESSAGE_MAX') ? (int) WS_MESSAGE_MAX : 4000;
}

/**
 * A shortcut as it is kept: without the ; it is typed after, trimmed, in
 * Turkish lower case. '' when it is not one word of letters, digits, - and _
 * of at most WS_SAVED_REPLY_SHORTCUT_MAX characters.
 *
 * @param mixed $typed
 * @return string
 */
function ws_saved_reply_shortcut($typed)
{
    if (!is_string($typed)) {
        return '';
    }

    $shortcut = ws_lower(ltrim(trim($typed), ';'));

    if (!preg_match('/^[\p{L}\p{N}_-]{1,' . WS_SAVED_REPLY_SHORTCUT_MAX . '}$/u', $shortcut)) {
        return '';
    }

    return $shortcut;
}

/**
 * Checks the fields of a reply and gives them back as they are kept. On a
 * change only the fields sent are checked and given back; a new reply needs
 * a shortcut and a body, and a reply without a title is called by its
 * shortcut.
 *
 * @param mixed $input   shortcut, title, body
 * @param bool  $partial a change to a kept reply
 * @return array ok, sets; or ok false, status 422, error (shortcut_invalid,
 *               title_too_long, body_empty, body_too_long, nothing_to_change)
 *               and field
 */
function ws_saved_reply_input_check($input, $partial = false)
{
    $input = is_array($input) ? $input : array();
    $refuse = function ($error, $field) {
        return array('ok' => false, 'status' => 422, 'error' => $error, 'field' => $field);
    };
    $sets = array();

    if (!$partial || array_key_exists('shortcut', $input)) {
        $sets['shortcut'] = ws_saved_reply_shortcut($input['shortcut'] ?? null);

        if ($sets['shortcut'] === '') {
            return $refuse('shortcut_invalid', 'shortcut');
        }
    }

    if (!$partial || array_key_exists('title', $input)) {
        $title = is_string($input['title'] ?? null) ? trim((string) preg_replace('/[\p{Cc}\s]+/u', ' ', $input['title'])) : '';

        if (mb_strlen($title, 'UTF-8') > WS_SAVED_REPLY_TITLE_MAX) {
            return $refuse('title_too_long', 'title');
        }

        $sets['title'] = $title;
    }

    if (!$partial || array_key_exists('body', $input)) {
        $body = is_string($input['body'] ?? null) ? rtrim(str_replace(array("\r\n", "\r"), "\n", $input['body'])) : '';

        if (trim($body) === '') {
            return $refuse('body_empty', 'body');
        }

        if (mb_strlen($body, 'UTF-8') > ws_saved_reply_body_max()) {
            return $refuse('body_too_long', 'body');
        }

        $sets['body'] = $body;
    }

    if ($partial && ($sets === array())) {
        return $refuse('nothing_to_change', null);
    }

    if (!$partial && ($sets['title'] === '')) {
        $sets['title'] = $sets['shortcut'];
    }

    return array('ok' => true, 'sets' => $sets);
}

/**
 * A person's saved replies, by shortcut.
 *
 * @param int $user_id
 * @return array[] ws_saved_replies rows
 */
function ws_saved_replies_rows($user_id)
{
    if (((int) $user_id <= 0) || !ws_saved_replies_ready()) {
        return array();
    }

    $result = db_q("SELECT id, user_id, scope, shortcut, title, body, created_at, updated_at FROM ws_saved_replies WHERE user_id = ? AND scope = ? ORDER BY shortcut, id",
        array((int) $user_id, WS_SAVED_REPLY_PERSONAL));
    $rows = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * One of a person's saved replies; null for one that is not theirs.
 *
 * @param int $user_id
 * @param int $id
 * @return array|null
 */
function ws_saved_reply_find($user_id, $id)
{
    if (((int) $user_id <= 0) || ((int) $id <= 0) || !ws_saved_replies_ready()) {
        return null;
    }

    $result = db_q("SELECT id, user_id, scope, shortcut, title, body, created_at, updated_at FROM ws_saved_replies WHERE id = ? AND user_id = ? AND scope = ?",
        array((int) $id, (int) $user_id, WS_SAVED_REPLY_PERSONAL));
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return is_array($row) ? $row : null;
}

/**
 * Which of the given rows holds a shortcut, leaving one row out.
 *
 * @param array[] $rows     ws_saved_replies_rows()
 * @param string  $shortcut ws_saved_reply_shortcut()
 * @param int     $except   the id of the reply being changed
 * @return array|null
 */
function ws_saved_reply_holder($rows, $shortcut, $except = 0)
{
    foreach ($rows as $row) {
        if (((int) $row['id'] !== (int) $except) && ((string) $row['shortcut'] === (string) $shortcut)) {
            return $row;
        }
    }

    return null;
}

/**
 * The checks of saving a reply, shared by the panel and both API calls (a
 * rehearsal meets the same refusals): the fields, the shortcut free among
 * the person's own replies, and at most WS_SAVED_REPLIES_MAX of them.
 *
 * @param array      $viewer ws_viewer()
 * @param mixed      $input  shortcut, title, body
 * @param array|null $row    the kept reply being changed, null for a new one
 * @return array ok, sets; or ok false, status (422, 409, 503), error
 *               (also shortcut_taken, limit_reached, not_ready) and field
 */
function ws_saved_reply_save_check($viewer, $input, $row = null)
{
    if (!ws_saved_replies_ready()) {
        return array('ok' => false, 'status' => 503, 'error' => 'not_ready', 'field' => null);
    }

    $checked = ws_saved_reply_input_check($input, $row !== null);

    if (!$checked['ok']) {
        return $checked;
    }

    $rows = ws_saved_replies_rows((int) $viewer['id']);

    if (($row === null) && (count($rows) >= WS_SAVED_REPLIES_MAX)) {
        return array('ok' => false, 'status' => 409, 'error' => 'limit_reached', 'field' => null);
    }

    if (isset($checked['sets']['shortcut'])
        && (ws_saved_reply_holder($rows, $checked['sets']['shortcut'], ($row === null) ? 0 : (int) $row['id']) !== null)) {
        return array('ok' => false, 'status' => 409, 'error' => 'shortcut_taken', 'field' => 'shortcut');
    }

    // A title cleared on a change is the shortcut again.
    if (($row !== null) && (($checked['sets']['title'] ?? null) === '')) {
        $checked['sets']['title'] = $checked['sets']['shortcut'] ?? (string) $row['shortcut'];
    }

    return $checked;
}

/**
 * Saves a new reply of the person's or a change to one of theirs.
 *
 * @param array      $viewer ws_viewer()
 * @param mixed      $input  shortcut, title, body
 * @param array|null $row    the kept reply being changed, null for a new one
 * @return array ok, id; or what ws_saved_reply_save_check() refused
 */
function ws_saved_reply_save($viewer, $input, $row = null)
{
    $checked = ws_saved_reply_save_check($viewer, $input, $row);

    if (!$checked['ok']) {
        return $checked;
    }

    $sets = $checked['sets'];
    $now = time();
    $user_id = (int) $viewer['id'];

    if ($row === null) {
        $ok = db_q("INSERT INTO ws_saved_replies (user_id, scope, shortcut, title, body, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
            array($user_id, WS_SAVED_REPLY_PERSONAL, $sets['shortcut'], $sets['title'], $sets['body'], $now, $now));

        // The unique key answers a shortcut written meanwhile, or one the
        // collation counts as the same word.
        if (!$ok) {
            return array('ok' => false, 'status' => 409, 'error' => 'shortcut_taken', 'field' => 'shortcut');
        }

        return array('ok' => true, 'id' => (int) mysqli_insert_id(db::$con));
    }

    $parts = array();
    $values = array();

    foreach (array('shortcut', 'title', 'body') as $field) {
        if (array_key_exists($field, $sets)) {
            $parts[] = $field . ' = ?';
            $values[] = $sets[$field];
        }
    }

    $values[] = $now;
    $values[] = (int) $row['id'];
    $values[] = $user_id;

    if (!db_q("UPDATE ws_saved_replies SET " . implode(', ', $parts) . ", updated_at = ? WHERE id = ? AND user_id = ? AND scope = '" . WS_SAVED_REPLY_PERSONAL . "'", $values)) {
        return array('ok' => false, 'status' => 409, 'error' => 'shortcut_taken', 'field' => 'shortcut');
    }

    return array('ok' => true, 'id' => (int) $row['id']);
}

/**
 * Deletes one of the person's replies.
 *
 * @param array $viewer ws_viewer()
 * @param array $row    ws_saved_reply_find()
 * @return bool
 */
function ws_saved_reply_delete($viewer, $row)
{
    if (!ws_saved_replies_ready()) {
        return false;
    }

    return (bool) db_q("DELETE FROM ws_saved_replies WHERE id = ? AND user_id = ? AND scope = ?",
        array((int) $row['id'], (int) $viewer['id'], WS_SAVED_REPLY_PERSONAL));
}

/**
 * A reply as the API and the composer read it.
 *
 * @param array $row ws_saved_replies row
 * @return array
 */
function ws_saved_reply_present($row)
{
    return array(
        'id'         => (int) $row['id'],
        'shortcut'   => (string) $row['shortcut'],
        'title'      => (string) $row['title'],
        'body'       => (string) $row['body'],
        'created_at' => ws_flow_time($row['created_at']),
        'updated_at' => ws_flow_time($row['updated_at']),
    );
}

/**
 * What a refusal of ws_saved_reply_save_check() says to the person, shared by
 * the panel and the API.
 *
 * @param string $error
 * @return string
 */
function ws_saved_reply_error_text($error)
{
    $messages = array(
        'shortcut_invalid'  => lang('A shortcut is one word of letters, digits, - and _, at most 32 characters.'),
        'shortcut_taken'    => lang('Another of your saved replies has this shortcut.'),
        'title_too_long'    => lang('The title is longer than 100 characters.'),
        'body_empty'        => lang('The reply is empty.'),
        'body_too_long'     => lang(array('string' => 'A message can be at most {var:1} characters long.', 'vars' => ws_saved_reply_body_max())),
        'nothing_to_change' => lang('Nothing to change was sent.'),
        'limit_reached'     => lang(array('string' => 'A person may keep {var:1} saved replies.', 'vars' => WS_SAVED_REPLIES_MAX)),
        'not_ready'         => lang('The database has to be updated first.'),
    );

    return $messages[(string) $error] ?? (string) $error;
}

/**
 * A reply's body with {customer} and {order} filled, in any case. A
 * placeholder without a value stays as typed, so the writer sees what is
 * left to fill before sending.
 *
 * @param string $body
 * @param array  $values customer, order (ws_saved_reply_values())
 * @return string
 */
function ws_saved_reply_fill($body, $values)
{
    return (string) preg_replace_callback('/\{(customer|order)\}/i', function ($match) use ($values) {
        $value = trim((string) ($values[strtolower($match[1])] ?? ''));

        return ($value !== '') ? $value : $match[0];
    }, (string) $body);
}

/**
 * The values a reply is filled with in a channel, as the reader sees its
 * customer (ws_customer_summary()): the customer's name when the reader may
 * see the record, the number of their latest order when the reader holds
 * the ecommerce right. Empty for a channel about nobody.
 *
 * @param array $viewer
 * @param array $channel a ws_channels row the reader may read
 * @return array customer, order; a value missing is not there
 */
function ws_saved_reply_values($viewer, $channel)
{
    $identity = ws_customer_identity($viewer, $channel);

    if (!is_array($identity)) {
        return array();
    }

    $values = array();

    if (!empty($identity['customer']['known'])) {
        $values['customer'] = (string) $identity['customer']['label'];
    }

    $summary = ws_customer_summary($viewer, $channel, $identity);
    $order = (string) ($summary['orders']['latest'][0]['number'] ?? '');

    if ($order !== '') {
        $values['order'] = $order;
    }

    return $values;
}

/**
 * The person's replies for the composer's ; list and the list that manages
 * them: each as kept, its body filled for the channel being written in
 * (text) and the names of the tags it holds (labels), for the writing box.
 *
 * @param array      $viewer
 * @param array|null $channel the channel being written in; null fills nothing
 * @return array[] ws_saved_reply_present() and text, labels
 */
function ws_saved_replies_for_box($viewer, $channel = null)
{
    $rows = ws_saved_replies_rows((int) $viewer['id']);

    if (empty($rows)) {
        return array();
    }

    $values = is_array($channel) ? ws_saved_reply_values($viewer, $channel) : array();
    $tokens = array();

    foreach ($rows as $row) {
        $tokens = array_merge($tokens, ws_tokens((string) $row['body']));
    }

    $refs = $tokens ? ws_refs_resolve($viewer, $tokens) : array();
    $items = array();

    foreach ($rows as $row) {
        $labels = array();

        foreach (ws_tokens((string) $row['body']) as $token) {
            $key = $token['type'] . ':' . $token['id'];

            if (isset($refs[$key])) {
                $labels['<' . $token['sigil'] . $key . '>'] = $refs[$key]['label'];
            }
        }

        $items[] = array_merge(ws_saved_reply_present($row), array(
            'text'   => ws_saved_reply_fill((string) $row['body'], $values),
            'labels' => $labels,
        ));
    }

    return $items;
}
