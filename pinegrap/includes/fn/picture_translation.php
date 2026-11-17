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

    return "SELECT f.id, f.name, ft.state, p.name AS picture,
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
 * an insert of the waiting rows on a first visit, the swap.
 *
 * @param string   $html
 * @param string   $language
 * @param string   $base
 * @param array    $hosts
 * @param bool     $rtl
 * @param callable $query SQL => rows (a SELECT) or bool
 * @return string
 */
function pg_picture_tr_page_apply($html, $language, $base, $hosts, $rtl, $query)
{
    $names = pg_picture_tr_page_names($html, $base, $hosts);

    if (!$names) {
        return $html;
    }

    $plan = pg_picture_tr_page_plan($query(pg_picture_tr_page_sql($names, $language)), $rtl);

    if ($plan['waiting']) {
        $now = time();
        $values = array();

        foreach ($plan['waiting'] as $file_id) {
            $values[] = "('" . (int) $file_id . "', '" . e($language) . "', 0, '$now')";
        }

        $query("INSERT IGNORE INTO file_translation (file_id, language, state, created_at) VALUES " . implode(', ', $values));
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
    });
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
 *                     document), sent (the stamp posted), stamp (now)
 * @return string '' | access | language | rtl | changed | stamp
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

    $sent = isset($facts['sent']) ? (string) $facts['sent'] : '';

    return (($sent !== '') && hash_equals((string) $facts['stamp'], $sent)) ? '' : 'stamp';
}

/**
 * The name a language's picture is first given: its source's stem and the
 * language, as a PNG. Made unique by the caller; found only through
 * file_translation, never by this name. Pure.
 *
 * @param string $source the source's name
 * @param string $language
 * @return string
 */
function pg_picture_tr_picture_name($source, $language)
{
    $stem = preg_replace('/\.[a-z0-9]{2,5}$/i', '', (string) $source);

    return $stem . '-' . strtolower(preg_replace('/[^A-Za-z0-9-]/', '', (string) $language)) . '.png';
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
 * A canvas file's live document and its hash: the newest version's, while
 * the file still holds its pixels; null for any other picture.
 *
 * @param int    $file_id
 * @param string $name the file's stored name
 * @return array|null doc (decoded as objects), hash
 */
function pg_picture_tr_canvas($file_id, $name)
{
    $newest = pg_file_versions_ready() ? pg_file_versions_newest((int) $file_id) : null;
    $json = $newest ? (string) $newest['doc_json'] : '';

    if (($json === '') || !pg_file_versions_matches_file($newest, FILE_DIRECTORY_PATH . '/' . basename((string) $name))) {
        return null;
    }

    $doc = json_decode($json);

    if (!is_object($doc) || !pg_image_editor_canvas_is_doc($doc)) {
        return null;
    }

    $hash = isset($newest['doc_hash']) ? (string) $newest['doc_hash'] : '';

    return array('doc' => $doc, 'hash' => ($hash !== '') ? $hash : sha1($json));
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
    $known = pg_tr_known_languages();

    return isset($known[$language]) && ((int) $known[$language][5] === 1);
}

/**
 * Action picture_languages of the Design Canvas: the Languages list of a
 * canvas file. words is 0 for a file with none (or before translation is
 * set up), and the list is then left closed.
 *
 *   { file_id } -> { status, words, hash, doc, languages: [{ code, name,
 *   rtl, state, total, translated, texts, stamp, url }] }
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

    $canvas = pg_picture_tr_canvas($file_id, $file['row']['name']);
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
        $translated = count(array_filter($texts, function ($text) { return ($text['text'] !== null) && ($text['text'] !== ''); }));
        $row = isset($rows[$code]) ? $rows[$code] : null;
        $picture = ($row && ((string) $row['name'] !== '')) ? (string) $row['name'] : '';

        $languages[] = array(
            'code'       => $code,
            'name'       => pg_tr_language_label($code),
            'rtl'        => pg_picture_tr_rtl($code),
            'state'      => pg_picture_tr_list_state($row, count($texts), $translated),
            'total'      => count($texts),
            'translated' => $translated,
            'texts'      => $texts,
            'stamp'      => pg_picture_tr_stamp($file_id, $code, $canvas['hash'], $texts),
            'url'        => ($picture !== '') ? PATH . encode_url_path($picture) . '?v=' . (int) $row['drawn_at'] : '');
    }

    return array(array('status' => 'success', 'words' => $words, 'hash' => $canvas['hash'], 'doc' => $canvas['doc'], 'languages' => $languages), 200);
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

    // The folder's edit right, the Recycle Bin and an ERP document, as for
    // a save of the picture itself.
    $file = pg_image_editor_file(isset($request['file_id']) ? $request['file_id'] : 0, $user);

    if ($file['refusal']) {
        return $file['refusal'];
    }

    $file_id = (int) $file['row']['id'];
    $language = isset($request['language']) ? (string) $request['language'] : '';
    $targets = pg_tr_target_languages();
    $canvas = pg_picture_tr_canvas($file_id, $file['row']['name']);
    $texts = ($canvas && isset($targets[$language])) ? pg_picture_tr_language_texts($file_id, $language) : array();
    $why = pg_picture_tr_draw_refusal(array(
        'access'   => true,
        'language' => ($language !== '') && isset($targets[$language]),
        'rtl'      => pg_picture_tr_rtl($language),
        'live'     => (bool) $canvas,
        'sent'     => isset($request['stamp']) ? (string) $request['stamp'] : '',
        'stamp'    => $canvas ? pg_picture_tr_stamp($file_id, $language, $canvas['hash'], $texts) : ''));

    if (($why === 'changed') || ($why === 'stamp')) {
        return pg_image_editor_refusal(lang('This picture or its translations changed meanwhile. Open the Languages list again.'), 409);
    }

    if ($why !== '') {
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    $board = $canvas['doc']->artboard;
    $width = (int) round(max(16, min(8000, (float) $board->width)));
    $height = (int) round(max(16, min(8000, (float) $board->height)));
    $now = time();

    // A later draw writes over the language's picture, so its address stays.
    $old = db_item("SELECT f.id, f.name, f.folder FROM file_translation ft INNER JOIN files f ON f.id = ft.picture_file_id
        WHERE ft.file_id = '$file_id' AND ft.language = '" . e($language) . "' LIMIT 1");
    $folder = $old ? (int) $old['folder'] : (int) $file['row']['folder'];
    $name = $old ? (string) $old['name']
        : get_unique_name(array('name' => prepare_file_name(pg_picture_tr_picture_name($file['row']['name'], $language)), 'type' => 'file'));

    if (pg_upload_name_blocked($name)) {
        return pg_image_editor_refusal(pg_upload_blocked_message($name));
    }

    $path = FILE_DIRECTORY_PATH . '/' . $name;
    $temporary = $path . '.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.tmp';

    if (!pg_image_editor_take_png(isset($request['picture']) ? $request['picture'] : '', $temporary, $width, $height)) {
        return pg_image_editor_refusal(lang('Invalid request.'));
    }

    pg_process_image_file($temporary);

    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        return pg_image_editor_refusal(lang('Sorry, the file could not be saved.'));
    }

    $record = pg_image_editor_record($old ? 'replace' : 'new', $name, $folder, 'png', $old, $user);

    if ($record[0]['status'] !== 'success') {
        return $record;
    }

    $fit = empty($request['fit']) ? 0 : 1;

    db_q("INSERT INTO file_translation (file_id, language, picture_file_id, state, fit, doc_hash, created_at, drawn_at, drawn_by)
        VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE picture_file_id = VALUES(picture_file_id), state = 1, fit = VALUES(fit), doc_hash = VALUES(doc_hash),
            drawn_at = VALUES(drawn_at), drawn_by = VALUES(drawn_by)",
        array($file_id, $language, (int) $record[0]['id'], $fit, (string) $canvas['hash'], $now, $now, (int) $user['id']));

    // A page in this language shows the new picture from the next request.
    pg_page_cache_mark_dirty();

    return array(array(
        'status' => 'success',
        'state'  => pg_picture_tr_list_state(array('state' => pg_picture_tr_state_after(0, 'draw'), 'fit' => $fit), count($texts), count($texts)),
        'url'    => PATH . encode_url_path($name) . '?v=' . $now), 200);
}
