<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Media of a content import: the files an export points at (a WordPress
 * attachment, an <img> in a post or page body) are fetched from the old site
 * into the file library and the body's addresses are rewritten to the stored
 * file.
 *
 * - A fetch goes through pg_curl_run() into a temporary file with the cap of
 *   a single upload, the address checked and pinned (no address inside the
 *   network), https only: an http address is asked for over https.
 * - The file is stored by pg_file_store_upload(), so the name rules, the
 *   refused extensions and the picture size cap of an upload apply.
 * - Every fetched file is listed in import_items as kind file with the key
 *   'media:' + sha1 of its address, so an attachment and a body that name
 *   the same address share one file, and undo removes it.
 * - A fetch that fails leaves the address as written; the writer names it in
 *   its answer and the report lists it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most pictures one item's body fetches; the rest keep their address.
const PG_IMPORT_MEDIA_PER_ITEM = 25;

// Seconds one fetch may take.
const PG_IMPORT_MEDIA_TIMEOUT = 20;

// The cap when the server sets no upload limit.
const PG_IMPORT_MEDIA_DEFAULT_MAX = 67108864;

/**
 * An address as the import fetches and keys it: http(s) or
 * protocol-relative, fragment dropped, asked for over https. '' for
 * anything else (data:, relative, javascript:). Pure.
 *
 * @param string $url
 * @return string
 */
function pg_import_media_normal($url)
{
    $url = trim(html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8'));
    $url = preg_replace('/#.*$/s', '', $url);

    if (strncmp($url, '//', 2) === 0) {
        $url = 'https:' . $url;
    } elseif (preg_match('#^http://#i', $url)) {
        $url = 'https://' . substr($url, 7);
    } elseif (preg_match('#^https://#i', $url)) {
        $url = 'https://' . substr($url, 8);
    } else {
        return '';
    }

    $host = parse_url($url, PHP_URL_HOST);

    if (!is_string($host) || ($host === '') || preg_match('/\s/', $url)) {
        return '';
    }

    return $url;
}

/**
 * The full-size picture behind a WordPress size variant
 * (photo-300x200.jpg → photo.jpg, photo-scaled.jpg → photo.jpg), '' when
 * the address names no variant. Fetched first, so a body and the
 * attachment share one file. Pure.
 *
 * @param string $url a pg_import_media_normal() address
 * @return string
 */
function pg_import_media_original($url)
{
    $url = (string) $url;
    $query = '';

    if (($at = strpos($url, '?')) !== false) {
        $query = substr($url, $at);
        $url = substr($url, 0, $at);
    }

    $original = preg_replace('/-(?:\d+x\d+|scaled)(\.[a-z0-9]{2,5})$/i', '$1', $url);

    return ($original !== $url) ? $original . $query : '';
}

// The import_items key of a fetched address.
function pg_import_media_key($url)
{
    return 'media:' . sha1((string) $url);
}

/**
 * The name a fetched file is offered under, before prepare_file_name(): the
 * last segment of the address, an extension from the answer's type when it
 * has none. Pure.
 *
 * @param string $url
 * @param string $content_type
 * @return string
 */
function pg_import_media_name($url, $content_type = '')
{
    $path = (string) parse_url((string) $url, PHP_URL_PATH);
    $name = pg_basename(rawurldecode($path));
    $name = trim(str_replace(array('/', '\\'), '', $name));

    if ($name === '') {
        $name = 'file';
    }

    $types = array(
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        'image/svg+xml' => 'svg', 'image/avif' => 'avif', 'application/pdf' => 'pdf',
    );
    $type = strtolower(trim(explode(';', (string) $content_type)[0]));

    if ((strpos($name, '.') === false) && isset($types[$type])) {
        $name .= '.' . $types[$type];
    }

    return $name;
}

/**
 * Why a fetch's answer is not kept, '' when it is. $images_only refuses an
 * answer that is not a picture (a body's <img> answered with an error page).
 * Pure.
 *
 * @param array $answer pg_curl_run() with 'cap' and 'sink'
 * @param int   $max    the cap, in bytes
 * @param bool  $images_only
 * @return string
 */
function pg_import_media_refusal($answer, $max, $images_only)
{
    if (!empty($answer['capped'])) {
        return lang(array('string' => 'The file is larger than {var:1}, the biggest single file this server accepts.', 'vars' => array(convert_bytes_to_string((int) $max, 1))));
    }

    if (!empty($answer['write_failed'])) {
        return lang('The server could not write a temporary file.');
    }

    $status = (int) ($answer['status'] ?? 0);

    if (empty($answer['ok'])) {
        $error = trim((string) ($answer['error'] ?? ''));

        return ($status > 0)
            ? lang(array('string' => 'The old site answered {var:1}.', 'vars' => array($status)))
            : lang(array('string' => 'The file could not be fetched: {var:1}', 'vars' => array(($error !== '') ? $error : lang('no answer'))));
    }

    if ((int) ($answer['received'] ?? 0) <= 0) {
        return lang('The old site sent an empty file.');
    }

    if ($images_only && (stripos(trim((string) ($answer['content_type'] ?? '')), 'image/') !== 0)) {
        return lang('The address did not answer with a picture.');
    }

    return '';
}

/**
 * The picture address of each <img> of a body: src, else the address a
 * lazy loader keeps (data-src, data-lazy-src, data-srcset), else the
 * largest srcset entry. Normal addresses, each once, in order. Pure.
 *
 * @param string $html
 * @return string[]
 */
function pg_import_media_img_sources($html)
{
    $out = array();

    foreach (pg_import_media_img_tags((string) $html) as $tag) {
        $url = pg_import_media_img_source($tag['attrs']);

        if (($url !== '') && !in_array($url, $out, true)) {
            $out[] = $url;
        }
    }

    return $out;
}

/**
 * The body with every <img> whose address is in $map pointing at the stored
 * file: src set, srcset, sizes and the lazy loaders' attributes dropped (one
 * stored file is the only source). Other tags stay byte for byte. Pure.
 *
 * @param string $html
 * @param array  $map normal address => new address
 * @return string
 */
function pg_import_media_rewrite($html, $map)
{
    $html = (string) $html;

    if (!$map) {
        return $html;
    }

    $drop = array('src', 'srcset', 'sizes', 'data-src', 'data-lazy-src', 'data-srcset', 'data-lazy-srcset', 'data-orig-file', 'data-large-file', 'data-medium-file');
    $offset = 0;
    $out = '';

    foreach (pg_import_media_img_tags($html) as $tag) {
        $url = pg_import_media_img_source($tag['attrs']);

        if (($url === '') || !isset($map[$url])) {
            continue;
        }

        $kept = '';

        foreach ($tag['attrs'] as $attr) {
            if (!in_array($attr['name'], $drop, true)) {
                $kept .= ' ' . $attr['raw'];
            }
        }

        $out .= substr($html, $offset, $tag['start'] - $offset)
            . '<img src="' . htmlspecialchars((string) $map[$url], ENT_QUOTES, 'UTF-8') . '"' . $kept . ($tag['closed'] ? ' />' : '>');
        $offset = $tag['start'] + $tag['length'];
    }

    return $out . substr($html, $offset);
}

/**
 * The <img> tags of a body with their place and attributes. Pure.
 *
 * @param string $html
 * @return array[] start, length, closed, attrs (name lower case, value decoded, raw)
 */
function pg_import_media_img_tags($html)
{
    $tags = array();

    if (!preg_match_all('/<img\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/i', (string) $html, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return $tags;
    }

    foreach ($found as $match) {
        $inner = $match[1][0];
        $closed = (substr(rtrim($inner), -1) === '/');

        if ($closed) {
            $inner = substr(rtrim($inner), 0, -1);
        }

        $attrs = array();
        preg_match_all('/([^\s=\/"\'>]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/', $inner, $pairs, PREG_SET_ORDER);

        foreach ($pairs as $pair) {
            $value = isset($pair[2]) ? $pair[2] : '';

            if (($value !== '') && (($value[0] === '"') || ($value[0] === "'"))) {
                $value = substr($value, 1, -1);
            }

            $attrs[] = array(
                'name'  => strtolower($pair[1]),
                'value' => html_entity_decode($value, ENT_QUOTES, 'UTF-8'),
                'raw'   => $pair[0],
            );
        }

        $tags[] = array('start' => $match[0][1], 'length' => strlen($match[0][0]), 'closed' => $closed, 'attrs' => $attrs);
    }

    return $tags;
}

/**
 * The address one <img> shows, normal, '' when it names none to fetch.
 * Pure.
 *
 * @param array $attrs pg_import_media_img_tags() attributes
 * @return string
 */
function pg_import_media_img_source($attrs)
{
    $by_name = array();

    foreach ((array) $attrs as $attr) {
        if (!isset($by_name[$attr['name']])) {
            $by_name[$attr['name']] = $attr['value'];
        }
    }

    // A lazy loader keeps the real picture aside and a placeholder in src.
    foreach (array('data-src', 'data-lazy-src', 'src') as $name) {
        $url = pg_import_media_normal($by_name[$name] ?? '');

        if ($url !== '') {
            return $url;
        }
    }

    foreach (array('data-srcset', 'data-lazy-srcset', 'srcset') as $name) {
        $url = pg_import_media_normal(pg_import_media_srcset_best($by_name[$name] ?? ''));

        if ($url !== '') {
            return $url;
        }
    }

    return '';
}

/**
 * The widest entry of a srcset (by its w descriptor, else its density), ''
 * when it has none. Pure.
 *
 * @param string $srcset
 * @return string
 */
function pg_import_media_srcset_best($srcset)
{
    $best = '';
    $best_size = -1.0;

    foreach (explode(',', (string) $srcset) as $entry) {
        $parts = preg_split('/\s+/', trim($entry));

        if (!$parts || ($parts[0] === '')) {
            continue;
        }

        $size = 1.0;

        if (isset($parts[1]) && preg_match('/^(\d*\.?\d+)([wx])$/i', $parts[1], $m)) {
            $size = (float) $m[1];
        }

        if ($size > $best_size) {
            $best_size = $size;
            $best = $parts[0];
        }
    }

    return $best;
}

// ---- Fetching ----------------------------------------------------------------

// The folder fetched files go to: the mapped one, else the root folder.
function pg_import_media_folder($import)
{
    $folder = (int) ($import['mapping']['file_folder'] ?? 0);

    if (($folder > 0) && (int) db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '" . $folder . "'") > 0) {
        return $folder;
    }

    return (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
}

/**
 * The stored file of an address: one these imports fetched already, else
 * fetched now (the full-size picture of a WordPress size variant first).
 *
 * @param array  $import pg_import_get()
 * @param int[]  $related pg_import_related_ids()
 * @param string $url    as written in the export
 * @param array  $options images_only (bool), description (string)
 * @return array{ok:bool,id:int,name:string,reason:string}
 */
function pg_import_media_fetch($import, $related, $url, $options = array())
{
    $url = pg_import_media_normal($url);

    if ($url === '') {
        return array('ok' => false, 'id' => 0, 'name' => '', 'reason' => lang('The address is not a web address.'));
    }

    $original = pg_import_media_original($url);
    $tries = ($original !== '') ? array($original, $url) : array($url);

    foreach ($tries as $try) {
        $id = pg_import_item_id($related, 'file', pg_import_media_key($try));
        $name = ($id > 0) ? (string) db_value("SELECT name FROM files WHERE id = '" . $id . "'") : '';

        if ($name !== '') {
            return array('ok' => true, 'id' => $id, 'name' => $name, 'reason' => '');
        }
    }

    $reason = '';

    foreach ($tries as $try) {
        $stored = pg_import_media_download($import, $try, $options);

        if ($stored['ok']) {
            db("INSERT IGNORE INTO import_items SET
                import_id = '" . (int) $import['id'] . "',
                kind = 'file',
                item_id = '" . (int) $stored['id'] . "',
                source_key = '" . e(pg_import_media_key($try)) . "'");

            return $stored;
        }

        // The size variant's own reason is the one worth naming.
        $reason = $stored['reason'];
    }

    return array('ok' => false, 'id' => 0, 'name' => '', 'reason' => $reason);
}

/**
 * One address into the file library.
 *
 * @param array  $import
 * @param string $url normal
 * @param array  $options
 * @return array{ok:bool,id:int,name:string,reason:string}
 */
function pg_import_media_download($import, $url, $options)
{
    $fail = function ($reason) {
        return array('ok' => false, 'id' => 0, 'name' => '', 'reason' => $reason);
    };

    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $limits = pg_upload_limits();
    $max = ((int) $limits['file_max'] > 0) ? (int) $limits['file_max'] : PG_IMPORT_MEDIA_DEFAULT_MAX;
    $temp = PG_FUNCTIONS_DIR . '/data/temp/import_' . (int) $import['id'] . '_media.tmp';
    $handle = @fopen($temp, 'wb');

    if ($handle === false) {
        return $fail(lang('The server could not write a temporary file.'));
    }

    $answer = pg_curl_run('GET', $url, array(
        'sink'          => $handle,
        'cap'           => $max,
        'timeout'       => PG_IMPORT_MEDIA_TIMEOUT,
        'check_address' => true,
    ));

    fclose($handle);

    $refusal = pg_import_media_refusal($answer, $max, !empty($options['images_only']));

    if ($refusal !== '') {
        @unlink($temp);

        return $fail($refusal);
    }

    $stored = pg_file_store_upload($temp, pg_import_media_name($url, (string) $answer['content_type']), (int) $answer['received'], array(
        'folder'         => pg_import_media_folder($import),
        'description'    => mb_substr(trim((string) ($options['description'] ?? '')), 0, 255),
        'image_settings' => function_exists('pg_image_settings') ? pg_image_settings() : array(),
        'file_max'       => $max,
    ));

    @unlink($temp);

    if (empty($stored['ok'])) {
        return $fail((string) ($stored['message'] ?? lang('The file could not be stored.')));
    }

    // The person who started the import made it, also when the job stored it.
    db("UPDATE files SET user = '" . (int) $import['user_id'] . "' WHERE id = '" . (int) $stored['file']['id'] . "'");

    return array('ok' => true, 'id' => (int) $stored['file']['id'], 'name' => (string) $stored['file']['name'], 'reason' => '');
}

/**
 * A body with its pictures in the file library: each <img> address fetched
 * (at most PG_IMPORT_MEDIA_PER_ITEM) and rewritten to the stored file. A
 * picture that could not be fetched keeps its address and is named in the
 * notes.
 *
 * @param array  $import
 * @param int[]  $related
 * @param string $html
 * @return array{html:string,notes:string[]}
 */
function pg_import_media_localize($import, $related, $html)
{
    $map = array();
    $notes = array();
    $sources = pg_import_media_img_sources($html);

    foreach ($sources as $index => $url) {
        if ($index >= PG_IMPORT_MEDIA_PER_ITEM) {
            $notes[] = lang(array('string' => '{var:1} more pictures keep their old address.', 'vars' => array(count($sources) - $index)));
            break;
        }

        $stored = pg_import_media_fetch($import, $related, $url, array('images_only' => true));

        if ($stored['ok']) {
            $map[$url] = OUTPUT_PATH . $stored['name'];
        } else {
            $notes[] = $url . ': ' . $stored['reason'];
        }
    }

    return array('html' => pg_import_media_rewrite($html, $map), 'notes' => $notes);
}

/**
 * The notes of a writer as one line for the report.
 *
 * @param string[] $notes
 * @return string
 */
function pg_import_media_notes_line($notes)
{
    if (!$notes) {
        return '';
    }

    return lang(array('string' => 'Kept at the old address: {var:1}', 'vars' => array(implode('; ', $notes))));
}
