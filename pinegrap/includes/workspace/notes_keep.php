<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - Google Keep notes brought in from a Takeout export.
 *
 * Google Takeout gives Keep as a zip: every note a JSON file (Takeout/Keep/
 * <title>.json) with its title, its text or checklist, its labels, its times
 * in microseconds, the web links Keep found in it and the names of its
 * attachments, which sit beside it in the zip. An HTML copy of every note
 * comes along and is not read. ws_note_import_stage() hands an uploaded zip
 * to ws_note_keep_stage(), which lays the Keep notes out the way an upload of
 * Markdown files would have been, as notes_enex.php does for Evernote: a
 * Markdown file a note, its times and labels in the front matter, and its
 * attachments under attachments/<n>/, linked at the end of its text. The
 * import then goes on as for any upload: the preview, the batches and the
 * finish of notes_import.php.
 *
 * A note's rich text (textContentHtml) is written by the ENML reader of
 * notes_enex.php, which keeps bold, italic and struck words; an older export
 * has the plain text only. A checklist is written as checklist items, the
 * web links after it. Notes in Keep's bin are left out.
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
 * How long a title taken from the first line of an untitled note may be.
 */
define('WS_NOTE_KEEP_TITLE_MAX', 80);

/**
 * A time Keep writes (microseconds since 1970) as a timestamp; 0 when it is
 * none, or not when a note can have been written.
 *
 * @param mixed $value
 * @return int
 */
function ws_note_keep_time($value)
{
    if (!is_int($value) && !is_float($value) && !(is_string($value) && preg_match('/^[0-9]{1,20}$/', trim($value)))) {
        return 0;
    }

    $seconds = (int) floor(((float) $value) / 1000000);

    return ($seconds > 0) ? ws_note_import_time((string) $seconds) : 0;
}

/**
 * One file of the export read as a Keep note: null when it is not one (a
 * JSON object with a Keep time and a text or a checklist).
 *
 * @param string $json
 * @return array|null title, text, html, list (text, checked), created,
 *                    updated, tags, files (path, mime), links (title, url),
 *                    trashed
 */
function ws_note_keep_parse($json)
{
    $data = json_decode((string) $json, true);

    if (!is_array($data) || (array_keys($data) === range(0, count($data) - 1))) {
        return null;
    }

    $has_time = isset($data['userEditedTimestampUsec']) || isset($data['createdTimestampUsec']);
    $has_text = array_key_exists('textContent', $data) || array_key_exists('textContentHtml', $data) || array_key_exists('listContent', $data);

    if (!$has_time || !$has_text) {
        return null;
    }

    $string = function ($value) {
        return is_string($value) ? $value : '';
    };

    $list = array();

    foreach ((array) ($data['listContent'] ?? array()) as $item) {
        if (is_array($item)) {
            $list[] = array('text' => $string($item['text'] ?? ''), 'checked' => !empty($item['isChecked']));
        }
    }

    $tags = array();

    foreach ((array) ($data['labels'] ?? array()) as $label) {
        $name = is_array($label) ? trim($string($label['name'] ?? '')) : '';

        if ($name !== '') {
            $tags[] = $name;
        }
    }

    $files = array();

    foreach ((array) ($data['attachments'] ?? array()) as $file) {
        $path = is_array($file) ? trim($string($file['filePath'] ?? '')) : '';

        if ($path !== '') {
            $files[] = array('path' => $path, 'mime' => $string($file['mimetype'] ?? ''));
        }
    }

    $links = array();

    foreach ((array) ($data['annotations'] ?? array()) as $annotation) {
        $url = is_array($annotation) ? trim($string($annotation['url'] ?? '')) : '';

        if ($url !== '') {
            $links[] = array('title' => trim($string($annotation['title'] ?? '')), 'url' => $url);
        }
    }

    $updated = ws_note_keep_time($data['userEditedTimestampUsec'] ?? 0);
    $created = ws_note_keep_time($data['createdTimestampUsec'] ?? 0);

    return array(
        'title'   => trim($string($data['title'] ?? '')),
        'text'    => str_replace(array("\r\n", "\r"), "\n", $string($data['textContent'] ?? '')),
        'html'    => $string($data['textContentHtml'] ?? ''),
        'list'    => $list,
        'created' => ($created > 0) ? $created : $updated,
        'updated' => $updated,
        'tags'    => $tags,
        'files'   => $files,
        'links'   => $links,
        'trashed' => !empty($data['isTrashed']),
    );
}

/**
 * The title a note is brought in under: its own, else the first line of its
 * text or checklist (Keep shows an untitled note by its text), cut to
 * WS_NOTE_KEEP_TITLE_MAX letters; '' when it has neither.
 *
 * @param array $note ws_note_keep_parse()
 * @return string
 */
function ws_note_keep_title($note)
{
    $title = trim((string) ($note['title'] ?? ''));

    if ($title === '') {
        $lines = explode("\n", (string) ($note['text'] ?? ''));

        foreach ((array) ($note['list'] ?? array()) as $item) {
            $lines[] = (string) $item['text'];
        }

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line));

            if ($line !== '') {
                $title = $line;
                break;
            }
        }
    }

    return (mb_strlen($title) > WS_NOTE_KEEP_TITLE_MAX) ? rtrim(mb_substr($title, 0, WS_NOTE_KEEP_TITLE_MAX)) : $title;
}

/**
 * A note's text as Markdown: the rich text through the ENML reader when the
 * export has it, else the plain text as written; then the checklist, then
 * the web links Keep found (http and https only).
 *
 * @param array $note ws_note_keep_parse()
 * @return string
 */
function ws_note_keep_body($note)
{
    $parts = array();
    $text = '';

    if (trim(strip_tags((string) $note['html'])) !== '') {
        // Keep writes every line of the text as a <p>; read as paragraphs,
        // each would be set apart by an empty line. A <div> is a line.
        $markdown = ws_note_enex_markdown(preg_replace('#<(/?)p(?=[\s>])#i', '<$1div', (string) $note['html']));
        $text = $markdown['text'];
    }

    if (trim($text) === '') {
        $text = trim((string) $note['text'], "\n");
    }

    if (trim($text) !== '') {
        $parts[] = rtrim($text);
    }

    $items = array();

    foreach ((array) $note['list'] as $item) {
        $words = trim(preg_replace('/\s+/u', ' ', (string) $item['text']));

        if ($words !== '') {
            $items[] = '- [' . ($item['checked'] ? 'x' : ' ') . '] ' . $words;
        }
    }

    if (!empty($items)) {
        $parts[] = implode("\n", $items);
    }

    $links = array();

    foreach ((array) $note['links'] as $link) {
        if (!preg_match('#^https?://#i', (string) $link['url'])) {
            continue;
        }

        $label = ($link['title'] !== '') ? $link['title'] : $link['url'];
        $links[] = '[' . str_replace(array('[', ']'), array('(', ')'), $label) . '](' . ws_note_enex_url($link['url']) . ')';
    }

    if (!empty($links)) {
        $parts[] = implode("\n", $links);
    }

    return implode("\n\n", $parts);
}
