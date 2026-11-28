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
 * How many notes one channel keeps (notes written in it, which are the
 * channel's and count in nobody's WS_NOTE_LIMIT).
 */
define('WS_NOTE_CHANNEL_LIMIT', 2000);

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
 * How long a deleted note is remembered for the apps that keep notes in sync
 * (ws_note_deletions), and how many deletions one listing carries at most.
 */
define('WS_NOTE_DELETIONS_KEEP', 30 * 86400);
define('WS_NOTE_DELETIONS_MAX', 1000);

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
 * Does a note keep the application that changed it (2026.11.0, 11.0.257)?
 * The columns are named only once the step has added them.
 *
 * @return bool
 */
function ws_note_app_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_notes', 'updated_app_id')
            && pg_schema_has('ws_note_versions', 'app_id');
    }

    return $ready;
}

/**
 * Does a note know the channel it belongs to (2026.11.0, 11.0.273)? The
 * column is named only once the step has added it.
 *
 * @return bool
 */
function ws_note_channel_owner_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_notes', 'owner_channel_id');
    }

    return $ready;
}

/**
 * The channel a note belongs to; 0 for a person's own note. Pure.
 *
 * @param array|null $note
 * @return int
 */
function ws_note_channel_of($note)
{
    return is_array($note) ? max(0, (int) ($note['owner_channel_id'] ?? 0)) : 0;
}

/**
 * The condition that keeps a person's own notes and leaves a channel's note
 * out: every list, search, export, count and limit of "my notes" adds it to
 * its user_id condition (tests/workspace_note_channel_readers_test.php).
 * Before the step has run every note is a person's, and nothing is added.
 *
 * @param string $alias the table's alias in the query, '' for none
 * @return string " AND ..." or ''
 */
function ws_note_own_sql($alias = 'n')
{
    if (!ws_note_channel_owner_ready()) {
        return '';
    }

    return " AND " . (($alias !== '') ? $alias . '.' : '') . "owner_channel_id = 0";
}

/**
 * The application a save is written as: the one whose key made it, 0 for a
 * person (the panel, a person's own device). Pure.
 *
 * @param array $data what ws_note_save() takes; app_id from the API only
 * @return int
 */
function ws_note_saver_app($data)
{
    return max(0, (int) ($data['app_id'] ?? 0));
}

/**
 * Who changed a note or saved one of its versions, as the card, the versions
 * and the API name them: the application when its key saved, else the
 * person. Pure.
 *
 * @param array|null $person ws_people() entry
 * @param array|null $app    ws_app_sender() entry, null when a person saved
 * @return array|null
 */
function ws_note_changer($person, $app)
{
    if (is_array($app)) {
        return $app;
    }

    return is_array($person) ? $person : null;
}

/**
 * The words under a note card: "Changed by @handle", the application by its
 * name. Empty when nobody is known. Pure.
 *
 * @param array|null $changer ws_note_changer()
 * @return string
 */
function ws_note_changed_by_words($changer)
{
    if (!is_array($changer)) {
        return '';
    }

    $handle = trim((string) ($changer['username'] ?? ''));
    $name = ($handle !== '') ? '@' . $handle : trim((string) ($changer['name'] ?? ''));

    return ($name !== '') ? lang(array('string' => 'Changed by {var:1}', 'vars' => $name)) : '';
}

/**
 * Are deleted notes remembered (2026.11.0, 11.0.85)?
 *
 * @return bool
 */
function ws_note_deletions_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_note_deletions');
    }

    return $ready;
}

/**
 * Is it remembered where an imported note came from (2026.11.0, 11.0.86)?
 *
 * @return bool
 */
function ws_note_sources_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_note_sources');
    }

    return $ready;
}

/**
 * Has the database the note groups (2026.11.0, 11.0.119): the groups, each
 * person's places of notes, group shares and the via_group column of note
 * shares that a group's share writes?
 *
 * @return bool
 */
function ws_note_groups_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has')
            && pg_schema_has('ws_note_groups')
            && pg_schema_has('ws_note_places')
            && pg_schema_has('ws_note_group_shares')
            && pg_schema_has('ws_note_shares', 'via_group');
    }

    return $ready;
}

/**
 * Has the database the notes lock (2026.11.0, 11.0.119): the PIN table and
 * the locked marks of notes and groups?
 *
 * @return bool
 */
function ws_note_lock_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has')
            && pg_schema_has('ws_note_locks')
            && pg_schema_has('ws_notes', 'locked')
            && pg_schema_has('ws_note_groups', 'locked');
    }

    return $ready;
}

/**
 * The note a file brought in before became, by the id it is known by in the
 * app it came from (ws_note_source_id()).
 *
 * @param int    $user_id  who brought it in
 * @param string $provider keep | enex | markdown | html
 * @param string $external
 * @return array|null hash, note_id; null when unknown or the table is not there yet
 */
function ws_note_source_find($user_id, $provider, $external)
{
    if (((string) $external === '') || !ws_note_sources_ready()) {
        return null;
    }

    $row = db_item("SELECT hash, note_id FROM ws_note_sources
        WHERE user_id = '" . (int) $user_id . "' AND provider = '" . e($provider) . "' AND external_id = '" . e($external) . "'");

    return $row ? array('hash' => (string) $row['hash'], 'note_id' => (int) $row['note_id']) : null;
}

/**
 * Remembers the note a file became and what the file held, so the same file
 * brought in again updates that note.
 *
 * @param int    $user_id
 * @param string $provider
 * @param string $external
 * @param string $hash     ws_note_source_hash()
 * @param int    $note_id
 * @return void
 */
function ws_note_source_save($user_id, $provider, $external, $hash, $note_id)
{
    if (((string) $external === '') || ((int) $note_id <= 0) || !ws_note_sources_ready()) {
        return;
    }

    db("INSERT INTO ws_note_sources (user_id, provider, external_id, hash, note_id)
        VALUES ('" . (int) $user_id . "', '" . e($provider) . "', '" . e($external) . "', '" . e($hash) . "', '" . (int) $note_id . "')
        ON DUPLICATE KEY UPDATE hash = VALUES(hash), note_id = VALUES(note_id)");
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

    // The button and the box's links stand for the My Notes row, so they
    // draw its icon (pg_menu_icon_cite()).
    $notes_icon = function_exists('pg_menu_icon_cite') ? pg_menu_icon_cite('notes') : '';
    $notes_icon_me = function_exists('pg_menu_icon_cite') ? pg_menu_icon_cite('notes', 'me-1') : '';

    if ($notes_icon === '') {
        $notes_icon = '<span class="bi bi-journal-plus" aria-hidden="true"></span>';
        $notes_icon_me = '<i class="bi bi-journal-text me-1" aria-hidden="true"></i>';
    }

    return '
                <ul class="navbar-nav pg-quick-note-nav">
                    <li class="nav-item pg-menu-wrap no-arrow no-popover" title="' . h(lang('Quick note')) . '">
                        <button type="button" id="pg_quick_note_toggle" class="nav-link nav-link-sm no-popover pg-menu-caret" data-bs-toggle="menu" data-bs-auto-close="outside" aria-expanded="false" aria-label="' . h(lang('Quick note')) . '">
                            ' . $notes_icon . '
                        </button>
                        <div id="pg_quick_note" class="menu shadow p-5 bg-body backdrop mt-nav-link-sm border-dropdown-menu position-fixed end-0 top-0" aria-labelledby="pg_quick_note_toggle">
                            <form data-pg-quick-note class="disable_shortcut" novalidate
                                data-api="' . h($base . 'api.php') . '"
                                data-note-url="' . h($base . 'workspace_notes.php?note=') . '"
                                data-saved="' . h(lang('Saved to your notes.')) . '"
                                data-open="' . h(lang('Open the note')) . '"
                                data-failed="' . h(lang('The note could not be saved.')) . '">
                                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                    <label for="pg_quick_note_text" class="fw-semibold small mb-0">' . $notes_icon_me . h(lang('Quick note')) . '</label>
                                    <a class="small pg-link-body-emphasis" href="' . h($base . 'workspace_notes.php') . '"' . $parent_target . '>' . $notes_icon_me . h(lang('My Notes')) . '</a>
                                </div>
                                <textarea id="pg_quick_note_text" name="body" class="pg-input pg-input-sm" rows="4" maxlength="' . WS_NOTE_MAX . '" placeholder="' . h(lang('Write a note. Its first line becomes its title.')) . '"></textarea>
                                <div class="d-flex align-items-center flex-wrap gap-3 mt-3">
                                    <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5">' . h(lang('Save')) . '</button>
                                    <span class="small fg-secondary d-none sm:d-inline">Ctrl+Enter</span>
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
 * may read it, or nothing at all. Being staff gives nothing here. A channel
 * share with can_edit lets whoever may post in that channel change it; one
 * who may only read the channel reads it.
 *
 * @param array      $viewer
 * @param array      $note
 * @param array|null $shares the note's ws_note_shares rows when read already
 *                           (ws_note_shares_map()), else read here
 * @return string owner | edit | view | ''
 */
function ws_note_access($viewer, $note, $shares = null)
{
    if (!$note) {
        return '';
    }

    // A channel's note is its channel's (ws_note_access_of()): its writer
    // owns nothing, and no share row is read.
    if (ws_note_channel_of($note) > 0) {
        $shares = array();
    } elseif ((int) $note['user_id'] === (int) $viewer['id']) {
        return 'owner';
    }

    if ($shares === null) {
        $shares = db_items("SELECT user_id, channel_id, can_edit FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "'");
    }

    // Posting is asked only of a channel whose share lets its posters edit.
    $reach = function ($channel_id, $need) use ($viewer) {
        $channel = ws_channel($channel_id);

        if (!$channel || !ws_can_read_channel($viewer, $channel)) {
            return '';
        }

        return (($need === 'post') && ws_can_post_channel($viewer, $channel)) ? 'post' : 'read';
    };

    return ws_note_access_of((int) $viewer['id'], $note, (array) $shares, $reach);
}

/**
 * ws_note_access() on rows given: pure but for $reach.
 *
 * A channel's note (owner_channel_id above 0) takes its access from that
 * channel alone, its writer included: who may post there edits it, who
 * reads it reads it (an archived channel is posted in by nobody), and no
 * share row counts. Nobody owns it, so a person's own rights (the lock, a
 * group, a share with a person, pinning) never reach it.
 *
 * @param int      $viewer_id
 * @param array    $note   user_id, owner_channel_id
 * @param array[]  $shares ws_note_shares rows: user_id, channel_id, can_edit
 * @param callable $reach  (channel_id, need: read | post) => '' | read | post,
 *                         the person's reach in that channel
 * @return string owner | edit | view | ''
 */
function ws_note_access_of($viewer_id, $note, $shares, $reach)
{
    $owner_channel = ws_note_channel_of($note);

    if ($owner_channel > 0) {
        $got = (string) $reach($owner_channel, 'post');

        return ($got === 'post') ? 'edit' : (($got === 'read') ? 'view' : '');
    }

    if ((int) $note['user_id'] === (int) $viewer_id) {
        return 'owner';
    }

    $access = '';

    foreach ((array) $shares as $share) {
        $editable = !empty($share['can_edit']);

        if ((int) ($share['user_id'] ?? 0) === (int) $viewer_id) {
            if ($editable) {
                return 'edit';
            }

            $access = 'view';
        } elseif (((int) ($share['channel_id'] ?? 0) > 0) && ($editable || ($access === ''))) {
            $got = (string) $reach((int) $share['channel_id'], $editable ? 'post' : 'read');

            if ($editable && ($got === 'post')) {
                return 'edit';
            }

            if ($got !== '') {
                $access = 'view';
            }
        }
    }

    return $access;
}

/**
 * Whether the application Claude works through reaches a note its owner may
 * read (access in the row): a channel's note only through its channel, which
 * Claude must be allowed in whatever the owner's access; a person's note the
 * owner owns or edits, or reads through a share by name or through a channel
 * Claude is allowed in. Pure but for $allowed.
 *
 * @param int      $viewer_id the owner of the application
 * @param array    $note      user_id, owner_channel_id, access
 * @param array[]  $shares    ws_note_shares rows: user_id, channel_id
 * @param callable $allowed   channel id => the owner reads it and Claude may
 *                            be asked there
 * @return bool
 */
function ws_note_claude_reach_of($viewer_id, $note, $shares, $allowed)
{
    $owner_channel = ws_note_channel_of($note);

    if ($owner_channel > 0) {
        return (bool) $allowed($owner_channel);
    }

    if ((string) ($note['access'] ?? '') !== 'view') {
        return true;
    }

    foreach ((array) $shares as $share) {
        if ((int) $share['user_id'] === (int) $viewer_id) {
            return true;
        }

        if (((int) $share['channel_id'] > 0) && $allowed((int) $share['channel_id'])) {
            return true;
        }
    }

    return false;
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
            $line = ws_plain_text($viewer, $line, null, true);
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
    // The reader's notes lock (note_lock.php): a note behind it, while the
    // lock is closed, keeps its title and loses its text.
    $lock = function_exists('ws_note_lock_view') ? ws_note_lock_view((int) $viewer['id']) : null;
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
        $app_id = (int) ($row['updated_app_id'] ?? 0);
        $changer = ws_note_changer(
            ((int) ($row['updated_by'] ?? 0) > 0) ? ($people[(int) $row['updated_by']] ?? null) : null,
            ($app_id > 0) ? ws_app_sender($app_id) : null
        );
        $excerpt = ws_plain_text($viewer, $body, null, true);

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
            // The application whose key made the last save (null: a person
            // did), and the words the card writes under the note.
            'updated_app' => ($app_id > 0) ? ws_app_sender($app_id) : null,
            'changed_by' => ws_note_changed_by_words($changer),
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
            // The channel it belongs to (a note written there); 0 for a
            // person's own note.
            'owner_channel_id' => ws_note_channel_of($row),
        );

        if ($full) {
            $note = array_merge($note, ws_note_body_view($viewer, $body));
            $note['claude'] = function_exists('ws_note_claude_state') ? ws_note_claude_state($viewer, $id) : array();
        }

        if ($lock) {
            $note = ws_note_lock_present($note, $row, (int) $viewer['id'], $lock, time());
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

    return array('body' => $body, 'labels' => $labels, 'html' => ws_render_body($body, $refs, null, false, null, null, true));
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
 * @param array $sets   the fields the save writes
 * @param int   $app_id the application this save is written as, 0 for a person
 * @return void
 */
function ws_note_version_keep($viewer, $note, $sets, $app_id = 0)
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
        || ((int) ($note['updated_app_id'] ?? 0) !== (int) $app_id)
        || ($body_changed && ($old_length >= 20) && ($new_length < $old_length * 0.6));

    if (!$keep) {
        return;
    }

    // The state is kept with whoever saved it: the person, and the
    // application when its key did.
    $app = ws_note_app_ready();

    db("INSERT INTO ws_note_versions (note_id, title, body, saved_by, " . ($app ? 'app_id, ' : '') . "saved_at, created_at)
        VALUES ('" . $note_id . "', '" . e($old_title) . "', '" . e($old_body) . "',
            '" . (int) $note['updated_by'] . "', " . ($app ? "'" . (int) ($note['updated_app_id'] ?? 0) . "', " : '') . "'" . (int) $note['updated_at'] . "', '" . time() . "')");

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
 * @return array[] id, title, saved, saved_full, by (person|null), app (application|null), changed_by, size
 */
function ws_note_versions($viewer, $note)
{
    if (!ws_note_versions_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT id, title, saved_by, " . (ws_note_app_ready() ? 'app_id, ' : '') . "saved_at, CHAR_LENGTH(body) AS size FROM ws_note_versions
        WHERE note_id = '" . (int) $note['id'] . "' ORDER BY id DESC LIMIT " . (int) WS_NOTE_VERSIONS_KEEP);

    $people = ws_people(array_unique(array_filter(array_map(function ($row) { return (int) $row['saved_by']; }, $rows))));
    $out = array();

    foreach ($rows as $row) {
        $app_id = (int) ($row['app_id'] ?? 0);
        $app = ($app_id > 0) ? ws_app_sender($app_id) : null;

        $out[] = array(
            'id'         => (int) $row['id'],
            'title'      => (string) $row['title'],
            'saved'      => ws_time_label($row['saved_at']),
            'saved_full' => ((int) $row['saved_at'] > 0) ? date('d.m.Y H:i', (int) $row['saved_at']) : '',
            'by'         => $people[(int) $row['saved_by']] ?? null,
            // The application whose key saved this state; null: the person.
            'app'        => $app,
            'changed_by' => ws_note_changed_by_words(ws_note_changer($people[(int) $row['saved_by']] ?? null, $app)),
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
        n.created_at, n.updated_at" . (ws_note_revision_ready() ? ', n.revision' : '') . (ws_note_lock_ready() ? ', n.locked' : '')
        . (ws_note_app_ready() ? ', n.updated_app_id' : '') . (ws_note_channel_owner_ready() ? ', n.owner_channel_id' : '');
}

/**
 * The words condition of a list where the notes behind the reader's closed
 * lock are found by their title alone, as the notes screen finds them
 * (ws_note_lock_list_filter()): the words are in the title, or in the text
 * of a note that is not behind the lock. Pure.
 *
 * @param string $title_sql  the words in the title
 * @param string $body_sql   the words in the text
 * @param string $behind_sql the note is behind the reader's lock
 *                           (ws_note_lock_behind_sql())
 * @return string "(...)"
 */
function ws_notes_search_closed_sql($title_sql, $body_sql, $behind_sql)
{
    return '(' . $title_sql . ' OR (NOT ' . $behind_sql . ' AND ' . $body_sql . '))';
}

/**
 * The conditions every list of notes shares: words in the title or the text,
 * and a change at or after a moment.
 *
 * @param string $search
 * @param int    $updated_since unix time; 0 for any
 * @param string $shared_at     the share's time column (s.created_at) for
 *                              notes shared with the person: a note shared
 *                              again since counts as changed, as sharing
 *                              does not move updated_at
 * @param string $behind_lock   the condition of a note behind the reader's
 *                              closed lock (ws_note_lock_behind_sql()), whose
 *                              text the words are not looked for in; '' when
 *                              no lock is closed for the reader
 * @return string " AND ..." or ''
 */
function ws_notes_filter_sql($search, $updated_since = 0, $shared_at = '', $behind_lock = '')
{
    $filter = '';
    $search = trim(mb_substr((string) $search, 0, 100));

    if ($search !== '') {
        $charset = pg_list_search_connection_charset();

        $filter .= ' AND ' . (((string) $behind_lock !== '')
            ? ws_notes_search_closed_sql(ws_search_like_sql($search, array('n.title'), $charset), ws_search_like_sql($search, array('n.body'), $charset), (string) $behind_lock)
            : ws_search_like_sql($search, array('n.title', 'n.body'), $charset));
    }

    if ((int) $updated_since > 0) {
        $changed = "n.updated_at >= '" . (int) $updated_since . "'";

        $filter .= ($shared_at !== '')
            ? " AND (" . $changed . " OR " . $shared_at . " >= '" . (int) $updated_since . "')"
            : " AND " . $changed;
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
        return array('mine' => array(), 'shared' => array(), 'groups' => array());
    }

    $me = (int) $viewer['id'];
    $filter = ws_notes_filter_sql($search);

    $mine = (array) db_items("SELECT " . ws_notes_list_columns() . " FROM ws_notes n WHERE n.user_id = '" . $me . "'" . ws_note_own_sql() . $filter . "
        ORDER BY n.pinned DESC, n.updated_at DESC, n.id DESC LIMIT " . WS_NOTE_LIMIT);

    $shared = (array) db_items("SELECT " . ws_notes_list_columns() . ", IF(MAX(s.can_edit) = 1, 'edit', 'view') AS access FROM ws_notes n
        INNER JOIN ws_note_shares s ON s.note_id = n.id AND s.user_id = '" . $me . "'
        WHERE n.user_id <> '" . $me . "'" . $filter . "
        GROUP BY n.id
        ORDER BY n.updated_at DESC, n.id DESC LIMIT " . WS_NOTE_LIMIT);

    foreach ($mine as $index => $row) {
        $mine[$index]['access'] = 'owner';
    }

    // The person's groups, and the group each note is kept in (group_id,
    // group_sort; note_groups.php).
    $out = array(
        'mine'   => ws_note_places_add($viewer, ws_notes_present($viewer, $mine)),
        'shared' => ws_note_places_add($viewer, ws_notes_present($viewer, $shared)),
        'groups' => ws_note_groups($viewer),
    );

    // Groups shared with the person, and the notes drawn in them
    // (note_group_shares.php).
    if (function_exists('ws_note_groups_shared_with') && ws_note_groups_ready()) {
        $with = ws_note_groups_shared_with($viewer);
        $own = array_map(function ($group) { return (int) $group['id']; }, $out['groups']);

        $out['groups'] = ws_note_groups_mark_shared($out['groups']);
        $out['shared_groups'] = $with['groups'];
        $out['mine'] = ws_note_groups_shared_assign($out['mine'], $own, $with['places']);
        $out['shared'] = ws_note_groups_shared_assign($out['shared'], $own, $with['places']);
    }

    // Behind a closed lock: a locked group's notes are not listed, and a
    // search finds a locked note by its title alone (note_lock.php).
    if (function_exists('ws_note_lock_list_filter') && ws_note_lock_ready() && ws_note_lock_has($me)) {
        $list_open = ws_note_lock_is_open($me);

        $out['mine'] = ws_note_lock_list_filter($out['mine'], $list_open, $search);
        $out['shared'] = ws_note_lock_list_filter($out['shared'], $list_open, $search);
    }

    return $out;
}

/**
 * The person's own notes, the latest changed first, as the notes list
 * presents them (ws_notes_present()): a note behind the closed lock keeps
 * its title alone, and a note in a locked group is left out until the list
 * is opened (ws_note_lock_list_filter()). For the dashboard's notes card.
 *
 * @param array $viewer
 * @param int   $limit
 * @return array[]
 */
function ws_notes_latest($viewer, $limit)
{
    $limit = max(1, min(50, (int) $limit));

    if (!ws_notes_ready()) {
        return array();
    }

    $me = (int) $viewer['id'];

    // A few more than asked, for the notes the lock leaves out.
    $rows = (array) db_items("SELECT " . ws_notes_list_columns() . " FROM ws_notes n WHERE n.user_id = '" . $me . "'" . ws_note_own_sql() . "
        ORDER BY n.updated_at DESC, n.id DESC LIMIT " . ($limit * 3));

    foreach ($rows as $index => $row) {
        $rows[$index]['access'] = 'owner';
    }

    $notes = ws_notes_present($viewer, $rows);

    if (function_exists('ws_note_lock_list_filter') && ws_note_lock_ready() && ws_note_lock_has($me)) {
        $notes = ws_note_lock_list_filter($notes, ws_note_lock_is_open($me), '');
    }

    return array_slice($notes, 0, $limit);
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
 *                       page), limit, behind_lock (the condition of a note
 *                       behind the reader's closed lock, which a search
 *                       finds by its title alone; '' or left out for none)
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
    $behind = (string) ($options['behind_lock'] ?? '');
    $filter = ws_notes_filter_sql((string) ($options['search'] ?? ''), (int) ($options['updated_since'] ?? 0), '', $behind);
    $rows = array();
    $total = 0;

    if ($scope !== 'shared') {
        $own = "IF(n.pinned = 1, 0, 1)";

        $total += (int) db_value("SELECT COUNT(*) FROM ws_notes n WHERE n.user_id = '" . $me . "'" . ws_note_own_sql() . $filter);

        $rows = (array) db_items("SELECT " . ws_notes_list_columns() . ", " . $own . " AS list_group FROM ws_notes n
            WHERE n.user_id = '" . $me . "'" . ws_note_own_sql() . $filter . ws_notes_after_sql($own, $after) . "
            ORDER BY list_group, n.updated_at DESC, n.id DESC LIMIT " . ($limit + 1));

        foreach ($rows as $index => $row) {
            $rows[$index]['access'] = 'owner';
        }
    }

    if ($scope !== 'mine') {
        $from = " FROM ws_notes n
            INNER JOIN ws_note_shares s ON s.note_id = n.id AND s.user_id = '" . $me . "'
            WHERE n.user_id <> '" . $me . "'" . ws_notes_filter_sql((string) ($options['search'] ?? ''), (int) ($options['updated_since'] ?? 0), 's.created_at', $behind);

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

    $me = (int) $viewer['id'];

    // No API client holds the notes lock open (note_lock.php), so a search
    // finds a note behind the person's lock by its title alone, as the notes
    // screen does while the lock is closed: a match in the text would tell
    // what the locked note holds.
    if ((trim((string) ($options['search'] ?? '')) !== '') && function_exists('ws_note_lock_behind_sql') && ws_note_lock_ready()) {
        $options['behind_lock'] = ws_note_lock_behind_sql($me, ws_note_lock_has($me), ws_note_lock_group_note_ids($me));
    }

    $page = ws_notes_page_rows($me, $options);
    $page['rows'] = ws_notes_present($viewer, $page['rows']);

    return $page;
}

/**
 * The rows of one page of a channel's own notes (owner_channel_id), the
 * latest change first, then the higher id; search finds words in the
 * titles. The position is the notes list's (ws_notes_position_pack(),
 * group 0), so a cursor reads the same way.
 *
 * @param int   $channel_id
 * @param array $options search, after (a position, null for the first
 *                       page), limit
 * @return array rows (with list_group), next, total
 */
function ws_note_channel_page_rows($channel_id, $options)
{
    if (((int) $channel_id <= 0) || !ws_note_channel_owner_ready()) {
        return array('rows' => array(), 'next' => null, 'total' => 0);
    }

    $limit = max(1, min(WS_NOTES_PAGE_MAX, (int) ($options['limit'] ?? 100)));
    $after = is_array($options['after'] ?? null) ? $options['after'] : null;
    $search = trim(mb_substr((string) ($options['search'] ?? ''), 0, 100));
    $where = "n.owner_channel_id = '" . (int) $channel_id . "'"
        . (($search !== '') ? ' AND ' . ws_search_like_sql($search, array('n.title'), pg_list_search_connection_charset()) : '');

    $total = (int) db_value("SELECT COUNT(*) FROM ws_notes n WHERE " . $where);
    $rows = (array) db_items("SELECT " . ws_notes_list_columns() . ", 0 AS list_group FROM ws_notes n
        WHERE " . $where . ws_notes_after_sql('0', $after) . "
        ORDER BY n.updated_at DESC, n.id DESC LIMIT " . ($limit + 1));
    $next = null;

    if (count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
        $last = $rows[$limit - 1];
        $next = array('group' => 0, 'updated_at' => (int) $last['updated_at'], 'id' => (int) $last['id']);
    }

    return array('rows' => $rows, 'next' => $next, 'total' => $total);
}

/**
 * One page of a channel's notes for a reader of the channel (the Notes tab
 * and GET /workspace/channels/{id}/notes), presented as the notes list
 * presents them. Each row's access is the channel's (ws_note_access_of()):
 * edit for who may post there, view otherwise; can_delete follows
 * ws_note_channel_may_delete().
 *
 * @param array $viewer
 * @param array $channel one the viewer may read
 * @param array $options see ws_note_channel_page_rows()
 * @return array rows (presented), next, total
 */
function ws_note_channel_page($viewer, $channel, $options)
{
    if (!ws_notes_ready() || !is_array($channel) || !ws_can_read_channel($viewer, $channel)) {
        return array('rows' => array(), 'next' => null, 'total' => 0);
    }

    $page = ws_note_channel_page_rows((int) $channel['id'], $options);
    $access = ws_can_post_channel($viewer, $channel) ? 'edit' : 'view';
    $manages = ws_can_manage_channel($viewer, $channel);

    foreach ($page['rows'] as $index => $row) {
        $page['rows'][$index]['access'] = $access;
    }

    $presented = ws_notes_present($viewer, $page['rows']);

    foreach ($presented as $index => $note) {
        $presented[$index]['can_delete'] = ws_note_channel_may_delete((int) $page['rows'][$index]['user_id'] === (int) $viewer['id'], true, $manages);
    }

    $page['rows'] = $presented;

    return $page;
}

/**
 * How many notes a channel keeps: the Notes tab is shown once it has one.
 *
 * @param int $channel_id
 * @return int
 */
function ws_note_channel_count($channel_id)
{
    if (((int) $channel_id <= 0) || !ws_notes_ready() || !ws_note_channel_owner_ready()) {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*) FROM ws_notes WHERE owner_channel_id = '" . (int) $channel_id . "'");
}

/**
 * Whether the person may write this note: the checks of ws_note_save(), made
 * on their own so that the API's dry run meets the same refusals.
 *
 * @param array $viewer
 * @param array $data see ws_note_save()
 * @param int   $owner_channel_id see ws_note_save()
 * @return array ok, error, note_id, conflict, note (as it is now, null for a
 *               new one), sets (the fields as they would be stored), pin (1 or
 *               0, null when it is left as it is)
 */
function ws_note_save_check($viewer, $data, $owner_channel_id = 0)
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

        // Behind the person's closed notes lock (note_lock.php).
        $locked = function_exists('ws_note_lock_gate') ? ws_note_lock_gate($viewer, $note) : '';

        if ($locked !== '') {
            return $fail($locked, $note_id);
        }

        if (ws_note_stale($note, $data, (int) $viewer['id'])) {
            $error = ((int) $note['updated_by'] !== (int) $viewer['id'])
                ? lang(array('string' => '{var:1} changed this note meanwhile.', 'vars' => ws_person_name($note['updated_by'])))
                : lang('You changed this note meanwhile on another screen or device.');

            return $fail($error, $note_id, true);
        }
    } elseif ((int) $owner_channel_id > 0) {
        if (!ws_note_channel_owner_ready()) {
            return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
        }

        // A channel's notes count in the channel's limit, not the writer's.
        if ((int) db_value("SELECT COUNT(*) FROM ws_notes WHERE owner_channel_id = '" . (int) $owner_channel_id . "'") >= WS_NOTE_CHANNEL_LIMIT) {
            return $fail(lang(array('string' => 'A channel can keep at most {var:1} notes.', 'vars' => WS_NOTE_CHANNEL_LIMIT)));
        }
    } elseif ((int) db_value("SELECT COUNT(*) FROM ws_notes WHERE user_id = '" . (int) $viewer['id'] . "'" . ws_note_own_sql('')) >= WS_NOTE_LIMIT) {
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

    // Pinning is the owner's, and is not an edit; a channel's note has none.
    $pin = null;

    if (array_key_exists('pinned', $data) && ((($note === null) && ((int) $owner_channel_id <= 0)) || (($note !== null) && ($note['access'] === 'owner')))) {
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
 * @param int   $owner_channel_id a new note written in this channel is the
 *                                channel's (ws_note_channel_write() only;
 *                                never taken from a request's data)
 * @return array ok, error, note_id, conflict
 */
function ws_note_save($viewer, $data, $owner_channel_id = 0)
{
    $check = ws_note_save_check($viewer, $data, $owner_channel_id);

    if (!$check['ok']) {
        return array('ok' => false, 'error' => $check['error'], 'note_id' => $check['note_id'], 'conflict' => $check['conflict']);
    }

    $note_id = (int) $check['note_id'];
    $note = $check['note'];
    $sets = $check['sets'];
    $pin = $check['pin'];
    $now = time();
    // The application whose key saves, written beside the person it acts for.
    $app_id = ws_note_saver_app($data);
    $app_ready = ws_note_app_ready();

    if ($note === null) {
        $owned = ((int) $owner_channel_id > 0);

        db("INSERT INTO ws_notes (user_id, title, body, pinned, updated_by, " . ($app_ready ? 'updated_app_id, ' : '') . ($owned ? 'owner_channel_id, ' : '') . "created_at, updated_at)
            VALUES ('" . (int) $viewer['id'] . "', '" . e($sets['title']) . "', '" . e($sets['body']) . "',
                '" . (int) $pin . "', '" . (int) $viewer['id'] . "', " . ($app_ready ? "'" . $app_id . "', " : '') . ($owned ? "'" . (int) $owner_channel_id . "', " : '') . "'" . $now . "', '" . $now . "')");

        $note_id = (int) mysqli_insert_id(db::$con);

        if (($note_id > 0) && function_exists('ws_blocks_index')) {
            ws_blocks_index('note', $note_id, $sets['body'], (int) $viewer['id']);
        }

        if ($note_id > 0) {
            ws_ref_source_write('note', array($note_id => ws_note_ref_tokens($sets['body'])));
        }

        return ($note_id > 0)
            ? array('ok' => true, 'error' => '', 'note_id' => $note_id, 'conflict' => false)
            : array('ok' => false, 'error' => lang('The note could not be saved.'), 'note_id' => 0, 'conflict' => false);
    }

    // The state before this save, when it is worth keeping.
    ws_note_version_keep($viewer, $note, $sets, $app_id);

    $parts = array();

    foreach ($sets as $field => $value) {
        $parts[] = $field . " = '" . e((string) $value) . "'";
    }

    if (!empty($parts)) {
        $parts[] = "updated_by = '" . (int) $viewer['id'] . "'";
        $parts[] = "updated_at = '" . $now . "'";

        if ($app_ready) {
            $parts[] = "updated_app_id = '" . $app_id . "'";
        }
    }

    if ($pin !== null) {
        $parts[] = "pinned = '" . $pin . "'";
    }

    if (!empty($parts) && ws_note_revision_ready()) {
        $parts[] = ws_note_revision_sql(false);
    }

    if (!empty($parts)) {
        db("UPDATE ws_notes SET " . implode(', ', $parts) . " WHERE id = '" . $note_id . "'");
        ws_note_cards_touch($note_id);
    }

    if (isset($sets['body']) && function_exists('ws_blocks_index')) {
        ws_blocks_index('note', $note_id, $sets['body'], (int) $note['user_id']);
    }

    if (isset($sets['body'])) {
        ws_ref_source_write('note', array($note_id => ws_note_ref_tokens($sets['body'])));
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

    // A tag the save adds wakes on the note's cards in agent channels.
    if (isset($sets['body'])) {
        ws_note_ask_tags($viewer, array_merge($note, $sets), (string) $note['body'], (string) $sets['body']);
    }

    return array('ok' => true, 'error' => '', 'note_id' => $note_id, 'conflict' => false);
}

/**
 * After a shared note's text was saved, or the note shared in a channel:
 * the tags it adds wake the agents they name, each once, on the oldest card
 * of an agent channel the agent is in and the writer manages
 * (ws_message_item_tags_writer()). The brief tells the note and where the
 * tag sits (ws_item_brief_lines()).
 *
 * @param array  $viewer the writer
 * @param array  $note   the note as saved
 * @param string $old    the text before ('' for a share)
 * @param string $new    the text now
 * @param int    $only_channel_id 0 for every channel it is shared in
 * @return string[] the agent values asked
 */
function ws_note_ask_tags($viewer, $note, $old, $new, $only_channel_id = 0)
{
    $added = ws_message_tags_added(ws_tokens_normalise((string) $old), ws_tokens_normalise((string) $new));

    if ($added === '') {
        return array();
    }

    $shares = (array) db_items("SELECT channel_id, message_id FROM ws_note_shares
        WHERE note_id = '" . (int) $note['id'] . "' AND channel_id > 0 AND message_id > 0"
        . (((int) $only_channel_id > 0) ? " AND channel_id = '" . (int) $only_channel_id . "'" : '') . "
        ORDER BY id");

    $item = array(
        'kind'   => 'note',
        'name'   => ws_note_title($viewer, $note),
        'where'  => '',
        'text'   => ws_item_tag_excerpt((string) $new, $added),
        'writer' => ws_person_name($viewer['id']),
        'route'  => 'GET /workspace/notes/' . (int) $note['id'],
        'under'  => 'card',
    );
    $asked = array();

    foreach ($shares as $share) {
        $channel = ws_channel((int) $share['channel_id']);
        $card = ws_message((int) $share['message_id']);

        if (!is_array($channel) || !is_array($card) || ((int) $card['deleted_at'] > 0) || !ws_message_item_tags_writer($viewer, $channel)) {
            continue;
        }

        $asked = array_merge($asked, ws_item_ask($viewer, $channel, (int) $card['id'], $added, $item, $asked));
    }

    return $asked;
}

/**
 * Who may delete a channel's note: its writer while they still read the
 * channel, and whoever manages the channel. Pure.
 *
 * @param bool $writer     the person wrote it (user_id)
 * @param bool $can_read   the person reads its channel
 * @param bool $can_manage the person manages its channel
 * @return bool
 */
function ws_note_channel_may_delete($writer, $can_read, $can_manage)
{
    return ($writer && $can_read) || $can_manage;
}

/**
 * Whether the person may delete a note: a person's note by its owner only;
 * a channel's note by its writer or whoever manages the channel
 * (ws_note_channel_may_delete()). The screen's delete and the API's (and
 * its dry run) meet the same refusal.
 *
 * @param array $viewer
 * @param array $note
 * @return array ok, error
 */
function ws_note_delete_check($viewer, $note)
{
    $owner_channel = ws_note_channel_of($note);

    if ($owner_channel > 0) {
        $channel = ws_channel($owner_channel);
        $may = is_array($channel) && ws_note_channel_may_delete(
            (int) $note['user_id'] === (int) $viewer['id'],
            ws_can_read_channel($viewer, $channel),
            ws_can_manage_channel($viewer, $channel)
        );

        return $may ? array('ok' => true, 'error' => '')
            : array('ok' => false, 'error' => lang('Only the one who wrote a note or who manages its channel can delete it.'));
    }

    if (!is_array($note) || ((int) $note['user_id'] !== (int) $viewer['id'])) {
        return array('ok' => false, 'error' => lang('Only the one who wrote a note can delete it.'));
    }

    $locked = function_exists('ws_note_lock_gate') ? ws_note_lock_gate($viewer, $note) : '';

    if ($locked !== '') {
        return array('ok' => false, 'error' => $locked);
    }

    return array('ok' => true, 'error' => '');
}

/**
 * The notes deleted since a moment that the person kept: their own, those
 * shared with them, and those shared in a channel they may read, each once,
 * the oldest deletion first; with them the notes no longer shared with
 * them (a share taken back), unless they own the note or it is shared with
 * them again.
 *
 * @param array $viewer ws_viewer()
 * @param int   $since
 * @return array[] id, deleted_at
 */
function ws_note_deletions_since($viewer, $since)
{
    if (!ws_note_deletions_ready()) {
        return array();
    }

    $since = max((int) $since, time() - WS_NOTE_DELETIONS_KEEP);
    $channels = array();

    foreach ((array) db_values("SELECT DISTINCT channel_id FROM ws_note_deletions
        WHERE channel_id > 0 AND deleted_at >= '" . $since . "'") as $channel_id) {
        if (ws_can_read_channel($viewer, ws_channel((int) $channel_id))) {
            $channels[] = (int) $channel_id;
        }
    }

    $reach = "user_id = '" . (int) $viewer['id'] . "'" . (!empty($channels) ? " OR channel_id IN (" . implode(',', $channels) . ")" : '');
    $rows = (array) db_items("SELECT note_id, MAX(deleted_at) AS deleted_at FROM ws_note_deletions
        WHERE deleted_at >= '" . $since . "' AND (" . $reach . ")
        GROUP BY note_id ORDER BY deleted_at ASC, note_id ASC LIMIT " . (int) WS_NOTE_DELETIONS_MAX);

    $out = array();

    foreach ($rows as $row) {
        $out[] = array('id' => (int) $row['note_id'], 'deleted_at' => (int) $row['deleted_at']);
    }

    if (empty($out)) {
        return $out;
    }

    // A note that still exists is in the list again when the person owns
    // it or a person share brings it to them; a deleted one has no rows.
    $me = (int) $viewer['id'];
    $ids = implode(',', array_column($out, 'id'));
    $kept = array_merge(
        (array) db_values("SELECT id FROM ws_notes WHERE user_id = '" . $me . "'" . ws_note_own_sql('') . " AND id IN (" . $ids . ")"),
        (array) db_values("SELECT note_id FROM ws_note_shares WHERE user_id = '" . $me . "' AND note_id IN (" . $ids . ")")
    );

    return ws_note_deletions_unkept($out, $kept);
}

/**
 * The deletions a sync client is told of, without the notes the person
 * still keeps in their list.
 *
 * @param array[] $deletions id, deleted_at
 * @param int[]   $kept      note ids the person owns or has a person share of
 * @return array[] id, deleted_at
 */
function ws_note_deletions_unkept($deletions, $kept)
{
    $kept = array_flip(array_map('intval', (array) $kept));
    $out = array();

    foreach ((array) $deletions as $deletion) {
        if (!isset($kept[(int) $deletion['id']])) {
            $out[] = $deletion;
        }
    }

    return $out;
}

/**
 * The ws_note_deletions row a person share taken back keeps for the
 * person, so that a sync client drops its copy; null for a channel share,
 * whose notes never come in the person's list.
 *
 * @param array $note  id
 * @param array $share the ws_note_shares row: user_id, channel_id
 * @param int   $by    who took it back
 * @param int   $now
 * @return array|null note_id, user_id, channel_id, deleted_by, deleted_at
 */
function ws_note_unshare_deletion_row($note, $share, $by, $now)
{
    if ((int) ($share['user_id'] ?? 0) <= 0) {
        return null;
    }

    return array('note_id' => (int) $note['id'], 'user_id' => (int) $share['user_id'], 'channel_id' => 0, 'deleted_by' => (int) $by, 'deleted_at' => (int) $now);
}

/**
 * The rows ws_note_deletions keeps when a note is deleted: one for its owner,
 * one for each person it was shared with, one for each channel it was shared
 * in (user_id 0), each once.
 *
 * @param array $note   id, user_id
 * @param array $shares the note's ws_note_shares rows: user_id, channel_id
 * @param int   $by     who deleted it
 * @param int   $now
 * @return array[] note_id, user_id, channel_id, deleted_by, deleted_at
 */
function ws_note_deletion_rows($note, $shares, $by, $now)
{
    $people = array((int) $note['user_id'] => true);
    $channels = array();

    foreach ((array) $shares as $share) {
        if ((int) ($share['user_id'] ?? 0) > 0) {
            $people[(int) $share['user_id']] = true;
        } elseif ((int) ($share['channel_id'] ?? 0) > 0) {
            $channels[(int) $share['channel_id']] = true;
        }
    }

    $rows = array();

    foreach (array_keys($people) as $user_id) {
        $rows[] = array('note_id' => (int) $note['id'], 'user_id' => $user_id, 'channel_id' => 0, 'deleted_by' => (int) $by, 'deleted_at' => (int) $now);
    }

    foreach (array_keys($channels) as $channel_id) {
        $rows[] = array('note_id' => (int) $note['id'], 'user_id' => 0, 'channel_id' => $channel_id, 'deleted_by' => (int) $by, 'deleted_at' => (int) $now);
    }

    return $rows;
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
    $check = ws_note_delete_check($viewer, $note);

    if (!$check['ok']) {
        return $check;
    }

    ws_note_delete_rows($note, (int) $viewer['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * Deletes a note with everything kept for it, without asking who may: the
 * caller has. Its cards say it is no longer shared.
 *
 * @param array $note
 * @param int   $by who deletes it (0: the site, as with its channel)
 */
function ws_note_delete_rows($note, $by)
{
    $id = (int) $note['id'];

    // Who kept it is read before the shares go, for the apps that keep
    // notes in sync.
    if (ws_note_deletions_ready()) {
        $now = time();
        $shares = (array) db_items("SELECT user_id, channel_id FROM ws_note_shares WHERE note_id = '" . $id . "'");

        foreach (ws_note_deletion_rows($note, $shares, (int) $by, $now) as $row) {
            db("INSERT INTO ws_note_deletions (note_id, user_id, channel_id, deleted_by, deleted_at)
                VALUES ('" . $row['note_id'] . "', '" . $row['user_id'] . "', '" . $row['channel_id'] . "', '" . $row['deleted_by'] . "', '" . $row['deleted_at'] . "')");
        }

        db("DELETE FROM ws_note_deletions WHERE deleted_at < '" . ($now - WS_NOTE_DELETIONS_KEEP) . "' LIMIT 500");
    }

    foreach ((array) db_items("SELECT message_id FROM ws_note_shares WHERE note_id = '" . $id . "' AND message_id > 0") as $share) {
        ws_note_card_retire($share['message_id']);
    }

    // Nobody keeps access to the note's files through it; the files stay in
    // the file manager, where staff still see them.
    ws_note_folder_sync($note, true);

    db("DELETE FROM ws_note_shares WHERE note_id = '" . $id . "'");
    db("DELETE FROM ws_notes WHERE id = '" . $id . "'");
    ws_note_places_forget_note($id);
    ws_ref_source_write('note', array($id => array()));

    // The same file brought in again is a new note.
    if (ws_note_sources_ready()) {
        db("DELETE FROM ws_note_sources WHERE note_id = '" . $id . "'");
    }

    if (ws_note_versions_ready()) {
        db("DELETE FROM ws_note_versions WHERE note_id = '" . $id . "'");
    }

    if (function_exists('ws_blocks_index')) {
        ws_blocks_index('note', $id, '');
    }

    if (ws_notes_inbox_ready()) {
        ws_inbox_delete("note_id = '" . $id . "'");
    }
}

/**
 * Deletes the notes that belong to a channel which is being deleted, as its
 * messages are (ws_threads_purge()).
 *
 * @param int $channel_id
 * @return int how many
 */
function ws_note_channel_purge($channel_id)
{
    if (((int) $channel_id <= 0) || !ws_notes_ready() || !ws_note_channel_owner_ready()) {
        return 0;
    }

    $notes = (array) db_items("SELECT * FROM ws_notes WHERE owner_channel_id = '" . (int) $channel_id . "'");

    foreach ($notes as $note) {
        ws_note_delete_rows($note, 0);
    }

    return count($notes);
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

    ws_ref_source_write('note', array($note_id => ws_note_ref_tokens($body)));

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
    if (ws_note_channel_of($note) > 0) {
        return array('ok' => false, 'error' => lang('A channel\'s note is not shared elsewhere.'), 'shared' => 0);
    }

    $locked = function_exists('ws_note_lock_share_refusal') ? ws_note_lock_share_refusal($note) : '';

    if ($locked !== '') {
        return array('ok' => false, 'error' => $locked, 'shared' => 0);
    }

    $shared = 0;
    $now = time();

    foreach (array_unique(array_map('intval', (array) $user_ids)) as $user_id) {
        if (($user_id <= 0) || ($user_id === (int) $note['user_id']) || !ws_is_team_member($user_id)) {
            continue;
        }

        // A share a shared group wrote (via_group) is the group's: a direct
        // share is a row of its own, which stays when the group is taken back.
        $existing = (int) db_value("SELECT id FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "' AND user_id = '" . $user_id . "'" . (ws_note_groups_ready() ? " AND via_group = 0" : '') . " LIMIT 1");

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
 * @param array $options can_edit (the channel's posters may change it),
 *                       app_id and via_client_id (who the card's message is
 *                       written as, see ws_message_send())
 * @return array ok, error, message_id
 */
function ws_note_share_channel($viewer, $note, $channel, $options = array())
{
    // A channel's note has its one card in its own channel.
    if ((ws_note_channel_of($note) > 0) && (ws_note_channel_of($note) !== (int) $channel['id'])) {
        return array('ok' => false, 'error' => lang('A channel\'s note is not shared elsewhere.'), 'message_id' => 0);
    }

    $locked = function_exists('ws_note_lock_share_refusal') ? ws_note_lock_share_refusal($note) : '';

    if ($locked !== '') {
        return array('ok' => false, 'error' => $locked, 'message_id' => 0);
    }

    if (!ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'), 'message_id' => 0);
    }

    $existing = db_item("SELECT * FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "' AND channel_id = '" . (int) $channel['id'] . "'" . (ws_note_groups_ready() ? " AND via_group = 0" : '') . " LIMIT 1");

    if (is_array($existing)) {
        return array('ok' => false, 'error' => lang('This note is already shared in that channel.'), 'message_id' => (int) $existing['message_id']);
    }

    // The card's words, for wherever the card itself is not drawn (a device
    // banner, a search, the external API).
    $sent = ws_message_send($viewer, $channel, ws_note_card_words($viewer, $note), ws_note_card_send_options($options));

    if (!$sent['ok']) {
        return array('ok' => false, 'error' => $sent['error'], 'message_id' => 0);
    }

    db("INSERT INTO ws_note_shares (note_id, channel_id, can_edit, message_id, shared_by, created_at)
        VALUES ('" . (int) $note['id'] . "', '" . (int) $channel['id'] . "', '" . (empty($options['can_edit']) ? 0 : 1) . "', '" . (int) $sent['message_id'] . "', '" . (int) $viewer['id'] . "', '" . time() . "')");

    ws_note_folder_sync($note);

    // Shared into an agent channel by its manager: the note's tags wake there.
    ws_note_ask_tags($viewer, $note, '', (string) $note['body'], (int) $channel['id']);

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
    // A channel's note has no owner and no share but its card, which stays
    // while the note does (ws_note_access_of()).
    if (ws_note_channel_of($note) > 0) {
        return array('ok' => false, 'error' => lang('A channel\'s note keeps its card in its channel.'));
    }

    $owner = ((int) $note['user_id'] === (int) $viewer['id']);

    $share = ((int) $share_id > 0)
        ? db_item("SELECT * FROM ws_note_shares WHERE id = '" . (int) $share_id . "' AND note_id = '" . (int) $note['id'] . "'")
        : db_item("SELECT * FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "' AND user_id = '" . (int) $viewer['id'] . "'" . (ws_note_groups_ready() ? " ORDER BY via_group" : '') . " LIMIT 1");

    if (!is_array($share) || (!$owner && ((int) $share['user_id'] !== (int) $viewer['id']))) {
        return array('ok' => false, 'error' => lang('That share could not be found.'));
    }

    // A shared group's row would be written again by the group: the note
    // leaves through the group (note_group_shares.php).
    if ((int) ($share['via_group'] ?? 0) > 0) {
        return array('ok' => false, 'error' => $owner
            ? lang('This note is shared through a group: take it out of the group or take the group back.')
            : lang('This note came with a shared group: leave the group to stop seeing it.'));
    }

    // The note leaves the person's list, for the apps that keep notes in
    // sync as for a deletion.
    $row = ws_note_unshare_deletion_row($note, $share, (int) $viewer['id'], time());

    if ($row && ws_note_deletions_ready()) {
        db("INSERT INTO ws_note_deletions (note_id, user_id, channel_id, deleted_by, deleted_at)
            VALUES ('" . $row['note_id'] . "', '" . $row['user_id'] . "', '" . $row['channel_id'] . "', '" . $row['deleted_by'] . "', '" . $row['deleted_at'] . "')");
    }

    db("DELETE FROM ws_note_shares WHERE id = '" . (int) $share['id'] . "'");

    // Without a share by name left, the note leaves the person's group.
    ws_note_places_forget_reader((int) $share['user_id'], (int) $note['id']);

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
 * The words of a note card's message, for wherever the card itself is not
 * drawn.
 *
 * @param array $viewer
 * @param array $note
 * @return string
 */
function ws_note_card_words($viewer, $note)
{
    return lang(array('string' => 'Shared a note: {var:1}', 'vars' => ws_note_title($viewer, $note)));
}

/**
 * The ws_message_send() options of a note card's message: who it is written
 * as, nothing else. Pure.
 *
 * @param array $options app_id, via_client_id
 * @return array
 */
function ws_note_card_send_options($options)
{
    $out = array();

    foreach (array('app_id', 'via_client_id') as $key) {
        if ((int) ($options[$key] ?? 0) > 0) {
            $out[$key] = (int) $options[$key];
        }
    }

    return $out;
}

/**
 * After a note changed: each message carrying its card in a channel is
 * touched, so an open conversation draws the card again and changed_since
 * returns it. No message is written, nobody is woken or told.
 *
 * @param int $note_id
 * @return int how many cards
 */
function ws_note_cards_touch($note_id)
{
    $message_ids = (array) db_values("SELECT DISTINCT message_id FROM ws_note_shares
        WHERE note_id = '" . (int) $note_id . "' AND channel_id > 0 AND message_id > 0");

    foreach ($message_ids as $message_id) {
        ws_message_touch((int) $message_id);
    }

    return count($message_ids);
}

/**
 * The note's owner lets the posters of a channel it is shared in change it,
 * or takes that back: the card's switch. A share a shared group wrote is the
 * group's, and a locked note is refused.
 *
 * @param array $viewer
 * @param array $note
 * @param int   $channel_id
 * @param bool  $can_edit
 * @return array ok, error, message_id
 */
function ws_note_channel_edit_set($viewer, $note, $channel_id, $can_edit)
{
    if (!is_array($note) || ((int) $note['user_id'] !== (int) $viewer['id']) || (ws_note_channel_of($note) > 0)) {
        return array('ok' => false, 'error' => lang('Only the one who wrote a note can share it.'), 'message_id' => 0);
    }

    $locked = function_exists('ws_note_lock_share_refusal') ? ws_note_lock_share_refusal($note) : '';

    if ($locked !== '') {
        return array('ok' => false, 'error' => $locked, 'message_id' => 0);
    }

    $share = db_item("SELECT * FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "' AND channel_id = '" . (int) $channel_id . "'"
        . (ws_note_groups_ready() ? " AND via_group = 0" : '') . " LIMIT 1");

    if (!is_array($share) || ((int) $channel_id <= 0)) {
        return array('ok' => false, 'error' => lang('That share could not be found.'), 'message_id' => 0);
    }

    db("UPDATE ws_note_shares SET can_edit = '" . ($can_edit ? 1 : 0) . "' WHERE id = '" . (int) $share['id'] . "'");

    if ((int) $share['message_id'] > 0) {
        ws_message_touch((int) $share['message_id']);
    }

    return array('ok' => true, 'error' => '', 'message_id' => (int) $share['message_id']);
}

/**
 * A body sent as Markdown, in the shape a note keeps (ws_note_import_convert():
 * "####" and deeper become "###", an underlined line a heading). Pure.
 *
 * @param string $body
 * @return string
 */
function ws_note_channel_body($body)
{
    return ws_note_import_convert(str_replace("\r\n", "\n", (string) $body));
}

/**
 * Whether a note may be written in a channel and its card posted there: the
 * checks of ws_note_channel_write(), shared by the panel's window and the
 * API's route (and its dry run). A body over WS_NOTE_MAX is refused with the
 * limit, never cut.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $data    title, body, channel_edit, markdown (convert the body
 *                       from Markdown first: the API), app_id, via_client_id
 * @return array ok, error, field, title, body (as it would be stored)
 */
function ws_note_channel_write_check($viewer, $channel, $data)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'title' => '', 'body' => '');
    };

    if (!ws_notes_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    if (!is_array($channel) || !ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('You cannot post in that channel.'));
    }

    $body = (string) ($data['body'] ?? '');

    if (!empty($data['markdown'])) {
        $body = ws_note_channel_body($body);
    }

    $title = (string) ($data['title'] ?? '');

    if ((trim($title) === '') && (trim($body) === '')) {
        return $fail(lang('The note is empty.'), 'body');
    }

    $saved = ws_note_save_check($viewer, array('note_id' => 0, 'title' => $title, 'body' => $body), (int) $channel['id']);

    if (!$saved['ok']) {
        return $fail($saved['error'], (mb_strlen($body) > WS_NOTE_MAX) ? 'body' : '');
    }

    $words = ws_note_card_words($viewer, array('title' => $saved['sets']['title'], 'body' => $saved['sets']['body']));
    $sendable = ws_message_send_check($viewer, $channel, $words, ws_note_card_send_options($data));

    if (!$sendable['ok']) {
        return $fail($sendable['error']);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'title' => $saved['sets']['title'], 'body' => $saved['sets']['body']);
}

/**
 * Writes a new note of the channel's and posts its card there in one move.
 * The note belongs to the channel (owner_channel_id), user_id keeps its
 * writer: who may post in the channel changes it, and it is on nobody's own
 * notes nor in their limit. Its card is tied to it by a channel share row
 * that gives no access (ws_note_access_of() reads none for a channel's
 * note), so channel_edit has no effect on it. The card's message is written
 * as $data's app_id / via_client_id say.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $data see ws_note_channel_write_check()
 * @return array ok, error, field, note_id, message_id, revision
 */
function ws_note_channel_write($viewer, $channel, $data)
{
    $check = ws_note_channel_write_check($viewer, $channel, $data);

    if (!$check['ok']) {
        return array('ok' => false, 'error' => $check['error'], 'field' => $check['field'], 'note_id' => 0, 'message_id' => 0, 'revision' => 0);
    }

    $saved = ws_note_save($viewer, array('note_id' => 0, 'title' => $check['title'], 'body' => $check['body'], 'app_id' => (int) ($data['app_id'] ?? 0)), (int) $channel['id']);

    if (!$saved['ok']) {
        return array('ok' => false, 'error' => $saved['error'], 'field' => '', 'note_id' => 0, 'message_id' => 0, 'revision' => 0);
    }

    $note = ws_note($saved['note_id']);
    $shared = ws_note_share_channel($viewer, $note, $channel, array(
        'can_edit'      => false,
        'app_id'        => (int) ($data['app_id'] ?? 0),
        'via_client_id' => (int) ($data['via_client_id'] ?? 0),
    ));

    // A card that could not be posted leaves no note behind.
    if (!$shared['ok']) {
        ws_note_delete($viewer, $note);

        return array('ok' => false, 'error' => $shared['error'], 'field' => '', 'note_id' => 0, 'message_id' => 0, 'revision' => 0);
    }

    return array(
        'ok'         => true,
        'error'      => '',
        'field'      => '',
        'note_id'    => (int) $note['id'],
        'message_id' => (int) $shared['message_id'],
        'revision'   => (int) ($note['revision'] ?? 0),
    );
}

/**
 * What moving a person's note into a channel does to its share rows. Pure.
 *
 * The note keeps one card, in the channel it moves to: the channel share of
 * its own there (not a note group's), or a card posted for it when there is
 * none. Every other share goes: a channel's note is not shared elsewhere.
 * The note leaves its owner's list and the lists of the people it was
 * shared with, so each of them gets a ws_note_deletions row for the apps
 * that keep notes in sync, as other channels it leaves do (user_id 0).
 *
 * @param array   $note       id, user_id
 * @param array[] $shares     the note's ws_note_shares rows: id, user_id,
 *                            channel_id, via_group, message_id
 * @param int     $channel_id the channel it moves to
 * @param int     $by         who moves it
 * @param int     $now
 * @return array card (the share row kept, null when a card is to be
 *               posted), drop (the share rows that go), deletions
 */
function ws_note_move_plan($note, $shares, $channel_id, $by, $now)
{
    $card = null;
    $drop = array();

    foreach ((array) $shares as $share) {
        if (($card === null) && ((int) ($share['channel_id'] ?? 0) === (int) $channel_id) && empty($share['via_group'])) {
            $card = $share;
            continue;
        }

        $drop[] = $share;
    }

    // The channel it moves to keeps the note: no deletion row for it.
    $leaving = array_values(array_filter($drop, function ($share) use ($channel_id) {
        return (int) ($share['channel_id'] ?? 0) !== (int) $channel_id;
    }));

    return array(
        'card'      => $card,
        'drop'      => $drop,
        'deletions' => ws_note_deletion_rows($note, $leaving, (int) $by, (int) $now),
    );
}

/**
 * Whether a person may move a note of theirs into a channel: the checks of
 * ws_note_move(), shared by the card's "Move to this channel" and
 * POST /workspace/notes/{id}/move (and its dry run).
 *
 * @param array      $viewer
 * @param array|null $note
 * @param array|null $channel
 * @return array ok, error, field (note_id | channel_id), card (the channel
 *               share kept as its card, null when one is to be posted)
 */
function ws_note_move_check($viewer, $note, $channel)
{
    $fail = function ($error, $field) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'card' => null);
    };

    if (!ws_notes_ready() || !ws_note_channel_owner_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'), '');
    }

    if (!is_array($note) || (ws_note_channel_of($note) > 0) || ((int) $note['user_id'] !== (int) $viewer['id'])) {
        return $fail(lang('Only the one who wrote a note can move it to a channel.'), 'note_id');
    }

    $locked = function_exists('ws_note_lock_share_refusal') ? ws_note_lock_share_refusal($note) : '';

    if ($locked !== '') {
        return $fail($locked, 'note_id');
    }

    if (!is_array($channel) || !ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('You cannot post in that channel.'), 'channel_id');
    }

    if (ws_note_channel_count((int) $channel['id']) >= WS_NOTE_CHANNEL_LIMIT) {
        return $fail(lang(array('string' => 'A channel can keep at most {var:1} notes.', 'vars' => WS_NOTE_CHANNEL_LIMIT)), 'channel_id');
    }

    $shares = (array) db_items("SELECT * FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "'");
    $plan = ws_note_move_plan($note, $shares, (int) $channel['id'], (int) $viewer['id'], time());

    // Without a card there, one is posted: the channel must take it.
    if ($plan['card'] === null) {
        $sendable = ws_message_send_check($viewer, $channel, ws_note_card_words($viewer, $note), array());

        if (!$sendable['ok']) {
            return $fail($sendable['error'], 'channel_id');
        }
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'card' => $plan['card']);
}

/**
 * Moves a person's own note into a channel: the note becomes the channel's
 * (owner_channel_id), leaves its owner's notes, their limit, groups and
 * pin, and every share but its card in that channel; a card is posted
 * when the channel has none. Who may post in the channel changes it from
 * then on. "Copy to my notes" is the way back (ws_note_copy_mine()).
 *
 * @param array $viewer
 * @param array $note
 * @param array $channel
 * @param array $options app_id, via_client_id: who a card posted for it is
 *                       written as (ws_note_card_send_options())
 * @return array ok, error, field, message_id (its card)
 */
function ws_note_move($viewer, $note, $channel, $options = array())
{
    $check = ws_note_move_check($viewer, $note, $channel);

    if (!$check['ok']) {
        return array('ok' => false, 'error' => $check['error'], 'field' => $check['field'], 'message_id' => 0);
    }

    $id = (int) $note['id'];
    $channel_id = (int) $channel['id'];

    if ($check['card'] === null) {
        $shared = ws_note_share_channel($viewer, $note, $channel, array(
            'can_edit'      => false,
            'app_id'        => (int) ($options['app_id'] ?? 0),
            'via_client_id' => (int) ($options['via_client_id'] ?? 0),
        ));

        if (!$shared['ok']) {
            return array('ok' => false, 'error' => $shared['error'], 'field' => 'channel_id', 'message_id' => 0);
        }
    }

    $now = time();
    $shares = (array) db_items("SELECT * FROM ws_note_shares WHERE note_id = '" . $id . "'");
    $plan = ws_note_move_plan($note, $shares, $channel_id, (int) $viewer['id'], $now);

    if (ws_note_deletions_ready()) {
        foreach ($plan['deletions'] as $row) {
            db("INSERT INTO ws_note_deletions (note_id, user_id, channel_id, deleted_by, deleted_at)
                VALUES ('" . $row['note_id'] . "', '" . $row['user_id'] . "', '" . $row['channel_id'] . "', '" . $row['deleted_by'] . "', '" . $row['deleted_at'] . "')");
        }
    }

    foreach ($plan['drop'] as $share) {
        db("DELETE FROM ws_note_shares WHERE id = '" . (int) $share['id'] . "'");

        if ((int) $share['message_id'] > 0) {
            ws_note_card_retire($share['message_id']);
        }
    }

    // The card gives no access to a channel's note: its can_edit is off.
    $card_message = (int) $plan['card']['message_id'];

    db("UPDATE ws_note_shares SET can_edit = '0' WHERE id = '" . (int) $plan['card']['id'] . "'");
    db("UPDATE ws_notes SET owner_channel_id = '" . $channel_id . "', pinned = '0'" . ws_note_revision_sql() . " WHERE id = '" . $id . "'");

    // Groups and inbox lines are a person's: none stays with it.
    ws_note_places_forget_note($id);

    if (ws_notes_inbox_ready()) {
        ws_inbox_delete("note_id = '" . $id . "'");
    }

    ws_note_folder_sync(ws_note($id));

    if ($card_message > 0) {
        ws_message_touch($card_message);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'message_id' => $card_message);
}

/**
 * Copies a channel's note into the person's own notes: a new note of
 * theirs with its title and text, counted in their WS_NOTE_LIMIT. The
 * channel's note stays as it is. The files it shows stay in the channel
 * note's folder.
 *
 * @param array $viewer
 * @param array $note a channel's note they may read (ws_note_for() view)
 * @return array ok, error, note_id (the copy)
 */
function ws_note_copy_mine($viewer, $note)
{
    if (!is_array($note) || (ws_note_channel_of($note) <= 0)) {
        return array('ok' => false, 'error' => lang('Only a channel\'s note is copied to your notes.'), 'note_id' => 0);
    }

    $saved = ws_note_save($viewer, array('note_id' => 0, 'title' => (string) $note['title'], 'body' => (string) $note['body']));

    return $saved['ok']
        ? array('ok' => true, 'error' => '', 'note_id' => (int) $saved['note_id'])
        : array('ok' => false, 'error' => $saved['error'], 'note_id' => 0);
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
    $kept = array();

    // How many versions each note keeps: the count in the card's foot.
    if (!empty($shares) && ws_note_versions_ready()) {
        $note_ids = array_unique(array_map(function ($share) { return (int) $share['note_id']; }, $shares));

        foreach ((array) db_items("SELECT note_id, COUNT(*) AS kept FROM ws_note_versions WHERE note_id IN (" . implode(',', $note_ids) . ") GROUP BY note_id") as $row) {
            $kept[(int) $row['note_id']] = (int) $row['kept'];
        }
    }

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
            'html'       => ws_render_body($body, ws_refs_resolve($viewer, ws_tokens($body)), null, false, null, null, true),
            'access'     => $access,
            'owner'      => $card['owner'],
            'updated_by' => $card['updated_by'],
            'updated_app' => $card['updated_app'],
            'changed_by' => $card['changed_by'],
            'updated'    => $card['updated'],
            'revision'   => (int) ($note['revision'] ?? 0),
            'updated_at' => (int) $note['updated_at'],
            // The share behind the card: its channel, whether the channel's
            // posters may edit, and whether a note group wrote it (the
            // owner's switch is only on the channel's own share).
            'channel_id'   => (int) $share['channel_id'],
            'channel_edit' => !empty($share['can_edit']),
            'via_group'    => !empty($share['via_group']),
            'versions'     => $kept[(int) $note['id']] ?? 0,
            // A note of the channel's own: its foot says who wrote it.
            'owned'        => ws_note_channel_of($note) > 0,
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
    $locked = function_exists('ws_note_lock_share_refusal') ? ws_note_lock_share_refusal($note) : '';

    if ($locked !== '') {
        return array('ok' => false, 'error' => $locked, 'field' => '', 'channel_id' => 0);
    }

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
    $shares = (array) db_items("SELECT user_id, channel_id FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "'");
    $channel_ids = array(ws_note_channel_of($note));

    foreach ($shares as $share) {
        $channel_ids[] = (int) $share['channel_id'];
    }

    $members = array();

    foreach (array_unique(array_filter($channel_ids)) as $channel_id) {
        $members[$channel_id] = array_map('intval', (array) db_values("SELECT user_id FROM ws_channel_members WHERE channel_id = '" . (int) $channel_id . "'"));
    }

    return ws_note_readers_of($note, $shares, $members);
}

/**
 * ws_note_readers() on rows given. A channel's note is read by the members
 * of its channel alone, its writer only while one of them; a person's note
 * by its owner, the people it is shared with and the members of the
 * channels it is shared in. Pure.
 *
 * @param array   $note    user_id, owner_channel_id
 * @param array[] $shares  ws_note_shares rows: user_id, channel_id
 * @param array   $members channel id => member user ids
 * @return int[]
 */
function ws_note_readers_of($note, $shares, $members)
{
    $owner_channel = ws_note_channel_of($note);

    if ($owner_channel > 0) {
        return array_values(array_unique(array_filter(array_map('intval', (array) ($members[$owner_channel] ?? array())))));
    }

    $ids = array((int) $note['user_id']);

    foreach ((array) $shares as $share) {
        if ((int) $share['user_id'] > 0) {
            $ids[] = (int) $share['user_id'];
        } elseif ((int) $share['channel_id'] > 0) {
            foreach ((array) ($members[(int) $share['channel_id']] ?? array()) as $member) {
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
 * Keeps a file for a note in the note's folder, from its bytes: what the
 * screen sends (ws_note_file, read by ws_upload_request_file()) and what a
 * note brought in from another app carries with it
 * (includes/workspace/notes_import.php). What a channel
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
            unset($finfo);
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

    // No assistant reads a locked note, open or not (note_lock.php).
    if (function_exists('ws_note_locked_by_owner') && ws_note_locked_by_owner($note)) {
        return array('ok' => false, 'error' => lang('No assistant is asked in a locked note.'), 'request_id' => 0);
    }

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
    list($pv_column, $pv_value) = pg_ai_prompt_version_insert();

    db("INSERT INTO ws_ai_requests (channel_id, message_id, note_id, note_text, requested_by, status, " . (($agent === 'ai') ? 'agent, ' : '') . "created_at" . $pv_column . ")
        VALUES ('0', '0', '" . (int) $note['id'] . "', '" . e($text) . "', '" . (int) $viewer['id'] . "', 'queued', " . (($agent === 'ai') ? "'ai', " : '') . "'" . time() . "'" . $pv_value . ")");

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

    // The person's screen writes the answer in, so no application is named.
    db("UPDATE ws_notes SET body = '" . e($body) . "', updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . time() . "'"
        . (ws_note_app_ready() ? ", updated_app_id = 0" : '') . ws_note_revision_sql() . "
        WHERE id = '" . (int) $note['id'] . "'");

    ws_note_cards_touch((int) $note['id']);

    if (function_exists('ws_blocks_index')) {
        ws_blocks_index('note', (int) $note['id'], $body, (int) $note['user_id']);
    }

    ws_ref_source_write('note', array((int) $note['id'] => ws_note_ref_tokens($body)));

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
    return array('note_shared', 'note_updated', 'note_answer', 'note_answer_ai', 'note_group_added');
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
    // A channel's note tells nobody: its card shows who changed it.
    if (!ws_notes_inbox_ready() || (ws_note_channel_of($note) > 0)) {
        return;
    }

    $shared = (array) db_values("SELECT user_id FROM ws_note_shares WHERE note_id = '" . (int) $note['id'] . "' AND user_id > 0");
    $now = time();

    foreach (ws_note_change_told($note, $shared, (int) $viewer['id'], (int) $skip) as $user_id) {
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
 * Who is told that a note changed: its owner and the people it is shared
 * with, but not the one who changed it nor the one skipped. A channel's note
 * tells nobody, its writer neither: the card says who changed it. Pure.
 *
 * @param array $note      user_id, owner_channel_id
 * @param int[] $shared    the user_id of its person shares
 * @param int   $viewer_id who changed it
 * @param int   $skip      somebody else not to tell
 * @return int[]
 */
function ws_note_change_told($note, $shared, $viewer_id, $skip)
{
    if (ws_note_channel_of($note) > 0) {
        return array();
    }

    $out = array();

    foreach (array_merge(array((int) $note['user_id']), array_map('intval', (array) $shared)) as $user_id) {
        if (($user_id > 0) && ($user_id !== (int) $viewer_id) && ($user_id !== (int) $skip) && !in_array($user_id, $out, true)) {
            $out[] = $user_id;
        }
    }

    return $out;
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
        'notes_group_new'       => lang('New group'),
        'notes_group_edit'      => lang('Change the group'),
        'notes_group_name'      => lang('Group name'),
        'notes_group_delete'    => lang('Delete the group'),
        'notes_group_delete_ask' => ws_js_template('Delete the group “{var:1}”? Its notes are not deleted: they go back to no group.', 1),
        'notes_group_empty'     => lang('No notes in this group yet.'),
        'notes_group_locked'    => lang('Locked'),
        'notes_group_move'      => lang('Move to group'),
        'notes_group_move_to'   => ws_js_template('Move “{var:1}” to a group', 1),
        'notes_group_none'      => lang('No group'),
        'notes_group_up'        => lang('Move up'),
        'notes_group_down'      => lang('Move down'),
        'notes_group_into_shared' => ws_js_template('Everyone in the group “{var:1}” will see this note.', 1),
        'notes_group_out_shared' => ws_js_template('The people in the group “{var:1}” lose this note unless it is shared with them on its own.', 1),
        'notes_group_into_locked' => ws_js_template('The group “{var:1}” is locked: locking takes this note’s shares back.', 1),
        'notes_group_own_only'  => lang('Only your own notes go into a shared group.'),
        'notes_tab_new'         => lang('New note in this group'),
        'notes_tab_shared'      => lang('Shared note'),
        'notes_group_shared'    => lang('Shared group'),
        'notes_group_share'     => lang('Share the group'),
        'notes_group_share_title' => ws_js_template('Share the group “{var:1}”', 1),
        'notes_group_share_help' => lang('Everyone you share it with sees every note in it. People who can add notes put in their own notes, which stay theirs.'),
        'notes_group_share_locked' => lang('A locked group cannot be shared.'),
        'notes_group_can_add'   => lang('Can add notes'),
        'notes_group_channel'   => lang('Or share it in a channel'),
        'notes_group_no_channel' => lang('No channel'),
        'notes_group_only_you'  => lang('Only you can see this group.'),
        'notes_group_shared_ok' => lang('The group is shared.'),
        'notes_group_leave'     => lang('Leave the group'),
        'notes_group_leave_ask' => ws_js_template('Leave the group “{var:1}”? Your notes in it are taken out of it; they are not deleted.', 1),
        'notes_group_card'      => lang('Group of notes'),
        'notes_group_open'      => lang('Open in My Notes'),
        'notes_group_not_listed' => lang('This group is not among your notes. Its notes can be read from the card where it was shared.'),
        'notes_export_all'      => lang('Export all'),
        'notes_import'          => lang('Import'),
        'notes_import_title'    => lang('Import notes'),
        'notes_import_intro'    => lang('Bring notes in from another app: Markdown (.md) or text (.txt) files, or a .zip of them, such as an Obsidian vault, a Notion, Joplin or Bear export or a Google Keep Takeout, Evernote exports (.enex), or a web page saved as .html. Notes exported from here are recognised and brought up to date.'),
        'notes_import_pick'     => lang('Choose the files or drop them here'),
        'notes_import_limit'    => ws_js_template('Up to {var:1} MB', 1),
        'notes_import_too_many' => ws_js_template('Pick at most {var:1} files at a time, or put them in a .zip.', 1),
        'notes_import_too_large' => ws_js_template('The upload is larger than {var:1} MB.', 1),
        'notes_import_reading'  => lang('Reading the files…'),
        'notes_import_picked'   => ws_js_template('{var:1} file(s) selected, {var:2}', 2),
        'notes_import_over'     => ws_js_template('{var:1} of them are over the limit and will not be sent', 1),
        'notes_import_blocked'  => ws_js_template('{var:1} of them are of a type that cannot be uploaded and will not be sent', 1),
        'notes_import_stale'    => ws_js_template('{var:1} of them cannot be read and will not be sent', 1),
        'notes_import_not_allowed' => lang('not allowed here'),
        'notes_import_unreadable' => lang('unreadable'),
        'notes_import_cannot_read' => ws_js_template('The file {var:1} cannot be read.', 1),
        'notes_import_clear'    => lang('Clear'),
        'notes_import_upload'   => lang('Upload'),
        'notes_import_choose'   => lang('Choose Markdown (.md) or text (.txt) files, one .zip of them, Evernote exports (.enex) or one web page (.html).'),
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
        // The From Notion part of the import window (notes_notion.php).
        'notes_notion_tab'      => lang('From Notion'),
        'notes_files_tab'       => lang('From files'),
        'notes_notion_intro'    => lang('Search your Notion pages, pick the ones to bring in, and each becomes a note of yours. Pages already imported are brought up to date.'),
        'notes_notion_search'   => lang('Search Notion'),
        'notes_notion_searching' => lang('Searching Notion…'),
        'notes_notion_none'     => lang('No page matches.'),
        'notes_notion_untitled' => lang('Untitled'),
        'notes_notion_fetching' => lang('Fetching the pages from Notion…'),
        'notes_notion_go'       => ws_js_template('Fetch {var:1} page(s)', 1),
        'notes_notion_max'      => ws_js_template('Pick at most {var:1} pages at a time.', 1),
        'notes_notion_settings' => lang('Open Workspace settings'),
        // The From OneNote part of the import window (notes_onenote.php).
        'notes_onenote_tab'     => lang('From OneNote'),
        'notes_onenote_intro'   => lang('Open a section of your OneNote notebooks, pick the pages to bring in, and each becomes a note of yours. Pages already imported are brought up to date.'),
        'notes_onenote_connect' => lang('Connect Microsoft account'),
        'notes_onenote_back'    => lang('All sections'),
        'notes_onenote_none'    => lang('This section has no pages.'),
        'notes_onenote_empty'   => lang('No OneNote notebook was found in your account.'),
        'notes_onenote_more'    => lang('Only the most recently changed pages are listed.'),
        'notes_onenote_fetching' => lang('Fetching the pages from OneNote…'),
        // The head of the list card (startNotes() in workspace.js).
        'notes_list_head'       => lang('Notes'),
        'notes_mine'            => lang('My notes'),
        'notes_shared'          => lang('Shared with me'),
        // The list's order in the bar's "⋯" (workspace_note_list.js).
        'notes_order'           => lang('Sort by'),
        'notes_order_changed'   => lang('Last changed'),
        'notes_order_name'      => lang('Name'),
        'notes_order_created'   => lang('Created'),
        'notes_new'             => lang('New note'),
        'notes_news'            => lang('Changed since you last opened it'),
        'notes_claude_answered' => lang('Claude answered'),
        'notes_claude_working'  => lang('Claude is on it'),
        'notes_search'          => lang('Search the notes'),
        'notes_empty'           => lang('No note yet.'),
        'notes_empty_shared'    => lang('Nothing is shared with you yet.'),
        'notes_empty_search'    => lang('No note matches.'),
        'notes_none'            => lang('A clean page - your first note is one click away.'),
        'notes_first_title'     => lang('Write your first note'),
        'notes_first_text'      => lang('A note is yours alone until you share it.'),
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
        'notes_share_right'     => lang('Access'),
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
        'notes_voice'           => lang('Record a voice note'),
        'notes_voices'          => lang('Recordings in this note'),
        'notes_drop'            => lang('Drop the files to attach them to the note'),
        'notes_uploading'       => lang('Uploading…'),
        'notes_tag'             => lang('Tag a person or a record'),
        'notes_claude'          => lang('Ask Claude'),
        'notes_emoji'           => lang('Emoji'),
        'notes_more_tools'      => lang('More tools'),
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
        // A note in a channel: its card and windows (workspace_channel_notes.js).
        'cn_write'              => lang('Write a note'),
        'cn_channel_edit'       => lang('People who may post here can edit'),
        'cn_channel_edit_on'    => lang('People who may post in this channel can edit the note now.'),
        'cn_channel_edit_off'   => lang('Only the people the note is shared with can edit it now.'),
        'cn_show_all'           => lang('Show all'),
        'cn_show_less'          => lang('Show less'),
        'cn_versions'           => lang('Versions'),
        'cn_menu'               => lang('Note actions'),
        'cn_open_notes'         => lang('Open on the notes screen'),
        'cn_show'               => lang('Show'),
        'cn_put_back_done'      => lang('The version was put back.'),
        // A channel's own notes: the card's foot and the Notes tab.
        'cn_written_by'         => ws_js_template('Written by {var:1}', 1),
        'cn_tab'                => lang('Notes'),
        'cn_tab_count'          => ws_js_template('{var:1} notes', 1),
        'cn_search'             => lang('Search the titles'),
        'cn_open'               => lang('Open'),
        'cn_none'               => lang('No notes have been written here yet.'),
        'cn_none_found'         => lang('No note has that in its title.'),
        'cn_older'              => lang('Older notes'),
        'cn_delete_confirm'     => lang('Delete this note? It disappears for everybody in the channel.'),
        // Moving a person's note into a channel, and the way back.
        'cn_move'               => lang('Move to this channel'),
        'cn_move_confirm'       => lang('Move this note to the channel? It leaves your notes and belongs to the channel: whoever may post here can change it, and it is no longer shared anywhere else.'),
        'cn_moved'              => lang('The note belongs to this channel now.'),
        'cn_copy_mine'          => lang('Copy to my notes'),
        'cn_copied'             => lang('A copy is in your notes now.'),
    );
}
