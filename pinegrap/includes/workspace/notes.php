<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - personal notes. Everybody keeps their own, written like a
 * document in the workspace's own writing box: tags for people and records
 * (#), tables and calculation blocks worked out as they are typed, files
 * attached or dropped in, and Claude asked with @Claude. A note can also be
 * taken from any message the person can read - their own or anybody else's,
 * Claude's answers among them - as a copy that remembers where it came from.
 *
 * A note is seen by nobody but the one who wrote it - administrators
 * included - until it is shared: with a person, who finds it under "Shared
 * with me" and may read it or, when allowed, change it (never delete it; they
 * may only leave it), or in a channel, whose members read it through a card
 * that always shows the note as it is now. The note says who changed it last,
 * and shows who else can see it.
 *
 * Tables and calculation blocks in a note are worked out by the same code as
 * in a message (includes/workspace/calc.php), so a figure reads the same
 * everywhere.
 *
 * Files attached to a note go to the file manager like everything uploaded
 * to the site, in a private folder of the note's own (Workspace / Notes /
 * Note <id>): staff see it as they see every private folder, and a basic
 * user holds view access to it exactly while they may read the note (see
 * ws_note_folder_sync()).
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
 * The longest note, in characters.
 */
define('WS_NOTE_MAX', 60000);

/**
 * How many notes one person keeps.
 */
define('WS_NOTE_LIMIT', 2000);

/**
 * Are notes installed (4.110)?
 *
 * @return bool
 */
function ws_notes_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ws_notes', 'ws_note_shares')") === 2)
            && ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_notes' AND COLUMN_NAME = 'updated_by'") > 0);
    }

    return $ready;
}

/**
 * Can an inbox row point at a note (4.110)?
 *
 * @return bool
 */
function ws_notes_inbox_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_inbox' AND COLUMN_NAME = 'note_id'") > 0);
    }

    return $ready;
}

/**
 * One note row.
 *
 * @param int $note_id
 * @return array|null
 */
function ws_note($note_id)
{
    if (!ws_notes_ready() || ((int) $note_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_notes WHERE id = '" . (int) $note_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * What the person may do with a note: it is theirs, they may change it, they
 * may read it, or nothing at all. Being staff gives nothing here.
 *
 * @param array $viewer
 * @param array $note
 * @return string owner | edit | view | ''
 */
function ws_note_access($viewer, $note)
{
    if (!$note) {
        return '';
    }

    if ((int) $note['user_id'] === (int) $viewer['id']) {
        return 'owner';
    }

    $access = '';

    foreach ((array) db_items("SELECT user_id, channel_id, can_edit FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "'") as $share) {
        if ((int) $share['user_id'] === (int) $viewer['id']) {
            if (!empty($share['can_edit'])) {
                return 'edit';
            }

            $access = 'view';
        } elseif (((int) $share['channel_id'] > 0) && ($access === '')) {
            $channel = ws_channel($share['channel_id']);

            if ($channel && ws_can_read_channel($viewer, $channel)) {
                $access = 'view';
            }
        }
    }

    return $access;
}

/**
 * A note the person may reach at least as far as asked.
 *
 * @param array  $viewer
 * @param int    $note_id
 * @param string $need view | edit | owner
 * @return array|null the row, with access
 */
function ws_note_for($viewer, $note_id, $need = 'view')
{
    $note = ws_note($note_id);
    $access = ws_note_access($viewer, $note);
    $rank = array('' => 0, 'view' => 1, 'edit' => 2, 'owner' => 3);

    if (($access === '') || ($rank[$access] < $rank[$need])) {
        return null;
    }

    $note['access'] = $access;

    return $note;
}

/**
 * What a note is called: its title, or its first line, or "Untitled note".
 *
 * @param array $viewer
 * @param array $row
 * @return string
 */
function ws_note_title($viewer, $row)
{
    $title = trim((string) $row['title']);

    if ($title !== '') {
        return $title;
    }

    // The first line with words in it; a table is named by its header.
    foreach (preg_split('/\R/', (string) $row['body']) as $line) {
        if (preg_match('/^\s*```/', $line)) {
            continue;
        }

        if (preg_match('/^\s*\|/', $line)) {
            $line = implode(' · ', array_filter(ws_table_cells($line), 'strlen'));
        } else {
            $line = ws_plain_text($viewer, $line);
        }

        $line = trim($line);

        if ($line !== '') {
            return (mb_strlen($line) > 80) ? rtrim(mb_substr($line, 0, 79)) . '…' : $line;
        }
    }

    return lang('Untitled note');
}

/**
 * The shares of a set of notes.
 *
 * @param int[] $note_ids
 * @return array note id => share rows
 */
function ws_note_shares_map($note_ids)
{
    $note_ids = array_filter(array_map('intval', (array) $note_ids));
    $out = array();

    if (empty($note_ids)) {
        return $out;
    }

    foreach ((array) db_items("SELECT * FROM ws_note_shares WHERE note_id IN (" . implode(',', array_unique($note_ids)) . ") ORDER BY id") as $share) {
        $out[(int) $share['note_id']][] = $share;
    }

    return $out;
}

/**
 * Notes as the screen shows them.
 *
 * @param array   $viewer
 * @param array[] $rows
 * @param bool    $full with the text, drawn and as written
 * @return array[]
 */
function ws_notes_present($viewer, $rows, $full = false)
{
    $people_ids = array();
    $channel_ids = array();
    $message_ids = array();
    $shares = ws_note_shares_map(array_map(function ($row) { return (int) $row['id']; }, $rows));

    foreach ($rows as $row) {
        foreach (array('user_id', 'source_user_id', 'updated_by') as $field) {
            if ((int) ($row[$field] ?? 0) > 0) {
                $people_ids[] = (int) $row[$field];
            }
        }

        foreach (array('source_channel_id', 'channel_id') as $field) {
            if ((int) $row[$field] > 0) {
                $channel_ids[(int) $row[$field]] = true;
            }
        }

        foreach ($shares[(int) $row['id']] ?? array() as $share) {
            if ((int) $share['user_id'] > 0) {
                $people_ids[] = (int) $share['user_id'];
            }

            if ((int) $share['channel_id'] > 0) {
                $channel_ids[(int) $share['channel_id']] = true;
            }
        }

        if ((int) $row['source_message_id'] > 0) {
            $message_ids[] = (int) $row['source_message_id'];
        }
    }

    $people = ws_people(array_unique($people_ids));
    $channels = array();

    foreach (array_keys($channel_ids) as $channel_id) {
        $channel = ws_channel($channel_id);
        $channels[$channel_id] = ($channel && ws_can_read_channel($viewer, $channel)) ? $channel : null;
    }

    // A source message that was deleted is not a place to go back to.
    $alive = array();

    if (!empty($message_ids)) {
        foreach ((array) db_items("SELECT id, sender_kind, sender_id FROM ws_messages
            WHERE id IN (" . implode(',', array_unique($message_ids)) . ") AND deleted_at = 0") as $message) {
            $alive[(int) $message['id']] = $message;
        }
    }

    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    $out = array();

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $access = $row['access'] ?? ws_note_access($viewer, $row);
        $owner = ($access === 'owner');
        $body = (string) $row['body'];
        $source = null;
        $source_channel = $channels[(int) $row['source_channel_id']] ?? null;

        if ((int) $row['source_message_id'] > 0) {
            $message = $alive[(int) $row['source_message_id']] ?? null;
            $sender = null;

            if ((int) $row['source_user_id'] > 0) {
                $sender = $people[(int) $row['source_user_id']] ?? null;
            } elseif ($message && ($message['sender_kind'] === 'app')) {
                $sender = ws_app_sender((int) $message['sender_id']);
            }

            $source = array(
                'channel' => $source_channel ? array('id' => (int) $source_channel['id'], 'name' => (string) $source_channel['name']) : null,
                'sender'  => $sender,
                'url'     => ($source_channel && $message) ? $base . 'workspace.php?channel=' . (int) $source_channel['id'] . '&message=' . (int) $row['source_message_id'] : '',
            );
        }

        // Who can see it: the one it belongs to, the people it is shared
        // with and the channels it is shared in. The details of a share (to
        // take it back) are the owner's alone.
        $with = array();
        $in = array();

        foreach ($shares[$id] ?? array() as $share) {
            if ((int) $share['user_id'] > 0) {
                $person = $people[(int) $share['user_id']] ?? null;

                if ($person) {
                    $with[] = array(
                        'share_id' => $owner ? (int) $share['id'] : 0,
                        'person'   => $person,
                        'can_edit' => !empty($share['can_edit']),
                    );
                }
            } elseif ((int) $share['channel_id'] > 0) {
                $channel = $channels[(int) $share['channel_id']] ?? null;

                if ($channel || $owner) {
                    $in[] = array(
                        'share_id' => $owner ? (int) $share['id'] : 0,
                        'id'       => (int) $share['channel_id'],
                        'name'     => $channel ? (string) $channel['name'] : '',
                        'url'      => $base . 'workspace.php?channel=' . (int) $share['channel_id'] . (((int) $share['message_id'] > 0) ? '&message=' . (int) $share['message_id'] : ''),
                    );
                }
            }
        }

        $made = $channels[(int) $row['channel_id']] ?? null;
        $excerpt = ws_plain_text($viewer, $body);

        if (mb_strlen($excerpt) > 140) {
            $excerpt = rtrim(mb_substr($excerpt, 0, 139)) . '…';
        }

        $note = array(
            'id'         => $id,
            'title'      => (string) $row['title'],
            'name'       => ws_note_title($viewer, $row),
            'excerpt'    => $excerpt,
            'access'     => $access,
            'pinned'     => $owner && !empty($row['pinned']),
            'owner'      => $people[(int) $row['user_id']] ?? null,
            'updated_by' => ((int) ($row['updated_by'] ?? 0) > 0) ? ($people[(int) $row['updated_by']] ?? null) : null,
            'updated'    => ws_time_label($row['updated_at']),
            'updated_at' => (int) $row['updated_at'],
            'source'     => $source,
            'with'       => $with,
            'in'         => $in,
            'channel'    => $made ? array('id' => (int) $made['id'], 'name' => (string) $made['name'], 'url' => $base . 'workspace.php?channel=' . (int) $made['id']) : null,
        );

        if ($full) {
            $refs = ws_refs_resolve($viewer, ws_tokens($body));
            $labels = array();

            foreach (ws_tokens($body) as $token) {
                $key = $token['type'] . ':' . $token['id'];

                if (isset($refs[$key])) {
                    $labels['<' . $token['sigil'] . $key . '>'] = $refs[$key]['label'];
                }
            }

            $note['body'] = $body;
            $note['labels'] = $labels;
            $note['html'] = ws_render_body($body, $refs);
            $note['claude'] = function_exists('ws_note_claude_state') ? ws_note_claude_state($viewer, $id) : array();
        }

        $out[] = $note;
    }

    return $out;
}

/**
 * One note as the screen shows it, with its text.
 *
 * @param array $viewer
 * @param array $row
 * @return array
 */
function ws_note_present($viewer, $row)
{
    $out = ws_notes_present($viewer, array($row), true);

    return $out[0];
}

/**
 * The person's notes and the notes shared with them: their own, the pinned
 * ones first, then the latest changed; then the ones people shared with
 * them, the latest changed first.
 *
 * @param array  $viewer
 * @param string $search words in the title or the text
 * @return array mine, shared
 */
function ws_notes_list($viewer, $search = '')
{
    if (!ws_notes_ready()) {
        return array('mine' => array(), 'shared' => array());
    }

    $me = (int) $viewer['id'];
    $filter = '';
    $search = trim(mb_substr((string) $search, 0, 100));

    if ($search !== '') {
        $like = e(addcslashes($search, '%_\\'));
        $filter = " AND (n.title LIKE '%" . $like . "%' OR n.body LIKE '%" . $like . "%')";
    }

    $mine = (array) db_items("SELECT n.* FROM ws_notes n WHERE n.user_id = '" . $me . "'" . $filter . "
        ORDER BY n.pinned DESC, n.updated_at DESC, n.id DESC LIMIT 500");

    $shared = (array) db_items("SELECT n.*, IF(MAX(s.can_edit) = 1, 'edit', 'view') AS access FROM ws_notes n
        INNER JOIN ws_note_shares s ON s.note_id = n.id AND s.user_id = '" . $me . "'
        WHERE n.user_id <> '" . $me . "'" . $filter . "
        GROUP BY n.id
        ORDER BY n.updated_at DESC, n.id DESC LIMIT 500");

    foreach ($mine as $index => $row) {
        $mine[$index]['access'] = 'owner';
    }

    return array(
        'mine'   => ws_notes_present($viewer, $mine),
        'shared' => ws_notes_present($viewer, $shared),
    );
}

/**
 * Writes a note: a new one of the person's own, or one they may change.
 * A change made against an older version than the note now holds, by
 * somebody else meanwhile, is refused as a conflict.
 *
 * @param array $viewer
 * @param array $data note_id (0 for a new one), title, body, pinned (the owner's), base (updated_at it was loaded at)
 * @return array ok, error, note_id, conflict
 */
function ws_note_save($viewer, $data)
{
    $fail = function ($error, $note_id = 0, $conflict = false) {
        return array('ok' => false, 'error' => $error, 'note_id' => $note_id, 'conflict' => $conflict);
    };

    if (!ws_notes_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    $note_id = (int) ($data['note_id'] ?? 0);
    $note = null;

    if ($note_id > 0) {
        $note = ws_note_for($viewer, $note_id, 'edit');

        if (!$note) {
            return $fail(lang('That note could not be found.'));
        }

        if (isset($data['base']) && ((int) $data['base'] > 0) && ((int) $note['updated_at'] > (int) $data['base'])
            && ((int) $note['updated_by'] !== (int) $viewer['id'])) {
            return $fail(lang(array('string' => '{var:1} changed this note meanwhile.', 'vars' => ws_person_name($note['updated_by']))), $note_id, true);
        }
    } elseif ((int) db_value("SELECT COUNT(*) FROM ws_notes WHERE user_id = '" . (int) $viewer['id'] . "'") >= WS_NOTE_LIMIT) {
        return $fail(lang(array('string' => 'You can keep at most {var:1} notes.', 'vars' => WS_NOTE_LIMIT)));
    }

    $sets = array();

    if (($note === null) || array_key_exists('title', $data)) {
        $sets['title'] = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($data['title'] ?? ''))), 0, 200);
    }

    if (($note === null) || array_key_exists('body', $data)) {
        $body = ws_tokens_normalise(rtrim(str_replace("\r\n", "\n", (string) ($data['body'] ?? ''))));

        if (mb_strlen($body) > WS_NOTE_MAX) {
            return $fail(lang(array('string' => 'A note can be at most {var:1} characters long.', 'vars' => WS_NOTE_MAX)), $note_id);
        }

        $sets['body'] = $body;
    }

    // Pinning is the owner's, and is not an edit.
    $pin = null;

    if (array_key_exists('pinned', $data) && (($note === null) || ($note['access'] === 'owner'))) {
        $pin = !empty($data['pinned']) ? 1 : 0;
    }

    $now = time();

    if ($note === null) {
        db("INSERT INTO ws_notes (user_id, title, body, pinned, updated_by, created_at, updated_at)
            VALUES ('" . (int) $viewer['id'] . "', '" . e($sets['title']) . "', '" . e($sets['body']) . "',
                '" . (int) $pin . "', '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

        $note_id = (int) mysqli_insert_id(db::$con);

        return ($note_id > 0) ? array('ok' => true, 'error' => '', 'note_id' => $note_id, 'conflict' => false) : $fail(lang('The note could not be saved.'));
    }

    $parts = array();

    foreach ($sets as $field => $value) {
        $parts[] = $field . " = '" . e((string) $value) . "'";
    }

    if (!empty($parts)) {
        $parts[] = "updated_by = '" . (int) $viewer['id'] . "'";
        $parts[] = "updated_at = '" . $now . "'";
    }

    if ($pin !== null) {
        $parts[] = "pinned = '" . $pin . "'";
    }

    if (!empty($parts)) {
        db("UPDATE ws_notes SET " . implode(', ', $parts) . " WHERE id = '" . $note_id . "'");
    }

    return array('ok' => true, 'error' => '', 'note_id' => $note_id, 'conflict' => false);
}

/**
 * Deletes a note: its owner only. Its shares, files and inbox lines go with
 * it; a card in a channel says the note is no longer shared.
 *
 * @param array $viewer
 * @param array $note
 * @return array ok, error
 */
function ws_note_delete($viewer, $note)
{
    if ((int) $note['user_id'] !== (int) $viewer['id']) {
        return array('ok' => false, 'error' => lang('Only the one who wrote a note can delete it.'));
    }

    $id = (int) $note['id'];

    foreach ((array) db_items("SELECT message_id FROM ws_note_shares WHERE note_id = '" . $id . "' AND message_id > 0") as $share) {
        ws_note_card_retire($share['message_id']);
    }

    // Nobody keeps access to the note's files through it; the files stay in
    // the file manager, where staff still see them.
    ws_note_folder_sync($note, true);

    db("DELETE FROM ws_note_shares WHERE note_id = '" . $id . "'");
    db("DELETE FROM ws_notes WHERE id = '" . $id . "'");

    if (ws_notes_inbox_ready()) {
        db("DELETE FROM ws_inbox WHERE note_id = '" . $id . "'");
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Keeps a copy of a message in the person's notes: their own message or
 * anybody else's, as long as they can read it. A message kept before is
 * not kept twice; the note it became is returned.
 *
 * @param array $viewer
 * @param array $message
 * @return array ok, error, note_id, existing
 */
function ws_note_from_message($viewer, $message)
{
    if (!ws_notes_ready()) {
        return array('ok' => false, 'error' => lang('The workspace is not installed yet: the database has to be updated first.'), 'note_id' => 0, 'existing' => false);
    }

    $channel = ws_channel($message['channel_id']);

    if (!$channel || !ws_can_read_channel($viewer, $channel) || ((int) $message['deleted_at'] > 0)
        || !in_array($message['sender_kind'], array('user', 'app'), true)
        || !in_array($message['kind'], array('message', 'note', 'decision'), true)) {
        return array('ok' => false, 'error' => lang('That message could not be found.'), 'note_id' => 0, 'existing' => false);
    }

    $existing = (int) db_value("SELECT id FROM ws_notes
        WHERE user_id = '" . (int) $viewer['id'] . "' AND source_message_id = '" . (int) $message['id'] . "'
        ORDER BY id DESC LIMIT 1");

    if ($existing > 0) {
        return array('ok' => true, 'error' => '', 'note_id' => $existing, 'existing' => true);
    }

    $body = trim((string) $message['body']);

    // The file goes with it: the words of a text file, a link to anything
    // else.
    if ((int) $message['file_id'] > 0) {
        $stored = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $message['file_id'] . "'");

        if ($stored !== '') {
            $text = function_exists('ws_file_text_read') ? ws_file_text_read((int) $message['file_id'], true) : null;
            $link = '[' . str_replace(array('[', ']'), '', (string) $message['file_name']) . '](' . URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($stored) . ')';

            $body .= (($body !== '') ? "\n\n" : '') . $link;

            if (is_string($text) && (trim($text) !== '')) {
                $body .= "\n\n" . rtrim(str_replace("\r\n", "\n", $text));
            }
        }
    }

    $body = mb_substr($body, 0, WS_NOTE_MAX);
    $now = time();

    db("INSERT INTO ws_notes (user_id, title, body, source_message_id, source_channel_id, source_user_id, updated_by, created_at, updated_at)
        VALUES ('" . (int) $viewer['id'] . "', '', '" . e($body) . "',
            '" . (int) $message['id'] . "', '" . (int) $channel['id'] . "',
            '" . (($message['sender_kind'] === 'user') ? (int) $message['sender_id'] : 0) . "',
            '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

    $note_id = (int) mysqli_insert_id(db::$con);

    if ($note_id <= 0) {
        return array('ok' => false, 'error' => lang('The note could not be saved.'), 'note_id' => 0, 'existing' => false);
    }

    return array('ok' => true, 'error' => '', 'note_id' => $note_id, 'existing' => false);
}

/**
 * Shares a note with colleagues, to read it or also to change it. Somebody
 * it is shared with already gets the new right. Each is told in their inbox.
 *
 * @param array $viewer
 * @param array $note
 * @param int[] $user_ids
 * @param bool  $can_edit
 * @return array ok, error, shared (how many)
 */
function ws_note_share_people($viewer, $note, $user_ids, $can_edit)
{
    $shared = 0;
    $now = time();

    foreach (array_unique(array_map('intval', (array) $user_ids)) as $user_id) {
        if (($user_id <= 0) || ($user_id === (int) $note['user_id']) || !ws_is_team_member($user_id)) {
            continue;
        }

        $existing = (int) db_value("SELECT id FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "' AND user_id = '" . $user_id . "' LIMIT 1");

        if ($existing > 0) {
            db("UPDATE ws_note_shares SET can_edit = '" . ($can_edit ? 1 : 0) . "' WHERE id = '" . $existing . "'");
            $shared++;
            continue;
        }

        db("INSERT INTO ws_note_shares (note_id, user_id, can_edit, shared_by, created_at)
            VALUES ('" . (int) $note['id'] . "', '" . $user_id . "', '" . ($can_edit ? 1 : 0) . "', '" . (int) $viewer['id'] . "', '" . $now . "')");

        $shared++;

        if (ws_notes_inbox_ready()) {
            ws_notify($user_id, 'note_shared', array('note_id' => (int) $note['id'], 'actor_id' => $viewer['id']));
        }
    }

    if ($shared === 0) {
        return array('ok' => false, 'error' => lang('Pick at least one colleague.'), 'shared' => 0);
    }

    ws_note_folder_sync($note);

    return array('ok' => true, 'error' => '', 'shared' => $shared);
}

/**
 * Shares a note in a channel, to be read there: a card goes into the
 * conversation, showing the note as it is now whenever it is opened.
 *
 * @param array $viewer
 * @param array $note
 * @param array $channel
 * @return array ok, error, message_id
 */
function ws_note_share_channel($viewer, $note, $channel)
{
    if (!ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'), 'message_id' => 0);
    }

    $existing = db_item("SELECT * FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "' AND channel_id = '" . (int) $channel['id'] . "' LIMIT 1");

    if (is_array($existing)) {
        return array('ok' => false, 'error' => lang('This note is already shared in that channel.'), 'message_id' => (int) $existing['message_id']);
    }

    // The card's words, for wherever the card itself is not drawn (a device
    // banner, a search, the external API).
    $sent = ws_message_send($viewer, $channel, lang(array('string' => 'Shared a note: {var:1}', 'vars' => ws_note_title($viewer, $note))));

    if (!$sent['ok']) {
        return array('ok' => false, 'error' => $sent['error'], 'message_id' => 0);
    }

    db("INSERT INTO ws_note_shares (note_id, channel_id, message_id, shared_by, created_at)
        VALUES ('" . (int) $note['id'] . "', '" . (int) $channel['id'] . "', '" . (int) $sent['message_id'] . "', '" . (int) $viewer['id'] . "', '" . time() . "')");

    ws_note_folder_sync($note);

    return array('ok' => true, 'error' => '', 'message_id' => (int) $sent['message_id']);
}

/**
 * Takes a share back: the owner any of them, the person it was shared with
 * their own (leaving the note).
 *
 * @param array $viewer
 * @param array $note
 * @param int   $share_id 0 for the person's own
 * @return array ok, error
 */
function ws_note_unshare($viewer, $note, $share_id = 0)
{
    $owner = ((int) $note['user_id'] === (int) $viewer['id']);

    $share = ((int) $share_id > 0)
        ? db_item("SELECT * FROM ws_note_shares WHERE id = '" . (int) $share_id . "' AND note_id = '" . (int) $note['id'] . "'")
        : db_item("SELECT * FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "' AND user_id = '" . (int) $viewer['id'] . "' LIMIT 1");

    if (!is_array($share) || (!$owner && ((int) $share['user_id'] !== (int) $viewer['id']))) {
        return array('ok' => false, 'error' => lang('That share could not be found.'));
    }

    db("DELETE FROM ws_note_shares WHERE id = '" . (int) $share['id'] . "'");

    if (((int) $share['user_id'] > 0) && ws_notes_inbox_ready()) {
        db("DELETE FROM ws_inbox WHERE user_id = '" . (int) $share['user_id'] . "' AND note_id = '" . (int) $note['id'] . "'");
    }

    if ((int) $share['message_id'] > 0) {
        ws_note_card_retire($share['message_id']);
    }

    ws_note_folder_sync($note);

    return array('ok' => true, 'error' => '');
}

/**
 * The message that carried a note's card, once the note is no longer shared
 * there: it says so, and the conversation around it still reads.
 *
 * @param int $message_id
 */
function ws_note_card_retire($message_id)
{
    db("UPDATE ws_messages SET body = '" . e(lang('A note was shared here. It is no longer shared.')) . "'
        WHERE id = '" . (int) $message_id . "' AND deleted_at = 0");

    ws_message_touch($message_id);
}

/**
 * The note cards of a set of messages: for each message that shares a note
 * in its channel, the note as it is now, or that it is no longer shared.
 *
 * @param array $viewer
 * @param int[] $message_ids
 * @return array message id => card
 */
function ws_note_cards_map($viewer, $message_ids)
{
    $message_ids = array_filter(array_map('intval', (array) $message_ids));

    if (empty($message_ids) || !ws_notes_ready()) {
        return array();
    }

    $shares = (array) db_items("SELECT * FROM ws_note_shares WHERE message_id IN (" . implode(',', $message_ids) . ")");
    $out = array();

    foreach ($shares as $share) {
        $note = ws_note($share['note_id']);

        if (!$note) {
            continue;
        }

        $present = ws_notes_present($viewer, array($note + array('access' => 'view')));
        $card = $present[0];

        $out[(int) $share['message_id']] = array(
            'id'         => (int) $note['id'],
            'name'       => $card['name'],
            'excerpt'    => $card['excerpt'],
            'owner'      => $card['owner'],
            'updated_by' => $card['updated_by'],
            'updated'    => $card['updated'],
        );
    }

    return $out;
}

/**
 * Opens a new channel from a note: the note is shared in it, and remembers
 * the channel it became.
 *
 * @param array $viewer
 * @param array $note
 * @param array $data what ws_channel_create() takes; the name defaults to the note's
 * @return array ok, error, field, channel_id
 */
function ws_note_to_channel($viewer, $note, $data)
{
    if (trim((string) ($data['name'] ?? '')) === '') {
        $data['name'] = ws_note_title($viewer, $note);
    }

    $result = ws_channel_create($viewer, $data);

    if (!$result['ok']) {
        return $result;
    }

    $shared = ws_note_share_channel($viewer, $note, ws_channel($result['channel_id']));

    db("UPDATE ws_notes SET channel_id = '" . (int) $result['channel_id'] . "' WHERE id = '" . (int) $note['id'] . "'");

    if (!$shared['ok']) {
        $result['error'] = $shared['error'];
    }

    return $result;
}

/* ---------------------------------------------------------------------------
   Files in notes: in the file manager, in a private folder of the note's own
   - Workspace / Notes / Note <id> - made on the note's first file. Staff see
   it as they see every private folder. A basic user holds private (view)
   access to it exactly while they may read the note: its owner, the people
   it is shared with and the members of the channels it is shared in
   (get_file.php opens the note files of a public channel to the whole team,
   as it does the channel's own files). Nobody is given edit rights on the
   folder: a note's files are added and changed through the note, by those
   who may change the note. Deleting the note takes the access away; the
   files stay, for staff to see.
   --------------------------------------------------------------------------- */

/**
 * Can notes keep their files in the file manager (4.110)?
 *
 * @return bool
 */
function ws_note_folders_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_folders_ready()
            && ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_notes' AND COLUMN_NAME = 'folder_id'") > 0)
            && ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config' AND COLUMN_NAME = 'ws_notes_folder_id'") > 0);
    }

    return $ready;
}

/**
 * The "Notes" folder inside the Workspace folder, made when missing.
 * config.ws_notes_folder_id remembers it, so a rename in the file manager
 * does not make a second.
 *
 * @param int $user_id who is making it, when it is made
 * @return int 0 when it cannot be had
 */
function ws_notes_folder_id($user_id = 0)
{
    $stored = (int) db_value("SELECT ws_notes_folder_id FROM config");

    if (($stored > 0) && ((int) db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '" . $stored . "'") > 0)) {
        return $stored;
    }

    $root = ws_root_folder_id($user_id);

    if ($root <= 0) {
        return 0;
    }

    $folder_id = ws_folder_create(lang('Notes'), $root, $user_id);

    if ($folder_id > 0) {
        db("UPDATE config SET ws_notes_folder_id = '" . $folder_id . "'");
    }

    return $folder_id;
}

/**
 * A note's folder; made on its first file, with the access of those who may
 * read the note.
 *
 * @param array $note
 * @param int   $user_id who is making it
 * @param bool  $create
 * @return int 0 when there is none (and none was to be made)
 */
function ws_note_folder_id($note, $user_id = 0, $create = true)
{
    if (!ws_note_folders_ready() || !is_array($note)) {
        return 0;
    }

    $folder_id = (int) db_value("SELECT folder_id FROM ws_notes WHERE id = '" . (int) $note['id'] . "'");

    if (($folder_id > 0) && ((int) db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '" . $folder_id . "'") > 0)) {
        return $folder_id;
    }

    if (!$create) {
        return 0;
    }

    $parent = ws_notes_folder_id($user_id);

    if ($parent <= 0) {
        return 0;
    }

    $folder_id = ws_folder_create(lang(array('string' => 'Note {var:1}', 'vars' => (int) $note['id'])), $parent, $user_id);

    if ($folder_id <= 0) {
        return 0;
    }

    db("UPDATE ws_notes SET folder_id = '" . $folder_id . "' WHERE id = '" . (int) $note['id'] . "'");

    ws_note_folder_sync($note);

    return $folder_id;
}

/**
 * Who may read a note and so open its files: its owner, the people it is
 * shared with, and the members of the channels it is shared in.
 *
 * @param array $note
 * @return int[]
 */
function ws_note_readers($note)
{
    $ids = array((int) $note['user_id']);

    foreach ((array) db_items("SELECT user_id, channel_id FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "'") as $share) {
        if ((int) $share['user_id'] > 0) {
            $ids[] = (int) $share['user_id'];
        } elseif ((int) $share['channel_id'] > 0) {
            foreach ((array) db_values("SELECT user_id FROM ws_channel_members WHERE channel_id = '" . (int) $share['channel_id'] . "'") as $member) {
                $ids[] = (int) $member;
            }
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

/**
 * Brings the view access to a note's folder in step with who may read the
 * note: given to whoever is missing it, taken from whoever no longer may.
 * The folder belongs to the note, so a view right given there by hand is
 * taken too; edit rights are never touched (ws_folder_revoke()).
 *
 * @param array $note
 * @param bool  $gone the note is being deleted: nobody keeps access
 */
function ws_note_folder_sync($note, $gone = false)
{
    $folder_id = ws_note_folder_id($note, 0, false);

    if ($folder_id <= 0) {
        return;
    }

    $wanted = $gone ? array() : ws_note_readers($note);
    $held = array_map('intval', (array) db_values("SELECT aclfolder_user FROM aclfolder WHERE aclfolder_folder = '" . $folder_id . "' AND aclfolder_rights = 1"));

    ws_folder_grant($folder_id, array_diff($wanted, $held));
    ws_folder_revoke($folder_id, array_diff($held, $wanted));
}

/**
 * The notes shared in a channel whose members changed: their folders follow.
 *
 * @param int $channel_id
 */
function ws_note_channel_sync($channel_id)
{
    if (!ws_notes_ready() || !ws_note_folders_ready()) {
        return;
    }

    foreach ((array) db_items("SELECT DISTINCT n.* FROM ws_notes n
        INNER JOIN ws_note_shares s ON s.note_id = n.id
        WHERE s.channel_id = '" . (int) $channel_id . "' AND n.folder_id > 0") as $note) {
        ws_note_folder_sync($note);
    }
}

/**
 * Keeps a file for a note in the note's folder, base64 as the panel sends
 * files. What a channel refuses is refused here too.
 *
 * @param array  $viewer
 * @param array  $note one the person may change
 * @param string $original_name
 * @param string $data
 * @return array ok, error, id, name, url, image
 */
function ws_note_file_store($viewer, $note, $original_name, $data)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'id' => 0, 'name' => '', 'url' => '', 'image' => false);
    };

    if (!ws_note_folders_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    $original_name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', basename(str_replace('\\', '/', (string) $original_name))));
    $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));

    if (($original_name === '') || ($extension === '') || ws_upload_refused($original_name)) {
        return $fail(function_exists('pg_upload_blocked_message') ? pg_upload_blocked_message($original_name) : lang('This file type is not allowed.'));
    }

    $parts = explode('base64,', (string) $data, 2);
    $binary = base64_decode(str_replace(' ', '+', end($parts)), true);

    if (($binary === false) || ($binary === '')) {
        return $fail(lang('The file could not be read.'));
    }

    if (strlen($binary) > ws_upload_max_bytes()) {
        return $fail(lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => (int) floor(ws_upload_max_bytes() / 1048576))));
    }

    $folder_id = ws_note_folder_id($note, $viewer['id']);

    if ($folder_id <= 0) {
        return $fail(lang('The file could not be saved.'));
    }

    $stored_name = function_exists('prepare_file_name') ? prepare_file_name($original_name) : preg_replace('/[^A-Za-z0-9._-]/', '_', $original_name);

    if (function_exists('get_unique_name')) {
        $stored_name = get_unique_name(array('name' => $stored_name, 'type' => 'file'));
    }

    $path = FILE_DIRECTORY_PATH . '/' . $stored_name;

    if (file_exists($path) || (@file_put_contents($path, $binary) === false)) {
        return $fail(lang('The file could not be saved.'));
    }

    $image = (ws_file_kind($original_name) === 'image');

    // A picture has to be a picture, and nothing that reads as a page or a
    // program is kept whatever it was called.
    if ($image && (@getimagesize($path) === false)) {
        @unlink($path);

        return $fail(lang('This file type is not allowed.'));
    }

    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) @finfo_file($finfo, $path) : '';

        if ($finfo) {
            @finfo_close($finfo);
        }

        if (preg_match('#^(text/html|application/xhtml|application/x-php|text/x-php|application/x-httpd|application/x-msdownload|application/x-dosexec|application/x-sh|text/x-shellscript|application/x-executable|image/svg|application/javascript|text/javascript)#i', $mime)) {
            @unlink($path);

            return $fail(lang('This file type is not allowed.'));
        }
    }

    db("INSERT INTO files (name, folder, type, size, user, design, optimized, timestamp)
        VALUES ('" . e($stored_name) . "', '" . $folder_id . "', '" . e($extension) . "', '" . (int) filesize($path) . "',
            '" . (int) $viewer['id'] . "', '0', '0', UNIX_TIMESTAMP())");

    $file_id = (int) mysqli_insert_id(db::$con);

    if ($file_id <= 0) {
        @unlink($path);

        return $fail(lang('The file could not be saved.'));
    }

    return array(
        'ok'    => true,
        'error' => '',
        'id'    => $file_id,
        'name'  => mb_substr($original_name, 0, 255),
        'url'   => URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($stored_name),
        'image' => $image,
    );
}

/* ---------------------------------------------------------------------------
   Claude asked in a note: a line that tags Claude, finished with Enter, is
   sent once; the answer is written into the note under that line when it
   comes back, by the first screen that has the note open (or the next one
   that opens it).
   --------------------------------------------------------------------------- */

/**
 * Can a request to Claude come from a note (4.110)?
 *
 * @return bool
 */
function ws_note_claude_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('ws_claude_schema_ready') && ws_claude_schema_ready()
            && ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_ai_requests' AND COLUMN_NAME = 'note_done'") > 0);
    }

    return $ready;
}

/**
 * Sends a line of a note to Claude. The same line asked before is not asked
 * again.
 *
 * @param array  $viewer
 * @param array  $note one the person may change
 * @param string $text the line, as written
 * @return array ok, error, request_id
 */
function ws_note_claude_ask($viewer, $note, $text)
{
    $text = trim(mb_substr(str_replace(array("\r", "\n"), ' ', (string) $text), 0, 2000));

    if (!ws_note_claude_ready() || !ws_claude_asked($text)) {
        return array('ok' => false, 'error' => lang('Invalid request.'), 'request_id' => 0);
    }

    if (!ws_claude_ready()) {
        return array('ok' => false, 'error' => ws_plain_text($viewer, ws_claude_setup_hint()), 'request_id' => 0);
    }

    $existing = (int) db_value("SELECT id FROM ws_ai_requests
        WHERE note_id = '" . (int) $note['id'] . "' AND note_text = '" . e($text) . "' AND status NOT IN ('failed', 'cancelled')
        ORDER BY id DESC LIMIT 1");

    if ($existing > 0) {
        return array('ok' => true, 'error' => '', 'request_id' => $existing);
    }

    $waiting = (int) db_value("SELECT COUNT(*) FROM ws_ai_requests
        WHERE requested_by = '" . (int) $viewer['id'] . "' AND status IN ('queued', 'sent', 'running')");

    if ($waiting >= WS_CLAUDE_PER_PERSON) {
        return array('ok' => false, 'error' => lang(array('string' => 'Claude already has {var:1} requests of yours waiting. Please wait for those to be answered.', 'vars' => WS_CLAUDE_PER_PERSON)), 'request_id' => 0);
    }

    db("INSERT INTO ws_ai_requests (channel_id, message_id, note_id, note_text, requested_by, status, created_at)
        VALUES ('0', '0', '" . (int) $note['id'] . "', '" . e($text) . "', '" . (int) $viewer['id'] . "', 'queued', '" . time() . "')");

    $request_id = (int) mysqli_insert_id(db::$con);

    return array('ok' => ($request_id > 0), 'error' => ($request_id > 0) ? '' : lang('Invalid request.'), 'request_id' => $request_id);
}

/**
 * The requests of a note that are still under way, or answered and not yet
 * written into it.
 *
 * @param array $viewer
 * @param int   $note_id
 * @return array[] id, status, text, error
 */
function ws_note_claude_state($viewer, $note_id)
{
    if (!ws_note_claude_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT id, status, note_text, error FROM ws_ai_requests
        WHERE note_id = '" . (int) $note_id . "' AND note_done = 0 AND status IN ('queued', 'sent', 'running', 'answered', 'failed')
        ORDER BY id") as $row) {
        $out[] = array(
            'id'     => (int) $row['id'],
            'status' => (string) $row['status'],
            'text'   => ws_plain_text($viewer, (string) $row['note_text']),
            'error'  => (string) $row['error'],
        );
    }

    return $out;
}

/**
 * Writes an answered request into the note, under the line that asked, once:
 * the first screen that gets here writes it, the others find it done. A
 * failed request is only set aside.
 *
 * @param array $viewer
 * @param array $note one the person may change
 * @param int   $request_id
 * @return bool the note was changed
 */
function ws_note_claude_deliver($viewer, $note, $request_id)
{
    $row = db_item("SELECT * FROM ws_ai_requests WHERE id = '" . (int) $request_id . "' AND note_id = '" . (int) $note['id'] . "'");

    if (!is_array($row) || !in_array($row['status'], array('answered', 'failed'), true)) {
        return false;
    }

    db("UPDATE ws_ai_requests SET note_done = 1 WHERE id = '" . (int) $row['id'] . "' AND note_done = 0");

    if ((mysqli_affected_rows(db::$con) !== 1) || ($row['status'] !== 'answered')) {
        return false;
    }

    $answer = trim(str_replace("\r\n", "\n", (string) $row['note_reply']));

    if ($answer === '') {
        return false;
    }

    $note = ws_note($note['id']);
    $lines = explode("\n", (string) $note['body']);
    $block = array_merge(array('**Claude:**'), explode("\n", $answer), array(''));
    $at = count($lines);

    foreach ($lines as $index => $line) {
        if (trim($line) === trim((string) $row['note_text'])) {
            $at = $index + 1;
            break;
        }
    }

    array_splice($lines, $at, 0, $block);

    $body = mb_substr(rtrim(implode("\n", $lines)), 0, WS_NOTE_MAX);

    db("UPDATE ws_notes SET body = '" . e($body) . "', updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . time() . "'
        WHERE id = '" . (int) $note['id'] . "'");

    return true;
}

/**
 * The texts of the notes screen and of the note cards in a conversation.
 *
 * @return array key => text
 */
function ws_notes_js_strings()
{
    return array(
        'notes'                 => lang('My Notes'),
        'nav_notes'             => lang('My Notes'),
        'notes_mine'            => lang('My notes'),
        'notes_shared'          => lang('Shared with me'),
        'notes_new'             => lang('New note'),
        'notes_search'          => lang('Search the notes'),
        'notes_empty'           => lang('No note yet.'),
        'notes_empty_shared'    => lang('Nothing is shared with you yet.'),
        'notes_empty_search'    => lang('No note matches.'),
        'notes_intro'           => lang('Your notes are yours alone - nobody else sees them, administrators included - until you share them. Keep any message here from its menu with "Save to my notes", or write your own.'),
        'notes_pick'            => lang('Pick a note on the left, or start a new one.'),
        'notes_title'           => lang('Title'),
        'notes_text'            => lang('Write here. # tags a person or a record, @Claude asks Claude; a line that ends with = is worked out.'),
        'notes_saving'          => lang('Saving…'),
        'notes_saved'           => lang('Saved'),
        'notes_unsaved'         => lang('Not saved'),
        'notes_last_change'     => ws_js_template('Last changed by {var:1} · {var:2}', 2),
        'notes_read_only'       => lang('You can read this note; only the people it is shared with for editing can change it.'),
        'notes_conflict'        => lang('Somebody changed this note meanwhile.'),
        'notes_conflict_load'   => lang('Load their version'),
        'notes_conflict_keep'   => lang('Keep mine'),
        'notes_changed_outside' => ws_js_template('{var:1} changed this note. It was brought up to date.', 1),
        'notes_pin'             => lang('Pin'),
        'notes_unpin'           => lang('Unpin'),
        'notes_pinned'          => lang('Pinned'),
        'notes_open'            => lang('Open the note'),
        'notes_share'           => lang('Share'),
        'notes_share_people'    => lang('Share with a colleague'),
        'notes_share_channel'   => lang('Share in a channel'),
        'notes_share_people_help' => lang('They find the note under "Shared with me" and are told in their inbox.'),
        'notes_share_channel_help' => lang('A card goes into the channel; its members can read the note as it is now, not change it.'),
        'notes_can_view'        => lang('Can read'),
        'notes_can_edit'        => lang('Can edit'),
        'notes_shared_ok'       => ws_js_template('Shared with {var:1} colleague(s).', 1),
        'notes_shared_channel_ok' => lang('The note was shared in the channel.'),
        'notes_shares'          => lang('Who can see it'),
        'notes_only_you'        => lang('Only you can see this note.'),
        'notes_unshare'         => lang('Stop sharing'),
        'notes_leave'           => lang('Leave the note'),
        'notes_leave_confirm'   => lang('Leave this note? It is no longer shown to you; its owner can share it again.'),
        'notes_left'            => lang('You left the note.'),
        'notes_owner'           => ws_js_template('Belongs to {var:1}', 1),
        'notes_to_channel'      => lang('Make a channel'),
        'notes_channel_help'    => lang('The note is shared in the new channel as a card.'),
        'notes_channel_made'    => lang('Channel'),
        'notes_delete'          => lang('Delete'),
        'notes_delete_confirm'  => lang('Delete this note? It disappears for everybody it is shared with.'),
        'notes_deleted'         => lang('The note was deleted.'),
        'notes_copy_link'       => lang('Copy the link'),
        'notes_from'            => ws_js_template('From {var:1}\'s message', 1),
        'notes_from_in'         => ws_js_template('in {var:1}', 1),
        'notes_go_source'       => lang('Go to the message'),
        'notes_save_message'    => lang('Save to my notes'),
        'notes_kept'            => lang('Saved to your notes.'),
        'notes_kept_before'     => lang('This message is already in your notes.'),
        'notes_untitled'        => lang('Untitled note'),
        'notes_back'            => lang('Notes'),
        'notes_attach'          => lang('Attach a file'),
        'notes_drop'            => lang('Drop the files to attach them to the note'),
        'notes_uploading'       => lang('Uploading…'),
        'notes_bold'            => lang('Bold'),
        'notes_italic'          => lang('Italic'),
        'notes_strike'          => lang('Strikethrough'),
        'notes_table'           => lang('Table'),
        'notes_calc'            => lang('Calculation'),
        'notes_checklist'       => lang('Checklist'),
        'notes_code'            => lang('Code'),
        'notes_tag'             => lang('Tag a person or a record'),
        'notes_claude'          => lang('Ask Claude'),
        'notes_emoji'           => lang('Emoji'),
        'notes_card'            => lang('Note'),
        'notes_card_gone'       => lang('This note is no longer shared here.'),
        'notes_card_open'       => lang('Read the note'),
        'notes_person'          => lang('Person'),
        'notes_not_ready'       => lang('The workspace is not installed yet: the database has to be updated first.'),
        'notes_claude_waiting'  => lang('Claude is working on it…'),
        'notes_claude_answer'   => lang('Claude'),
        'mark_channel_note'     => lang('Mark as a channel note'),
    );
}
