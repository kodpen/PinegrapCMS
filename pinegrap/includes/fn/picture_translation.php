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
 * A picture starts to wait when a page that shows it is saved or first
 * visited in another language; a signed-in save sends its texts to the
 * languages set to translate on save. A mark in data/temp says, without a
 * query, whether a row may be waiting to be drawn.
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
 * now (none for a flat version) and its drawn rows are out of date. A
 * picture with words that a page shows in another language (it has a row)
 * may be drawn again, so the mark is set, and the save of a signed-in user
 * sends its texts to the languages set to translate on save.
 *
 * @param int   $file_id
 * @param mixed $doc     the document kept with the file, null when none was
 * @param int   $user_id who saved it; 0 opens no translation job
 */
function pg_picture_tr_saved($file_id, $doc, $user_id = 0)
{
    $file_id = (int) $file_id;

    if ($file_id <= 0) {
        return;
    }

    $words = array();

    if (pg_picture_tr_store_ready()) {
        $words = pg_picture_tr_doc_words($doc);
        pg_picture_tr_uses_write($file_id, $words);
    }

    if (!pg_picture_tr_ready()) {
        return;
    }

    db("UPDATE file_translation SET state = 2 WHERE file_id = '$file_id' AND state = 1");

    if ($words && db_value("SELECT 1 FROM file_translation WHERE file_id = '$file_id' LIMIT 1")) {
        pg_picture_tr_mark_set();
        pg_picture_tr_texts_jobs(array($file_id), $user_id);
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
 * A row of the language waiting or out of date may be drawable with it, so
 * the mark is set; while it is set, nothing more is asked.
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

    if (((string) $text !== '') && !pg_picture_tr_marked() && db_value(pg_picture_tr_drawable_use_sql($string_id, $language))) {
        pg_picture_tr_mark_set();
    }
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
 * Whether a file holds its newest version's pixels and that version holds a
 * document, with what it needs handed in. Bytes on this disk are compared
 * with the version. A file whose bytes are only in the object store is not
 * fetched by a pass over many files: the size of the version reads as held,
 * since a door that changes the bytes (rotate, optimize, an upload over it)
 * writes them to the disk first. Pure.
 *
 * @param array|null $newest  the newest file_versions row (size, doc_json)
 * @param string     $path    the file's bytes on this disk, '' when none
 * @param int|null   $stored  the files row's size when its bytes are only in
 *                            the object store, else null
 * @param callable   $matches (row, path) => whether the bytes are the version's
 * @return bool
 */
function pg_picture_tr_holds_version($newest, $path, $stored, $matches)
{
    if (!is_array($newest) || (pg_picture_tr_row_doc($newest) === null)) {
        return false;
    }

    if ((string) $path !== '') {
        return (bool) call_user_func($matches, $newest, (string) $path);
    }

    return ($stored !== null) && isset($newest['size']) && ((int) $stored === (int) $newest['size']);
}

/**
 * Whether a file's newest version holds a document and the file still holds
 * that version's pixels (not rotated, optimized or uploaded over since).
 * Nothing is fetched from the object store: the daily prune asks it of every
 * translated picture.
 *
 * @param int    $file_id
 * @param string $name the file's stored name
 * @return bool
 */
function pg_picture_tr_live($file_id, $name)
{
    $newest = pg_file_versions_ready() ? pg_file_versions_newest($file_id) : null;
    $path = FILE_DIRECTORY_PATH . '/' . basename((string) $name);
    $stored = null;

    // Moved to the object store, the file is no longer on this disk: its
    // cached copy, else its size.
    if ($newest && !is_file($path)) {
        $row = db_item("SELECT id, name, type, size" . (pg_file_storage_ready() ? ', storage, storage_key' : '') . " FROM files WHERE id = '" . (int) $file_id . "' LIMIT 1");
        $path = is_array($row) ? pg_file_present_path($row) : '';
        $stored = (($path === '') && is_array($row) && (pg_file_storage($row) === 's3')) ? (int) $row['size'] : null;
    }

    return pg_picture_tr_holds_version($newest, $path, $stored, 'pg_file_versions_matches_file');
}

/**
 * Drops the texts and rows of every picture that was deleted, binned or
 * changed outside the editor, within a time budget. The daily sweep calls
 * it; each run goes on after the picture the last one stopped at, so every
 * picture is looked at in turn however many there are.
 *
 * @param float $deadline microtime(true) to stop after
 * @return int pictures dropped
 */
function pg_picture_tr_prune($deadline)
{
    // What a door that does not call the module left, first: a picture
    // still in the folder its source moved out of, a row whose picture is
    // gone. Two queries, so a budget spent on the pictures never skips them.
    pg_picture_tr_follow(null);
    $lost = pg_picture_tr_lost();

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

    $cursor_path = pg_picture_tr_prune_cursor_path();
    $after = (int) @file_get_contents($cursor_path);
    $checked = array();
    $through = true;
    $dropped = 0;

    foreach (pg_picture_tr_prune_order(array_keys($ids), $after) as $file_id) {
        if (microtime(true) > $deadline) {
            $through = false;
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

        $checked[] = $file_id;
    }

    if ($ids) {
        @file_put_contents($cursor_path, (string) pg_picture_tr_prune_cursor($checked, $after, $through), LOCK_EX);
    }

    // A page kept by the file cache may name a language's picture just
    // deleted; this runs in the job, which no signed-in request follows.
    if (($dropped > 0) || ($lost > 0)) {
        pg_page_cache_mark_dirty();
    }

    // The mark as the queue finds the rows now: a mark lost with a swept
    // data/temp is set again, one left by rows the prune dropped clears.
    pg_picture_tr_mark_check();

    return $dropped;
}

/**
 * The order the prune looks at the pictures in: those after the one the
 * last run stopped at, then from the start. Pure.
 *
 * @param array $ids   file ids
 * @param int   $after the last id the previous run looked at, 0 for none
 * @return array ids
 */
function pg_picture_tr_prune_order($ids, $after)
{
    $ids = array_values(array_unique(array_map('intval', (array) $ids)));
    sort($ids);

    $later = array();
    $earlier = array();

    foreach ($ids as $id) {
        if ($id > (int) $after) {
            $later[] = $id;
        } else {
            $earlier[] = $id;
        }
    }

    return array_merge($later, $earlier);
}

/**
 * Where the next run starts: from the start after a whole pass, else after
 * the last picture looked at (where it was when none was). Pure.
 *
 * @param array $checked ids looked at, in order
 * @param int   $after   where this run started
 * @param bool  $through every picture was looked at
 * @return int
 */
function pg_picture_tr_prune_cursor($checked, $after, $through)
{
    if ($through) {
        return 0;
    }

    return $checked ? (int) end($checked) : (int) $after;
}

/**
 * The file that keeps where the prune stopped (data/temp, per database).
 *
 * @return string
 */
function pg_picture_tr_prune_cursor_path()
{
    $database = defined('DB_DATABASE') ? DB_DATABASE . '@' . (defined('DB_HOST') ? DB_HOST : '') : '';

    return PG_FUNCTIONS_DIR . '/data/temp/picture_prune_' . md5($database) . '.txt';
}

/* ---------------------------------------------------------------------------
   The file doors
   ---------------------------------------------------------------------------
   A language's picture sits in its source's folder: the page pass joins it
   only there, so get_file.php serves it with the source's rights. A move of
   the source carries it along (pg_picture_tr_follow()), a delete for good
   takes it (pg_picture_tr_forget()), a source in the Recycle Bin is never
   swapped (pg_picture_tr_page_apply()), and the daily sweep puts back what a
   door missed. Disk Usage counts it as any files row. */

/**
 * Where each language's picture goes so it sits in its source's folder:
 * picture id => folder id. A picture already there needs nothing; one whose
 * source is in the Recycle Bin stays where it is, out of the bin's list, and
 * follows when the source is restored. Pure.
 *
 * @param array $rows picture_id, picture_folder, source_folder, binned
 * @return array
 */
function pg_picture_tr_follow_moves($rows)
{
    $moves = array();

    foreach (is_array($rows) ? $rows : array() as $row) {
        $picture = isset($row['picture_id']) ? (int) $row['picture_id'] : 0;
        $source = isset($row['source_folder']) ? (int) $row['source_folder'] : 0;
        $now = isset($row['picture_folder']) ? (int) $row['picture_folder'] : 0;

        if (($picture > 0) && ($source !== $now) && empty($row['binned'])) {
            $moves[$picture] = $source;
        }
    }

    return $moves;
}

/**
 * After a source picture changed folder: its languages' pictures follow it.
 * Called by every door that moves a file; the daily sweep calls it for all.
 *
 * @param array|null $file_ids the moved files; null for every picture out of place
 * @return int pictures moved
 */
function pg_picture_tr_follow($file_ids)
{
    if (!pg_picture_tr_ready()) {
        return 0;
    }

    $where = 'p.folder <> s.folder';

    if ($file_ids !== null) {
        $ids = array_filter(array_map('intval', (array) $file_ids));

        if (!$ids) {
            return 0;
        }

        $where .= " AND ft.file_id IN ('" . implode("','", $ids) . "')";
    }

    $rows = db_items("SELECT ft.picture_file_id AS picture_id, p.folder AS picture_folder, s.folder AS source_folder
        FROM file_translation ft
        INNER JOIN files s ON s.id = ft.file_id
        INNER JOIN files p ON p.id = ft.picture_file_id
        WHERE " . $where);
    $rows = is_array($rows) ? $rows : array();

    foreach ($rows as $i => $row) {
        $rows[$i]['binned'] = pg_folder_binned((int) $row['source_folder']);
    }

    $moves = pg_picture_tr_follow_moves($rows);

    foreach ($moves as $picture_id => $folder_id) {
        db("UPDATE files SET folder = '" . (int) $folder_id . "' WHERE id = '" . (int) $picture_id . "'");
    }

    if ($moves) {
        pg_page_cache_mark_dirty();
    }

    return count($moves);
}

/**
 * Rows whose language picture is gone (deleted with a folder, say) wait to
 * be drawn again instead of naming a file that is not there.
 *
 * @return int rows changed
 */
function pg_picture_tr_lost()
{
    if (!pg_picture_tr_ready()) {
        return 0;
    }

    $done = db("UPDATE file_translation ft
        LEFT JOIN files p ON p.id = ft.picture_file_id
        SET ft.picture_file_id = 0, ft.state = 0
        WHERE ft.picture_file_id > 0 AND p.id IS NULL");

    return $done ? (int) mysqli_affected_rows(db::$con) : 0;
}

/* ---------------------------------------------------------------------------
   The page pass: a language's picture in place of the source's
   ---------------------------------------------------------------------------
   On a page drawn in another language, after the links are rewritten
   (pg_tr_render_finalize()), every address of this site's picture in an
   img's or a source's src and srcset, the share picture (og:image), a link
   to the picture and url() in a style attribute is pointed at the
   language's picture where its row is drawn. A style block, a script (the
   JSON-LD image, the variant data) and any other attribute keep the source.
   One query reads the page's pictures; no document is decoded. */

/**
 * The picture types the pass looks at.
 *
 * @return array extensions
 */
function pg_picture_tr_extensions()
{
    return array('jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif', 'avif');
}

/**
 * The hosts this site answers on, lower-cased, as the link rewriter reads
 * them (pg_tr_rewrite_url()).
 *
 * @return array host => true
 */
function pg_picture_tr_hosts()
{
    $hosts = array();

    foreach (array(defined('HOSTNAME') ? HOSTNAME : '', defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') as $host) {
        $host = strtolower(preg_replace('~:\d+$~', '', trim((string) $host)));

        if ($host !== '') {
            $hosts[$host] = true;
        }
    }

    return $hosts;
}

/**
 * The file name of this site's picture an address names, or '': a path
 * right below the site's path, root-relative or on one of the site's hosts,
 * with a picture's extension; its query (?w=, ?f=) does not count. Pure.
 *
 * @param string $value the attribute as written (entities included)
 * @param string $base  the site's path (PATH)
 * @param array  $hosts host => true
 * @return string
 */
function pg_picture_tr_url_name($value, $base, $hosts)
{
    $url = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if (preg_match('~^(https?:)?//~i', $url)) {
        $parts = parse_url($url);

        if (!is_array($parts) || empty($parts['host']) || !isset($hosts[strtolower($parts['host'])])) {
            return '';
        }

        $path = isset($parts['path']) ? $parts['path'] : '';
    } elseif (($url !== '') && ($url[0] === '/')) {
        $path = preg_replace('~[?#].*$~s', '', $url);
    } else {
        return '';
    }

    $base = (string) $base;

    if (($base === '') || (strpos($path, $base) !== 0)) {
        return '';
    }

    $name = rawurldecode(substr($path, strlen($base)));
    $dot = strrpos($name, '.');

    if (($name === '') || (strpos($name, '/') !== false) || (strpos($name, "\0") !== false) || ($dot === false)
        || !in_array(strtolower(substr($name, $dot + 1)), pg_picture_tr_extensions(), true)) {
        return '';
    }

    return $name;
}

/**
 * One address pointed at the picture $map gives for its name: the host, the
 * query and the quoting as written stay. Pure.
 *
 * @param string $value
 * @param string $base
 * @param array  $hosts
 * @param array  $map   source name => picture name
 * @return string
 */
function pg_picture_tr_url_swap($value, $base, $hosts, $map)
{
    $name = pg_picture_tr_url_name($value, $base, $hosts);

    if (($name === '') || !isset($map[$name]) || !preg_match('~^(\s*(?:(?:https?:)?//[^/?#]+)?)(/[^?#]*)(.*)$~s', (string) $value, $m)) {
        return $value;
    }

    return $m[1] . $base . rawurlencode($map[$name]) . $m[3];
}

/**
 * Walks the places the pass reads, outside script, style, textarea, pre and
 * code blocks: an img's or a source's src and srcset, a link's href, the
 * og:image meta's content and url() in a style attribute. Each address goes
 * through $visit, which answers its new text. Pure.
 *
 * @param string   $html
 * @param callable $visit address as written => address to write
 * @return string
 */
function pg_picture_tr_page_walk($html, $visit)
{
    $parts = preg_split('~(<(script|style|textarea|pre|code)\b[^>]*>.*?</\2>)~is', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    $count = count($parts);

    for ($i = 0; $i < $count; $i++) {
        if (($i % 3) === 2) {
            continue;
        }

        if (($i % 3) === 1) {
            $out .= $parts[$i];
            continue;
        }

        $out .= preg_replace_callback('~<([a-z][a-z0-9-]*)\b([^>]*)>~i', function ($tag) use ($visit) {
            $name = strtolower($tag[1]);

            return '<' . $tag[1] . preg_replace_callback('~(\s)(src|srcset|href|content|style)=("([^"]*)"|\'([^\']*)\')~i', function ($a) use ($name, $tag, $visit) {
                $attr = strtolower($a[2]);
                $quote = $a[3][0];
                $value = ($quote === '"') ? $a[4] : (isset($a[5]) ? $a[5] : '');
                $new = $value;

                if ((($attr === 'src') || ($attr === 'srcset')) && (($name === 'img') || ($name === 'source'))) {
                    $new = ($attr === 'src') ? $visit($value) : preg_replace_callback('~(^|,)(\s*)(\S+)~', function ($c) use ($visit) {
                        return $c[1] . $c[2] . $visit($c[3]);
                    }, $value);
                } elseif (($attr === 'href') && (($name === 'a') || ($name === 'link'))) {
                    $new = $visit($value);
                } elseif (($attr === 'content') && ($name === 'meta') && preg_match('~\sproperty=("|\')og:image(:url|:secure_url)?\1~i', $tag[2])) {
                    $new = $visit($value);
                } elseif ($attr === 'style') {
                    $new = preg_replace_callback('~(url\(\s*)(&quot;|&#0*39;|&apos;|"|\'|)(.*?)(\2)(\s*\))~i', function ($u) use ($visit) {
                        return $u[1] . $u[2] . $visit($u[3]) . $u[4] . $u[5];
                    }, $value);
                }

                return ($new === $value) ? $a[0] : $a[1] . $a[2] . '=' . $quote . $new . $quote;
            }, $tag[2]) . '>';
        }, $parts[$i]);
    }

    return $out;
}

/**
 * The names of this site's pictures a page shows in the places the pass
 * reads, in order, once each. Pure.
 *
 * @param string $html
 * @param string $base
 * @param array  $hosts
 * @return array
 */
function pg_picture_tr_page_names($html, $base, $hosts)
{
    $names = array();

    pg_picture_tr_page_walk($html, function ($value) use ($base, $hosts, &$names) {
        $name = pg_picture_tr_url_name($value, $base, $hosts);

        if ($name !== '') {
            $names[$name] = true;
        }

        return $value;
    });

    return array_keys($names);
}

/**
 * A page with each picture of $map pointed at its language's picture. Pure.
 *
 * @param string $html
 * @param string $base
 * @param array  $hosts
 * @param array  $map source name => picture name
 * @return string
 */
function pg_picture_tr_page_swap($html, $base, $hosts, $map)
{
    if (!$map) {
        return $html;
    }

    return pg_picture_tr_page_walk($html, function ($value) use ($base, $hosts, $map) {
        return pg_picture_tr_url_swap($value, $base, $hosts, $map);
    });
}

/**
 * The one query of a page: each named file with its row in the language,
 * the row's picture when it sits in the source's folder (so it is served by
 * the same rights: a private folder's picture stays private) and whether
 * the file has words in the translation. Pure.
 *
 * @param array  $names
 * @param string $language
 * @return string SQL
 */
function pg_picture_tr_page_sql($names, $language)
{
    $list = implode(',', array_map(function ($name) {
        return "'" . e($name) . "'";
    }, array_values($names)));

    return "SELECT f.id, f.name, f.folder, ft.state, p.name AS picture,
                   (SELECT 1 FROM translation_uses u WHERE u.owner_type = 'file' AND u.owner_id = f.id LIMIT 1) AS has_uses
            FROM files f
            LEFT JOIN file_translation ft ON ft.file_id = f.id AND ft.language = '" . e($language) . "'
            LEFT JOIN files p ON p.id = ft.picture_file_id AND p.folder = f.folder
            WHERE f.name IN (" . $list . ")";
}

/**
 * What a page's rows ask for: the pictures to swap (a drawn row whose
 * picture was found) and the files with words that have no row in the
 * language yet (none for a right-to-left language, which is not drawn).
 * Pure.
 *
 * @param array $rows from pg_picture_tr_page_sql()
 * @param bool  $rtl
 * @return array map (name => picture), waiting (file ids)
 */
function pg_picture_tr_page_plan($rows, $rtl)
{
    $plan = array('map' => array(), 'waiting' => array());

    foreach (is_array($rows) ? $rows : array() as $row) {
        $picture = isset($row['picture']) ? (string) $row['picture'] : '';
        $state = isset($row['state']) ? $row['state'] : null;

        if (($state !== null) && pg_picture_tr_shown($state) && ($picture !== '')) {
            $plan['map'][(string) $row['name']] = $picture;
        } elseif (($state === null) && !empty($row['has_uses']) && !$rtl) {
            $plan['waiting'][] = (int) $row['id'];
        }
    }

    return $plan;
}

/**
 * The pass with what it needs handed in: the page's names, the one query,
 * an insert of the waiting rows on a first visit (and the mark set), the
 * swap.
 *
 * @param string   $html
 * @param string   $language
 * @param string   $base
 * @param array    $hosts
 * @param bool     $rtl
 * @param callable $query  SQL => rows (a SELECT) or bool
 * @param callable|null $binned folder id => whether it is in the Recycle Bin
 * @param callable|null $mark   called once a waiting row was written
 * @return string
 */
function pg_picture_tr_page_apply($html, $language, $base, $hosts, $rtl, $query, $binned = null, $mark = null)
{
    $names = pg_picture_tr_page_names($html, $base, $hosts);

    if (!$names) {
        return $html;
    }

    $rows = $query(pg_picture_tr_page_sql($names, $language));

    // A file inside a binned folder keeps its name (only the binned row is
    // parked), so its address still matches: it is neither swapped nor
    // given a waiting row.
    if ($binned && is_array($rows)) {
        $rows = array_values(array_filter($rows, function ($row) use ($binned) {
            return !$binned(isset($row['folder']) ? (int) $row['folder'] : 0);
        }));
    }

    $plan = pg_picture_tr_page_plan($rows, $rtl);

    if ($plan['waiting']) {
        $now = time();
        $values = array();

        foreach ($plan['waiting'] as $file_id) {
            $values[] = "('" . (int) $file_id . "', '" . e($language) . "', 0, '$now')";
        }

        $query("INSERT IGNORE INTO file_translation (file_id, language, state, created_at) VALUES " . implode(', ', $values));

        if ($mark) {
            $mark();
        }
    }

    return pg_picture_tr_page_swap($html, $base, $hosts, $plan['map']);
}

/**
 * The page pass of a page drawn in a language (pg_tr_render_finalize()).
 *
 * @param string $content
 * @param string $language
 * @return string
 */
function pg_picture_tr_page_pass($content, $language)
{
    if (($language === '') || !defined('PATH') || !pg_picture_tr_ready() || !pg_picture_tr_store_ready()) {
        return $content;
    }

    $known = pg_tr_known_languages();
    $rtl = isset($known[$language]) && ($known[$language][5] === 1);

    return pg_picture_tr_page_apply($content, $language, (string) PATH, pg_picture_tr_hosts(), $rtl, function ($sql) {
        return (strpos($sql, 'SELECT') === 0) ? db_items($sql) : db($sql);
    }, 'pg_folder_binned', 'pg_picture_tr_mark_set');
}

/* ---------------------------------------------------------------------------
   Waiting rows, the texts' jobs and the mark
   ---------------------------------------------------------------------------
   A page saved in the visual editor writes the waiting rows of the pictures
   its own code shows, by the page pass's address rule and one query (a
   shared part, a widget or a menu is left to the first visit). The texts of
   those pictures, and of a picture saved with a row, become a job of scope
   file:<id> for each language set to translate on save, with the lock and
   the open-job guard of a page's (pg_tr_auto_update()); a visit opens none.
   The mark, a file in data/temp, says without a query that a row may be
   waiting to be drawn: set by a waiting row, a save and a translation
   written; cleared when the queue's query finds nothing. */

/**
 * The codes of the languages written left to right among $codes: the only
 * ones a picture is drawn in. A code $known does not list counts as left
 * to right, as pg_picture_tr_rtl() takes it. Pure.
 *
 * @param array $codes
 * @param array $known pg_tr_known_languages()
 * @return array codes
 */
function pg_picture_tr_ltr_languages($codes, $known)
{
    $ltr = array();

    foreach ((array) $codes as $code) {
        $code = (string) $code;

        if (!isset($known[$code][5]) || ((int) $known[$code][5] !== 1)) {
            $ltr[] = $code;
        }
    }

    return $ltr;
}

/**
 * The target languages a picture is drawn in (left to right).
 *
 * @return array codes
 */
function pg_picture_tr_languages()
{
    pg_tr_load();

    return pg_picture_tr_ltr_languages(array_map('strval', array_keys(pg_tr_target_languages())), pg_tr_known_languages());
}

/**
 * The read of a page save: which of the named files have words in the
 * translation, with their folder. No document is decoded. Pure.
 *
 * @param array $names
 * @return string SQL
 */
function pg_picture_tr_save_sql($names)
{
    $list = implode(', ', array_map(function ($name) {
        return "'" . e($name) . "'";
    }, array_values($names)));

    return "SELECT f.id, f.folder FROM files f
            WHERE f.name IN (" . $list . ")
              AND EXISTS (SELECT 1 FROM translation_uses u WHERE u.owner_type = 'file' AND u.owner_id = f.id)";
}

/**
 * The waiting rows of a page save with what they need handed in: the
 * pictures the page's code names, the one read, and one insert of a row per
 * picture with words and language; a row already there stays as it is.
 *
 * @param string        $html      the page's code, its addresses filled in
 * @param array         $languages the codes a picture is drawn in
 * @param string        $base
 * @param array         $hosts
 * @param callable      $query     SQL => rows (a SELECT) or the rows written
 * @param callable|null $binned    folder id => whether it is in the Recycle Bin
 * @return array files (ids of the pictures with words), inserted (rows written)
 */
function pg_picture_tr_save_rows($html, $languages, $base, $hosts, $query, $binned = null)
{
    $saved = array('files' => array(), 'inserted' => 0);
    $names = $languages ? pg_picture_tr_page_names($html, $base, $hosts) : array();

    if (!$names) {
        return $saved;
    }

    $rows = $query(pg_picture_tr_save_sql($names));

    foreach (is_array($rows) ? $rows : array() as $row) {
        if (!$binned || !$binned((int) $row['folder'])) {
            $saved['files'][] = (int) $row['id'];
        }
    }

    if (!$saved['files']) {
        return $saved;
    }

    $now = time();
    $values = array();

    foreach ($saved['files'] as $file_id) {
        foreach ($languages as $language) {
            $values[] = "('" . (int) $file_id . "', '" . e((string) $language) . "', 0, '$now')";
        }
    }

    $saved['inserted'] = (int) $query("INSERT IGNORE INTO file_translation (file_id, language, state, created_at) VALUES " . implode(', ', $values));

    return $saved;
}

/**
 * After a page was saved in the visual editor: the pictures its code shows
 * wait to be drawn in every language a picture is drawn in, and their texts
 * go to the languages set to translate on save.
 *
 * @param string $html    the page's code, its address placeholders filled in
 * @param int    $user_id who saved it; 0 opens no translation job
 */
function pg_picture_tr_page_saved($html, $user_id = 0)
{
    if (!defined('PATH') || !pg_picture_tr_ready() || !pg_picture_tr_store_ready()) {
        return;
    }

    $saved = pg_picture_tr_save_rows($html, pg_picture_tr_languages(), (string) PATH, pg_picture_tr_hosts(), function ($sql) {
        if (strpos($sql, 'SELECT') === 0) {
            return db_items($sql);
        }

        return db($sql) ? (int) mysqli_affected_rows(db::$con) : 0;
    }, 'pg_folder_binned');

    if ($saved['inserted'] > 0) {
        pg_picture_tr_mark_set();
    }

    pg_picture_tr_texts_jobs($saved['files'], $user_id);
}

/**
 * The languages a picture's texts are sent to by themselves: those set to
 * translate on save with an engine that works without the Translations
 * screen (pg_tr_auto_languages()), left to right, as a picture is drawn in
 * no other.
 *
 * @return array code => site_languages row
 */
function pg_picture_tr_auto_languages()
{
    $auto = pg_tr_auto_languages();

    if (!$auto) {
        return array();
    }

    pg_tr_load();

    return array_intersect_key($auto, array_flip(pg_picture_tr_ltr_languages(array_map('strval', array_keys($auto)), pg_tr_known_languages())));
}

/**
 * The read that finds which pictures have a text with no translation in at
 * least one of the languages. Pure.
 *
 * @param array $file_ids
 * @param array $codes
 * @return string SQL, '' without a picture or a language
 */
function pg_picture_tr_pending_files_sql($file_ids, $codes)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $file_ids), function ($id) {
        return $id > 0;
    })));
    $codes = array_values(array_unique(array_map('strval', (array) $codes)));

    if (!$ids || !$codes) {
        return '';
    }

    $list = implode(', ', array_map(function ($code) {
        return "'" . e($code) . "'";
    }, $codes));

    return "SELECT DISTINCT u.owner_id FROM translation_uses u
            WHERE u.owner_type = 'file' AND u.owner_id IN (" . implode(', ', $ids) . ")
              AND (SELECT COUNT(*) FROM translations t WHERE t.string_id = u.string_id AND t.language IN (" . $list . ")) < " . count($codes);
}

/**
 * The texts' jobs of pictures saved, or shown by a page saved, by a
 * signed-in user: for each picture with a text untranslated, a job of scope
 * file:<id> per language set to translate on save, opened as a page's
 * (pg_tr_auto_update(): one at a time under a lock, none while one of the
 * same language and picture is open; no engine is called here). Never from
 * a visit: nobody outside spends the site's engine credit.
 *
 * @param array $file_ids
 * @param int   $user_id
 * @return int jobs opened
 */
function pg_picture_tr_texts_jobs($file_ids, $user_id)
{
    $file_ids = array_values(array_filter(array_map('intval', (array) $file_ids), function ($id) {
        return $id > 0;
    }));

    if (((int) $user_id <= 0) || !$file_ids || !pg_tr_ready() || !pg_picture_tr_store_ready()) {
        return 0;
    }

    $auto = pg_picture_tr_auto_languages();
    $sql = $auto ? pg_picture_tr_pending_files_sql($file_ids, array_keys($auto)) : '';
    $rows = ($sql !== '') ? db_items($sql) : array();
    $opened = 0;

    foreach (is_array($rows) ? $rows : array() as $row) {
        $opened += pg_tr_auto_update($auto, 'file:' . (int) $row['owner_id'], (int) $user_id);
    }

    return $opened;
}

/**
 * The read of a written translation: whether a picture that uses the string
 * has a row of the language waiting or out of date. Pure.
 *
 * @param int    $string_id
 * @param string $language
 * @return string SQL
 */
function pg_picture_tr_drawable_use_sql($string_id, $language)
{
    return "SELECT 1 FROM translation_uses u
            INNER JOIN file_translation ft ON ft.file_id = u.owner_id
            WHERE u.owner_type = 'file' AND u.string_id = '" . (int) $string_id . "'
              AND ft.language = '" . e($language) . "' AND ft.state IN (0, 2)
            LIMIT 1";
}

/**
 * Where the mark is kept: a file of this database in data/temp (sites that
 * share one software directory share data/temp).
 *
 * @return string
 */
function pg_picture_tr_mark_path()
{
    $database = defined('DB_DATABASE') ? DB_DATABASE . '@' . (defined('DB_HOST') ? DB_HOST : '') : '';

    return PG_FUNCTIONS_DIR . '/data/temp/picture_draw_' . md5($database) . '.mark';
}

/**
 * Whether a row may be waiting to be drawn. No query: a look at the mark.
 * Where the mark cannot be kept (no writable folder) it reads as set, so a
 * waiting row is never left unseen.
 *
 * @param string|null $path the mark's file; pg_picture_tr_mark_path() when null
 * @return bool
 */
function pg_picture_tr_marked($path = null)
{
    $path = ($path === null) ? pg_picture_tr_mark_path() : (string) $path;

    // A long run (the scheduled job) sees another request's set or clear.
    clearstatcache(true, $path);

    return is_file($path) || !pg_dir_writable(dirname($path));
}

/**
 * The mark's token: changed by every set, '' while the mark is clear.
 *
 * @param string|null $path
 * @return string
 */
function pg_picture_tr_mark_token($path = null)
{
    $path = ($path === null) ? pg_picture_tr_mark_path() : (string) $path;

    clearstatcache(true, $path);

    return is_file($path) ? trim((string) @file_get_contents($path)) : '';
}

/**
 * Sets the mark with a new token.
 *
 * @param string|null $path
 */
function pg_picture_tr_mark_set($path = null)
{
    $path = ($path === null) ? pg_picture_tr_mark_path() : (string) $path;

    @file_put_contents($path, uniqid('', true) . '.' . mt_rand(), LOCK_EX);
    clearstatcache(true, $path);
}

/**
 * Clears the mark when its token is still $seen, the one read before the
 * queue's query: a mark set again meanwhile stays.
 *
 * @param string      $seen
 * @param string|null $path
 * @return bool cleared
 */
function pg_picture_tr_mark_clear($seen, $path = null)
{
    $path = ($path === null) ? pg_picture_tr_mark_path() : (string) $path;

    if (((string) $seen === '') || (pg_picture_tr_mark_token($path) !== (string) $seen)) {
        return false;
    }

    $cleared = @unlink($path);
    clearstatcache(true, $path);

    return $cleared;
}

/**
 * The mark set or cleared by the queue's query, with what it needs handed
 * in: one row due (waiting or out of date, every text translated) sets it,
 * none clears it.
 *
 * @param array       $languages the codes a picture is drawn in
 * @param callable    $query     SQL => rows
 * @param string|null $path
 * @return bool whether a row is due
 */
function pg_picture_tr_mark_check_with($languages, $query, $path = null)
{
    $seen = pg_picture_tr_mark_token($path);
    $sql = pg_picture_tr_queue_sql($languages, 1);
    $rows = ($sql !== '') ? $query($sql) : array();

    if (is_array($rows) && $rows) {
        if ($seen === '') {
            pg_picture_tr_mark_set($path);
        }

        return true;
    }

    if (is_array($rows)) {
        pg_picture_tr_mark_clear($seen, $path);
    }

    return false;
}

/**
 * The mark set or cleared by the queue's query over every folder.
 *
 * @return bool whether a row is due
 */
function pg_picture_tr_mark_check()
{
    if (!pg_picture_tr_ready() || !pg_picture_tr_store_ready()) {
        return false;
    }

    return pg_picture_tr_mark_check_with(pg_picture_tr_languages(), 'db_items');
}

/* ---------------------------------------------------------------------------
   Drawing a language's picture
   ---------------------------------------------------------------------------
   The Design Canvas's Languages list, for a canvas file with words: each
   target language with its state and its words. The browser puts a
   language's words into the layers, fits each text to its source's box and
   draws the picture with the save's own renderer; the draw action keeps it
   as the language's files row beside the source, sets the row drawn and
   marks the page cache. The language's picture is listed nowhere: not in
   the File Manager, the file pickers or Where used. */

/**
 * The list's state of a language: Drawn, Look by hand (a text drawn at the
 * smallest size still did not fit) or Out of date from its row, otherwise
 * Translated when every text has a translation. Pure.
 *
 * @param array|null $row        the file_translation row (state, fit), or null
 * @param int        $total      the picture's texts
 * @param int        $translated those with a translation in the language
 * @return string not_translated | translated | drawn | look | out_of_date
 */
function pg_picture_tr_list_state($row, $total, $translated)
{
    $state = is_array($row) ? (int) $row['state'] : 0;

    if ($state === 1) {
        return empty($row['fit']) ? 'drawn' : 'look';
    }

    if ($state === 2) {
        return 'out_of_date';
    }

    return (($total > 0) && ($translated >= $total)) ? 'translated' : 'not_translated';
}

/**
 * The stamp a draw is posted with: the file, the language, the document
 * drawn from and the words given. A picture of another file or language, or
 * drawn from words or a document that changed since, carries another. Pure.
 *
 * @param int    $file_id
 * @param string $language
 * @param string $doc_hash
 * @param array  $texts pg_picture_tr_language_texts()
 * @return string SHA-1
 */
function pg_picture_tr_stamp($file_id, $language, $doc_hash, $texts)
{
    $lines = array();

    foreach ($texts as $text) {
        $lines[] = $text['id'] . "\x1f" . $text['field'] . "\x1f" . (($text['text'] === null) ? "\x00" : (string) $text['text']);
    }

    return sha1((int) $file_id . "\x1e" . $language . "\x1e" . $doc_hash . "\x1e" . implode("\x1e", $lines));
}

/**
 * Why a draw is refused, or '' when it is taken. Pure.
 *
 * @param array $facts access (the folder may be edited), language (a target
 *                     language), rtl, live (the file holds its canvas
 *                     document), missing (texts with no translation in the
 *                     language), sent (the stamp posted), stamp (now)
 * @return string '' | access | language | rtl | changed | untranslated | stamp
 */
function pg_picture_tr_draw_refusal($facts)
{
    if (empty($facts['access'])) {
        return 'access';
    }

    if (empty($facts['language'])) {
        return 'language';
    }

    if (!empty($facts['rtl'])) {
        return 'rtl';
    }

    if (empty($facts['live'])) {
        return 'changed';
    }

    // A language is drawn only once every text is translated into it.
    if (!empty($facts['missing'])) {
        return 'untranslated';
    }

    $sent = isset($facts['sent']) ? (string) $facts['sent'] : '';

    return (($sent !== '') && hash_equals((string) $facts['stamp'], $sent)) ? '' : 'stamp';
}

/**
 * How many of a language's texts have no translation (none or empty). Pure.
 *
 * @param array $texts pg_picture_tr_language_texts()
 * @return int
 */
function pg_picture_tr_untranslated($texts)
{
    $missing = 0;

    foreach ($texts as $text) {
        if (($text['text'] === null) || ($text['text'] === '')) {
            $missing++;
        }
    }

    return $missing;
}

/**
 * The name a language's picture is first given: its source's stem and the
 * language, in the picture's format (a canvas is a PNG, a photo keeps its
 * own or its export's). Made unique by the caller; found only through
 * file_translation, never by this name. Pure.
 *
 * @param string $source    the source's name
 * @param string $language
 * @param string $extension
 * @return string
 */
function pg_picture_tr_picture_name($source, $language, $extension = 'png')
{
    $stem = preg_replace('/\.[a-z0-9]{2,5}$/i', '', (string) $source);

    return $stem . '-' . strtolower(preg_replace('/[^A-Za-z0-9-]/', '', (string) $language)) . '.' . strtolower((string) $extension);
}

/**
 * The condition that leaves the languages' pictures out of a files list.
 *
 * @param string    $column the files id column of the query
 * @param bool|null $ready  whether the table is there; null asks the schema
 * @return string ' AND (…)' or ''
 */
function pg_picture_tr_hidden_sql($column, $ready = null)
{
    if ($ready === null) {
        $ready = pg_picture_tr_ready();
    }

    return $ready ? ' AND (' . $column . ' NOT IN (SELECT picture_file_id FROM file_translation))' : '';
}

/**
 * Whether a file is a language's picture.
 *
 * @param int $file_id
 * @return bool
 */
function pg_picture_tr_is_picture($file_id)
{
    return ((int) $file_id > 0) && pg_picture_tr_ready()
        && (bool) db_value("SELECT 1 FROM file_translation WHERE picture_file_id = '" . (int) $file_id . "' LIMIT 1");
}

/**
 * Which kind of document a live version holds: 'canvas' (an artboard, no
 * source picture), 'photo' (a source picture with layers over it) or ''.
 * Works on the array and the object decode alike. Pure.
 *
 * @param mixed $doc
 * @return string canvas | photo | ''
 */
function pg_picture_tr_doc_kind($doc)
{
    if (is_object($doc)) {
        $doc = json_decode(json_encode($doc), true);
    }

    if (!is_array($doc) || ((isset($doc['v']) ? $doc['v'] : null) !== 1)) {
        return '';
    }

    $board = !empty($doc['artboard']);
    $source = isset($doc['source']) && is_array($doc['source']) && $doc['source'];

    if ($board && !$source) {
        return pg_image_editor_canvas_is_doc($doc) ? 'canvas' : '';
    }

    return ($source && !$board) ? 'photo' : '';
}

/**
 * A picture's live document, its hash and its kind: the newest version's,
 * while the file still holds its pixels. A photo's also names the base row
 * the document is drawn on, which must still be there; null for any other
 * picture.
 *
 * @param int    $file_id
 * @param string $name the file's stored name
 * @return array|null doc (decoded as objects), hash, kind, base (the base
 *                    row of a photo, null for a canvas)
 */
function pg_picture_tr_live_doc($file_id, $name)
{
    $newest = pg_file_versions_ready() ? pg_file_versions_newest((int) $file_id) : null;
    $json = $newest ? (string) $newest['doc_json'] : '';
    $path = FILE_DIRECTORY_PATH . '/' . basename((string) $name);

    // A file moved to the object store is read through its cached copy, as
    // the Design Canvas opens it (pg_image_editor_file()).
    if (($json !== '') && !is_file($path)) {
        $path = pg_file_local_copy((int) $file_id);
    }

    if (($json === '') || ($path === '') || !pg_file_versions_matches_file($newest, $path)) {
        return null;
    }

    $doc = json_decode($json);
    $kind = is_object($doc) ? pg_picture_tr_doc_kind($doc) : '';
    $base = null;

    if ($kind === '') {
        return null;
    }

    // A photo is drawn on its base row, as the editor opens it; a base the
    // retention removed leaves nothing to draw on.
    if ($kind === 'photo') {
        $base_no = pg_file_versions_base_no($newest);
        $base = ($base_no > 0) ? db_item("SELECT * FROM file_versions WHERE file_id = '" . (int) $file_id . "' AND version_no = '" . (int) $base_no . "' AND doc_json = '' LIMIT 1") : null;

        if (!$base || !is_file(pg_file_versions_path($base))) {
            return null;
        }
    }

    $hash = isset($newest['doc_hash']) ? (string) $newest['doc_hash'] : '';

    return array('doc' => $doc, 'hash' => ($hash !== '') ? $hash : sha1($json), 'kind' => $kind, 'base' => $base);
}

/**
 * A file's texts in a language, in layer order: the layer's id, the field
 * and the translation, null where there is none.
 *
 * @param int    $file_id
 * @param string $language
 * @return array of array(id, field, text)
 */
function pg_picture_tr_language_texts($file_id, $language)
{
    $rows = db_items("SELECT u.node_id, u.field, t.text
        FROM translation_uses u
        LEFT JOIN translations t ON t.string_id = u.string_id AND t.language = '" . e($language) . "'
        WHERE u.owner_type = 'file' AND u.owner_id = '" . (int) $file_id . "'
        ORDER BY u.position, u.node_id");
    $texts = array();

    foreach (is_array($rows) ? $rows : array() as $row) {
        $texts[] = array('id' => (string) $row['node_id'], 'field' => (string) $row['field'], 'text' => ($row['text'] === null) ? null : (string) $row['text']);
    }

    return $texts;
}

/**
 * Whether a language is written right to left; its picture is not drawn.
 *
 * @param string $language
 * @return bool
 */
function pg_picture_tr_rtl($language)
{
    pg_tr_load();

    $known = pg_tr_known_languages();

    return isset($known[$language]) && ((int) $known[$language][5] === 1);
}

/**
 * Action picture_languages of the Design Canvas: the Languages list of a
 * canvas file. words is 0 for a file with none (or before translation is
 * set up), and the list is then left closed.
 *
 *   { file_id } -> { status, words, hash, doc, languages: [{ code, name,
 *   rtl, state, queued, total, translated, texts, stamp, url }] }
 *
 * queued: the language has a row that is not drawn (waiting or out of
 * date), so a page has shown the picture in it: the Design Canvas draws such
 * a language again right after a Save; one without a row is never drawn by
 * itself.
 *
 * @return array array(payload, HTTP status)
 */
function pg_picture_tr_languages_answer($request, $user)
{
    $file = pg_image_editor_file(isset($request['file_id']) ? $request['file_id'] : 0, $user);

    if ($file['refusal']) {
        return $file['refusal'];
    }

    $none = array(array('status' => 'success', 'words' => 0, 'languages' => array()), 200);
    $file_id = (int) $file['row']['id'];

    if (!pg_picture_tr_ready() || !pg_picture_tr_store_ready()) {
        return $none;
    }

    // The language helpers (target languages, labels) load with the store.
    pg_tr_load();

    $canvas = pg_picture_tr_live_doc($file_id, $file['row']['name']);
    $targets = pg_tr_target_languages();
    $words = $canvas ? (int) db_value("SELECT COUNT(*) FROM translation_uses WHERE owner_type = 'file' AND owner_id = '$file_id'") : 0;

    if (!$words || !$targets) {
        return $none;
    }

    $rows = array();

    $found = db_items("SELECT ft.language, ft.state, ft.fit, ft.drawn_at, f.name
        FROM file_translation ft LEFT JOIN files f ON f.id = ft.picture_file_id
        WHERE ft.file_id = '$file_id'");

    foreach (is_array($found) ? $found : array() as $row) {
        $rows[(string) $row['language']] = $row;
    }

    $languages = array();

    foreach (array_keys($targets) as $code) {
        $code = (string) $code;
        $texts = pg_picture_tr_language_texts($file_id, $code);
        $translated = count($texts) - pg_picture_tr_untranslated($texts);
        $row = isset($rows[$code]) ? $rows[$code] : null;
        $picture = ($row && ((string) $row['name'] !== '')) ? (string) $row['name'] : '';

        $languages[] = array(
            'code'       => $code,
            'name'       => pg_tr_language_label($code),
            'rtl'        => pg_picture_tr_rtl($code),
            'state'      => pg_picture_tr_list_state($row, count($texts), $translated),
            'queued'     => ($row !== null) && ((int) $row['state'] !== 1),
            'total'      => count($texts),
            'translated' => $translated,
            'texts'      => $texts,
            'stamp'      => pg_picture_tr_stamp($file_id, $code, $canvas['hash'], $texts),
            'url'        => ($picture !== '') ? PATH . encode_url_path($picture) . '?v=' . (int) $row['drawn_at'] : '');
    }

    // A photo's document is drawn on its base row (action picture).
    return array(array('status' => 'success', 'words' => $words, 'hash' => $canvas['hash'], 'kind' => $canvas['kind'], 'doc' => $canvas['doc'],
        'base' => $canvas['base'] ? (int) $canvas['base']['version_id'] : 0, 'name' => (string) $file['row']['name'], 'languages' => $languages), 200);
}

/**
 * Action picture_language_draw of the Design Canvas: a language's picture
 * drawn in the browser, kept as its own files row in the source's folder
 * (written over on a later draw, its id and name kept), the row set drawn
 * (fit 1 when a text did not fit at the smallest size) and the page cache
 * marked.
 *
 *   { file_id, language, stamp, fit, picture } -> { status, state, url }
 *
 * @return array array(payload, HTTP status)
 */
function pg_picture_tr_draw_answer($request, $user)
{
    if (!pg_picture_tr_ready() || !pg_picture_tr_store_ready()) {
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    pg_tr_load();

    // The folder's edit right, the Recycle Bin and an ERP document, as for
    // a save of the picture itself.
    $file = pg_image_editor_file(isset($request['file_id']) ? $request['file_id'] : 0, $user);

    if ($file['refusal']) {
        return $file['refusal'];
    }

    $file_id = (int) $file['row']['id'];
    $language = isset($request['language']) ? (string) $request['language'] : '';
    $targets = pg_tr_target_languages();
    $canvas = pg_picture_tr_live_doc($file_id, $file['row']['name']);
    $texts = ($canvas && isset($targets[$language])) ? pg_picture_tr_language_texts($file_id, $language) : array();
    $why = pg_picture_tr_draw_refusal(array(
        'access'   => true,
        'language' => ($language !== '') && isset($targets[$language]),
        'rtl'      => pg_picture_tr_rtl($language),
        'live'     => (bool) $canvas,
        'missing'  => pg_picture_tr_untranslated($texts),
        'sent'     => isset($request['stamp']) ? (string) $request['stamp'] : '',
        'stamp'    => $canvas ? pg_picture_tr_stamp($file_id, $language, $canvas['hash'], $texts) : ''));

    if (($why === 'changed') || ($why === 'stamp')) {
        return pg_image_editor_refusal(lang('This picture or its translations changed meanwhile. Open the Languages list again.'), 409);
    }

    if ($why === 'untranslated') {
        return pg_image_editor_refusal(lang('Every text of the picture must be translated into this language before it is drawn.'), 409);
    }

    if ($why !== '') {
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    // The picture's bytes: a canvas is the PNG drawn in the browser, a photo
    // is drawn on its base by the save's own steps.
    $drawn = ($canvas['kind'] === 'photo') ? pg_picture_tr_draw_photo($request, $file, $canvas) : pg_picture_tr_draw_canvas($request, $canvas);

    if ($drawn['refusal']) {
        return $drawn['refusal'];
    }

    $fit = empty($request['fit']) ? 0 : 1;
    $kept = pg_picture_tr_keep($file['row'], $language, $drawn, $fit, (string) $canvas['hash'], (int) $user['id']);

    if ($kept[0]['status'] !== 'success') {
        return $kept;
    }

    return array(array(
        'status' => 'success',
        'state'  => pg_picture_tr_list_state(array('state' => pg_picture_tr_state_after(0, 'draw'), 'fit' => $fit), count($texts), count($texts) - pg_picture_tr_untranslated($texts)),
        'url'    => PATH . encode_url_path($kept[0]['name']) . '?v=' . $kept[0]['drawn_at']), 200);
}

/**
 * The upsert that sets a language's row drawn: its picture, the fit mark,
 * the document drawn from, when and by whom (0 for the server). Pure.
 *
 * @param int    $file_id
 * @param string $language
 * @param int    $picture_id the language's files row
 * @param int    $fit        1 when a text did not fit at the smallest size
 * @param string $doc_hash
 * @param int    $now
 * @param int    $user_id
 * @return array array(sql, params) for db_q()
 */
function pg_picture_tr_keep_query($file_id, $language, $picture_id, $fit, $doc_hash, $now, $user_id)
{
    return array("INSERT INTO file_translation (file_id, language, picture_file_id, state, fit, doc_hash, created_at, drawn_at, drawn_by)
        VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE picture_file_id = VALUES(picture_file_id), state = 1, fit = VALUES(fit), doc_hash = VALUES(doc_hash),
            drawn_at = VALUES(drawn_at), drawn_by = VALUES(drawn_by)",
        array((int) $file_id, (string) $language, (int) $picture_id, empty($fit) ? 0 : 1, (string) $doc_hash, (int) $now, (int) $now, (int) $user_id));
}

/**
 * Keeps a language's picture drawn from a file's live document, by a
 * browser (the user's id) or by the server (0): its files row in the
 * source's folder (a later draw writes over it, so its address stays; one
 * in another format is replaced by a new file), the row set drawn, the
 * page cache marked.
 *
 * @param array  $source   the source's files row (id, name, folder)
 * @param string $language
 * @param array  $drawn    path (the picture in a temporary file), extension
 * @param int    $fit      1 when a text did not fit at the smallest size
 * @param string $doc_hash the live document drawn from
 * @param int    $user_id
 * @return array array(payload, HTTP status); the payload names the picture
 *               (id, name) and drawn_at
 */
function pg_picture_tr_keep($source, $language, $drawn, $fit, $doc_hash, $user_id)
{
    // Two draws of one picture and language at once (a panel tab and the
    // server's job) would each find no picture yet and write a files row of
    // their own, the first left visible in Files. Under the lock the second
    // finds the first's row and writes over it. A server that answers no
    // lock (null) still keeps the picture, without that protection.
    $lock = pg_picture_tr_keep_lock_sql((int) $source['id'], (string) $language);
    $got = db_value("SELECT GET_LOCK(" . $lock . ", 10)");

    if (($got !== null) && ((int) $got !== 1)) {
        @unlink($drawn['path']);
        return pg_image_editor_refusal(lang('Sorry, the file could not be saved.'));
    }

    $kept = pg_picture_tr_keep_write($source, $language, $drawn, $fit, $doc_hash, $user_id);

    if ($got !== null) {
        db_value("SELECT RELEASE_LOCK(" . $lock . ")");
    }

    return $kept;
}

/**
 * The name of the lock a language's picture is kept under, as SQL. GET_LOCK
 * names belong to the whole database server, so the database is part of
 * the name; hashed, so the name stays under 64 characters and the language
 * is never quoted into the statement. Pure.
 *
 * @param int    $file_id
 * @param string $language
 * @return string an SQL expression
 */
function pg_picture_tr_keep_lock_sql($file_id, $language)
{
    return "CONCAT('pg_picture_keep_', MD5(CONCAT(DATABASE(), ':', '" . md5((int) $file_id . '|' . (string) $language) . "')))";
}

/**
 * The keep's writes, under its lock (pg_picture_tr_keep()).
 *
 * @return array array(payload, HTTP status)
 */
function pg_picture_tr_keep_write($source, $language, $drawn, $fit, $doc_hash, $user_id)
{
    $file_id = (int) $source['id'];
    $now = time();

    $old = db_item("SELECT f.id, f.name, f.folder FROM file_translation ft INNER JOIN files f ON f.id = ft.picture_file_id
        WHERE ft.file_id = '$file_id' AND ft.language = '" . e($language) . "' LIMIT 1");
    $gone = ($old && (strtolower(pathinfo((string) $old['name'], PATHINFO_EXTENSION)) !== $drawn['extension'])) ? (int) $old['id'] : 0;

    if ($gone) {
        $old = null;
    }

    $folder = $old ? (int) $old['folder'] : (int) $source['folder'];
    $name = $old ? (string) $old['name']
        : get_unique_name(array('name' => prepare_file_name(pg_picture_tr_picture_name($source['name'], $language, $drawn['extension'])), 'type' => 'file'));

    if (pg_upload_name_blocked($name)) {
        @unlink($drawn['path']);
        return pg_image_editor_refusal(pg_upload_blocked_message($name));
    }

    if (!@rename($drawn['path'], FILE_DIRECTORY_PATH . '/' . $name)) {
        @unlink($drawn['path']);
        return pg_image_editor_refusal(lang('Sorry, the file could not be saved.'));
    }

    @clearstatcache(true, FILE_DIRECTORY_PATH . '/' . $name);
    $record = pg_image_editor_record($old ? 'replace' : 'new', $name, $folder, $drawn['extension'], $old, array('id' => (int) $user_id));

    if ($record[0]['status'] !== 'success') {
        return $record;
    }

    list($sql, $params) = pg_picture_tr_keep_query($file_id, $language, (int) $record[0]['id'], $fit, $doc_hash, $now, $user_id);
    db_q($sql, $params);

    if ($gone) {
        pg_picture_tr_picture_delete($gone);
    }

    // A page in this language shows the new picture from the next request.
    pg_page_cache_mark_dirty();

    return array(array('status' => 'success', 'id' => (int) $record[0]['id'], 'name' => $name, 'drawn_at' => $now), 200);
}

/**
 * A canvas's picture for a language: the browser's PNG of the artboard's
 * size, recompressed when that makes it smaller, in a temporary file.
 *
 * @param array $request picture
 * @param array $live    pg_picture_tr_live_doc()
 * @return array refusal (or null), path, extension
 */
function pg_picture_tr_draw_canvas($request, $live)
{
    $board = $live['doc']->artboard;
    $width = (int) round(max(16, min(8000, (float) $board->width)));
    $height = (int) round(max(16, min(8000, (float) $board->height)));
    $path = FILE_DIRECTORY_PATH . '/picture-language.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.' . mt_rand() . '.tmp';

    if (!pg_image_editor_take_png(isset($request['picture']) ? $request['picture'] : '', $path, $width, $height)) {
        return array('refusal' => pg_image_editor_refusal(lang('Invalid request.')), 'path' => '', 'extension' => '');
    }

    pg_process_image_file($path);

    return array('refusal' => null, 'path' => $path, 'extension' => 'png');
}

/**
 * A photo's picture for a language, drawn as a save draws it: the recipe
 * from the live document, the pixels of the base row that document names,
 * and the parts the browser drew from it with the language's words (the
 * overlay; the canvas around the picture, the masks and the colour layer
 * are the document's own). The first frame of an animated photo; the
 * photo's format, or the export's (a JPEG with a see-through erase is a
 * PNG). The bytes in a temporary file.
 *
 * @param array $request overlay, extend, redact, masks, colour, export
 * @param array $file    pg_image_editor_file()
 * @param array $live    pg_picture_tr_live_doc() of kind photo
 * @return array refusal (or null), path, extension
 */
function pg_picture_tr_draw_photo($request, $file, $live)
{
    $fail = function ($refusal) {
        return array('refusal' => $refusal, 'path' => '', 'extension' => '');
    };
    $check = pg_image_edit_validate(json_decode(json_encode($live['doc']), true));

    if (($check['error'] !== '') || $check['artboard']) {
        return $fail(pg_image_editor_refusal(lang('Invalid request.')));
    }

    $export = pg_image_editor_export_options(isset($request['export']) ? $request['export'] : null, pg_image_editor_export_format_of($file['type']), pg_image_editor_export_formats());

    if ($export === false) {
        return $fail(pg_image_editor_refusal(lang('Invalid request.')));
    }

    @set_time_limit(120);

    $edit = $check['edit'];
    $edit['first_frame'] = true;
    $parts = pg_image_editor_photo_parts($edit, $request, (string) $file['row']['name']);

    if ($parts === false) {
        return $fail(pg_image_editor_refusal(lang('Invalid request.')));
    }

    list($edit, $parts) = $parts;
    $pixels = pg_image_editor_photo_pixels($edit, $parts, $request, $file, pg_file_versions_path($live['base']), $export);

    if ($pixels['refusal']) {
        return $fail($pixels['refusal']);
    }

    $name = (string) $file['row']['name'];
    $extension = strtolower(pathinfo(($pixels['export']['format'] !== '') ? pg_image_editor_export_name($name, $pixels['export']['format']) : $name, PATHINFO_EXTENSION));
    $path = FILE_DIRECTORY_PATH . '/picture-language.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.' . mt_rand() . '.tmp';

    if (@file_put_contents($path, $pixels['data']) === false) {
        @unlink($path);
        return $fail(pg_image_editor_refusal(lang('Sorry, the file could not be saved.')));
    }

    return array('refusal' => null, 'path' => $path, 'extension' => $extension);
}

/* ---------------------------------------------------------------------------
   The queue: the waiting pictures drawn one after another
   ---------------------------------------------------------------------------
   "Draw the waiting pictures" on the Pictures group of the Translations
   screen opens a view (translations.php?group=pictures&draw=1) that draws
   each language row waiting or out of date, whose every text is
   translated, in this browser with the Design Canvas's own drawing
   (assets/js/picture_queue.js). Nothing is drawn with nobody there. */

/**
 * The queue's query: the language rows waiting (0) or out of date (2) of
 * the given languages, whose picture has texts and every one of them
 * translated into the row's language, with the picture's name and folder.
 * A code other than letters, digits and hyphens is dropped. Pure.
 *
 * @param array $languages the target languages that are drawn
 * @param int   $limit
 * @return string SQL, or '' without a language
 */
function pg_picture_tr_queue_sql($languages, $limit)
{
    $codes = array();

    foreach ((array) $languages as $code) {
        if (is_string($code) && preg_match('/^[A-Za-z0-9-]{1,10}$/', $code)) {
            $codes[] = "'" . $code . "'";
        }
    }

    if (!$codes) {
        return '';
    }

    return "SELECT ft.file_id, ft.language, ft.state, f.name, f.folder
        FROM file_translation ft
        INNER JOIN files f ON f.id = ft.file_id
        WHERE ft.state IN (0, 2) AND ft.language IN (" . implode(', ', $codes) . ")
          AND EXISTS (SELECT 1 FROM translation_uses u WHERE u.owner_type = 'file' AND u.owner_id = ft.file_id)
          AND NOT EXISTS (SELECT 1 FROM translation_uses u
              LEFT JOIN translations t ON t.string_id = u.string_id AND t.language = ft.language
              WHERE u.owner_type = 'file' AND u.owner_id = ft.file_id AND (t.id IS NULL OR t.text = ''))
        ORDER BY f.name, ft.language
        LIMIT " . max(1, (int) $limit);
}

/**
 * The pictures waiting to be drawn that this user may draw: the queue's
 * rows of the target languages written left to right, in a folder the
 * user may edit and not in the Recycle Bin. A query that finds no row in
 * any folder clears the mark.
 *
 * @param int $limit
 * @return array of array(file_id, name, language, label, state)
 */
function pg_picture_tr_queue($limit = 200)
{
    if (!pg_picture_tr_ready() || !pg_picture_tr_store_ready()) {
        return array();
    }

    pg_tr_load();

    $seen = pg_picture_tr_mark_token();
    $sql = pg_picture_tr_queue_sql(pg_picture_tr_languages(), $limit);
    $rows = ($sql !== '') ? db_items($sql) : array();
    $items = array();
    $folders = array();

    if (is_array($rows) && !$rows) {
        pg_picture_tr_mark_clear($seen);
    }

    foreach (is_array($rows) ? $rows : array() as $row) {
        $folder = (int) $row['folder'];

        if (!isset($folders[$folder])) {
            $folders[$folder] = !pg_folder_binned($folder) && check_edit_access($folder);
        }

        if ($folders[$folder]) {
            $items[] = array(
                'file_id'  => (int) $row['file_id'],
                'name'     => (string) $row['name'],
                'language' => (string) $row['language'],
                'label'    => pg_tr_language_label((string) $row['language']),
                'state'    => (int) $row['state']);
        }
    }

    return $items;
}
