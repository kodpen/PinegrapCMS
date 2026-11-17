<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The texts of a Design Canvas picture in the site's translation. A picture
 * whose live document (the newest file_versions row, while the file still
 * holds its pixels) has text layers gives its words to the translation store
 * as owner type 'file': the owner is the file's id, the node the layer's id,
 * the field 'text' for a text layer and 'label' for a sticker. The same words
 * as a page's are the same string, so a translation, the glossary and a
 * review apply to both.
 *
 * A language's picture is kept in file_translation (file_id, language):
 * state 0 waiting, 1 drawn, 2 out of date. Only a drawn row is shown to a
 * visitor. A save of the source and a changed translation of one of its
 * texts turn a drawn row out of date. A deleted, binned or flattened picture
 * loses its texts and rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Pure rules
   --------------------------------------------------------------------------- */

/**
 * The layer fields that hold words, by layer type.
 *
 * @return array type => field
 */
function pg_picture_tr_fields()
{
    return array('text' => 'text', 'sticker' => 'label');
}

/**
 * The words of a document's layers that enter the translation, bottom layer
 * first: a visible text layer's text and a sticker's label, normalized as a
 * page's plain text is, without what pg_tr_translatable() rejects (no
 * letter, a code, a token). Only doc.layers is read: the picture a still
 * save draws. Works on the array and the object decode alike. Pure.
 *
 * @param mixed $doc the document, or null for a flat version
 * @return array of array(node_id, field, text, hash, position)
 */
function pg_picture_tr_doc_words($doc)
{
    pg_tr_load();

    if (is_object($doc)) {
        $doc = json_decode(json_encode($doc), true);
    }

    $layers = (is_array($doc) && isset($doc['layers']) && is_array($doc['layers'])) ? $doc['layers'] : array();
    $fields = pg_picture_tr_fields();
    $words = array();

    foreach ($layers as $layer) {
        if (!is_array($layer) || !isset($layer['type']) || !is_string($layer['type']) || !isset($fields[$layer['type']])) {
            continue;
        }

        $field = $fields[$layer['type']];
        $id = isset($layer['id']) ? $layer['id'] : '';

        if (!empty($layer['hidden']) || !is_string($id) || ($id === '') || (strlen($id) > 64) || !isset($layer[$field]) || !is_string($layer[$field])) {
            continue;
        }

        $text = pg_tr_normalize($layer[$field], 'text');

        if (($text === '') || !pg_tr_translatable($text)) {
            continue;
        }

        $words[] = array(
            'node_id'  => $id,
            'field'    => $field,
            'text'     => $text,
            'hash'     => pg_tr_hash($text, 'text'),
            'position' => count($words),
        );
    }

    return $words;
}

/**
 * The document of a version row, or null for a base row (pixels with no
 * document) or one that does not decode. Pure.
 *
 * @param array $row a file_versions row with doc_json
 * @return array|null
 */
function pg_picture_tr_row_doc($row)
{
    $json = (is_array($row) && isset($row['doc_json'])) ? (string) $row['doc_json'] : '';

    if ($json === '') {
        return null;
    }

    $doc = json_decode($json, true);

    return is_array($doc) ? $doc : null;
}

/**
 * Why a file's texts and rows are dropped, or '' when they stay. Pure.
 *
 * @param bool $exists the files row is there
 * @param bool $binned its folder is the Recycle Bin or below it
 * @param bool $live   its newest version holds a document and the file still
 *                     holds that version's pixels
 * @return string '' | deleted | binned | changed
 */
function pg_picture_tr_prune_reason($exists, $binned, $live)
{
    if (!$exists) {
        return 'deleted';
    }

    if ($binned) {
        return 'binned';
    }

    return $live ? '' : 'changed';
}

/**
 * A language row's state after an event. 0 waiting, 1 drawn, 2 out of date;
 * a save of the source or a changed translation turns a drawn row out of
 * date, a draw makes any row drawn. Pure.
 *
 * @param int    $state
 * @param string $event save | translation | draw
 * @return int
 */
function pg_picture_tr_state_after($state, $event)
{
    $state = in_array((int) $state, array(0, 1, 2), true) ? (int) $state : 0;

    if ($event === 'draw') {
        return 1;
    }

    if ((($event === 'save') || ($event === 'translation')) && ($state === 1)) {
        return 2;
    }

    return $state;
}

/**
 * Whether a visitor is shown a row's picture: only a drawn one. Pure.
 *
 * @param int $state
 * @return bool
 */
function pg_picture_tr_shown($state)
{
    return ((int) $state === 1);
}

/* ---------------------------------------------------------------------------
   The store
   --------------------------------------------------------------------------- */

/**
 * Whether the table is in place (the 2026.11.0 step has run).
 *
 * @return bool
 */
function pg_picture_tr_ready()
{
    return pg_schema_has('file_translation');
}

/**
 * Whether the translation store can take a file's texts.
 *
 * @return bool
 */
function pg_picture_tr_store_ready()
{
    return pg_tr_ready() && pg_schema_has('translation_uses');
}

/**
 * After a save or a restore wrote the file: its texts are the document's
 * now (none for a flat version) and its drawn rows are out of date.
 *
 * @param int   $file_id
 * @param mixed $doc the document kept with the file, null when none was
 */
function pg_picture_tr_saved($file_id, $doc)
{
    $file_id = (int) $file_id;

    if ($file_id <= 0) {
        return;
    }

    if (pg_picture_tr_store_ready()) {
        pg_picture_tr_uses_write($file_id, pg_picture_tr_doc_words($doc));
    }

    if (pg_picture_tr_ready()) {
        db("UPDATE file_translation SET state = 2 WHERE file_id = '$file_id' AND state = 1");
    }
}

/**
 * Writes a file's uses from its words; a file with none loses its uses.
 *
 * @param int   $file_id
 * @param array $words from pg_picture_tr_doc_words()
 */
function pg_picture_tr_uses_write($file_id, $words)
{
    pg_tr_load();

    if (!$words) {
        db("DELETE FROM translation_uses WHERE owner_type = 'file' AND owner_id = '" . (int) $file_id . "'");
        return;
    }

    $segments = array();

    foreach ($words as $word) {
        $string_id = pg_tr_string_upsert($word['text'], 'text', 'content');

        if ($string_id > 0) {
            $segments[] = array('string_id' => $string_id, 'node_id' => $word['node_id'], 'field' => $word['field'], 'position' => $word['position']);
        }
    }

    pg_tr_uses_rewrite('file', (int) $file_id, $segments);
}

/**
 * A file deleted for good, binned or flattened: its uses, its rows and the
 * languages' pictures go. Strings and translations stay, as for a page.
 *
 * @param int $file_id
 */
function pg_picture_tr_forget($file_id)
{
    $file_id = (int) $file_id;

    if ($file_id <= 0) {
        return;
    }

    if (pg_picture_tr_store_ready()) {
        db("DELETE FROM translation_uses WHERE owner_type = 'file' AND owner_id = '$file_id'");
    }

    if (!pg_picture_tr_ready()) {
        return;
    }

    $pictures = db_items("SELECT picture_file_id FROM file_translation WHERE file_id = '$file_id' AND picture_file_id > 0");

    db("DELETE FROM file_translation WHERE file_id = '$file_id'");

    foreach (is_array($pictures) ? $pictures : array() as $row) {
        pg_picture_tr_picture_delete((int) $row['picture_file_id']);
    }
}

/**
 * Deletes a language's picture: its files row, its bytes and its versions.
 *
 * @param int $picture_id
 */
function pg_picture_tr_picture_delete($picture_id)
{
    $name = ($picture_id > 0) ? (string) db_value("SELECT name FROM files WHERE id = '" . (int) $picture_id . "'") : '';

    if ($name === '') {
        return;
    }

    db("DELETE FROM files WHERE id = '" . (int) $picture_id . "'");
    pg_file_versions_delete_file($picture_id);
    @unlink(FILE_DIRECTORY_PATH . '/' . basename($name));
}

/**
 * Before a translation is written: the drawn rows of that language whose
 * picture uses the string go out of date when the text is new or changed.
 *
 * @param int    $string_id
 * @param string $language
 * @param string $text the translation about to be written
 */
function pg_picture_tr_translation_written($string_id, $language, $text)
{
    if (!pg_picture_tr_ready()) {
        return;
    }

    db("UPDATE file_translation ft
        INNER JOIN translation_uses u ON u.owner_type = 'file' AND u.owner_id = ft.file_id
        LEFT JOIN translations t ON t.string_id = u.string_id AND t.language = ft.language
        SET ft.state = 2
        WHERE u.string_id = '" . (int) $string_id . "' AND ft.language = '" . e($language) . "' AND ft.state = 1
          AND (t.id IS NULL OR BINARY t.text <> '" . e($text) . "')");
}

/**
 * After translations of a language were taken away (an emptied box, Clear
 * translations, back to pending over the API): a drawn row whose picture
 * uses a text that has none any more goes out of date.
 *
 * @param string $language
 */
function pg_picture_tr_translations_removed($language)
{
    if (!pg_picture_tr_ready()) {
        return;
    }

    db("UPDATE file_translation ft
        INNER JOIN translation_uses u ON u.owner_type = 'file' AND u.owner_id = ft.file_id
        LEFT JOIN translations t ON t.string_id = u.string_id AND t.language = ft.language
        SET ft.state = 2
        WHERE ft.language = '" . e($language) . "' AND ft.state = 1 AND t.id IS NULL");
}

/**
 * Whether a file's newest version holds a document and the file still holds
 * that version's pixels (not rotated, optimized or uploaded over since).
 *
 * @param int    $file_id
 * @param string $name the file's stored name
 * @return bool
 */
function pg_picture_tr_live($file_id, $name)
{
    $newest = pg_file_versions_ready() ? pg_file_versions_newest($file_id) : null;

    return $newest && (pg_picture_tr_row_doc($newest) !== null)
        && pg_file_versions_matches_file($newest, FILE_DIRECTORY_PATH . '/' . basename((string) $name));
}

/**
 * Drops the texts and rows of every picture that was deleted, binned or
 * changed outside the editor, within a time budget. The daily sweep calls
 * it; a picture not reached is looked at on the next run.
 *
 * @param float $deadline microtime(true) to stop after
 * @return int pictures dropped
 */
function pg_picture_tr_prune($deadline)
{
    $ids = array();

    if (pg_picture_tr_store_ready()) {
        $rows = db_items("SELECT DISTINCT owner_id FROM translation_uses WHERE owner_type = 'file'");

        foreach (is_array($rows) ? $rows : array() as $row) {
            $ids[(int) $row['owner_id']] = true;
        }
    }

    if (pg_picture_tr_ready()) {
        $rows = db_items("SELECT DISTINCT file_id FROM file_translation");

        foreach (is_array($rows) ? $rows : array() as $row) {
            $ids[(int) $row['file_id']] = true;
        }
    }

    $dropped = 0;

    foreach (array_keys($ids) as $file_id) {
        if (microtime(true) > $deadline) {
            break;
        }

        $file = db_item("SELECT name, folder FROM files WHERE id = '" . (int) $file_id . "' LIMIT 1");
        $exists = is_array($file);
        $binned = $exists && pg_folder_binned((int) $file['folder']);
        $live = $exists && !$binned && pg_picture_tr_live($file_id, $file['name']);

        if (pg_picture_tr_prune_reason($exists, $binned, $live) !== '') {
            pg_picture_tr_forget($file_id);
            $dropped++;
        }
    }

    return $dropped;
}
