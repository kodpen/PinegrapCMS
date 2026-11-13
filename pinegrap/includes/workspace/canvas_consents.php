<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a reader's answer to a live page's first write (2026.11.0
 * step 11.0.193). Before a page writes to a table in a reader's name for
 * the first time, the panel asks that person once for this canvas: which
 * tables it writes. The answer holds for the page's version it was given
 * on; a new version of the page asks again, and so does a write table bound
 * after the answer. A page never sees the question, only the refusal
 * (consent) while the answer is not yes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_tables.php');

/**
 * Has the step that brings the answers run?
 *
 * @return bool
 */
function ws_canvas_consents_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_canvas_consents', 'page_version') && ws_canvas_tables_ready();
    }

    return $ready;
}

/**
 * The page's newest version, the one a reader runs.
 *
 * @param array $message
 * @return int 0 when the file is no page
 */
function ws_canvas_page_version($message)
{
    $file_id = (int) ($message['file_id'] ?? 0);

    if (($file_id <= 0) || !pg_file_versions_ready()) {
        return 0;
    }

    return (int) db_value("SELECT MAX(version_no) FROM file_versions WHERE file_id = '" . $file_id . "' AND format = 'html'");
}

/**
 * What the stored answer means for a write now. Pure.
 *
 * @param array|null $answer      the stored row (answer, tables as JSON) or null
 * @param int[]      $write_ids   the ids of the tables the canvas binds for
 *                                writing that the reader reaches now
 * @return string ask (no answer, or a write table it was not asked about),
 *                yes or no
 */
function ws_canvas_consent_state($answer, $write_ids)
{
    if (!is_array($answer)) {
        return 'ask';
    }

    if ((string) ($answer['answer'] ?? '') !== 'yes') {
        return 'no';
    }

    $asked = json_decode((string) ($answer['tables'] ?? ''), true);
    $asked = is_array($asked) ? array_map('intval', $asked) : array();

    foreach ((array) $write_ids as $id) {
        if (!in_array((int) $id, $asked, true)) {
            return 'ask';
        }
    }

    return 'yes';
}

/**
 * The stored answer of a person for one version of a canvas's page.
 *
 * @param int $message_id
 * @param int $user_id
 * @param int $version
 * @return array|null answer, tables, created_at
 */
function ws_canvas_consent($message_id, $user_id, $version)
{
    if (!ws_canvas_consents_ready() || ((int) $message_id <= 0) || ((int) $user_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT answer, tables, created_at FROM ws_canvas_consents
        WHERE message_id = '" . (int) $message_id . "' AND user_id = '" . (int) $user_id . "' AND page_version = '" . (int) $version . "'");

    return is_array($row) ? $row : null;
}

/**
 * The tables the question lists for the reader: each bound table they
 * reach, with what the page does to it.
 *
 * @param array $viewer
 * @param array $message
 * @return array[] id, name, access (read | write)
 */
function ws_canvas_consent_tables($viewer, $message)
{
    $out = array();

    foreach (ws_canvas_tables((int) $message['id']) as $binding) {
        $table = ws_wtable_for($viewer, $binding['table_id'], 'view');

        if ($table) {
            $out[] = array('id' => (int) $table['id'], 'name' => (string) $table['name'], 'access' => (string) $binding['access']);
        }
    }

    return $out;
}

/**
 * The ids of the write tables among ws_canvas_consent_tables(). Pure.
 *
 * @param array[] $tables
 * @return int[]
 */
function ws_canvas_consent_write_ids($tables)
{
    $ids = array();

    foreach ((array) $tables as $table) {
        if ((string) ($table['access'] ?? '') === 'write') {
            $ids[] = (int) $table['id'];
        }
    }

    return $ids;
}

/**
 * What the panel needs to ask: the canvas's name, the page version the
 * answer is for, the tables, and the state now.
 *
 * @param array $viewer
 * @param array $message
 * @return array name, version, tables, state (ask | yes | no)
 */
function ws_canvas_consent_question($viewer, $message)
{
    $version = ws_canvas_page_version($message);
    $tables = ws_canvas_consent_tables($viewer, $message);

    return array(
        'name'    => preg_replace('/\.html?$/i', '', (string) ($message['file_name'] ?? '')),
        'version' => $version,
        'tables'  => $tables,
        'state'   => ws_canvas_consent_state(ws_canvas_consent((int) $message['id'], (int) $viewer['id'], $version), ws_canvas_consent_write_ids($tables)),
    );
}

/**
 * Keeps a reader's answer for the page's version they were asked on. A
 * version that is no longer the newest is refused: the page changed while
 * the question was open, and the new one asks again.
 *
 * @param array      $viewer
 * @param array|null $message
 * @param string     $answer  yes | no
 * @param int        $version the version the question named
 * @return array ok, error (not_page, invalid, changed), text
 */
function ws_canvas_consent_answer($viewer, $message, $answer, $version)
{
    if (!ws_canvas_consents_ready() || !ws_canvas_page_reach($viewer, $message)) {
        return array('ok' => false, 'error' => 'not_page', 'text' => lang('That message could not be found.'));
    }

    if (!in_array((string) $answer, array('yes', 'no'), true)) {
        return array('ok' => false, 'error' => 'invalid', 'text' => lang('The request is not valid.'));
    }

    $now = ws_canvas_page_version($message);

    if (($now <= 0) || ((int) $version !== $now)) {
        return array('ok' => false, 'error' => 'changed', 'text' => lang('The page has a new version: open it again.'));
    }

    $tables = json_encode(ws_canvas_consent_write_ids(ws_canvas_consent_tables($viewer, $message)));

    // One answer a person and version: answering again replaces it.
    db("INSERT INTO ws_canvas_consents (message_id, user_id, page_version, answer, tables, created_at)
        VALUES ('" . (int) $message['id'] . "', '" . (int) $viewer['id'] . "', '" . $now . "', '" . escape($answer) . "', '" . escape($tables) . "', UNIX_TIMESTAMP())
        ON DUPLICATE KEY UPDATE answer = VALUES(answer), tables = VALUES(tables), created_at = VALUES(created_at)");

    return array('ok' => true, 'error' => '', 'text' => '');
}

/**
 * The canvas a row was last written through, as the table screen names it:
 * its name when the reader reads its channel, else a nameless canvas; ''
 * for a row written another way.
 *
 * @param array $viewer
 * @param int   $message_id ws_wtable_rows.via_message_id
 * @return string
 */
function ws_canvas_via_label($viewer, $message_id)
{
    if ((int) $message_id <= 0) {
        return '';
    }

    $message = ws_message((int) $message_id);
    $name = ws_canvas_page_reach($viewer, $message) ? preg_replace('/\.html?$/i', '', (string) ($message['file_name'] ?? '')) : '';

    return ($name !== '')
        ? lang(array('string' => 'Last written through {var:1}', 'vars' => $name))
        : lang('Last written through a canvas you do not reach');
}

/**
 * The texts of the first-write question on the page card.
 *
 * @return array key => text
 */
function ws_canvas_consents_js_strings()
{
    return array(
        'page_consent_title'   => ws_js_template('{var:1} writes in your name?', 1),
        'page_consent_write'   => lang('Adds and changes rows, cannot delete'),
        'page_consent_read'    => lang('Only reads'),
        'page_consent_writes'  => lang('Writes'),
        'page_consent_reads'   => lang('Reads'),
        'page_consent_hint'    => ws_js_template('Each write shows "through {var:1}" on the row. A new version of the page asks again.', 1),
        'page_consent_allow'   => lang('Allow'),
        'page_consent_deny'    => lang("Don't allow"),
        'page_consent_refused' => lang('Write permission not given. The page only reads.'),
        'page_consent_change'  => lang('Change'),
    );
}
