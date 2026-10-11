<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - one search box for the whole workspace, opened with Ctrl+K (or
 * the magnifier of the rail) on every workspace screen. What is typed is
 * looked for in the channels, the messages, the decisions, the tasks, the
 * notes, the files posted in channels and the records a tag may point at,
 * and the answer comes back in groups of a few lines each.
 *
 * Each group asks the function its own screen asks - ws_message_search(),
 * ws_tasks_list(), ws_notes_list(), ws_ref_search(), ws_channels_for() - so
 * whatever decides who may see what there decides it here too. The decisions
 * and the files have no search of their own: they are read the way
 * ws_message_search() reads messages, newest first, each one kept only when
 * its channel is open to the reader (ws_can_read_channel()), and without the
 * ones the reader deleted for themselves.
 *
 * A first character changes what is looked for: # a record (with the kind's
 * prefix, "#sip 1042", only that kind), @ a person of the team, / one of the
 * writing box's commands. The screen side is assets/js/workspace_palette.js.
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
 * Lines per group unless the screen asks for fewer.
 */
define('WS_PALETTE_LIMIT', 8);

/**
 * The groups of an answer, in the order they are shown.
 *
 * @return array key => title, icon
 */
function ws_palette_groups()
{
    return array(
        'channels'  => array('title' => lang('Channels'), 'icon' => 'bi-hash'),
        'messages'  => array('title' => lang('Messages'), 'icon' => 'bi-chat-left-text'),
        'decisions' => array('title' => lang('Decisions'), 'icon' => 'bi-patch-check'),
        'tasks'     => array('title' => lang('Tasks'), 'icon' => 'bi-check2-square'),
        'notes'     => array('title' => lang('Notes'), 'icon' => 'bi-journal-text'),
        'files'     => array('title' => lang('Files'), 'icon' => 'bi-paperclip'),
        'records'   => array('title' => lang('Panel records'), 'icon' => 'bi-tags'),
    );
}

/**
 * What is being looked for, read off the first character: # records, @
 * people, / commands, anything else every group.
 *
 * @param string $q
 * @return array mode (all | records | people | commands), query (without the character)
 */
function ws_palette_mode($q)
{
    $q = trim(preg_replace('/\s+/u', ' ', (string) $q));
    $first = mb_substr($q, 0, 1);
    $modes = array('#' => 'records', '@' => 'people', '/' => 'commands');

    if (isset($modes[$first])) {
        return array('mode' => $modes[$first], 'query' => trim(mb_substr($q, 1)));
    }

    return array('mode' => 'all', 'query' => $q);
}

/**
 * The part of a long text around the first place the words are found, so a
 * line shows why it matched. Cut at a word where it can be.
 *
 * @param string $text   plain text
 * @param string $query
 * @param int    $length characters at most, the ellipses included
 * @return string
 */
function ws_palette_snippet($text, $query, $length = 120)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
    $length = max(20, (int) $length);

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    $at = ((string) $query !== '') ? mb_stripos($text, (string) $query) : false;

    // Found near the start, or not at all: the start of the text.
    if (($at === false) || ($at < (int) ($length / 3))) {
        return rtrim(mb_substr($text, 0, $length - 1)) . '…';
    }

    $start = $at - (int) ($length / 3);
    $space = mb_strpos($text, ' ', $start);

    if (($space !== false) && ($space < $at)) {
        $start = $space + 1;
    }

    $piece = mb_substr($text, $start, $length - 2);

    return '…' . rtrim($piece) . ((($start + mb_strlen($piece)) < mb_strlen($text)) ? '…' : '');
}

/**
 * Messages that match, newest first, of the channels this person can read:
 * read the way ws_message_search() reads them (the channel of every row is
 * asked about, and only a readable one's rows are kept), without the ones the
 * person deleted for themselves.
 *
 * @param array  $viewer
 * @param string $where      the extra condition, built from escaped values
 * @param int    $channel_id 0 for every channel
 * @param int    $limit
 * @return array[] rows of ws_messages, each with _channel (the channel row)
 */
function ws_palette_message_rows($viewer, $where, $channel_id, $limit)
{
    $rows = (array) db_items("SELECT * FROM ws_messages
        WHERE deleted_at = 0 AND " . $where
        . (((int) $channel_id > 0) ? " AND channel_id = '" . (int) $channel_id . "'" : '') . "
        ORDER BY id DESC
        LIMIT 200");

    $channels = array();
    $visible = array();

    foreach ($rows as $row) {
        $id = (int) $row['channel_id'];

        if (!array_key_exists($id, $channels)) {
            $channels[$id] = ws_channel($id);
        }

        if (ws_can_read_channel($viewer, $channels[$id])) {
            $row['_channel'] = $channels[$id];
            $visible[] = $row;
        }

        // A few more than shown: some may be hidden from this person.
        if (count($visible) >= ($limit * 2)) {
            break;
        }
    }

    $hidden = ws_message_hidden_ids($viewer['id'], array_map(function ($row) { return (int) $row['id']; }, $visible));
    $out = array();

    foreach ($visible as $row) {
        if (!isset($hidden[(int) $row['id']])) {
            $out[] = $row;
        }

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * Who wrote a message, as a name.
 *
 * @param array $row
 * @param array $people ws_people() of the user senders
 * @return string
 */
function ws_palette_sender_name($row, $people)
{
    if ($row['sender_kind'] === 'user') {
        return (string) ($people[(int) $row['sender_id']]['name'] ?? '');
    }

    if ($row['sender_kind'] === 'app') {
        return (string) (ws_app_sender((int) $row['sender_id'])['name'] ?? '');
    }

    if (($row['sender_kind'] === 'guest') && function_exists('ws_guest_sender')) {
        return (string) (ws_guest_sender((int) $row['sender_id'])['name'] ?? '');
    }

    return '';
}

/**
 * Message rows as lines of the answer.
 *
 * @param array   $viewer
 * @param array[] $rows   with _channel
 * @param string  $type   message | decision | file
 * @param string  $query
 * @return array[]
 */
function ws_palette_message_items($viewer, $rows, $type, $query)
{
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    $senders = array();
    $files = array();

    foreach ($rows as $row) {
        if ($row['sender_kind'] === 'user') {
            $senders[] = (int) $row['sender_id'];
        }

        if ((int) $row['file_id'] > 0) {
            $files[] = (int) $row['file_id'];
        }
    }

    $people = ws_people(array_unique($senders));
    $stored = array();

    if (!empty($files)) {
        foreach ((array) db_items("SELECT id, name FROM files WHERE id IN (" . implode(',', array_unique($files)) . ")") as $file) {
            $stored[(int) $file['id']] = (string) $file['name'];
        }
    }

    $out = array();

    foreach ($rows as $row) {
        $channel = $row['_channel'];
        $link = $base . 'workspace.php?channel=' . (int) $channel['id'] . '&message=' . (int) $row['id'];
        $text = ws_plain_text($viewer, (string) $row['body']);
        $title = ws_palette_snippet($text, $query, 140);
        $file_url = '';

        if ($type === 'file') {
            $title = (string) $row['file_name'];
            $file_url = isset($stored[(int) $row['file_id']]) ? PATH . encode_url_path($stored[(int) $row['file_id']]) : '';
        } elseif (($title === '') && ((string) $row['file_name'] !== '')) {
            $title = (string) $row['file_name'];
        }

        $sub = array('#' . (string) $channel['name']);
        $sender = ws_palette_sender_name($row, $people);

        if ($sender !== '') {
            $sub[] = $sender;
        }

        // A file found by the line written with it says so.
        if (($type === 'file') && ($text !== '')) {
            $sub[] = ws_palette_snippet($text, $query, 60);
        }

        $out[] = array(
            'type'       => $type,
            'id'         => (int) $row['id'],
            'title'      => $title,
            'sub'        => implode(' · ', $sub),
            'date'       => ws_time_label($row['created_at']),
            'icon'       => ($type === 'decision') ? 'bi-patch-check' : (($type === 'file') ? 'bi-file-earmark' : 'bi-chat-left-text'),
            'url'        => ($type === 'file') ? $file_url : $link,
            'message_url' => $link,
            'channel_id' => (int) $channel['id'],
            'message_id' => (int) $row['id'],
        );
    }

    return $out;
}

/**
 * The channels whose name or subject holds the words.
 *
 * @param array  $viewer
 * @param string $query
 * @param int    $limit
 * @return array[]
 */
function ws_palette_channels($viewer, $query, $limit)
{
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    $out = array();

    foreach (ws_channels_for($viewer) as $channel) {
        if ((mb_stripos($channel['name'], $query) === false) && (mb_stripos($channel['topic'], $query) === false)) {
            continue;
        }

        $out[] = array(
            'type'       => 'channel',
            'id'         => (int) $channel['id'],
            'title'      => (string) $channel['name'],
            'sub'        => ws_palette_snippet((string) $channel['topic'], $query, 90),
            'date'       => ($channel['last_message_at'] > 0) ? ws_time_label($channel['last_message_at']) : '',
            'icon'       => (string) $channel['icon'],
            'url'        => $base . 'workspace.php?channel=' . (int) $channel['id'],
            'channel_id' => (int) $channel['id'],
            'joined'     => (bool) $channel['joined'],
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * The tasks whose title holds the words, of those the person may see
 * (ws_tasks_list() asks ws_can_see_task() of each).
 *
 * @param array  $viewer
 * @param string $query
 * @param int    $channel_id 0 for every channel
 * @param int    $limit
 * @return array[]
 */
function ws_palette_tasks($viewer, $query, $channel_id, $limit)
{
    // More rows than lines: the list drops the tasks the person may not see
    // after reading them.
    $tasks = ws_tasks_list($viewer, array(
        'scope'      => ((int) $channel_id > 0) ? 'channel' : 'all',
        'channel_id' => (int) $channel_id,
        'status'     => 'all',
        'search'     => $query,
        'limit'      => max($limit, 40),
    ));

    $channels = array();
    $out = array();

    foreach (array_slice($tasks, 0, $limit) as $task) {
        $sub = array($task['number'], $task['status_label']);
        $id = (int) $task['channel_id'];

        // Somebody on a task may see it without reading its channel: the
        // channel's name is said only to those who may read it.
        if ($id > 0) {
            if (!array_key_exists($id, $channels)) {
                $channel = ws_channel($id);
                $channels[$id] = ($channel && ws_can_read_channel($viewer, $channel)) ? (string) $channel['name'] : '';
            }

            if ($channels[$id] !== '') {
                $sub[] = '#' . $channels[$id];
            }
        }

        $names = array();

        foreach (array_slice($task['assignees'], 0, 3) as $person) {
            $names[] = $person['name'];
        }

        if (!empty($names)) {
            $sub[] = implode(', ', $names);
        }

        $out[] = array(
            'type'    => 'task',
            'id'      => (int) $task['id'],
            'title'   => (string) $task['title'],
            'sub'     => implode(' · ', $sub),
            'date'    => ($task['due_date'] !== null) ? (string) $task['due_label'] : '',
            'overdue' => (bool) $task['overdue'],
            'done'    => !$task['open'],
            'icon'    => 'bi-check2-square',
            'url'     => (string) $task['url'],
        );
    }

    return $out;
}

/**
 * The person's notes and the ones shared with them that hold the words
 * (ws_notes_list()), the latest change first.
 *
 * @param array  $viewer
 * @param string $query
 * @param int    $limit
 * @return array[]
 */
function ws_palette_notes($viewer, $query, $limit)
{
    if (!function_exists('ws_notes_ready') || !ws_notes_ready()) {
        return array();
    }

    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    $lists = ws_notes_list($viewer, $query);
    $notes = array_merge($lists['mine'], $lists['shared']);

    usort($notes, function ($a, $b) {
        return ($b['updated_at'] <=> $a['updated_at']) ?: ($b['id'] <=> $a['id']);
    });

    $out = array();

    foreach (array_slice($notes, 0, $limit) as $note) {
        $sub = array();

        if (($note['access'] !== 'owner') && $note['owner']) {
            $sub[] = $note['owner']['name'];
        }

        if ($note['excerpt'] !== '') {
            $sub[] = ws_palette_snippet($note['excerpt'], $query, 90);
        }

        $out[] = array(
            'type'  => 'note',
            'id'    => (int) $note['id'],
            'title' => (string) $note['name'],
            'sub'   => implode(' · ', $sub),
            'date'  => (string) $note['updated'],
            'icon'  => 'bi-journal-text',
            'url'   => $base . 'workspace_notes.php?note=' . (int) $note['id'],
        );
    }

    return $out;
}

/**
 * The kind of record "#sip 1042" asks for: a kind's prefix and something
 * after it. Only the kinds the person may tag count.
 *
 * @param array  $types ws_ref_types()
 * @param string $query what follows the #
 * @return array type ('' for every kind), query
 */
function ws_palette_record_type($types, $query)
{
    $words = explode(' ', trim((string) $query), 2);

    if ((count($words) === 2) && (trim($words[1]) !== '')) {
        $prefix = ws_lower($words[0]);

        foreach ($types as $key => $info) {
            if (!empty($info['record']) && in_array($prefix, (array) $info['prefixes'], true)) {
                return array('type' => $key, 'query' => trim($words[1]));
            }
        }
    }

    return array('type' => '', 'query' => trim((string) $query));
}

/**
 * The records a tag may point at (orders, products, contacts, invoices...)
 * that match, with the address of their own screen. The workspace's own
 * kinds - tasks, channels, plan items - have groups of their own.
 *
 * @param array  $viewer
 * @param string $query
 * @param int    $limit
 * @param string $type   one kind, '' for every kind
 * @return array[]
 */
function ws_palette_records($viewer, $query, $limit, $type = '')
{
    $types = ws_ref_types($viewer);
    $kinds = array();

    foreach ($types as $key => $info) {
        if (!empty($info['record'])) {
            $kinds[] = $key;
        }
    }

    $found = ($type !== '')
        ? ws_ref_search($viewer, $type, $query, $limit)
        : ws_ref_search($viewer, 'all', $query, $limit, $kinds);

    $tokens = array();

    foreach ($found as $item) {
        $tokens[] = array('sigil' => '#', 'type' => $item['type'], 'id' => (int) $item['id']);
    }

    $resolved = ws_refs_resolve($viewer, $tokens);
    $out = array();

    foreach (array_slice($found, 0, $limit) as $item) {
        $ref = $resolved[$item['type'] . ':' . (int) $item['id']] ?? null;

        $out[] = array(
            'type'        => 'record',
            'record_type' => (string) $item['type'],
            'id'          => (int) $item['id'],
            'title'       => (string) $item['label'],
            'sub'         => (($type !== '') && isset($types[$type])) ? trim($types[$type]['label'] . ' · ' . $item['meta'], ' ·') : (string) $item['meta'],
            'date'        => '',
            'icon'        => (string) $item['icon'],
            'url'         => $ref ? (string) $ref['url'] : '',
        );
    }

    return $out;
}

/**
 * The people of the team whose name matches; those in the channel open on
 * the screen first.
 *
 * @param array  $viewer
 * @param string $query
 * @param int    $channel_id
 * @param int    $limit
 * @return array[]
 */
function ws_palette_people($viewer, $query, $channel_id, $limit)
{
    $members = array();

    if ((int) $channel_id > 0) {
        $channel = ws_channel($channel_id);

        if ($channel && ws_can_read_channel($viewer, $channel)) {
            $members = array_flip(array_map('intval', (array) db_values("SELECT user_id FROM ws_channel_members WHERE channel_id = '" . (int) $channel_id . "'")));
        }
    }

    $people = array();

    foreach (ws_team_members() as $person) {
        if (($query === '') || (mb_stripos($person['name'], $query) !== false) || (mb_stripos($person['username'], $query) !== false)) {
            $people[] = $person;
        }
    }

    usort($people, function ($a, $b) use ($members) {
        return (isset($members[$b['id']]) <=> isset($members[$a['id']])) ?: strcmp(ws_lower($a['name']), ws_lower($b['name']));
    });

    $out = array();

    foreach (array_slice($people, 0, $limit) as $person) {
        $sub = array();

        if ($person['title'] !== '') {
            $sub[] = $person['title'];
        }

        if (isset($members[$person['id']])) {
            $sub[] = lang('In this channel');
        }

        $out[] = array(
            'type'    => 'person',
            'id'      => (int) $person['id'],
            'title'   => (string) $person['name'],
            'sub'     => implode(' · ', $sub),
            'date'    => '',
            'icon'    => 'bi-person',
            'avatar'  => (string) $person['avatar'],
            'avatar_kind' => (string) $person['avatar_kind'],
            'me'      => ((int) $person['id'] === (int) $viewer['id']),
            'url'     => ws_person_edit_url($viewer, $person),
        );
    }

    return $out;
}

/**
 * The writing box's commands whose names start with what is typed, one line
 * per command, with the name to write.
 *
 * @param string $query
 * @return array[]
 */
function ws_palette_commands($query)
{
    $query = ws_lower((string) $query);
    $help = array();

    foreach (ws_command_help() as $entry) {
        foreach (preg_split('/\s*·\s*/u', (string) $entry['command']) as $name) {
            $help[ltrim($name, '/')] = $entry;
        }
    }

    $out = array();

    foreach (ws_command_names() as $name => $command) {
        if (isset($out[$command]) || (($query !== '') && (strpos($name, $query) !== 0))) {
            continue;
        }

        $entry = $help[$command] ?? null;

        $out[$command] = array(
            'type'   => 'command',
            'id'     => 0,
            'title'  => '/' . $name,
            'sub'    => $entry ? (string) $entry['description'] : lang('Lists the commands and what they do.'),
            'date'   => '',
            'icon'   => 'bi-slash-square',
            'insert' => '/' . $name . ' ',
        );
    }

    return array_values($out);
}

/**
 * One search, every group the person may see.
 *
 * @param array  $viewer
 * @param string $q       as typed, with its first character
 * @param array  $options channel_id (only in that channel), types (the groups to ask), limit
 * @return array mode, query, groups (key, title, icon, items)
 */
function ws_palette_search($viewer, $q, $options = array())
{
    $read = ws_palette_mode(mb_substr((string) $q, 0, 200));
    $query = $read['query'];
    $limit = max(1, min(20, (int) ($options['limit'] ?? WS_PALETTE_LIMIT)));
    $channel_id = (int) ($options['channel_id'] ?? 0);
    $known = ws_palette_groups();
    $out = array('mode' => $read['mode'], 'query' => $query, 'groups' => array());

    // Only in one channel: one the person may read, or nothing at all
    // rather than everywhere.
    if (($channel_id > 0) && !ws_can_read_channel($viewer, ws_channel($channel_id))) {
        return $out;
    }

    if ($read['mode'] === 'people') {
        $out['groups'][] = array('key' => 'people', 'title' => lang('People'), 'icon' => 'bi-people', 'items' => ws_palette_people($viewer, $query, (int) ($options['here'] ?? $channel_id), $limit));

        return $out;
    }

    if ($read['mode'] === 'commands') {
        $out['groups'][] = array('key' => 'commands', 'title' => lang('Slash commands'), 'icon' => 'bi-slash-square', 'items' => ws_palette_commands($query));

        return $out;
    }

    if ($read['mode'] === 'records') {
        $asked = ws_palette_record_type(ws_ref_types($viewer), $query);

        if (mb_strlen($asked['query']) >= (($asked['type'] !== '') ? 1 : 2)) {
            $out['groups'][] = array('key' => 'records') + $known['records'] + array('items' => ws_palette_records($viewer, $asked['query'], $limit, $asked['type']));
        }

        return $out;
    }

    if (mb_strlen($query) < 2) {
        return $out;
    }

    $types = array_values(array_intersect(array_keys($known), array_map('strval', (array) ($options['types'] ?? array()))));

    if (empty($types)) {
        $types = array_keys($known);
    }

    // In one channel only what is written there is asked about.
    if ($channel_id > 0) {
        $types = array_values(array_intersect($types, array('messages', 'decisions', 'tasks', 'files')));
    }

    $like = e(escape_like($query));

    foreach ($types as $key) {
        $items = array();

        switch ($key) {
            case 'channels':
                $items = ws_palette_channels($viewer, $query, $limit);
                break;

            case 'messages':
                // The decisions have their own group.
                $rows = array();

                foreach (ws_message_search($viewer, $query, $channel_id) as $message) {
                    if ($message['kind'] !== 'decision') {
                        $rows[] = (int) $message['id'];
                    }

                    if (count($rows) >= $limit) {
                        break;
                    }
                }

                if (!empty($rows)) {
                    $found = array();

                    foreach ((array) db_items("SELECT * FROM ws_messages WHERE id IN (" . implode(',', $rows) . ") ORDER BY id DESC") as $row) {
                        $row['_channel'] = ws_channel($row['channel_id']);
                        $found[] = $row;
                    }

                    $items = ws_palette_message_items($viewer, $found, 'message', $query);
                }
                break;

            case 'decisions':
                $items = ws_palette_message_items($viewer, ws_palette_message_rows($viewer, "kind = 'decision' AND body LIKE '%" . $like . "%'", $channel_id, $limit), 'decision', $query);
                break;

            case 'tasks':
                $items = ws_palette_tasks($viewer, $query, $channel_id, $limit);
                break;

            case 'notes':
                $items = ws_palette_notes($viewer, $query, $limit);
                break;

            case 'files':
                $items = ws_palette_message_items($viewer, ws_palette_message_rows($viewer, "file_id > 0 AND (file_name LIKE '%" . $like . "%' OR body LIKE '%" . $like . "%')", $channel_id, $limit), 'file', $query);
                break;

            case 'records':
                $items = ws_palette_records($viewer, $query, $limit);
                break;
        }

        if (!empty($items)) {
            $out['groups'][] = array('key' => $key) + $known[$key] + array('items' => $items);
        }
    }

    return $out;
}

/**
 * The words the search box needs (assets/js/workspace_palette.js).
 *
 * @return array key => text
 */
function ws_palette_js_strings()
{
    $strings = array(
        'pal_open'        => lang('Search the workspace'),
        'pal_shortcut'    => lang('Search the workspace (Ctrl+K)'),
        'pal_placeholder' => lang('Search channels, messages, tasks, notes… # a record, @ a person, / a command'),
        'pal_everywhere'  => lang('Everywhere'),
        'pal_here'        => ws_js_template('Only in #{var:1}', 1),
        'pal_all_kinds'   => lang('All'),
        'pal_recent'      => lang('Recent searches'),
        'pal_clear_recent' => lang('Forget the recent searches'),
        'pal_short'       => lang('Type at least two letters.'),
        'pal_hint'        => lang('↑ ↓ to move, Enter to open, Esc to close'),
        'pal_no_composer' => lang('Commands are written in a channel\'s writing box: open a channel first.'),
        'pal_profile'     => lang('Open the account'),
        'pal_count'       => ws_js_template('{var:1} found', 1),
    );

    foreach (ws_palette_groups() as $key => $group) {
        $strings['pal_kind_' . $key] = $group['title'];
    }

    return $strings;
}
