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
 * How many earlier versions of a note are kept, and how long a note is
 * written on before the state it had is kept again (seconds). A save that
 * takes away much of a note keeps the state before it whatever the time.
 */
define('WS_NOTE_VERSIONS_KEEP', 30);
define('WS_NOTE_VERSION_GAP', 600);

/**
 * How much of a note's text a list reads (characters): enough for its name
 * and its excerpt (ws_notes_present()), so a list of every note a person has
 * does not pull up to WS_NOTE_MAX characters for each. A single note is read
 * whole.
 */
define('WS_NOTE_LIST_BODY', 4000);

/**
 * The most notes one page of the API's list carries.
 */
define('WS_NOTES_PAGE_MAX', 200);

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
 * Does a note carry its revision (2026.11.0, 11.0.84)? The files of an update
 * land before its schema step runs, and a statement that names the column
 * before then fails as a whole, so the column is named only once it is there.
 *
 * @return bool
 */
function ws_note_revision_ready()
{
    static $ready = null;

    if ($ready === null) {
        // A column of the table answers for the table too; without a
        // connection the answer is no.
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_notes', 'revision');
    }

    return $ready;
}

/**
 * The part of an UPDATE of ws_notes that moves the revision on, for every
 * write of a note's title, text or pin: ", revision = revision + 1" ('' while
 * the column is not there yet).
 *
 * @param bool $comma with the comma that joins it to the fields before it
 * @return string
 */
function ws_note_revision_sql($comma = true)
{
    if (!ws_note_revision_ready()) {
        return '';
    }

    return ($comma ? ', ' : '') . 'revision = revision + 1';
}

/**
 * Whether a change was made against an older note than the one stored. The
 * revision that was read decides when it is sent and the note has one: any
 * change since, the person's own on another device included. Without it,
 * base (the updated_at that was read) refuses only a later change by
 * somebody else, to the second.
 *
 * @param array $note      the stored row: revision, updated_at, updated_by
 * @param array $data      revision and base as the change sent them
 * @param int   $viewer_id
 * @return bool
 */
function ws_note_stale($note, $data, $viewer_id)
{
    if (isset($data['revision'], $note['revision']) && ((int) $data['revision'] > 0)) {
        return (int) $note['revision'] !== (int) $data['revision'];
    }

    return isset($data['base']) && ((int) $data['base'] > 0) && ((int) $note['updated_at'] > (int) $data['base'])
        && ((int) $note['updated_by'] !== (int) $viewer_id);
}

/**
 * Are a note's earlier versions kept (2026.4.8, 8.111)?
 *
 * @return bool
 */
function ws_note_versions_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_notes_ready() && ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_note_versions'") > 0);
    }

    return $ready;
}

/**
 * Can a person pin My Notes to the panel menu (2026.4.8, 8.112)?
 *
 * @return bool
 */
function ws_notes_menu_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_notes_ready() && ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_profiles' AND COLUMN_NAME = 'notes_pinned'") > 0);
    }

    return $ready;
}

/**
 * Has the person pinned My Notes to the panel menu? The menu then draws it as
 * a row of its own right under the workspace (includes/fn/output.php).
 *
 * @param int $user_id
 * @return bool
 */
function ws_notes_menu_pinned($user_id)
{
    if (((int) $user_id <= 0) || !ws_notes_menu_ready()) {
        return false;
    }

    return ((int) db_value("SELECT notes_pinned FROM ws_profiles WHERE user_id = '" . (int) $user_id . "'") === 1);
}

/**
 * Pins My Notes to the person's menu, or takes it off. A person with no
 * workspace profile yet gets one, its other fields at their defaults (0 is
 * "the site's default" for each of them).
 *
 * @param array $viewer
 * @param bool  $pinned
 * @return bool done
 */
function ws_notes_menu_pin($viewer, $pinned)
{
    if (!ws_notes_menu_ready()) {
        return false;
    }

    db("INSERT INTO ws_profiles (user_id, notes_pinned, updated_at)
        VALUES ('" . (int) $viewer['id'] . "', '" . ($pinned ? 1 : 0) . "', '" . time() . "')
        ON DUPLICATE KEY UPDATE notes_pinned = VALUES(notes_pinned)");

    return true;
}

/**
 * The quick note in the panel's header: a button beside the bell that opens a
 * box, and what is written there becomes one of the person's notes through
 * the same save the notes screen uses (ws_note_save, the ws_note_save action).
 * The box also leads to My Notes. Drawn by output_header() for the members of
 * the team while the workspace is on; the script is backend.src.js's.
 *
 * @param array  $rights        ws_rights() of the person
 * @param string $parent_target the target attribute the header's links carry
 * @return string '' when the person keeps no notes here
 */
function ws_notes_quick_markup($rights, $parent_target = '')
{
    if (empty($rights['member']) || !ws_notes_ready()) {
        return '';
    }

    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

    return '
                <ul class="navbar-nav">
                    <li class="nav-item dropdown no-arrow no-popover" title="' . h(lang('Quick note')) . '">
                        <button type="button" id="pg_quick_note_toggle" class="nav-link nav-link-sm no-popover dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="' . h(lang('Quick note')) . '">
                            <span class="bi bi-journal-plus" aria-hidden="true"></span>
                        </button>
                        <div id="pg_quick_note" class="dropdown-menu shadow p-3 bg-body backdrop mt-nav-link-sm border-dropdown-menu position-fixed end-0 top-0" aria-labelledby="pg_quick_note_toggle">
                            <form data-pg-quick-note class="disable_shortcut" novalidate
                                data-api="' . h($base . 'api.php') . '"
                                data-note-url="' . h($base . 'workspace_notes.php?note=') . '"
                                data-saved="' . h(lang('Saved to your notes.')) . '"
                                data-open="' . h(lang('Open the note')) . '"
                                data-failed="' . h(lang('The note could not be saved.')) . '">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <label for="pg_quick_note_text" class="fw-semibold small mb-0"><i class="bi bi-journal-plus me-1" aria-hidden="true"></i>' . h(lang('Quick note')) . '</label>
                                    <a class="small link-body-emphasis" href="' . h($base . 'workspace_notes.php') . '"' . $parent_target . '><i class="bi bi-journal-text me-1" aria-hidden="true"></i>' . h(lang('My Notes')) . '</a>
                                </div>
                                <textarea id="pg_quick_note_text" name="body" class="form-control form-control-sm" rows="4" maxlength="' . WS_NOTE_MAX . '" placeholder="' . h(lang('Write a note. Its first line becomes its title.')) . '"></textarea>
                                <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">' . h(lang('Save')) . '</button>
                                    <span class="small text-body-secondary d-none d-sm-inline">Ctrl+Enter</span>
                                    <span class="small ms-auto" data-pg-quick-note-status role="status"></span>
                                </div>
                            </form>
                        </div>
                    </li>
                </ul>';
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
    $news = ws_note_news_map($viewer, array_map(function ($row) { return (int) $row['id']; }, $rows));

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
            'created'    => ws_time_label($row['created_at'] ?? 0),
            'created_at' => (int) ($row['created_at'] ?? 0),
            'revision'   => isset($row['revision']) ? (int) $row['revision'] : null,
            'versions'   => ws_note_versions_ready(),
            'source'     => $source,
            'with'       => $with,
            'in'         => $in,
            // Changed by somebody else, or answered by Claude, since the
            // person last had it open; Claude's state: waiting | answered.
            'unread'     => !empty($news[$id]['unread']),
            'claude_state' => (string) ($news[$id]['claude'] ?? ''),
            'channel'    => $made ? array('id' => (int) $made['id'], 'name' => (string) $made['name'], 'url' => $base . 'workspace.php?channel=' . (int) $made['id']) : null,
        );

        if ($full) {
            $note = array_merge($note, ws_note_body_view($viewer, $body));
            $note['claude'] = function_exists('ws_note_claude_state') ? ws_note_claude_state($viewer, $id) : array();
        }

        $out[] = $note;
    }

    return $out;
}

/**
 * A note's text for the screen: as written (for the writing box), the
 * names its tags have for this reader, and drawn.
 *
 * @param array  $viewer
 * @param string $body
 * @return array body, labels, html
 */
function ws_note_body_view($viewer, $body)
{
    $body = (string) $body;
    $refs = ws_refs_resolve($viewer, ws_tokens($body));
    $labels = array();

    foreach (ws_tokens($body) as $token) {
        $key = $token['type'] . ':' . $token['id'];

        if (isset($refs[$key])) {
            $labels['<' . $token['sigil'] . $key . '>'] = $refs[$key]['label'];
        }
    }

    return array('body' => $body, 'labels' => $labels, 'html' => ws_render_body($body, $refs));
}

/**
 * Keeps the note as it stands before a save changes it, when that state is
 * worth a version: the note has no version yet, it was last kept a while
 * ago, somebody else wrote what it holds, or the save takes much of it away
 * (a note emptied by mistake can always be brought back). The oldest
 * versions beyond WS_NOTE_VERSIONS_KEEP go.
 *
 * @param array $viewer
 * @param array $note the row before the save
 * @param array $sets the fields the save writes
 * @return void
 */
function ws_note_version_keep($viewer, $note, $sets)
{
    if (!ws_note_versions_ready()) {
        return;
    }

    $old_body = (string) $note['body'];
    $old_title = (string) $note['title'];
    $body_changed = array_key_exists('body', $sets) && ((string) $sets['body'] !== $old_body);
    $title_changed = array_key_exists('title', $sets) && ((string) $sets['title'] !== $old_title);

    if ((!$body_changed && !$title_changed) || ((trim($old_body) === '') && (trim($old_title) === ''))) {
        return;
    }

    $note_id = (int) $note['id'];
    $last = db_item("SELECT id, title, body, created_at FROM ws_note_versions
        WHERE note_id = '" . $note_id . "' ORDER BY id DESC LIMIT 1");

    // The same state kept already.
    if (is_array($last) && ((string) $last['body'] === $old_body) && ((string) $last['title'] === $old_title)) {
        return;
    }

    $old_length = mb_strlen($old_body);
    $new_length = $body_changed ? mb_strlen((string) $sets['body']) : $old_length;

    $keep = !is_array($last)
        || ((time() - (int) $last['created_at']) >= WS_NOTE_VERSION_GAP)
        || ((int) $note['updated_by'] !== (int) $viewer['id'])
        || ($body_changed && ($old_length >= 20) && ($new_length < $old_length * 0.6));

    if (!$keep) {
        return;
    }

    db("INSERT INTO ws_note_versions (note_id, title, body, saved_by, saved_at, created_at)
        VALUES ('" . $note_id . "', '" . e($old_title) . "', '" . e($old_body) . "',
            '" . (int) $note['updated_by'] . "', '" . (int) $note['updated_at'] . "', '" . time() . "')");

    $old = db_values("SELECT id FROM ws_note_versions WHERE note_id = '" . $note_id . "'
        ORDER BY id DESC LIMIT " . (int) WS_NOTE_VERSIONS_KEEP . ", 1000");

    if (!empty($old)) {
        db("DELETE FROM ws_note_versions WHERE id IN (" . implode(',', array_map('intval', (array) $old)) . ")");
    }
}

/**
 * A note's earlier versions, the latest first: when that state was saved,
 * by whom, how long it was.
 *
 * @param array $viewer
 * @param array $note
 * @return array[] id, title, saved, saved_full, by (person|null), size
 */
function ws_note_versions($viewer, $note)
{
    if (!ws_note_versions_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT id, title, saved_by, saved_at, CHAR_LENGTH(body) AS size FROM ws_note_versions
        WHERE note_id = '" . (int) $note['id'] . "' ORDER BY id DESC LIMIT " . (int) WS_NOTE_VERSIONS_KEEP);

    $people = ws_people(array_unique(array_filter(array_map(function ($row) { return (int) $row['saved_by']; }, $rows))));
    $out = array();

    foreach ($rows as $row) {
        $out[] = array(
            'id'         => (int) $row['id'],
            'title'      => (string) $row['title'],
            'saved'      => ws_time_label($row['saved_at']),
            'saved_full' => ((int) $row['saved_at'] > 0) ? date('d.m.Y H:i', (int) $row['saved_at']) : '',
            'by'         => $people[(int) $row['saved_by']] ?? null,
            'size'       => (int) $row['size'],
        );
    }

    return $out;
}

/**
 * One earlier version of a note, to look at or to put back.
 *
 * @param array $viewer
 * @param array $note
 * @param int   $version_id
 * @return array|null id, title, saved, body, labels, html
 */
function ws_note_version($viewer, $note, $version_id)
{
    if (!ws_note_versions_ready()) {
        return null;
    }

    $row = db_item("SELECT id, title, body, saved_at FROM ws_note_versions
        WHERE id = '" . (int) $version_id . "' AND note_id = '" . (int) $note['id'] . "'");

    if (!is_array($row)) {
        return null;
    }

    return array_merge(array(
        'id'    => (int) $row['id'],
        'title' => (string) $row['title'],
        'saved' => ws_time_label($row['saved_at']),
    ), ws_note_body_view($viewer, (string) $row['body']));
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
 * The columns a list of notes reads: every one, with the text cut to
 * WS_NOTE_LIST_BODY characters.
 *
 * @return string
 */
function ws_notes_list_columns()
{
    return "n.id, n.user_id, n.title, LEFT(n.body, " . WS_NOTE_LIST_BODY . ") AS body, n.pinned,
        n.source_message_id, n.source_channel_id, n.source_user_id, n.channel_id, n.updated_by, n.folder_id,
        n.created_at, n.updated_at" . (ws_note_revision_ready() ? ', n.revision' : '');
}

/**
 * The conditions every list of notes shares: words in the title or the text,
 * and a change at or after a moment.
 *
 * @param string $search
 * @param int    $updated_since unix time; 0 for any
 * @return string " AND ..." or ''
 */
function ws_notes_filter_sql($search, $updated_since = 0)
{
    $filter = '';
    $search = trim(mb_substr((string) $search, 0, 100));

    if ($search !== '') {
        $like = e(addcslashes($search, '%_\\'));
        $filter .= " AND (n.title LIKE '%" . $like . "%' OR n.body LIKE '%" . $like . "%')";
    }

    if ((int) $updated_since > 0) {
        $filter .= " AND n.updated_at >= '" . (int) $updated_since . "'";
    }

    return $filter;
}

/**
 * The person's notes and the notes shared with them: their own, the pinned
 * ones first, then the latest changed; then the ones people shared with
 * them, the latest changed first. Every note they own is listed (a person
 * keeps at most WS_NOTE_LIMIT), and as many shared ones.
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
    $filter = ws_notes_filter_sql($search);

    $mine = (array) db_items("SELECT " . ws_notes_list_columns() . " FROM ws_notes n WHERE n.user_id = '" . $me . "'" . $filter . "
        ORDER BY n.pinned DESC, n.updated_at DESC, n.id DESC LIMIT " . WS_NOTE_LIMIT);

    $shared = (array) db_items("SELECT " . ws_notes_list_columns() . ", IF(MAX(s.can_edit) = 1, 'edit', 'view') AS access FROM ws_notes n
        INNER JOIN ws_note_shares s ON s.note_id = n.id AND s.user_id = '" . $me . "'
        WHERE n.user_id <> '" . $me . "'" . $filter . "
        GROUP BY n.id
        ORDER BY n.updated_at DESC, n.id DESC LIMIT " . WS_NOTE_LIMIT);

    foreach ($mine as $index => $row) {
        $mine[$index]['access'] = 'owner';
    }

    return array(
        'mine'   => ws_notes_present($viewer, $mine),
        'shared' => ws_notes_present($viewer, $shared),
    );
}

/**
 * A note's place in the API's list, which runs the screen's two lists one
 * after the other: group 0 for an own pinned note, 1 for another own note,
 * 2 for a shared one; within a group the latest change first, then the
 * higher id. Packed into the one number a cursor carries beside the id.
 *
 * @param int $group
 * @param int $updated_at
 * @return int
 */
function ws_notes_position_pack($group, $updated_at)
{
    return (int) $group * 10000000000 + max(0, (int) $updated_at);
}

/**
 * A position from a cursor's two numbers; null when they cannot be one.
 *
 * @param int $value
 * @param int $id
 * @return array|null group, updated_at, id
 */
function ws_notes_position_unpack($value, $id)
{
    $value = (int) $value;
    $id = (int) $id;
    $group = intdiv($value, 10000000000);

    if (($value < 0) || ($id <= 0) || ($group > 2)) {
        return null;
    }

    return array('group' => $group, 'updated_at' => $value % 10000000000, 'id' => $id);
}

/**
 * The condition that keeps the notes after a position, for a query whose
 * rows all fall in the group $group_sql gives.
 *
 * @param string     $group_sql an SQL expression or number
 * @param array|null $after     a position, or null for the start
 * @return string " AND (...)" or ''
 */
function ws_notes_after_sql($group_sql, $after)
{
    if (!is_array($after)) {
        return '';
    }

    $group = (int) $after['group'];
    $updated_at = (int) $after['updated_at'];
    $id = (int) $after['id'];

    return " AND ((" . $group_sql . ") > " . $group . " OR ((" . $group_sql . ") = " . $group . "
        AND (n.updated_at < '" . $updated_at . "' OR (n.updated_at = '" . $updated_at . "' AND n.id < '" . $id . "'))))";
}

/**
 * The rows of one page of a person's notes, in the order of
 * ws_notes_position_pack(), with the conditions applied in the query, so no
 * note is lost to a cut made before them.
 *
 * @param int   $user_id
 * @param array $options scope (all, mine or shared), search, updated_since
 *                       (unix time), after (a position, null for the first
 *                       page), limit
 * @return array rows (with list_group and access), next (the position to go
 *               on from, null on the last page), total (every note that
 *               matches)
 */
function ws_notes_page_rows($user_id, $options)
{
    $me = (int) $user_id;
    $scope = (string) ($options['scope'] ?? 'all');
    $limit = max(1, min(WS_NOTES_PAGE_MAX, (int) ($options['limit'] ?? 100)));
    $after = is_array($options['after'] ?? null) ? $options['after'] : null;
    $filter = ws_notes_filter_sql((string) ($options['search'] ?? ''), (int) ($options['updated_since'] ?? 0));
    $rows = array();
    $total = 0;

    if ($scope !== 'shared') {
        $own = "IF(n.pinned = 1, 0, 1)";

        $total += (int) db_value("SELECT COUNT(*) FROM ws_notes n WHERE n.user_id = '" . $me . "'" . $filter);

        $rows = (array) db_items("SELECT " . ws_notes_list_columns() . ", " . $own . " AS list_group FROM ws_notes n
            WHERE n.user_id = '" . $me . "'" . $filter . ws_notes_after_sql($own, $after) . "
            ORDER BY list_group, n.updated_at DESC, n.id DESC LIMIT " . ($limit + 1));

        foreach ($rows as $index => $row) {
            $rows[$index]['access'] = 'owner';
        }
    }

    if ($scope !== 'mine') {
        $from = " FROM ws_notes n
            INNER JOIN ws_note_shares s ON s.note_id = n.id AND s.user_id = '" . $me . "'
            WHERE n.user_id <> '" . $me . "'" . $filter;

        $total += (int) db_value("SELECT COUNT(DISTINCT n.id)" . $from);

        if (count($rows) <= $limit) {
            $rows = array_merge($rows, (array) db_items("SELECT " . ws_notes_list_columns() . ", 2 AS list_group,
                    IF(MAX(s.can_edit) = 1, 'edit', 'view') AS access" . $from . ws_notes_after_sql('2', $after) . "
                GROUP BY n.id
                ORDER BY n.updated_at DESC, n.id DESC LIMIT " . ($limit + 1 - count($rows))));
        }
    }

    $next = null;

    if (count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
        $last = $rows[$limit - 1];
        $next = array('group' => (int) $last['list_group'], 'updated_at' => (int) $last['updated_at'], 'id' => (int) $last['id']);
    }

    return array('rows' => $rows, 'next' => $next, 'total' => $total);
}

/**
 * One page of the person's notes for the API (ws_notes_page_rows()),
 * presented as the screen's list presents them.
 *
 * @param array $viewer
 * @param array $options see ws_notes_page_rows()
 * @return array rows (presented), next, total
 */
function ws_notes_page($viewer, $options)
{
    if (!ws_notes_ready()) {
        return array('rows' => array(), 'next' => null, 'total' => 0);
    }

    $page = ws_notes_page_rows((int) $viewer['id'], $options);
    $page['rows'] = ws_notes_present($viewer, $page['rows']);

    return $page;
}

/**
 * Whether the person may write this note: the checks of ws_note_save(), made
 * on their own so that the API's dry run meets the same refusals.
 *
 * @param array $viewer
 * @param array $data see ws_note_save()
 * @return array ok, error, note_id, conflict, note (as it is now, null for a
 *               new one), sets (the fields as they would be stored), pin (1 or
 *               0, null when it is left as it is)
 */
function ws_note_save_check($viewer, $data)
{
    $fail = function ($error, $note_id = 0, $conflict = false) {
        return array('ok' => false, 'error' => $error, 'note_id' => $note_id, 'conflict' => $conflict, 'note' => null, 'sets' => array(), 'pin' => null);
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

        if (ws_note_stale($note, $data, (int) $viewer['id'])) {
            $error = ((int) $note['updated_by'] !== (int) $viewer['id'])
                ? lang(array('string' => '{var:1} changed this note meanwhile.', 'vars' => ws_person_name($note['updated_by'])))
                : lang('You changed this note meanwhile on another screen or device.');

            return $fail($error, $note_id, true);
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

    return array('ok' => true, 'error' => '', 'note_id' => $note_id, 'conflict' => false, 'note' => $note, 'sets' => $sets, 'pin' => $pin);
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
    $check = ws_note_save_check($viewer, $data);

    if (!$check['ok']) {
        return array('ok' => false, 'error' => $check['error'], 'note_id' => $check['note_id'], 'conflict' => $check['conflict']);
    }

    $note_id = (int) $check['note_id'];
    $note = $check['note'];
    $sets = $check['sets'];
    $pin = $check['pin'];
    $now = time();

    if ($note === null) {
        db("INSERT INTO ws_notes (user_id, title, body, pinned, updated_by, created_at, updated_at)
            VALUES ('" . (int) $viewer['id'] . "', '" . e($sets['title']) . "', '" . e($sets['body']) . "',
                '" . (int) $pin . "', '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

        $note_id = (int) mysqli_insert_id(db::$con);

        if (($note_id > 0) && function_exists('ws_blocks_index')) {
            ws_blocks_index('note', $note_id, $sets['body'], (int) $viewer['id']);
        }

        return ($note_id > 0)
            ? array('ok' => true, 'error' => '', 'note_id' => $note_id, 'conflict' => false)
            : array('ok' => false, 'error' => lang('The note could not be saved.'), 'note_id' => 0, 'conflict' => false);
    }

    // The state before this save, when it is worth keeping.
    ws_note_version_keep($viewer, $note, $sets);

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

    if (!empty($parts) && ws_note_revision_ready()) {
        $parts[] = ws_note_revision_sql(false);
    }

    if (!empty($parts)) {
        db("UPDATE ws_notes SET " . implode(', ', $parts) . " WHERE id = '" . $note_id . "'");
    }

    if (isset($sets['body']) && function_exists('ws_blocks_index')) {
        ws_blocks_index('note', $note_id, $sets['body'], (int) $note['user_id']);
    }

    // The others who keep it are told it changed - once, until they look.
    $changed = false;

    foreach ($sets as $field => $value) {
        if ((string) $value !== (string) $note[$field]) {
            $changed = true;
        }
    }

    if ($changed) {
        ws_note_notify_change($viewer, $note);
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

    if (ws_note_versions_ready()) {
        db("DELETE FROM ws_note_versions WHERE note_id = '" . $id . "'");
    }

    if (function_exists('ws_blocks_index')) {
        ws_blocks_index('note', $id, '');
    }

    if (ws_notes_inbox_ready()) {
        ws_inbox_delete("note_id = '" . $id . "'");
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
        ws_inbox_delete("user_id = '" . (int) $share['user_id'] . "' AND note_id = '" . (int) $note['id'] . "'");
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

        $access = ws_note_access($viewer, $note);
        $present = ws_notes_present($viewer, array($note + array('access' => ($access !== '') ? $access : 'view')));
        $card = $present[0];
        $body = (string) $note['body'];

        // The card shows the note drawn - tables and checklists as such -
        // the same way ws_notes_present() draws it in full; the excerpt
        // stays for whatever reads the card as plain text.
        $out[(int) $share['message_id']] = array(
            'id'         => (int) $note['id'],
            'name'       => $card['name'],
            'excerpt'    => $card['excerpt'],
            'html'       => ws_render_body($body, ws_refs_resolve($viewer, ws_tokens($body))),
            'access'     => $access,
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
 * files (see ws_note_file_store_bytes()).
 *
 * @param array  $viewer
 * @param array  $note one the person may change
 * @param string $original_name
 * @param string $data
 * @return array ok, error, id, name, url, image
 */
function ws_note_file_store($viewer, $note, $original_name, $data)
{
    $parts = explode('base64,', (string) $data, 2);
    $binary = base64_decode(str_replace(' ', '+', end($parts)), true);

    if (($binary === false) || ($binary === '')) {
        return array('ok' => false, 'error' => lang('The file could not be read.'), 'id' => 0, 'name' => '', 'url' => '', 'image' => false);
    }

    return ws_note_file_store_bytes($viewer, $note, $original_name, $binary);
}

/**
 * Keeps a file for a note in the note's folder, from its bytes: what the
 * screen sends (ws_note_file_store()) and what a note brought in from another
 * app carries with it (includes/workspace/notes_import.php). What a channel
 * refuses is refused here too.
 *
 * @param array  $viewer
 * @param array  $note one the person may change
 * @param string $original_name
 * @param string $binary
 * @return array ok, error, id, name, url, image
 */
function ws_note_file_store_bytes($viewer, $note, $original_name, $binary)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'id' => 0, 'name' => '', 'url' => '', 'image' => false);
    };

    if (!ws_note_folders_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    $original_name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', pg_basename(str_replace('\\', '/', (string) $original_name))));
    $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));

    if (($original_name === '') || ($extension === '') || ws_upload_refused($original_name)) {
        return $fail(ws_upload_refused_message($original_name));
    }

    $binary = (string) $binary;

    if ($binary === '') {
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

    // Pinegrap AI (ai.php) is asked the same way, with its own tag.
    $agent = (function_exists('ws_ai_asked') && ws_ai_schema_ready() && ws_ai_asked($text) && !ws_claude_asked($text)) ? 'ai' : 'claude';

    if (!ws_note_claude_ready() || (($agent === 'claude') && !ws_claude_asked($text))) {
        return array('ok' => false, 'error' => lang('Invalid request.'), 'request_id' => 0);
    }

    if (($agent === 'ai') && !ws_ai_ready()) {
        return array('ok' => false, 'error' => ws_plain_text($viewer, ws_ai_setup_hint()), 'request_id' => 0);
    }

    if (($agent === 'claude') && !ws_claude_ready()) {
        return array('ok' => false, 'error' => ws_plain_text($viewer, ws_claude_setup_hint()), 'request_id' => 0);
    }

    $existing = (int) db_value("SELECT id FROM ws_ai_requests
        WHERE note_id = '" . (int) $note['id'] . "' AND note_text = '" . e($text) . "' AND status NOT IN ('failed', 'cancelled')
        ORDER BY id DESC LIMIT 1");

    if ($existing > 0) {
        return array('ok' => true, 'error' => '', 'request_id' => $existing);
    }

    $waiting = (int) db_value("SELECT COUNT(*) FROM ws_ai_requests
        WHERE requested_by = '" . (int) $viewer['id'] . "' AND status IN ('queued', 'sent', 'running')"
        . (function_exists('ws_ai_agent_where') ? ws_ai_agent_where($agent) : ''));

    if (($agent === 'ai') && ($waiting >= WS_AI_PER_PERSON)) {
        return array('ok' => false, 'error' => lang(array('string' => 'Pinegrap AI already has {var:1} requests of yours waiting. Please wait for those to be answered.', 'vars' => WS_AI_PER_PERSON)), 'request_id' => 0);
    }

    if (($agent === 'claude') && ($waiting >= WS_CLAUDE_PER_PERSON)) {
        return array('ok' => false, 'error' => lang(array('string' => 'Claude already has {var:1} requests of yours waiting. Please wait for those to be answered.', 'vars' => WS_CLAUDE_PER_PERSON)), 'request_id' => 0);
    }

    // Written with its assistant in one go: a request to Pinegrap AI must
    // never sit in the table as Claude's, where Claude's run would take it.
    db("INSERT INTO ws_ai_requests (channel_id, message_id, note_id, note_text, requested_by, status, " . (($agent === 'ai') ? 'agent, ' : '') . "created_at)
        VALUES ('0', '0', '" . (int) $note['id'] . "', '" . e($text) . "', '" . (int) $viewer['id'] . "', 'queued', " . (($agent === 'ai') ? "'ai', " : '') . "'" . time() . "')");

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

    foreach ((array) db_items("SELECT * FROM ws_ai_requests
        WHERE note_id = '" . (int) $note_id . "' AND note_done = 0 AND status IN ('queued', 'sent', 'running', 'answered', 'failed')
        ORDER BY id") as $row) {
        $out[] = array(
            'id'     => (int) $row['id'],
            'status' => (string) $row['status'],
            'text'   => ws_plain_text($viewer, (string) $row['note_text']),
            'error'  => (string) $row['error'],
            'agent'  => (string) ($row['agent'] ?? 'claude'),
        );
    }

    return $out;
}

/**
 * An assistant's answer as it fits under the note: whole when the note has
 * room for it, shortened with an ellipsis to what is left otherwise, null
 * when fewer than 20 characters are left. The note itself is never cut.
 *
 * @param string $body    the note as it stands
 * @param string $heading the line the answer goes under
 * @param string $answer
 * @return string|null
 */
function ws_note_answer_fit($body, $heading, $answer)
{
    $room = WS_NOTE_MAX - mb_strlen($body) - mb_strlen($heading) - 3;

    if ($room < 20) {
        return null;
    }

    return (mb_strlen($answer) > $room) ? rtrim(mb_substr($answer, 0, $room - 1)) . '…' : $answer;
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
    $before = (string) $note['body'];
    $heading = ((string) ($row['agent'] ?? 'claude') === 'ai') ? '**Pinegrap AI:**' : '**Claude:**';

    // The note is never cut to make room: an answer longer than what the
    // note has left is shortened instead, and when nothing fits the request
    // is set aside as failed, with the reason the screen shows, and the
    // note stays as it is.
    $answer = ws_note_answer_fit($before, $heading, $answer);

    if ($answer === null) {
        db("UPDATE ws_ai_requests SET status = 'failed', note_done = 0, error = '" . e(lang('The note is full, so the answer could not be written under the line that asked.')) . "'
            WHERE id = '" . (int) $row['id'] . "'");

        return false;
    }

    $lines = explode("\n", $before);
    $block = array_merge(array($heading), explode("\n", $answer), array(''));
    $at = count($lines);

    foreach ($lines as $index => $line) {
        if (trim($line) === trim((string) $row['note_text'])) {
            $at = $index + 1;
            break;
        }
    }

    array_splice($lines, $at, 0, $block);

    $body = rtrim(implode("\n", $lines));
    $sets = array('body' => $body);

    // The state before the answer, kept as a version like any other save,
    // so what the assistant wrote under the line can be undone.
    ws_note_version_keep($viewer, $note, $sets);

    db("UPDATE ws_notes SET body = '" . e($body) . "', updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . time() . "'" . ws_note_revision_sql() . "
        WHERE id = '" . (int) $note['id'] . "'");

    if (function_exists('ws_blocks_index')) {
        ws_blocks_index('note', (int) $note['id'], $body, (int) $note['user_id']);
    }

    ws_note_notify_change($viewer, $note, (int) $row['requested_by']);

    return true;
}

/**
 * The inbox kinds that are about a note.
 *
 * @return string[]
 */
function ws_note_inbox_kinds()
{
    return array('note_shared', 'note_updated', 'note_answer', 'note_answer_ai');
}

/**
 * Tells the people a note is kept by - its owner and the people it is shared
 * with - that somebody else changed it. One unread line per person and note:
 * a note being written in is saved every few seconds, and the line only moves
 * up to the latest change until it is read. Members of a channel it is shared
 * in are not told; the card in the channel shows the note as it is. No device
 * banner: a change to a note is not a call for attention the way a mention is.
 *
 * @param array $viewer
 * @param array $note
 * @param int   $skip  somebody else not to tell (the one Claude answered, who hears that instead)
 */
function ws_note_notify_change($viewer, $note, $skip = 0)
{
    if (!ws_notes_inbox_ready()) {
        return;
    }

    $people = array((int) $note['user_id']);

    foreach ((array) db_values("SELECT user_id FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "' AND user_id > 0") as $user_id) {
        $people[] = (int) $user_id;
    }

    $now = time();

    foreach (array_unique($people) as $user_id) {
        if (($user_id <= 0) || ($user_id === (int) $viewer['id']) || ($user_id === (int) $skip)) {
            continue;
        }

        $existing = (int) db_value("SELECT id FROM ws_inbox
            WHERE user_id = '" . $user_id . "' AND kind = 'note_updated' AND note_id = '" . (int) $note['id'] . "' AND read_at = 0
            LIMIT 1");

        if ($existing > 0) {
            db("UPDATE ws_inbox SET actor_id = '" . (int) $viewer['id'] . "', created_at = '" . $now . "' WHERE id = '" . $existing . "'");
            continue;
        }

        ws_notify($user_id, 'note_updated', array('note_id' => (int) $note['id'], 'actor_id' => (int) $viewer['id'], 'quiet' => true));
    }
}

/**
 * The person has the note in front of them: what the inbox said about it is
 * read.
 *
 * @param array $viewer
 * @param int   $note_id
 */
function ws_note_mark_seen($viewer, $note_id)
{
    if (!ws_notes_inbox_ready()) {
        return;
    }

    $ids = array_map('intval', (array) db_values("SELECT id FROM ws_inbox
        WHERE user_id = '" . (int) $viewer['id'] . "' AND note_id = '" . (int) $note_id . "' AND read_at = 0
        AND kind IN ('" . implode("', '", ws_note_inbox_kinds()) . "')"));

    if (!empty($ids)) {
        ws_inbox_mark_read($viewer['id'], $ids);
    }
}

/**
 * What is new about a set of notes for this person: an unread inbox line
 * about it, and where Claude stands with a line asked in it - still working,
 * or answered with the answer not yet written in (it is written when
 * somebody who may change the note has it open).
 *
 * @param array $viewer
 * @param int[] $note_ids
 * @return array note id => [unread => bool, claude => waiting|answered|'']
 */
function ws_note_news_map($viewer, $note_ids)
{
    $note_ids = array_values(array_unique(array_filter(array_map('intval', (array) $note_ids))));
    $out = array();

    if (empty($note_ids)) {
        return $out;
    }

    if (ws_notes_inbox_ready()) {
        foreach ((array) db_values("SELECT DISTINCT note_id FROM ws_inbox
            WHERE user_id = '" . (int) $viewer['id'] . "' AND read_at = 0 AND note_id IN (" . implode(',', $note_ids) . ")
            AND kind IN ('" . implode("', '", ws_note_inbox_kinds()) . "')") as $note_id) {
            $out[(int) $note_id]['unread'] = true;
        }
    }

    if (ws_note_claude_ready()) {
        foreach ((array) db_items("SELECT note_id, status FROM ws_ai_requests
            WHERE note_id IN (" . implode(',', $note_ids) . ") AND note_done = 0
            AND status IN ('queued', 'sent', 'running', 'answered')") as $row) {
            $state = ($row['status'] === 'answered') ? 'answered' : 'waiting';

            if (($out[(int) $row['note_id']]['claude'] ?? '') !== 'answered') {
                $out[(int) $row['note_id']]['claude'] = $state;
            }
        }
    }

    return $out;
}

/**
 * The texts of the notes screen and of the note cards in a conversation.
 *
 * @return array key => text
 */
function ws_notes_js_strings()
{
    // Merged into the screen's own strings, so a key must not repeat one of
    // those: 'notes' there is the task drawer's "Notes" heading.
    return array(
        'my_notes'              => lang('My Notes'),
        'nav_notes'             => lang('My Notes'),
        'notes_export_md'       => lang('Download as Markdown'),
        'notes_print'           => lang('Print'),
        'notes_export_all'      => lang('Export all'),
        'notes_import'          => lang('Import'),
        'notes_import_title'    => lang('Import notes'),
        'notes_import_intro'    => lang('Bring notes in from another app: Markdown (.md) or text (.txt) files, or a .zip of them, such as an Obsidian vault, a Notion, Joplin or Bear export or a Google Keep Takeout, or Evernote exports (.enex). Notes exported from here are recognised and brought up to date.'),
        'notes_import_pick'     => lang('Choose the files or drop them here'),
        'notes_import_limit'    => ws_js_template('Up to {var:1} MB', 1),
        'notes_import_too_many' => ws_js_template('Pick at most {var:1} files at a time, or put them in a .zip.', 1),
        'notes_import_too_large' => ws_js_template('The upload is larger than {var:1} MB.', 1),
        'notes_import_reading'  => lang('Reading the files…'),
        'notes_import_picked'   => ws_js_template('{var:1} file(s) selected, {var:2}', 2),
        'notes_import_over'     => ws_js_template('{var:1} of them are over the limit and will not be sent', 1),
        'notes_import_blocked'  => ws_js_template('{var:1} of them are of a type that cannot be uploaded and will not be sent', 1),
        'notes_import_not_allowed' => lang('not allowed here'),
        'notes_import_unreadable' => lang('unreadable'),
        'notes_import_cannot_read' => ws_js_template('The file {var:1} cannot be read.', 1),
        'notes_import_clear'    => lang('Clear'),
        'notes_import_upload'   => lang('Upload'),
        'notes_import_choose'   => lang('Choose Markdown (.md) or text (.txt) files, one .zip of them, or Evernote exports (.enex).'),
        'notes_import_zip_alone' => lang('Upload a .zip on its own, without other files.'),
        'notes_import_enex_alone' => lang('Upload Evernote exports (.enex) on their own, without other files.'),
        'notes_import_sent'     => ws_js_template('{var:1} of {var:2}', 2),
        'notes_import_cancel_upload' => lang('Cancel upload'),
        'notes_import_leave'    => lang('A job is still running on this screen. If you leave now it stops where it is.'),
        'notes_import_request_failed' => lang('The request failed. Please try again.'),
        'notes_import_too_large_server' => lang('The upload is larger than this server accepts in one request.'),
        'notes_import_found'    => ws_js_template('{var:1} note file(s) found', 1),
        'notes_import_count_new' => ws_js_template('{var:1} new', 1),
        'notes_import_count_update' => ws_js_template('{var:1} to bring up to date', 1),
        'notes_import_count_same' => ws_js_template('{var:1} already here', 1),
        'notes_import_count_error' => ws_js_template('{var:1} cannot be imported', 1),
        'notes_import_more'     => ws_js_template('{var:1} more note file(s) were not read: one import reads at most {var:2}.', 2),
        'notes_import_mode'     => lang('Notes exported from here'),
        'notes_import_mode_update' => lang('Bring the note they came from up to date'),
        'notes_import_mode_new' => lang('Add them as new notes'),
        'notes_import_all'      => lang('Select all'),
        'notes_import_kind_new' => lang('New note'),
        'notes_import_kind_copy' => lang('New note, a copy'),
        'notes_import_kind_update' => ws_js_template('Brings "{var:1}" up to date', 1),
        'notes_import_kind_same' => lang('Already in your notes, unchanged'),
        'notes_import_kind_error' => lang('Cannot be imported'),
        'notes_import_files'    => ws_js_template('{var:1} file(s)', 1),
        'notes_import_capacity' => ws_js_template('You can add {var:1} more note(s): one person keeps at most {var:2}.', 2),
        'notes_import_go'       => ws_js_template('Import {var:1} note(s)', 1),
        'notes_import_running'  => ws_js_template('Importing… {var:1} of {var:2}', 2),
        'notes_import_linking'  => lang('Linking the notes to each other…'),
        'notes_import_done'     => ws_js_template('{var:1} note(s) added, {var:2} brought up to date, {var:3} left as they were.', 3),
        'notes_import_failed'   => lang('Not imported:'),
        'notes_mine'            => lang('My notes'),
        'notes_shared'          => lang('Shared with me'),
        'notes_new'             => lang('New note'),
        'notes_news'            => lang('Changed since you last opened it'),
        'notes_claude_answered' => lang('Claude answered'),
        'notes_claude_working'  => lang('Claude is on it'),
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
        'notes_tag'             => lang('Tag a person or a record'),
        'notes_claude'          => lang('Ask Claude'),
        'notes_emoji'           => lang('Emoji'),
        'notes_card'            => lang('Note'),
        'notes_card_gone'       => lang('This note is no longer shared here.'),
        'notes_card_open'       => lang('Read the note'),
        'notes_card_edit'       => lang('Edit note'),
        'notes_person'          => lang('Person'),
        'notes_not_ready'       => lang('The workspace is not installed yet: the database has to be updated first.'),
        'notes_claude_waiting'  => lang('Claude is working on it…'),
        'notes_claude_answer'   => lang('Claude'),
        'mark_channel_note'     => lang('Mark as a channel note'),
        'notes_created'         => ws_js_template('Created {var:1}', 1),
        'notes_versions'        => lang('Earlier versions'),
        'notes_versions_none'   => lang('No earlier version yet. The note is kept as it was when it is changed after a pause, when somebody else changes it, or when much of it is removed.'),
        'notes_version_size'    => ws_js_template('{var:1} characters', 1),
        'notes_version_pick'    => lang('Pick a version to see it here.'),
        'notes_version_restore' => lang('Put this version back'),
        'notes_version_restored' => lang('The version is back in the note. Undo takes it back again.'),
        'notes_menu_label'      => lang('My Notes'),
        'notes_menu_pin'        => lang('Pin to the menu'),
        'notes_menu_unpin'      => lang('Remove from the menu'),
        'notes_menu_pinned'     => lang('My Notes is in the menu now, right under the workspace.'),
        'notes_menu_unpinned'   => lang('My Notes is no longer in the menu; the workspace menu still has it.'),
    );
}
