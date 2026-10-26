<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - one HTML file brought in as a note.
 *
 * A page saved from a browser, a web clip (SingleFile, a clipper that keeps
 * pictures inside the page) or a note another app exported as HTML is one
 * note. ws_note_import_stage() hands the uploaded file to
 * ws_note_html_stage(), which lays it out the way an upload of a Markdown
 * file would have been, as notes_enex.php and notes_keep.php do: a Markdown
 * file with the page's title and dates in the front matter, and the pictures
 * the page carries inside it (data: addresses) under attachments/1/, linked
 * where the page shows them. The import then goes on as for any upload: the
 * preview, the batches and the finish of notes_import.php.
 *
 * The file is untrusted. It is read by the HTML parser only (never as XML,
 * so a document type declares nothing), and only the words of the page's
 * main part come out, through the ENML reader of notes_enex.php: lines,
 * bold, italic, struck and code words, headings and lists as Markdown for
 * ws_note_import_convert(), links to web and mail addresses, web pictures as
 * links. Scripts, styles, frames, form controls and hidden parts leave
 * nothing; no attribute of the page is written anywhere.
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
 * How large an HTML file may be: the parser holds the page and the pictures
 * inside it in memory at once.
 */
define('WS_NOTE_HTML_MAX', 16777216);

/**
 * The page's text as UTF-8: the character set its byte order mark or its
 * <meta> declares, else what ws_note_import_text() makes of it (UTF-8, else
 * the Turkish Windows code page). The declaration is rewritten to UTF-8, so
 * the parser does not read the converted text in the old set again.
 *
 * @param string $bytes
 * @return string
 */
function ws_note_html_decode($bytes)
{
    $bytes = (string) $bytes;
    $bom = (strncmp($bytes, "\xEF\xBB\xBF", 3) === 0) || (strncmp($bytes, "\xFF\xFE", 2) === 0) || (strncmp($bytes, "\xFE\xFF", 2) === 0);

    if (!$bom && preg_match('/<meta\b[^>]*?charset\s*=\s*["\']?\s*([A-Za-z0-9_.:-]{1,40})/i', substr($bytes, 0, 16384), $match)) {
        $declared = strtolower($match[1]);

        // Browsers read Latin-1 and ASCII as Windows-1252.
        if (in_array($declared, array('iso-8859-1', 'latin1', 'us-ascii', 'ascii'), true)) {
            $declared = 'windows-1252';
        }

        if (!in_array($declared, array('utf-8', 'utf8'), true)) {
            try {
                $converted = @mb_convert_encoding($bytes, 'UTF-8', $declared);
            } catch (\Throwable $error) {
                $converted = false;
            }

            if (is_string($converted) && ($converted !== '')) {
                $bytes = $converted;
            }
        }
    }

    $text = ws_note_import_text($bytes);

    return preg_replace_callback('/<meta\b[^>]*>/i', function ($meta) {
        return preg_replace('/(charset\s*=\s*)(["\']?)[^"\'\s;>\/]*\2/i', '${1}${2}utf-8${2}', $meta[0]);
    }, $text);
}

/**
 * The page parsed by the HTML parser: no network, no entity of a document
 * type expanded; null when it cannot be read.
 *
 * @param string $html UTF-8, ws_note_html_decode()
 * @return DOMDocument|null
 */
function ws_note_html_document($html)
{
    if (trim($html) === '') {
        return null;
    }

    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"?>' . $html, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $loaded ? $document : null;
}

/**
 * The words of an element on one line.
 *
 * @param DOMNode $node
 * @return string
 */
function ws_note_html_words($node)
{
    return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $node->textContent));
}

/**
 * What the page says about itself: its title (og:title, else <title>, else
 * its first heading), when it was written and changed, and the web address
 * it was saved from (canonical link, else og:url).
 *
 * @param DOMDocument $document
 * @return array title, created, updated, source
 */
function ws_note_html_meta($document)
{
    $meta = array();

    foreach ($document->getElementsByTagName('meta') as $node) {
        foreach (array('property', 'name', 'itemprop', 'http-equiv') as $attribute) {
            $key = strtolower(trim($node->getAttribute($attribute)));

            if (($key !== '') && !isset($meta[$key])) {
                $meta[$key] = trim($node->getAttribute('content'));
            }
        }
    }

    $pick = function ($keys) use ($meta) {
        foreach ($keys as $key) {
            if (isset($meta[$key]) && ($meta[$key] !== '')) {
                return $meta[$key];
            }
        }

        return '';
    };

    $title = preg_replace('/[\s\x{00A0}]+/u', ' ', trim($pick(array('og:title'))));

    foreach (array('title', 'h1') as $tag) {
        if ($title === '') {
            $nodes = $document->getElementsByTagName($tag);
            $title = ($nodes->length > 0) ? ws_note_html_words($nodes->item(0)) : '';
        }
    }

    $time = function ($keys) use ($pick) {
        $value = $pick($keys);

        return ($value !== '') ? ws_note_import_time($value) : 0;
    };

    $source = '';

    foreach ($document->getElementsByTagName('link') as $node) {
        if (in_array('canonical', preg_split('/\s+/', strtolower(trim($node->getAttribute('rel')))), true)) {
            $source = trim($node->getAttribute('href'));
            break;
        }
    }

    if (!preg_match('#^https?://[^\s]+$#i', $source)) {
        $source = $pick(array('og:url'));
    }

    return array(
        'title'   => (string) $title,
        'created' => $time(array('article:published_time', 'datepublished', 'dcterms.created', 'dc.date.created', 'date', 'dc.date', 'created')),
        'updated' => $time(array('article:modified_time', 'og:updated_time', 'datemodified', 'dcterms.modified', 'last-modified', 'modified')),
        'source'  => preg_match('#^https?://[^\s]+$#i', $source) ? $source : '',
    );
}

/**
 * The part of the page that is its content: its <main>, else its only
 * <article>, else its body. Menus, the page's own head and what it hides are
 * taken out first.
 *
 * @param DOMDocument $document
 * @return DOMElement|null
 */
function ws_note_html_content($document)
{
    static $dropped = array(
        'head', 'script', 'style', 'noscript', 'template', 'iframe', 'frame', 'frameset', 'object', 'embed',
        'applet', 'svg', 'math', 'canvas', 'video', 'audio', 'source', 'track', 'map', 'nav', 'button',
        'select', 'textarea', 'option', 'optgroup', 'datalist', 'output', 'dialog', 'link', 'meta', 'base',
    );

    $doomed = array();

    foreach ($document->getElementsByTagName('*') as $node) {
        $tag = strtolower($node->nodeName);

        if (in_array($tag, $dropped, true)
            || (strncmp($tag, 'en-', 3) === 0)
            || $node->hasAttribute('hidden')
            || (strtolower(trim($node->getAttribute('aria-hidden'))) === 'true')
            || (ws_note_enex_style($node, 'display') === 'none')
            || (ws_note_enex_style($node, 'visibility') === 'hidden')
            || (($tag === 'input') && (strtolower(trim($node->getAttribute('type'))) !== 'checkbox'))) {
            $doomed[] = $node;
        }
    }

    // An element HTML 5 keeps empty that an older libxml does not know
    // (<embed>, <source>) takes the rest of its parent's content inside it:
    // what it holds goes back where it stood.
    static $empty = array('embed', 'source', 'track', 'param', 'area', 'link', 'meta', 'base', 'input', 'keygen', 'wbr');

    foreach ($doomed as $node) {
        if (!$node->parentNode) {
            continue;
        }

        if (in_array(strtolower($node->nodeName), $empty, true)) {
            while ($node->firstChild) {
                $node->parentNode->insertBefore($node->firstChild, $node);
            }
        }

        $node->parentNode->removeChild($node);
    }

    // A checklist an app exported as check boxes is read as Evernote's.
    $boxes = array();

    foreach ($document->getElementsByTagName('input') as $node) {
        $boxes[] = $node;
    }

    foreach ($boxes as $node) {
        $todo = $document->createElement('en-todo');
        $todo->setAttribute('checked', $node->hasAttribute('checked') ? 'true' : 'false');
        $node->parentNode->replaceChild($todo, $node);
    }

    $mains = $document->getElementsByTagName('main');

    if ($mains->length > 0) {
        return $mains->item(0);
    }

    $articles = $document->getElementsByTagName('article');

    if ($articles->length === 1) {
        return $articles->item(0);
    }

    $bodies = $document->getElementsByTagName('body');

    return ($bodies->length > 0) ? $bodies->item(0) : $document->documentElement;
}

/**
 * A picture the page carries inside it (a data: address): its type and
 * bytes, or null when it is not a PNG, JPEG, GIF or WebP picture whose bytes
 * say so. An SVG is never one: it can hold a script.
 *
 * @param string $source the src of an <img>
 * @return array|null extension, mime, bytes
 */
function ws_note_html_data_picture($source)
{
    if (!preg_match('#^\s*data:image/(?:png|jpe?g|gif|webp)\s*;\s*base64\s*,#i', $source, $match)) {
        return null;
    }

    $bytes = base64_decode(preg_replace('/\s+/', '', substr($source, strlen($match[0]))), true);

    if (!is_string($bytes) || ($bytes === '')) {
        return null;
    }

    $types = array(
        array("\x89PNG\r\n\x1A\n", 0, 'png', 'image/png'),
        array("\xFF\xD8\xFF", 0, 'jpg', 'image/jpeg'),
        array('GIF87a', 0, 'gif', 'image/gif'),
        array('GIF89a', 0, 'gif', 'image/gif'),
        array('WEBP', 8, 'webp', 'image/webp'),
    );

    foreach ($types as $type) {
        if (substr($bytes, $type[1], strlen($type[0])) === $type[0]) {
            if (($type[2] === 'webp') && (strncmp($bytes, 'RIFF', 4) !== 0)) {
                continue;
            }

            return array('extension' => $type[2], 'mime' => $type[3], 'bytes' => $bytes);
        }
    }

    return null;
}

/**
 * One HTML file read as a note.
 *
 * Pictures inside the page are kept as files up to $file_max each and
 * $bytes_max in all; the same picture shown twice is one file. A picture
 * past either, or not a picture at all, is left out.
 *
 * @param string   $bytes
 * @param int|null $file_max  bytes one picture may have; null for ws_upload_max_bytes()
 * @param int|null $bytes_max bytes of pictures in all; null for WS_NOTE_IMPORT_FILES_BYTES
 * @param int|null $html_max  bytes the file may have; null for WS_NOTE_HTML_MAX
 * @return array ok, error, title, created, updated, body (Markdown, its
 *               pictures linked under attachments/1/), files (name, mime, bytes)
 */
function ws_note_html_read($bytes, $file_max = null, $bytes_max = null, $html_max = null)
{
    $out = array('ok' => false, 'error' => '', 'title' => '', 'created' => 0, 'updated' => 0, 'body' => '', 'files' => array());
    $html_max = ($html_max !== null) ? (int) $html_max : WS_NOTE_HTML_MAX;
    $file_max = ($file_max !== null) ? (int) $file_max : (function_exists('ws_upload_max_bytes') ? ws_upload_max_bytes() : 20971520);
    $bytes_max = ($bytes_max !== null) ? (int) $bytes_max : WS_NOTE_IMPORT_FILES_BYTES;

    if (strlen((string) $bytes) > $html_max) {
        $out['error'] = lang(array('string' => 'The web page is larger than {var:1} MB.', 'vars' => max(1, (int) floor($html_max / 1048576))));

        return $out;
    }

    $document = ws_note_html_document(ws_note_html_decode($bytes));
    $empty = lang('The web page holds no text.');

    if (!$document) {
        $out['error'] = $empty;

        return $out;
    }

    $meta = ws_note_html_meta($document);
    $root = ws_note_html_content($document);

    if (!$root) {
        $out['error'] = $empty;

        return $out;
    }

    // Pictures inside the page become attachments, shown where the page
    // shows them (the ENML reader finds an en-media by its hash).
    $images = array();

    foreach ($root->getElementsByTagName('img') as $node) {
        $images[] = $node;
    }

    $media = array();
    $hashes = array();
    $taken = array();
    $total = 0;

    foreach ($images as $node) {
        $source = $node->getAttribute('src');

        if (strncasecmp(ltrim($source), 'data:', 5) !== 0) {
            continue;
        }

        $picture = ws_note_html_data_picture($source);
        $hash = $picture ? md5($picture['bytes']) : '';

        if ($picture && !isset($hashes[$hash])) {
            $size = strlen($picture['bytes']);

            if (($size > $file_max) || (($total + $size) > $bytes_max)) {
                $picture = null;
            } else {
                $number = count($out['files']) + 1;
                $name = ws_note_enex_file_name('image-' . $number . '.' . $picture['extension'], $picture['mime'], $number, $taken);
                $hashes[$hash] = true;
                $total += $size;
                $out['files'][] = array('name' => $name, 'mime' => $picture['mime'], 'bytes' => $picture['bytes']);
                $media[] = array('hash' => $hash, 'name' => $name, 'link' => ws_note_import_link_path('attachments/1/' . $name));
            }
        }

        if ($picture) {
            $box = $document->createElement('en-media');
            $box->setAttribute('hash', $hash);
            $node->parentNode->replaceChild($box, $node);
        } else {
            $node->parentNode->removeChild($node);
        }
    }

    $markdown = ws_note_enex_markdown_node($root, $media);
    $body = $markdown['text'];

    if ((trim($body) === '') && empty($out['files'])) {
        $out['error'] = $empty;

        return $out;
    }

    if ($meta['source'] !== '') {
        $label = str_replace(array('[', ']'), array('(', ')'), $meta['source']);
        $body = ltrim(rtrim($body) . "\n\n[" . $label . '](' . ws_note_enex_url($meta['source']) . ')');
    }

    $out['ok'] = true;
    $out['title'] = $meta['title'];
    $out['created'] = $meta['created'];
    $out['updated'] = $meta['updated'];
    $out['body'] = $body;

    return $out;
}

/**
 * Lays out an uploaded HTML file in its stage as one note and its pictures,
 * as the files of an upload. The source file is removed once read.
 *
 * @param string $dir    the stage folder (its files/ is made here)
 * @param string $source the HTML file, moved into the stage
 * @param string $name   the name it was uploaded under, for a page without a title
 * @return array ok, error, entries (as ws_note_import_stage() keeps them; a
 *               picture's row carries a fifth value, 1: never read as a note)
 */
function ws_note_html_stage($dir, $source, $name)
{
    $out = array('ok' => false, 'error' => '', 'entries' => array());
    $size = @filesize($source);

    if (($size !== false) && ($size > WS_NOTE_HTML_MAX)) {
        @unlink($source);
        $out['error'] = lang(array('string' => 'The web page is larger than {var:1} MB.', 'vars' => (int) floor(WS_NOTE_HTML_MAX / 1048576)));

        return $out;
    }

    $bytes = @file_get_contents($source);
    @unlink($source);

    if (!is_string($bytes)) {
        $out['error'] = lang('The file could not be read.');

        return $out;
    }

    $read = ws_note_html_read($bytes);
    unset($bytes);

    if (!$read['ok']) {
        $out['error'] = $read['error'];

        return $out;
    }

    if (!is_dir($dir . '/files') && !@mkdir($dir . '/files', 0755)) {
        ws_note_import_log_failure('creating the files folder of an HTML import');
        $out['error'] = lang('The file could not be saved.');

        return $out;
    }

    $title = ($read['title'] !== '') ? $read['title'] : trim(pg_file_name_stem(str_replace('\\', '/', (string) $name)));
    $taken = array();
    $time = ($read['updated'] > 0) ? $read['updated'] : $read['created'];
    $text = ws_note_enex_file(array('title' => $title, 'created' => $read['created'], 'updated' => $read['updated'], 'tags' => array()), $read['body']);

    if (@file_put_contents($dir . '/files/0.txt', $text) === false) {
        ws_note_import_log_failure('writing the note of an HTML import');
        $out['error'] = lang('The file could not be saved.');

        return $out;
    }

    $out['entries'][] = array(ws_note_export_name($title, 1, $taken) . '.md', strlen($text), $time, 0);

    foreach ($read['files'] as $index => $file) {
        $number = $index + 1;

        if (@file_put_contents($dir . '/files/' . $number . '.txt', $file['bytes']) === false) {
            ws_note_import_log_failure('writing a picture of an HTML import');
            $out['error'] = lang('The file could not be saved.');

            return $out;
        }

        $out['entries'][] = array('attachments/1/' . $file['name'], strlen($file['bytes']), $time, $number, 1);
    }

    $out['ok'] = true;

    return $out;
}
