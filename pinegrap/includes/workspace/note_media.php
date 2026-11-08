<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a file of this site shown in a note.
 *
 * A note line that holds nothing but a link to a file of this site
 * ("[name](https://site/file.png)", what attaching a file writes) is drawn
 * as the file itself: a picture, a video or sound player, or a card with
 * the file's icon, name and size. Only ws_render_body() in a note's mode
 * draws it; the text keeps the link, so export, import and the API see the
 * same Markdown as before, and a link to another host stays a link.
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
 * Where this site's files are served: its host and path, without the
 * scheme ("example.com/" or "example.com/shop/").
 *
 * @return string '' when the site's address is not known
 */
function ws_note_media_base()
{
    if (!defined('HOSTNAME_SETTING') || !defined('PATH') || ((string) HOSTNAME_SETTING === '')) {
        return '';
    }

    return (string) HOSTNAME_SETTING . (string) PATH;
}

/**
 * The file a note line links to, when the line holds only that link and
 * the link is to a file of this site (one name right under the site's
 * path, as files are served).
 *
 * No database access.
 *
 * @param string $line a line of the stored text
 * @param string $base ws_note_media_base()
 * @return array|null label, url, name (the stored file name, decoded)
 */
function ws_note_media_line($line, $base)
{
    $base = strtolower((string) $base);

    if (($base === '') || !preg_match('#^[ \t]*\[([^\]\n]{1,300})\]\((https?://([^\s()<>"]{1,1000}))\)[ \t]*$#i', (string) $line, $match)) {
        return null;
    }

    $address = $match[3];

    if (strtolower(substr($address, 0, strlen($base))) !== $base) {
        return null;
    }

    $rest = (string) substr($address, strlen($base));

    // One name, no folder, query or fragment: that is how a file is served.
    if (!preg_match('#^[^/?\#\\\\]{1,255}$#', $rest)) {
        return null;
    }

    $name = rawurldecode($rest);

    if (($name === '') || ($name[0] === '.') || preg_match('#[/\\\\\x00-\x1F\x7F]#', $name) || (strpos($name, '.') === false)) {
        return null;
    }

    return array('label' => trim($match[1]), 'url' => $match[2], 'name' => $name);
}

/**
 * The sizes of this site's files by stored name, for the names asked. A
 * seed sets the answers instead and the database is not asked (the tests,
 * which have none); an empty seed asks it again.
 *
 * @param array      $names stored file names
 * @param array|null $seed  name => size in bytes
 * @return array name => size in bytes, only for files that exist
 */
function ws_note_media_files($names, $seed = null)
{
    static $known = array();
    static $seeded = false;

    if (is_array($seed)) {
        $known = array();
        $seeded = !empty($seed);

        foreach ($seed as $name => $size) {
            $known[(string) $name] = (int) $size;
        }

        return $known;
    }

    $out = array();
    $missing = array();

    foreach (array_unique(array_map('strval', (array) $names)) as $name) {
        if (array_key_exists($name, $known)) {
            if ($known[$name] !== null) {
                $out[$name] = $known[$name];
            }
        } elseif ($name !== '') {
            $missing[] = $name;
        }
    }

    if (empty($missing) || $seeded || !function_exists('db_items') || !function_exists('e')) {
        return $out;
    }

    $quoted = array();

    foreach (array_slice($missing, 0, 200) as $name) {
        $quoted[] = "'" . e($name) . "'";
        $known[$name] = null;
    }

    foreach ((array) db_items("SELECT name, size FROM files WHERE name IN (" . implode(',', $quoted) . ")") as $row) {
        $known[(string) $row['name']] = (int) $row['size'];
        $out[(string) $row['name']] = (int) $row['size'];
    }

    return $out;
}

/**
 * A file's size for a person to read.
 *
 * No database access.
 *
 * @param int $bytes
 * @return string
 */
function ws_note_media_size_label($bytes)
{
    $bytes = max(0, (int) $bytes);

    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if (function_exists('pg_innodb_size_label')) {
        return pg_innodb_size_label($bytes);
    }

    return ($bytes >= 1048576) ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024) . ' KB';
}

/**
 * The icon of a file shown as a card.
 *
 * @param string $name
 * @return string a Bootstrap Icons class
 */
function ws_note_media_icon($name)
{
    $extension = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));
    $by_extension = array(
        'pdf' => 'bi-file-earmark-pdf', 'doc' => 'bi-file-earmark-word', 'docx' => 'bi-file-earmark-word',
        'xls' => 'bi-file-earmark-spreadsheet', 'xlsx' => 'bi-file-earmark-spreadsheet', 'csv' => 'bi-file-earmark-spreadsheet', 'ods' => 'bi-file-earmark-spreadsheet',
        'ppt' => 'bi-file-earmark-slides', 'pptx' => 'bi-file-earmark-slides', 'zip' => 'bi-file-earmark-zip', 'txt' => 'bi-file-earmark-text', 'md' => 'bi-file-earmark-text',
    );

    return $by_extension[$extension] ?? 'bi-file-earmark';
}

/**
 * A linked file drawn as itself: a picture (loaded when it scrolls near,
 * its height bounded by the stylesheet), a video or sound player, or a card
 * (icon, name, size) for a PDF or any other file. The picture and the card
 * are links to the file, so the page works without the panel's script; the
 * panel opens them in its viewer and preview (data-ws-media).
 *
 * No database access.
 *
 * @param array $media ws_note_media_line()
 * @param int   $size  the file's size in bytes
 * @return string
 */
function ws_note_media_html($media, $size)
{
    $kind = function_exists('ws_file_kind') ? ws_file_kind($media['name']) : 'file';
    $url = h($media['url']);
    $label = h($media['label']);
    $data = ' data-ws-media="' . h($kind) . '" data-ws-media-name="' . $label . '"';

    if ($kind === 'image') {
        return '<figure class="ws-media ws-media-image"><a href="' . $url . '" target="_blank" rel="noopener noreferrer"' . $data . '>'
            . '<img src="' . $url . '" alt="' . $label . '" loading="lazy" decoding="async"></a></figure>';
    }

    if ($kind === 'video') {
        return '<figure class="ws-media ws-media-video"><video src="' . $url . '" controls preload="metadata" aria-label="' . $label . '"></video></figure>';
    }

    if ($kind === 'audio') {
        return '<figure class="ws-media ws-media-audio"><audio src="' . $url . '" controls preload="metadata" aria-label="' . $label . '"></audio></figure>';
    }

    return '<a class="ws-media ws-media-file" href="' . $url . '" target="_blank" rel="noopener noreferrer"' . $data . '>'
        . '<i class="bi ' . ws_note_media_icon($media['name']) . '" aria-hidden="true"></i>'
        . '<span class="ws-media-name">' . $label . '</span>'
        . '<span class="ws-media-size">' . h(ws_note_media_size_label($size)) . '</span></a>';
}

/**
 * The media blocks of a note's lines, by line number: each line that links
 * only to a file of this site that exists.
 *
 * @param array       $lines the text's lines
 * @param string|null $base  ws_note_media_base() when null
 * @return array line number => HTML
 */
function ws_note_media_blocks($lines, $base = null)
{
    $base = ($base === null) ? ws_note_media_base() : (string) $base;

    if ($base === '') {
        return array();
    }

    $found = array();

    foreach ($lines as $index => $line) {
        // A cheap look first: nearly every line is not a lone link.
        if ((strpos($line, '](') === false) || !preg_match('/^[ \t]*\[/', $line)) {
            continue;
        }

        $media = ws_note_media_line($line, $base);

        if ($media !== null) {
            $found[$index] = $media;
        }
    }

    if (empty($found)) {
        return array();
    }

    $sizes = ws_note_media_files(array_column($found, 'name'));
    $blocks = array();

    foreach ($found as $index => $media) {
        if (isset($sizes[$media['name']])) {
            $blocks[$index] = ws_note_media_html($media, $sizes[$media['name']]);
        }
    }

    return $blocks;
}
