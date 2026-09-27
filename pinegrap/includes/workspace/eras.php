<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - clearing a channel's conversation. Staff inside a channel start
 * its conversation again; what was written before is not deleted but kept as
 * an earlier version, which the channel's settings list and anybody who reads
 * the channel can open, as it was: nothing in it is changed any more.
 *
 * The messages stay where they are. A channel's messages whose id is at or
 * below ws_channels.era_floor are before its current conversation, and
 * ws_channel_eras keeps where each earlier version begins and ends (message
 * ids only grow, so a version is a range of them). Links, search results and
 * quoted replies that point at an earlier message open its version.
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
 * Has the database the versions of a conversation (2026.4.5, 5.83)?
 *
 * @return bool
 */
function ws_eras_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('ws_channels', 'era_floor')
            && waf_table_has_column('ws_channel_eras', 'last_message_id');
    }

    return $ready;
}

/**
 * The last message before the channel's current conversation (0: it was
 * never cleared). Read once per request and channel; ws_channel_floor_forget()
 * after a clear.
 *
 * @param int $channel_id
 * @return int
 */
function ws_channel_floor($channel_id, $forget = false)
{
    static $cache = array();

    if ($forget) {
        $cache = array();
        return 0;
    }

    if (!ws_eras_ready()) {
        return 0;
    }

    $channel_id = (int) $channel_id;

    if (!isset($cache[$channel_id])) {
        $cache[$channel_id] = (int) db_value("SELECT era_floor FROM ws_channels WHERE id = '" . $channel_id . "'");
    }

    return $cache[$channel_id];
}

function ws_channel_floor_forget()
{
    ws_channel_floor(0, true);
}

/**
 * Is the message in an earlier version of its channel's conversation?
 *
 * @param array|null $message
 * @return bool
 */
function ws_message_in_past($message)
{
    return is_array($message) && ((int) $message['id'] <= ws_channel_floor($message['channel_id']));
}

/**
 * May this person clear the channel's conversation? Staff inside it, as for
 * making it public or private (ws_can_change_channel_kind()).
 *
 * @param array $viewer
 * @param array $channel
 * @return bool
 */
function ws_can_clear_channel($viewer, $channel)
{
    return ws_eras_ready() && ws_can_change_channel_kind($viewer, $channel);
}

/**
 * The earlier versions of a channel's conversation, the newest first.
 *
 * @param int $channel_id
 * @return array[]
 */
function ws_channel_eras($channel_id)
{
    if (!ws_eras_ready()) {
        return array();
    }

    return (array) db_items("SELECT * FROM ws_channel_eras WHERE channel_id = '" . (int) $channel_id . "' ORDER BY number DESC");
}

/**
 * @param int $channel_id
 * @param int $era_id
 * @return array|null
 */
function ws_channel_era($channel_id, $era_id)
{
    if (!ws_eras_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_channel_eras WHERE id = '" . (int) $era_id . "' AND channel_id = '" . (int) $channel_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The earlier version a message of the channel is in.
 *
 * @param int $channel_id
 * @param int $message_id
 * @return array|null
 */
function ws_channel_era_of($channel_id, $message_id)
{
    if (!ws_eras_ready() || ((int) $message_id > ws_channel_floor($channel_id))) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_channel_eras
        WHERE channel_id = '" . (int) $channel_id . "' AND last_message_id >= '" . (int) $message_id . "'
        ORDER BY number ASC LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The first message a version may hold: the one after where the version
 * before it ended.
 *
 * @param array $era
 * @return int
 */
function ws_era_start_id($era)
{
    return (int) db_value("SELECT COALESCE(MAX(last_message_id), 0) FROM ws_channel_eras
        WHERE channel_id = '" . (int) $era['channel_id'] . "' AND number < '" . (int) $era['number'] . "'") + 1;
}

/**
 * A page of an earlier version, oldest first: the end of it, or the page
 * before a message of it.
 *
 * @param array $era
 * @param int   $before_id
 * @param int   $limit
 * @return array[]
 */
function ws_messages_era_page($era, $before_id = 0, $limit = 50)
{
    $top = ((int) $before_id > 0) ? min((int) $before_id - 1, (int) $era['last_message_id']) : (int) $era['last_message_id'];

    $rows = (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $era['channel_id'] . "'
        AND id >= '" . ws_era_start_id($era) . "' AND id <= '" . $top . "'
        ORDER BY id DESC
        LIMIT " . max(1, min(200, (int) $limit)));

    return array_reverse($rows);
}

/**
 * The messages of an earlier version around one of them.
 *
 * @param array $era
 * @param int   $message_id
 * @return array[]
 */
function ws_messages_era_around($era, $message_id)
{
    $start = ws_era_start_id($era);

    $before = (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $era['channel_id'] . "' AND id >= '" . $start . "' AND id <= '" . (int) $message_id . "'
        ORDER BY id DESC LIMIT 30");

    $after = (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $era['channel_id'] . "' AND id > '" . (int) $message_id . "' AND id <= '" . (int) $era['last_message_id'] . "'
        ORDER BY id ASC LIMIT 30");

    return array_merge(array_reverse($before), $after);
}

/**
 * Are there messages of the version before this one?
 *
 * @param array $era
 * @param int   $first_id the first message shown
 * @return bool
 */
function ws_era_has_more($era, $first_id)
{
    return (int) db_value("SELECT COUNT(*) FROM ws_messages
        WHERE channel_id = '" . (int) $era['channel_id'] . "' AND id >= '" . ws_era_start_id($era) . "' AND id < '" . (int) $first_id . "'") > 0;
}

/**
 * A version for the screen.
 *
 * @param array $era
 * @return array
 */
function ws_era_present($era)
{
    $from = (int) $era['started_at'];
    $to = (int) $era['ended_at'];
    $format = function ($time) {
        return ($time > 0) ? trim(strip_tags(get_absolute_time(array('timestamp' => $time, 'type' => 'date', 'size' => 'short')))) : '';
    };

    return array(
        'id'         => (int) $era['id'],
        'number'     => (int) $era['number'],
        'title'      => (string) $era['title'],
        'label'      => ((string) $era['title'] !== '') ? (string) $era['title'] : lang(array('string' => 'Version {var:1}', 'vars' => (int) $era['number'])),
        'count'      => (int) $era['message_count'],
        'from'       => $from,
        'to'         => $to,
        'from_text'  => $format($from),
        'to_text'    => $format($to),
        'cleared_by' => ws_person_name($era['cleared_by']),
        'last_id'    => (int) $era['last_message_id'],
    );
}

/**
 * Clears the channel's conversation: what was written so far becomes an
 * earlier version, and the conversation starts again with a line saying so.
 * Nothing is deleted; nobody's unread count keeps what they can no longer
 * see in front of them.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $title a name for the version kept, optional
 * @return array ok, error, era (ws_era_present())
 */
function ws_channel_clear($viewer, $channel, $title = '')
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'era' => null);
    };

    if (!ws_eras_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    if (!ws_can_clear_channel($viewer, $channel)) {
        return $fail(lang('Only staff who are in the channel can clear its conversation.'));
    }

    $channel_id = (int) $channel['id'];
    $floor = ws_channel_floor($channel_id);
    $span = db_item("SELECT MIN(id) AS first_id, MAX(id) AS last_id, MIN(created_at) AS started_at,
            SUM(deleted_at = 0 AND sender_kind <> 'system') AS written
        FROM ws_messages WHERE channel_id = '" . $channel_id . "' AND id > '" . $floor . "'");

    if (!is_array($span) || ((int) $span['last_id'] <= $floor) || ((int) $span['written'] === 0)) {
        return $fail(lang('There is nothing to clear: nothing has been written since the conversation began.'));
    }

    $number = (int) db_value("SELECT COALESCE(MAX(number), 0) FROM ws_channel_eras WHERE channel_id = '" . $channel_id . "'") + 1;
    $last_id = (int) $span['last_id'];
    $now = time();

    db("INSERT INTO ws_channel_eras (channel_id, number, title, first_message_id, last_message_id, message_count, started_at, ended_at, cleared_by)
        VALUES ('" . $channel_id . "', '" . $number . "', '" . e(mb_substr(trim((string) $title), 0, 160)) . "',
            '" . (int) $span['first_id'] . "', '" . $last_id . "', '" . (int) $span['written'] . "',
            '" . (int) $span['started_at'] . "', '" . $now . "', '" . (int) $viewer['id'] . "')");

    $era_id = (int) mysqli_insert_id(db::$con);

    db("UPDATE ws_channels SET era_floor = '" . $last_id . "' WHERE id = '" . $channel_id . "'");
    ws_channel_floor_forget();

    // What stayed unread is in the version before now: nobody is counted
    // unread for lines they can no longer see in front of them.
    db("UPDATE ws_channel_members SET last_read_id = GREATEST(last_read_id, '" . $last_id . "') WHERE channel_id = '" . $channel_id . "'");

    // A message pinned above the conversation belonged to it.
    if (function_exists('ws_pins_ready') && ws_pins_ready()) {
        db("UPDATE ws_channels SET pinned_message_id = 0 WHERE id = '" . $channel_id . "' AND pinned_message_id <= '" . $last_id . "'");
    }

    ws_message_system($channel_id, lang(array(
        'string' => '{var:1} cleared the conversation. What was written before is kept as version {var:2}; it is listed in the channel\'s settings.',
        'vars'   => array('<@user:' . (int) $viewer['id'] . '>', $number),
    )));

    log_activity(lang(array('string' => 'the conversation of workspace channel ({var:1}) was cleared (version {var:2} kept)', 'vars' => array($channel['name'], $number))), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'era' => ws_era_present(ws_channel_era($channel_id, $era_id)));
}

/**
 * Gives an earlier version a name, or takes it off.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param array  $era
 * @param string $title
 * @return array ok, error
 */
function ws_channel_era_rename($viewer, $channel, $era, $title)
{
    if (!ws_can_clear_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('Only staff who are in the channel can clear its conversation.'));
    }

    db("UPDATE ws_channel_eras SET title = '" . e(mb_substr(trim((string) $title), 0, 160)) . "' WHERE id = '" . (int) $era['id'] . "'");

    return array('ok' => true, 'error' => '');
}

/**
 * The words the screen needs for the versions of a conversation.
 *
 * @return array
 */
function ws_eras_js_strings()
{
    return array(
        'era_clear'          => lang('Clear the conversation'),
        'era_clear_help'     => lang('The conversation starts again. Nothing is deleted: what was written so far is kept as an earlier version, listed here, that everybody in the channel can read as it was.'),
        'era_clear_confirm'  => lang('Clear the conversation of this channel? What was written so far is kept as an earlier version in the channel\'s settings and can still be read; the conversation starts again.'),
        'era_title'          => lang('A name for the version kept'),
        'era_title_help'     => lang('Optional, like “Before the launch”. Without one it is called by its number.'),
        'era_cleared'        => lang('The conversation is cleared. The earlier one is kept.'),
        'era_list'           => lang('Versions of the conversation'),
        'era_current'        => lang('The current conversation'),
        'era_current_since'  => ws_js_template('Since {var:1}', 1),
        'era_none'           => lang('The conversation has never been cleared.'),
        'era_open'           => lang('Read'),
        'era_rename'         => lang('Rename'),
        'era_meta'           => ws_js_template('{var:1} – {var:2} · {var:3} messages · cleared by {var:4}', 4),
        'era_bar'            => ws_js_template('You are reading {var:1} of this conversation ({var:2} – {var:3}). It is kept as it was: nothing in it can be changed.', 3),
        'era_back'           => lang('Back to the current conversation'),
        'era_readonly'       => lang('An earlier version of the conversation is read only.'),
        'era_past_message'   => lang('This message is in an earlier version of the conversation.'),
    );
}
