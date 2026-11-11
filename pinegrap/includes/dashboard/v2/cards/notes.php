<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The My notes card, a shortcut to My Notes: a box that adds a note there
 * (the notes' own ws_note_save action, as the header's quick note does) and
 * the person's latest notes by title and date, each opening on the My Notes
 * screen. The text an older card kept in the layout is moved into My Notes
 * the first time the card is drawn (personal.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/personal.php');

/**
 * How many of the person's notes the card lists.
 */
if (!defined('PG_DASHBOARD_V2_NOTES_SHOWN')) {
    define('PG_DASHBOARD_V2_NOTES_SHOWN', 5);
}

/**
 * The card's rows from notes as the notes list presents them
 * (ws_notes_present()): the name, the date of the last change and the
 * address that opens the note on My Notes. A note behind a closed lock keeps
 * the name the list gives it and is drawn with the lock. Pure.
 *
 * @param array[] $notes
 * @param string  $note_url the My Notes address the note's id is added to
 * @param int     $limit
 * @return array[] id, text, note, url, locked
 */
function pg_dashboard_v2_notes_rows($notes, $note_url, $limit)
{
    $rows = array();

    foreach (array_slice((array) $notes, 0, max(0, (int) $limit)) as $note) {
        $rows[] = array(
            'id'     => (int) $note['id'],
            'text'   => (string) $note['name'],
            'note'   => (string) ($note['updated'] ?? ''),
            'url'    => $note_url . (int) $note['id'],
            'locked' => !empty($note['locked']) || !empty($note['lock_closed']),
        );
    }

    return $rows;
}

function pg_dashboard_v2_card_notes($user, $period, $compare)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

    $rights = pg_dashboard_workspace_rights($user);

    if (!pg_dashboard_v2_notes_gate($rights, ($rights !== null) && ws_notes_ready())) {
        return array('status' => 'error', 'message' => lang('Access denied'));
    }

    $viewer = ws_viewer($user);

    pg_dashboard_v2_notes_move_stored($user, $viewer);

    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    $note_url = $base . 'workspace_notes.php?note=';

    return array('status' => 'success', 'data' => array(
        'rows'     => pg_dashboard_v2_notes_rows(ws_notes_latest($viewer, PG_DASHBOARD_V2_NOTES_SHOWN), $note_url, PG_DASHBOARD_V2_NOTES_SHOWN),
        'shown'    => PG_DASHBOARD_V2_NOTES_SHOWN,
        'most'     => WS_NOTE_MAX,
        'api'      => $base . 'api.php',
        'note_url' => $note_url,
        'url'      => $base . 'workspace_notes.php',
        'link'     => lang('All notes'),
        'labels'   => array(
            'label'   => lang('Add a note'),
            'hint'    => lang('Write a note. Its first line becomes its title.'),
            'save'    => lang('Save'),
            'cancel'  => lang('Cancel'),
            'saved'   => lang('Saved to your notes.'),
            'open'    => lang('Open the note'),
            'failed'  => lang('The note could not be saved.'),
            'none'    => lang('No notes yet.'),
            'locked'  => lang('Locked'),
        ),
    ));
}
