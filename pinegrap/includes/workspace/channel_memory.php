<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's memory: entries the members and the agents keep
 * (a title, a text, tags, pinned, an expiry) in any channel, and the index
 * every agent brief carries (ids, titles and tags, the pinned entries first,
 * then the newest, then the channel's decision lines, within a size the
 * brief can afford): the agent reads the entries it needs on demand. Who
 * marked a message (a person or an application) is read here too.
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
 * The longest title, text and tag of an entry, and its most tags.
 */
define('WS_MEMORY_TITLE_MAX', 150);
define('WS_MEMORY_TEXT_MAX', 4000);
define('WS_MEMORY_TAG_MAX', 32);
define('WS_MEMORY_TAGS_MAX', 10);

/**
 * The digest's default size in characters: room for the brief's other parts.
 */
define('WS_MEMORY_DIGEST_MAX', 6000);

/**
 * An entry's tags as stored: trimmed, lower case, without a leading #,
 * each once, in the order given. Pure.
 *
 * @param string|string[] $tags a list, or one text split at commas and spaces
 * @return string[]
 */
function ws_memory_tags($tags)
{
    if (!is_array($tags)) {
        $tags = preg_split('/[\s,]+/u', (string) $tags);
    }

    $out = array();

    foreach ($tags as $tag) {
        $tag = mb_strtolower(ltrim(trim((string) $tag), '#'));

        if (($tag !== '') && !in_array($tag, $out, true)) {
            $out[] = $tag;
        }
    }

    return $out;
}

/**
 * What is wrong with a memory entry: '' when nothing is. Pure: the time is
 * given so an expiry in the past is refused against the caller's clock.
 *
 * @param array $entry title, text, tags (ws_memory_tags() form), expires_at
 *                     (0 for never)
 * @param int   $now
 * @return string
 */
function ws_memory_entry_problem($entry, $now)
{
    $fault = ws_memory_entry_fault($entry, $now);

    return $fault ? $fault['error'] : '';
}

/**
 * The first fault of a memory entry and the field it is in, or null. Pure.
 *
 * @param array $entry as ws_memory_entry_problem()
 * @param int   $now
 * @return array|null field, error
 */
function ws_memory_entry_fault($entry, $now)
{
    $title = trim((string) ($entry['title'] ?? ''));
    $text = trim((string) ($entry['text'] ?? ''));
    $tags = (array) ($entry['tags'] ?? array());
    $expires_at = (int) ($entry['expires_at'] ?? 0);

    $fault = function ($field, $error) {
        return array('field' => $field, 'error' => $error);
    };

    if ($title === '') {
        return $fault('title', lang('A memory entry needs a title.'));
    }

    if (mb_strlen($title) > WS_MEMORY_TITLE_MAX) {
        return $fault('title', lang(array('string' => 'A memory entry\'s title is at most {var:1} characters.', 'vars' => WS_MEMORY_TITLE_MAX)));
    }

    if (mb_strlen($text) > WS_MEMORY_TEXT_MAX) {
        return $fault('text', lang(array('string' => 'A memory entry\'s text is at most {var:1} characters.', 'vars' => WS_MEMORY_TEXT_MAX)));
    }

    if (count($tags) > WS_MEMORY_TAGS_MAX) {
        return $fault('tags', lang(array('string' => 'A memory entry has at most {var:1} tags.', 'vars' => WS_MEMORY_TAGS_MAX)));
    }

    foreach ($tags as $tag) {
        if (!preg_match('/^[\p{Ll}\p{Lo}\p{N}][\p{Ll}\p{Lo}\p{N}_.\-]*$/u', (string) $tag) || (mb_strlen((string) $tag) > WS_MEMORY_TAG_MAX)) {
            return $fault('tags', lang(array('string' => 'A tag is one word of letters, digits, dots, dashes or underscores, at most {var:1} characters.', 'vars' => WS_MEMORY_TAG_MAX)));
        }
    }

    if (($expires_at < 0) || (($expires_at > 0) && ($expires_at <= (int) $now))) {
        return $fault('expires_at', lang('A memory entry\'s expiry must be in the future, or left empty.'));
    }

    return null;
}

/**
 * The index of a channel's memory for an agent's brief: the live entries,
 * pinned first and then the newest, each as "- #id title [tags]" on one
 * line (no texts: the agent reads the entries it needs with
 * workspace.memory.get), then the decision lines, newest first. A line that
 * no longer fits is left out whole, never cut, with every line after it,
 * and the count left out closes the digest so the agent knows to read the
 * rest. Pure.
 *
 * @param array[] $entries   id, title, tags, pinned, expires_at, updated_at
 * @param array[] $decisions body, marked_at (newest first)
 * @param int     $now
 * @param int     $max       characters
 * @return string '' when there is nothing to tell
 */
function ws_memory_digest($entries, $decisions, $now, $max = WS_MEMORY_DIGEST_MAX)
{
    $live = array();

    foreach ((array) $entries as $entry) {
        $expires_at = (int) ($entry['expires_at'] ?? 0);

        if (($expires_at === 0) || ($expires_at > (int) $now)) {
            $live[] = $entry;
        }
    }

    usort($live, function ($a, $b) {
        $pinned = (int) !empty($b['pinned']) - (int) !empty($a['pinned']);

        if ($pinned !== 0) {
            return $pinned;
        }

        $newer = (int) ($b['updated_at'] ?? 0) - (int) ($a['updated_at'] ?? 0);

        return ($newer !== 0) ? $newer : ((int) ($b['id'] ?? 0) - (int) ($a['id'] ?? 0));
    });

    $one_line = function ($text) {
        return trim(preg_replace('/\s+/u', ' ', (string) $text));
    };

    $sections = array();

    foreach ($live as $entry) {
        $tags = ws_memory_tags($entry['tags'] ?? array());
        $line = '- #' . (int) ($entry['id'] ?? 0) . ' ' . $one_line($entry['title'] ?? '');

        if ($tags) {
            $line .= ' [' . implode(', ', $tags) . ']';
        }

        $sections['memory'][] = $line;
    }

    foreach ((array) $decisions as $decision) {
        $body = $one_line($decision['body'] ?? '');

        if ($body !== '') {
            $sections['decisions'][] = '- ' . $body;
        }
    }

    if (!$sections) {
        return '';
    }

    $headings = array(
        'memory'    => 'Channel memory (read an entry with workspace.memory.get, its id without the #):',
        'decisions' => 'Decisions in this channel:',
    );
    $out = array();
    $used = 0;
    $left = 0;
    $full = false;

    foreach ($headings as $key => $heading) {
        if (empty($sections[$key])) {
            continue;
        }

        $kept = array();

        foreach ($sections[$key] as $line) {
            $cost = mb_strlen($line) + 1 + ($kept ? 0 : mb_strlen($heading) + 2);

            // Once a line does not fit, the lines after it wait too, so a
            // short old line never takes the place of a newer one.
            if ($full || ($used + $cost > $max)) {
                $full = true;
                $left++;
                continue;
            }

            $kept[] = $line;
            $used += $cost;
        }

        if ($kept) {
            $out[] = $heading . "\n" . implode("\n", $kept);
        }
    }

    if ($left > 0) {
        $out[] = '(' . $left . ' more not shown: list them with workspace.memory.list and workspace.decisions.list.)';
    }

    return implode("\n\n", $out);
}

/* ---------------------------------------------------------------------------
   The rows
   --------------------------------------------------------------------------- */

/**
 * Whether the memory table is there: files land before the upgrade runs.
 *
 * @return bool
 */
function ws_memory_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_channel_memory', 'updated_by_id');
    }

    return $ready;
}

/**
 * Whether ws_messages says who marked a message as a kind and an id.
 *
 * @return bool
 */
function ws_marks_kind_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_messages', 'marked_by_kind');
    }

    return $ready;
}

/**
 * Whether an application (an agent) marked the message, not a person.
 *
 * @param array $row ws_messages
 * @return bool
 */
function ws_mark_by_app($row)
{
    return ((string) ($row['marked_by_kind'] ?? 'user') === 'app') && ((int) ($row['marked_by'] ?? 0) > 0);
}

/**
 * The person ids among the markers of these messages, for ws_people().
 *
 * @param array[] $rows ws_messages
 * @return int[]
 */
function ws_mark_people_ids($rows)
{
    $ids = array();

    foreach ((array) $rows as $row) {
        if (((int) ($row['marked_by'] ?? 0) > 0) && !ws_mark_by_app($row)) {
            $ids[] = (int) $row['marked_by'];
        }
    }

    return array_values(array_unique($ids));
}

/**
 * The name of who marked a message: a person from $people, or the
 * application an agent writes through; '' when nobody did.
 *
 * @param array $row    ws_messages
 * @param array $people ws_people()
 * @return string
 */
function ws_mark_by_name($row, $people)
{
    $id = (int) ($row['marked_by'] ?? 0);

    if ($id <= 0) {
        return '';
    }

    if (ws_mark_by_app($row)) {
        return (string) (ws_app_sender($id)['name'] ?? '');
    }

    return (string) ($people[$id]['name'] ?? '');
}

/**
 * The name of a writer of an entry.
 *
 * @param string $kind user | app
 * @param int    $id
 * @return string
 */
function ws_memory_writer_name($kind, $id)
{
    if ((int) $id <= 0) {
        return '';
    }

    return ($kind === 'app') ? (string) (ws_app_sender((int) $id)['name'] ?? '') : ws_person_name((int) $id);
}

/**
 * Whether a channel shows the Memory tab: an agent is in it (an allowed
 * channel-agent row). Elsewhere its memory opens from the channel settings.
 *
 * @param int $channel_id
 * @return bool
 */
function ws_memory_tab_shown($channel_id)
{
    if (!function_exists('ws_agents_ready') || !ws_agents_ready()) {
        return false;
    }

    return (int) db_value("SELECT COUNT(*) FROM ws_channel_agents WHERE channel_id = '" . (int) $channel_id . "' AND allowed = 1") > 0;
}

/**
 * One entry, or null.
 *
 * @param int $id
 * @return array|null
 */
function ws_memory_row($id)
{
    if (!ws_memory_ready() || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_channel_memory WHERE id = '" . (int) $id . "'");

    return (is_array($row) && $row) ? $row : null;
}

/**
 * A channel's entries, pinned first and then the newest.
 *
 * @param int  $channel_id
 * @param bool $expired the expired ones too
 * @return array[]
 */
function ws_memory_rows($channel_id, $expired = true)
{
    if (!ws_memory_ready()) {
        return array();
    }

    return (array) db_items("SELECT * FROM ws_channel_memory
        WHERE channel_id = '" . (int) $channel_id . "'"
        . ($expired ? '' : " AND (expires_at = 0 OR expires_at > '" . time() . "')") . "
        ORDER BY pinned DESC, updated_at DESC, id DESC
        LIMIT 500");
}

/**
 * An entry as the panel and the API give it.
 *
 * @param array $row ws_channel_memory
 * @param bool  $text with its text (the list leaves it out)
 * @return array
 */
function ws_memory_present($row, $text = true)
{
    $expires_at = (int) $row['expires_at'];
    $out = array(
        'id'         => (int) $row['id'],
        'channel_id' => (int) $row['channel_id'],
        'title'      => (string) $row['title'],
        'tags'       => ws_memory_tags((string) $row['tags']),
        'pinned'     => !empty($row['pinned']),
        'expires_at' => $expires_at,
        'expired'    => ($expires_at > 0) && ($expires_at <= time()),
        'author'     => array('kind' => (string) $row['author_kind'], 'id' => (int) $row['author_id'], 'name' => ws_memory_writer_name((string) $row['author_kind'], (int) $row['author_id'])),
        'updated_by' => array('kind' => (string) $row['updated_by_kind'], 'id' => (int) $row['updated_by_id'], 'name' => ws_memory_writer_name((string) $row['updated_by_kind'], (int) $row['updated_by_id'])),
        'created_at' => (int) $row['created_at'],
        'updated_at' => (int) $row['updated_at'],
    );

    if ($text) {
        $out['text'] = (string) $row['text'];
    }

    return $out;
}

/**
 * What a write of an entry would store, checked: the fields given over the
 * entry as it is (a new one: empty, not pinned, never expiring). Whoever may
 * post in the channel writes its memory; the panel and the API share this.
 *
 * @param array      $viewer
 * @param array      $channel
 * @param array      $input   title, text, tags (a list or one text), pinned, expires_at (0 never)
 * @param array|null $current the entry changed, null for a new one
 * @return array ok, error, field, forbidden, values (title, text, tags as text, pinned, expires_at)
 */
function ws_memory_check($viewer, $channel, $input, $current = null)
{
    $fail = function ($error, $field = '', $forbidden = false) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'forbidden' => $forbidden, 'values' => array());
    };

    if (!ws_memory_ready()) {
        return $fail(lang('The database has to be updated first.'));
    }

    if (!$channel || !ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('You cannot post in that channel.'), '', true);
    }

    $pick = function ($key, $default) use ($input, $current) {
        if (array_key_exists($key, (array) $input) && ($input[$key] !== null)) {
            return $input[$key];
        }

        return $current ? $current[$key] : $default;
    };

    $entry = array(
        'title'      => trim((string) $pick('title', '')),
        'text'       => trim(str_replace("\r\n", "\n", (string) $pick('text', ''))),
        'tags'       => ws_memory_tags($pick('tags', '')),
        'expires_at' => (int) $pick('expires_at', 0),
    );

    // An expiry kept as it was may have passed meanwhile: only a new one is
    // held against the clock.
    $check = $entry;

    if ($current && ((int) $current['expires_at'] === $entry['expires_at'])) {
        $check['expires_at'] = 0;
    }

    $fault = ws_memory_entry_fault($check, time());

    if ($fault) {
        return $fail($fault['error'], $fault['field']);
    }

    $entry['tags'] = implode(',', $entry['tags']);
    $entry['pinned'] = (int) (bool) $pick('pinned', 0);

    return array('ok' => true, 'error' => '', 'field' => '', 'forbidden' => false, 'values' => $entry);
}

/**
 * Writes an entry checked by ws_memory_check().
 *
 * @param int        $channel_id
 * @param array      $values  ws_memory_check()['values']
 * @param string     $kind    user | app: who writes
 * @param int        $id      their user or application id
 * @param array|null $current the entry changed, null for a new one
 * @return int the entry's id
 */
function ws_memory_save($channel_id, $values, $kind, $id, $current = null)
{
    $kind = ($kind === 'app') ? 'app' : 'user';
    $now = time();
    $set = "title = '" . e($values['title']) . "',
        text = '" . e($values['text']) . "',
        tags = '" . e($values['tags']) . "',
        pinned = '" . (int) $values['pinned'] . "',
        expires_at = '" . (int) $values['expires_at'] . "',
        updated_by_kind = '" . $kind . "',
        updated_by_id = '" . (int) $id . "',
        updated_at = '" . $now . "'";

    if ($current) {
        db("UPDATE ws_channel_memory SET " . $set . " WHERE id = '" . (int) $current['id'] . "'");

        return (int) $current['id'];
    }

    db("INSERT INTO ws_channel_memory SET " . $set . ",
        channel_id = '" . (int) $channel_id . "',
        author_kind = '" . $kind . "',
        author_id = '" . (int) $id . "',
        created_at = '" . $now . "'");

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Deletes an entry: whoever may post in its channel.
 *
 * @param array $viewer
 * @param array $row ws_channel_memory
 * @return array ok, error, forbidden
 */
function ws_memory_delete($viewer, $row)
{
    if (!ws_can_post_channel($viewer, ws_channel($row['channel_id']))) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'), 'forbidden' => true);
    }

    db("DELETE FROM ws_channel_memory WHERE id = '" . (int) $row['id'] . "'");

    return array('ok' => true, 'error' => '', 'forbidden' => false);
}

/**
 * Deletes the entries expired more than 30 days ago and those of channels
 * that are gone (a discussion deleted with what was said in it).
 *
 * @param int $limit
 * @return int how many
 */
function ws_memory_sweep($limit = 500)
{
    if (!ws_memory_ready()) {
        return 0;
    }

    $ids = array_map('intval', (array) db_values("SELECT m.id FROM ws_channel_memory m
        LEFT JOIN ws_channels c ON c.id = m.channel_id
        WHERE (m.expires_at > 0 AND m.expires_at < '" . (time() - 30 * 86400) . "') OR c.id IS NULL
        LIMIT " . max(1, (int) $limit)));

    if ($ids) {
        db("DELETE FROM ws_channel_memory WHERE id IN (" . implode(',', $ids) . ")");
    }

    return count($ids);
}

/**
 * The memory part of an agent's brief for a channel: the index of its live
 * entries and its decision lines (ws_memory_digest()), the text as the
 * asker reads it.
 *
 * @param array $viewer
 * @param array $channel
 * @return string '' when there is nothing
 */
function ws_memory_brief($viewer, $channel)
{
    if (!$channel) {
        return '';
    }

    $decisions = array();

    foreach ((array) db_items("SELECT body, marked_at FROM ws_messages
        WHERE channel_id = '" . (int) $channel['id'] . "' AND kind = 'decision' AND deleted_at = 0
        ORDER BY marked_at DESC, id DESC
        LIMIT 40") as $row) {
        $decisions[] = array('body' => ws_plain_text($viewer, (string) $row['body']), 'marked_at' => (int) $row['marked_at']);
    }

    return ws_memory_digest(ws_memory_rows($channel['id'], false), $decisions, time());
}

/**
 * The words of the Memory tab (assets/js/workspace_memory.js).
 *
 * @return array key => text
 */
function ws_memory_js_strings()
{
    return array(
        'tab_memory'        => lang('Memory'),
        'mem_open'          => lang('Channel memory'),
        'mem_help'          => lang('What the members and the agents of this channel should keep knowing. Agents see the titles and tags in every request and read the entries they need.'),
        'mem_empty'         => lang('Nothing in this channel\'s memory yet.'),
        'mem_add'           => lang('New entry'),
        'mem_edit'          => lang('Edit'),
        'mem_delete'        => lang('Delete'),
        'mem_delete_ask'    => lang('Delete this memory entry? It cannot be undone.'),
        'mem_title'         => lang('Title'),
        'mem_text'          => lang('Text'),
        'mem_tags'          => lang('Tags'),
        'mem_tags_help'     => lang('Up to 10 words, separated by commas.'),
        'mem_pinned'        => lang('Pinned: first in the list agents see'),
        'mem_expires'       => lang('Expires on'),
        'mem_expires_help'  => lang('Left empty, it never expires. An expired entry leaves the agents\' list and is deleted 30 days later.'),
        'mem_save'          => lang('Save'),
        'mem_cancel'        => lang('Cancel'),
        'mem_saved'         => lang('Saved.'),
        'mem_expired'       => lang('Expired'),
        'mem_pin_mark'      => lang('Pinned'),
        'mem_by'            => ws_js_template('Written by {var:1}', 1),
        'mem_changed_by'    => ws_js_template('Changed by {var:1}', 1),
        'mem_until'         => ws_js_template('Until {var:1}', 1),
    );
}
