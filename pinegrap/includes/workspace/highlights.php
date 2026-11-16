<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a reader's highlights on a message (ws_highlights): a part of
 * a message's drawn text marked for everyone (scope 'all') or for its maker
 * only (scope 'me'). The message's text is never changed: a highlight is
 * kept beside it by its text (quote) and which occurrence of that text in
 * the message's plain text it is (occurrence, 0 the first). This file holds
 * the readiness check and the pure part both the server and the browser's
 * twin follow: the plain text, finding a quote, and what an edit of the
 * message does to a highlight.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The longest quote and note a highlight keeps, in characters.
if (!defined('WS_HIGHLIGHT_QUOTE_MAX')) {
    define('WS_HIGHLIGHT_QUOTE_MAX', 1000);
}

if (!defined('WS_HIGHLIGHT_NOTE_MAX')) {
    define('WS_HIGHLIGHT_NOTE_MAX', 255);
}

// What keeps the table small: per person on one message, on one message in
// all, and per person.
if (!defined('WS_HIGHLIGHTS_MESSAGE_MINE_MAX')) {
    define('WS_HIGHLIGHTS_MESSAGE_MINE_MAX', 10);
}

if (!defined('WS_HIGHLIGHTS_MESSAGE_MAX')) {
    define('WS_HIGHLIGHTS_MESSAGE_MAX', 30);
}

if (!defined('WS_HIGHLIGHTS_PERSON_MAX')) {
    define('WS_HIGHLIGHTS_PERSON_MAX', 2000);
}

// The rows of My highlights one request lists.
if (!defined('WS_HIGHLIGHTS_PAGE')) {
    define('WS_HIGHLIGHTS_PAGE', 50);
}

/**
 * Whether the highlights table is there: on a site whose update has not run,
 * nothing can be highlighted and everything else works as before.
 *
 * @return bool
 */
function ws_highlights_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_highlights', 'occurrence');
    }

    return $ready;
}

/**
 * Every run of white space folded to one space, the ends trimmed. White
 * space is what the browser's \s reads: ASCII white space, the no-break
 * space and every other Unicode space. Pure.
 *
 * @param string $text
 * @return string
 */
function ws_highlight_fold($text)
{
    return trim((string) preg_replace('/[\s\p{Z}\x{FEFF}]+/u', ' ', (string) $text), ' ');
}

/**
 * The plain text of a message's drawn body (ws_render_body()'s HTML): the
 * tags taken out, a line break and the edge of a block (a line, a list item,
 * a quote, a table cell) read as white space, entities decoded, white space
 * folded. A highlight's quote and occurrence are read against this text.
 * Pure.
 *
 * @param string $html
 * @return string
 */
function ws_highlight_plain($html)
{
    $html = (string) $html;

    // A block's edge separates words even when no white space is written
    // between two blocks (</li><li>).
    $html = preg_replace('#<(?:br|hr)\b[^>]*>|</?(?:div|p|li|ul|ol|blockquote|pre|table|thead|tbody|tr|td|th|h[1-6])\b[^>]*>#i', ' ', $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return ws_highlight_fold($text);
}

/**
 * Where the given occurrence of a quote begins in a plain text, in
 * characters, or null when the text has no such occurrence. The quote's
 * white space is folded as the text's is. Every place a quote begins is an
 * occurrence, overlapping ones too, so each place can be named. Pure.
 *
 * @param string $plain      ws_highlight_plain() output
 * @param string $quote
 * @param int    $occurrence 0 the first
 * @return int|null
 */
function ws_highlight_find($plain, $quote, $occurrence)
{
    $plain = (string) $plain;
    $quote = ws_highlight_fold($quote);
    $occurrence = (int) $occurrence;

    if (($quote === '') || ($occurrence < 0)) {
        return null;
    }

    $offset = -1;

    for ($index = 0; $index <= $occurrence; $index++) {
        $offset = mb_strpos($plain, $quote, $offset + 1, 'UTF-8');

        if ($offset === false) {
            return null;
        }
    }

    return $offset;
}

/**
 * How many times a quote occurs in a plain text, overlapping places
 * counted. Pure.
 *
 * @param string $plain
 * @param string $quote
 * @return int
 */
function ws_highlight_count($plain, $quote)
{
    $plain = (string) $plain;
    $quote = ws_highlight_fold($quote);

    if ($quote === '') {
        return 0;
    }

    $count = 0;
    $offset = -1;

    while (($offset = mb_strpos($plain, $quote, $offset + 1, 'UTF-8')) !== false) {
        $count++;
    }

    return $count;
}

/**
 * What an edit of its message does to one highlight: the row as it was when
 * its occurrence is still in the new text, the row moved to the quote's
 * last occurrence when that one is gone, or null (the row is deleted) when
 * the quote is no longer in the new text. Pure.
 *
 * @param array  $row       a ws_highlights row (quote, occurrence)
 * @param string $old_plain the plain text before the edit
 * @param string $new_plain the plain text after it
 * @return array|null
 */
function ws_highlight_remap_one($row, $old_plain, $new_plain)
{
    $row = (array) $row;
    $quote = (string) ($row['quote'] ?? '');
    $occurrence = (int) ($row['occurrence'] ?? 0);

    if (ws_highlight_fold($quote) === '') {
        return null;
    }

    // Nothing in the text changed: the row stays where it is.
    if ((string) $old_plain === (string) $new_plain) {
        return $row;
    }

    $count = ws_highlight_count($new_plain, $quote);

    if ($count === 0) {
        return null;
    }

    if ($occurrence < $count) {
        return $row;
    }

    $row['occurrence'] = $count - 1;

    return $row;
}

/* ---------------------------------------------------------------------------
   The rules, pure
   --------------------------------------------------------------------------- */

/**
 * A scope as stored: 'all' or 'me'; anything else is read as 'me', the one
 * nobody but its maker sees. Pure.
 *
 * @param mixed $scope
 * @return string
 */
function ws_highlight_scope($scope)
{
    return ((string) $scope === 'all') ? 'all' : 'me';
}

/**
 * Of a person's rows on one message, the one at this place (the same quote,
 * folded, and the same occurrence), or null. Pure.
 *
 * @param array[] $rows
 * @param string  $quote
 * @param int     $occurrence
 * @return array|null
 */
function ws_highlight_same($rows, $quote, $occurrence)
{
    $quote = ws_highlight_fold($quote);

    foreach ((array) $rows as $row) {
        if ((ws_highlight_fold($row['quote'] ?? '') === $quote) && ((int) ($row['occurrence'] ?? -1) === (int) $occurrence)) {
            return $row;
        }
    }

    return null;
}

/**
 * Whether a highlight may be made, or its scope changed: the message is
 * there and read by the person, an 'all' one only where he may react
 * (ws_reaction_check()'s rules), the quote is 1 to 1,000 characters and at
 * its occurrence in the message's plain text, and no limit is reached (a row
 * he has at that place is changed, never counted again). Pure.
 *
 * @param array $args message, can_read, can_react, plain, quote, occurrence,
 *                    scope, same (his row at that place or null), counts
 *                    (mine_here, here, mine)
 * @return array ok, error, field, limit, scope, quote, occurrence, same_id
 */
function ws_highlight_make_check($args)
{
    $message = (array) ($args['message'] ?? array());
    $scope = ws_highlight_scope($args['scope'] ?? 'me');
    $quote = ws_highlight_fold($args['quote'] ?? '');
    $occurrence = (int) ($args['occurrence'] ?? 0);
    $refuse = function ($error, $field = '', $limit = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'limit' => $limit);
    };

    if (empty($message['id']) || ((int) ($message['deleted_at'] ?? 0) > 0) || ((string) ($message['kind'] ?? '') === 'system') || empty($args['can_read'])) {
        return $refuse(lang('That message could not be found.'));
    }

    if (($scope === 'all') && empty($args['can_react'])) {
        return $refuse(lang('You cannot highlight for everyone here.'), 'scope');
    }

    $length = mb_strlen($quote, 'UTF-8');

    if ($length < 1) {
        return $refuse(lang('Select a part of the message first.'), 'quote');
    }

    if ($length > WS_HIGHLIGHT_QUOTE_MAX) {
        return $refuse(lang('Select a shorter part'), 'quote');
    }

    if (($occurrence < 0) || (ws_highlight_find((string) ($args['plain'] ?? ''), $quote, $occurrence) === null)) {
        return $refuse(lang('That text is no longer in the message.'), 'quote');
    }

    $same = $args['same'] ?? null;

    if (!is_array($same)) {
        $counts = (array) ($args['counts'] ?? array());

        if ((int) ($counts['mine_here'] ?? 0) >= WS_HIGHLIGHTS_MESSAGE_MINE_MAX) {
            return $refuse(lang(array('string' => 'You can highlight at most {var:1} parts of one message.', 'vars' => array(WS_HIGHLIGHTS_MESSAGE_MINE_MAX))), '', 'limit_message_mine');
        }

        if ((int) ($counts['here'] ?? 0) >= WS_HIGHLIGHTS_MESSAGE_MAX) {
            return $refuse(lang(array('string' => 'This message already carries {var:1} highlights, as many as it can.', 'vars' => array(WS_HIGHLIGHTS_MESSAGE_MAX))), '', 'limit_message');
        }

        if ((int) ($counts['mine'] ?? 0) >= WS_HIGHLIGHTS_PERSON_MAX) {
            return $refuse(lang(array('string' => 'You keep {var:1} highlights, as many as you can. Remove some in My highlights.', 'vars' => array(number_format(WS_HIGHLIGHTS_PERSON_MAX)))), '', 'limit_person');
        }
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'limit' => '', 'scope' => $scope, 'quote' => $quote,
        'occurrence' => $occurrence, 'same_id' => is_array($same) ? (int) $same['id'] : 0);
}

/**
 * A note as kept: plain text, control characters dropped, white space
 * folded, up to 255 characters. Pure.
 *
 * @param string $note
 * @return array ok, error, note
 */
function ws_highlight_note_clean($note)
{
    $note = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', ' ', (string) $note);
    $note = ws_highlight_fold($note);

    if (mb_strlen($note, 'UTF-8') > WS_HIGHLIGHT_NOTE_MAX) {
        return array('ok' => false, 'error' => lang(array('string' => 'A note can be at most {var:1} characters long.', 'vars' => array(WS_HIGHLIGHT_NOTE_MAX))), 'note' => $note);
    }

    return array('ok' => true, 'error' => '', 'note' => $note);
}

/**
 * Whether the person may write or change a highlight's note: its maker
 * only, and on an 'all' one only where he may still react. Pure.
 *
 * @param int   $viewer_id
 * @param array $row
 * @param bool  $can_react
 * @return bool
 */
function ws_highlight_can_note($viewer_id, $row, $can_react)
{
    if ((int) $row['user_id'] !== (int) $viewer_id) {
        return false;
    }

    return (ws_highlight_scope($row['scope'] ?? '') === 'me') || $can_react;
}

/**
 * Whether the person may remove a highlight: a 'me' one its maker only; an
 * 'all' one its maker, the message's writer or whoever may delete the
 * message, where the message can still be reacted to (an archived channel's
 * highlights are read-only). Pure.
 *
 * @param int   $viewer_id
 * @param array $row
 * @param array $rights can_react, writer, may_delete: the person on the message
 * @return bool
 */
function ws_highlight_can_remove($viewer_id, $row, $rights)
{
    $maker = ((int) $row['user_id'] === (int) $viewer_id);

    if (ws_highlight_scope($row['scope'] ?? '') === 'me') {
        return $maker;
    }

    return !empty($rights['can_react']) && ($maker || !empty($rights['writer']) || !empty($rights['may_delete']));
}

/**
 * Whether a change stamps the message so that open conversations draw it
 * again: when the highlight was or is one everybody sees. A 'me' one stamps
 * nothing, as hiding a message does. Pure.
 *
 * @param string|null $before the scope before (null: a new row)
 * @param string|null $after  the scope after (null: removed)
 * @return bool
 */
function ws_highlight_stamps($before, $after)
{
    return (($before !== null) && (ws_highlight_scope($before) === 'all'))
        || (($after !== null) && (ws_highlight_scope($after) === 'all'));
}

/**
 * The highlights of messages as one reader receives them: 'all' ones and
 * his own 'me' ones, oldest first, never another person's 'me'. Pure.
 *
 * @param array[] $rows      ws_highlights rows
 * @param int     $viewer_id
 * @param array   $people    user id => name, tone
 * @param array   $rights    message id => can_react, writer, may_delete
 * @return array message id => [ id, quote, occurrence, scope, note, user_id,
 *               name, tone, mine, can_remove ]
 */
function ws_highlights_map_rows($rows, $viewer_id, $people, $rights)
{
    $out = array();

    foreach ((array) $rows as $row) {
        $scope = ws_highlight_scope($row['scope'] ?? '');
        $mine = ((int) $row['user_id'] === (int) $viewer_id);

        if (($scope === 'me') && !$mine) {
            continue;
        }

        $message_id = (int) $row['message_id'];
        $person = $people[(int) $row['user_id']] ?? array();

        $out[$message_id][] = array(
            'id'         => (int) $row['id'],
            'quote'      => (string) $row['quote'],
            'occurrence' => (int) $row['occurrence'],
            'scope'      => $scope,
            'note'       => (string) $row['note'],
            'user_id'    => (int) $row['user_id'],
            'name'       => (string) ($person['name'] ?? ''),
            'tone'       => (int) ($person['tone'] ?? 0),
            'mine'       => $mine,
            'can_remove' => ws_highlight_can_remove($viewer_id, array('scope' => $scope) + $row, (array) ($rights[$message_id] ?? array())),
        );
    }

    return $out;
}

/* ---------------------------------------------------------------------------
   Reading and writing
   --------------------------------------------------------------------------- */

/**
 * The plain text of a message as this reader's screen draws it.
 *
 * @param array $viewer
 * @param array $message
 * @return string
 */
function ws_highlight_message_plain($viewer, $message)
{
    $payload = ws_message_payloads($viewer, array($message));

    return ws_highlight_plain((string) ($payload[0]['html'] ?? ''));
}

/**
 * The plain text of a body drawn for this reader, without the cards a
 * message carries: what an edit compares before and after.
 *
 * @param array  $viewer
 * @param string $body
 * @return string
 */
function ws_highlight_body_plain($viewer, $body)
{
    return ws_highlight_plain(ws_render_body((string) $body, ws_refs_resolve($viewer, ws_tokens($body))));
}

/**
 * The person's rights on a message as the rules above read them.
 *
 * @param array      $viewer
 * @param array      $message
 * @param array|null $channel
 * @return array can_read, can_react, writer, may_delete
 */
function ws_highlight_rights($viewer, $message, $channel = null)
{
    $channel = is_array($channel) ? $channel : ws_channel($message['channel_id']);
    $alive = ((int) $message['deleted_at'] === 0) && ($message['kind'] !== 'system');
    $past = function_exists('ws_message_in_past') && ws_message_in_past($message);

    return array(
        'can_read'   => $alive && is_array($channel) && ws_can_read_channel($viewer, $channel),
        'can_react'  => $alive && !$past && is_array($channel) && ws_can_post_channel($viewer, $channel),
        'writer'     => ($message['sender_kind'] === 'user') && ((int) $message['sender_id'] === (int) $viewer['id']),
        'may_delete' => $alive && !$past && ws_message_delete_check($viewer, $message)['ok'],
    );
}

/**
 * @param int $id
 * @return array|null
 */
function ws_highlight_row($id)
{
    if (!ws_highlights_ready() || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_highlights WHERE id = '" . (int) $id . "'");

    return is_array($row) ? $row : null;
}

/**
 * Highlights a part of a message, or changes the scope (and the note) of
 * the person's own highlight at that place (H5).
 *
 * @param array       $viewer
 * @param array       $message
 * @param string      $quote
 * @param int         $occurrence
 * @param string      $scope 'all' | 'me'
 * @param string|null $note  null leaves the note as it is
 * @return array ok, error, field, limit, id
 */
function ws_highlight_set($viewer, $message, $quote, $occurrence, $scope, $note = null)
{
    if (!ws_highlights_ready() || !is_array($message)) {
        return array('ok' => false, 'error' => lang('Invalid request.'), 'field' => '', 'limit' => '');
    }

    $rights = ws_highlight_rights($viewer, $message);
    $message_id = (int) $message['id'];
    $user_id = (int) $viewer['id'];
    $mine = (array) db_items("SELECT * FROM ws_highlights WHERE message_id = '" . $message_id . "' AND user_id = '" . $user_id . "'");

    $checked = ws_highlight_make_check(array(
        'message'    => $message,
        'can_read'   => $rights['can_read'],
        'can_react'  => $rights['can_react'],
        'plain'      => $rights['can_read'] ? ws_highlight_message_plain($viewer, $message) : '',
        'quote'      => $quote,
        'occurrence' => $occurrence,
        'scope'      => $scope,
        'same'       => ws_highlight_same($mine, $quote, $occurrence),
        'counts'     => array(
            'mine_here' => count($mine),
            'here'      => (int) db_value("SELECT COUNT(*) FROM ws_highlights WHERE message_id = '" . $message_id . "'"),
            'mine'      => (int) db_value("SELECT COUNT(*) FROM ws_highlights WHERE user_id = '" . $user_id . "'"),
        ),
    ));

    if (!$checked['ok']) {
        return $checked;
    }

    $note_sql = '';

    if ($note !== null) {
        $cleaned = ws_highlight_note_clean($note);

        if (!$cleaned['ok']) {
            return array('ok' => false, 'error' => $cleaned['error'], 'field' => 'note', 'limit' => '');
        }

        $note_sql = ", note = '" . e($cleaned['note']) . "'";
    }

    if ($checked['same_id'] > 0) {
        $before = ws_highlight_same($mine, $quote, $occurrence)['scope'];

        db("UPDATE ws_highlights SET scope = '" . $checked['scope'] . "'" . $note_sql . " WHERE id = '" . $checked['same_id'] . "'");
        $id = $checked['same_id'];
    } else {
        $before = null;

        db("INSERT INTO ws_highlights (message_id, channel_id, user_id, scope, quote, occurrence, note, created_at)
            VALUES ('" . $message_id . "', '" . (int) $message['channel_id'] . "', '" . $user_id . "', '" . $checked['scope'] . "',
            '" . e($checked['quote']) . "', '" . (int) $checked['occurrence'] . "', '" . e(($note !== null) ? $cleaned['note'] : '') . "', '" . time() . "')");
        $id = (int) mysqli_insert_id(db::$con);
    }

    if (ws_highlight_stamps($before, $checked['scope'])) {
        ws_message_touch($message_id);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'limit' => '', 'id' => (int) $id);
}

/**
 * Writes, changes or clears the note of the person's own highlight (H6).
 *
 * @param array  $viewer
 * @param int    $id
 * @param string $note
 * @return array ok, error, message_id
 */
function ws_highlight_note($viewer, $id, $note)
{
    $row = ws_highlight_row($id);
    $message = $row ? ws_message((int) $row['message_id']) : null;

    if (!$row || !$message) {
        return array('ok' => false, 'error' => lang('That highlight could not be found.'));
    }

    $rights = ws_highlight_rights($viewer, $message);

    if (!$rights['can_read'] || !ws_highlight_can_note($viewer['id'], $row, $rights['can_react'])) {
        return array('ok' => false, 'error' => lang('That highlight could not be found.'));
    }

    $cleaned = ws_highlight_note_clean($note);

    if (!$cleaned['ok']) {
        return array('ok' => false, 'error' => $cleaned['error']);
    }

    db("UPDATE ws_highlights SET note = '" . e($cleaned['note']) . "' WHERE id = '" . (int) $row['id'] . "'");

    if (ws_highlight_stamps($row['scope'], $row['scope'])) {
        ws_message_touch($message['id']);
    }

    return array('ok' => true, 'error' => '', 'message_id' => (int) $message['id']);
}

/**
 * Removes one highlight (H7).
 *
 * @param array $viewer
 * @param int   $id
 * @return array ok, error, message_id
 */
function ws_highlight_remove($viewer, $id)
{
    $row = ws_highlight_row($id);
    $message = $row ? ws_message((int) $row['message_id']) : null;

    if (!$row || !$message) {
        return array('ok' => false, 'error' => lang('That highlight could not be found.'));
    }

    $rights = ws_highlight_rights($viewer, $message);
    $visible = (ws_highlight_scope($row['scope']) === 'all') || ((int) $row['user_id'] === (int) $viewer['id']);

    if (!$rights['can_read'] || !$visible) {
        return array('ok' => false, 'error' => lang('That highlight could not be found.'));
    }

    if (!ws_highlight_can_remove($viewer['id'], $row, $rights)) {
        return array('ok' => false, 'error' => lang('You cannot remove this highlight.'));
    }

    db("DELETE FROM ws_highlights WHERE id = '" . (int) $row['id'] . "'");

    if (ws_highlight_stamps($row['scope'], null)) {
        ws_message_touch($message['id']);
    }

    return array('ok' => true, 'error' => '', 'message_id' => (int) $message['id']);
}

/**
 * Removes many of the person's own highlights at once (My highlights): the
 * ids given, or with $every every one of his in a filter ('' all, 'all',
 * 'me'), loaded on his screen or not. An 'all' one where he may no longer
 * react stays (read-only). Each message is stamped once.
 *
 * @param array       $viewer
 * @param int[]       $ids
 * @param string|null $every null: the ids; otherwise the filter
 * @return array ok, error, removed, kept
 */
function ws_highlights_remove_many($viewer, $ids, $every = null)
{
    if (!ws_highlights_ready()) {
        return array('ok' => false, 'error' => lang('Invalid request.'), 'removed' => 0, 'kept' => 0);
    }

    $where = "h.user_id = '" . (int) $viewer['id'] . "'";

    if ($every === null) {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) $ids)))), 0, WS_HIGHLIGHTS_PERSON_MAX);

        if (empty($ids)) {
            return array('ok' => true, 'error' => '', 'removed' => 0, 'kept' => 0);
        }

        $where .= " AND h.id IN (" . implode(',', $ids) . ")";
    } elseif (in_array((string) $every, array('all', 'me'), true)) {
        $where .= " AND h.scope " . (($every === 'all') ? "= 'all'" : "<> 'all'");
    }

    $rows = (array) db_items("SELECT h.id, h.scope, h.user_id, h.message_id FROM ws_highlights h WHERE " . $where . " LIMIT " . WS_HIGHLIGHTS_PERSON_MAX);
    $gone = array();
    $stamp = array();
    $kept = 0;
    $rights = array();

    foreach ($rows as $row) {
        $message_id = (int) $row['message_id'];

        if (ws_highlight_scope($row['scope']) === 'all') {
            if (!isset($rights[$message_id])) {
                $message = ws_message($message_id);
                $rights[$message_id] = $message ? ws_highlight_rights($viewer, $message) : array();
            }

            if (!ws_highlight_can_remove($viewer['id'], $row, $rights[$message_id])) {
                $kept++;
                continue;
            }

            $stamp[$message_id] = true;
        }

        $gone[] = (int) $row['id'];
    }

    if (!empty($gone)) {
        db("DELETE FROM ws_highlights WHERE id IN (" . implode(',', $gone) . ")");
    }

    foreach (array_keys($stamp) as $message_id) {
        ws_message_touch($message_id);
    }

    return array('ok' => true, 'error' => '', 'removed' => count($gone), 'kept' => $kept);
}

/**
 * The highlights a reader receives on a page of messages.
 *
 * @param int[] $message_ids
 * @param array $viewer
 * @return array ws_highlights_map_rows() output
 */
function ws_highlights_map($message_ids, $viewer)
{
    $message_ids = array_values(array_unique(array_filter(array_map('intval', (array) $message_ids))));

    if (empty($message_ids) || !ws_highlights_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT * FROM ws_highlights
        WHERE message_id IN (" . implode(',', $message_ids) . ") AND (scope = 'all' OR user_id = '" . (int) $viewer['id'] . "')
        ORDER BY id");

    if (empty($rows)) {
        return array();
    }

    $people = ws_people(array_map(function ($row) { return (int) $row['user_id']; }, $rows));
    $rights = array();

    foreach ($rows as $row) {
        $message_id = (int) $row['message_id'];

        if (!isset($rights[$message_id])) {
            $message = ws_message($message_id);
            $rights[$message_id] = $message ? ws_highlight_rights($viewer, $message) : array();
        }
    }

    return ws_highlights_map_rows($rows, $viewer['id'], $people, $rights);
}

/**
 * The person's own highlights for My highlights, newest first, a page at a
 * time, only on messages he can still read; with the count of each filter.
 *
 * @param array  $viewer
 * @param string $filter '' every one, 'all' for everyone, 'me' only his
 * @param int    $after  the last id of the page before (0: the first page)
 * @return array rows, counts (every, all, me), more
 */
function ws_highlights_mine($viewer, $filter = '', $after = 0)
{
    $empty = array('rows' => array(), 'counts' => array('every' => 0, 'all' => 0, 'me' => 0), 'more' => false);

    if (!ws_highlights_ready()) {
        return $empty;
    }

    $user_id = (int) $viewer['id'];
    $counts = $empty['counts'];

    foreach ((array) db_items("SELECT h.scope, COUNT(*) AS n FROM ws_highlights h
        INNER JOIN ws_messages m ON m.id = h.message_id AND m.deleted_at = 0
        WHERE h.user_id = '" . $user_id . "' GROUP BY h.scope") as $row) {
        $counts[ws_highlight_scope($row['scope'])] += (int) $row['n'];
    }

    $counts['every'] = $counts['all'] + $counts['me'];

    $where = "h.user_id = '" . $user_id . "' AND m.deleted_at = 0";

    if (in_array((string) $filter, array('all', 'me'), true)) {
        $where .= " AND h.scope " . (($filter === 'all') ? "= 'all'" : "<> 'all'");
    }

    $out = array();
    $channels = array();
    $cursor = max(0, (int) $after);
    $more = false;

    // Rows on messages he no longer reads are skipped, so a page is read in
    // a few rounds at most.
    for ($round = 0; $round < 5; $round++) {
        $batch = (array) db_items("SELECT h.*, m.sender_kind, m.sender_id, m.created_at AS message_at FROM ws_highlights h
            INNER JOIN ws_messages m ON m.id = h.message_id
            WHERE " . $where . (($cursor > 0) ? " AND h.id < '" . $cursor . "'" : '') . "
            ORDER BY h.id DESC LIMIT " . (WS_HIGHLIGHTS_PAGE + 1));

        foreach ($batch as $row) {
            $cursor = (int) $row['id'];

            if (count($out) >= WS_HIGHLIGHTS_PAGE) {
                $more = true;
                break 2;
            }

            $channel_id = (int) $row['channel_id'];

            if (!isset($channels[$channel_id])) {
                $channel = ws_channel($channel_id);
                $channels[$channel_id] = (is_array($channel) && ws_can_read_channel($viewer, $channel)) ? $channel : false;
            }

            if ($channels[$channel_id] === false) {
                continue;
            }

            $out[] = array(
                'id'           => (int) $row['id'],
                'message_id'   => (int) $row['message_id'],
                'channel_id'   => $channel_id,
                'channel_name' => (string) ($channels[$channel_id]['name'] ?? ''),
                'channel_kind' => (string) ($channels[$channel_id]['kind'] ?? ''),
                'writer'       => ($row['sender_kind'] === 'user') ? ws_person_name($row['sender_id']) : '',
                'message_at'   => (int) $row['message_at'],
                'created_at'   => (int) $row['created_at'],
                'scope'        => ws_highlight_scope($row['scope']),
                'quote'        => (string) $row['quote'],
                'note'         => (string) $row['note'],
            );
        }

        if (count($batch) <= WS_HIGHLIGHTS_PAGE) {
            break;
        }
    }

    return array('rows' => $out, 'counts' => $counts, 'more' => $more);
}

/* ---------------------------------------------------------------------------
   Cleanup (H9)
   --------------------------------------------------------------------------- */

/**
 * A deleted message takes its highlights with it.
 *
 * @param int $message_id
 */
function ws_highlights_message_deleted($message_id)
{
    if (ws_highlights_ready()) {
        db("DELETE FROM ws_highlights WHERE message_id = '" . (int) $message_id . "'");
    }
}

/**
 * An edited message: a highlight whose quote is gone is deleted, one whose
 * occurrence is gone moves to the last one (ws_highlight_remap_one()).
 *
 * @param array  $viewer   the person who edited it
 * @param array  $message  the row before the edit
 * @param string $old_body
 * @param string $new_body
 */
function ws_highlights_remap($viewer, $message, $old_body, $new_body)
{
    if (!ws_highlights_ready()) {
        return;
    }

    $rows = (array) db_items("SELECT * FROM ws_highlights WHERE message_id = '" . (int) $message['id'] . "'");

    if (empty($rows)) {
        return;
    }

    $old_plain = ws_highlight_body_plain($viewer, $old_body);
    $new_plain = ws_highlight_body_plain($viewer, $new_body);

    foreach ($rows as $row) {
        $mapped = ws_highlight_remap_one($row, $old_plain, $new_plain);

        if ($mapped === null) {
            db("DELETE FROM ws_highlights WHERE id = '" . (int) $row['id'] . "'");
        } elseif ((int) $mapped['occurrence'] !== (int) $row['occurrence']) {
            db("UPDATE ws_highlights SET occurrence = '" . (int) $mapped['occurrence'] . "' WHERE id = '" . (int) $row['id'] . "'");
        }
    }
}

/**
 * People who can no longer read a channel (they left it, were removed, it
 * was made private) lose their own 'me' highlights there and in its
 * discussions; their 'all' ones stay for the others.
 *
 * @param array      $channel
 * @param int[]|null $user_ids null: everyone with such a row there
 */
function ws_highlights_reach_lost($channel, $user_ids = null)
{
    $channel_id = is_array($channel) ? (int) $channel['id'] : 0;

    if (($channel_id <= 0) || !ws_highlights_ready()) {
        return;
    }

    $channels = array($channel_id);

    if (function_exists('ws_threads_ready') && ws_threads_ready()) {
        foreach ((array) db_values("SELECT channel_id FROM ws_threads WHERE parent_channel_id = '" . $channel_id . "'") as $id) {
            $channels[] = (int) $id;
        }
    }

    $in = implode(',', $channels);

    if ($user_ids === null) {
        $user_ids = db_values("SELECT DISTINCT user_id FROM ws_highlights WHERE channel_id IN (" . $in . ") AND scope <> 'all'");
    }

    $channel = ws_channel($channel_id);

    foreach (array_unique(array_map('intval', (array) $user_ids)) as $user_id) {
        if (($user_id <= 0) || ws_can_read_channel(ws_rights_for_id($user_id), $channel)) {
            continue;
        }

        db("DELETE FROM ws_highlights WHERE user_id = '" . $user_id . "' AND scope <> 'all' AND channel_id IN (" . $in . ")");
    }
}
