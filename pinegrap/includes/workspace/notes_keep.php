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

/* ---------------------------------------------------------------------------
   The Takeout zip laid out in the stage.
   --------------------------------------------------------------------------- */

/**
 * The entry of the zip an attachment of a note names: the path beside the
 * note, in any case, else a file of the same name with another extension in
 * that folder (Takeout names a picture .jpeg in the note and .jpg in the zip,
 * or the other way round).
 *
 * @param array  $lookup path, lower and stem maps of the zip's entries
 * @param string $dir    the folder the note sits in
 * @param string $path   the attachment's filePath
 * @return array|null the entry: name, size, time, index
 */
function ws_note_keep_attachment($lookup, $dir, $path)
{
    $full = ws_note_import_path($dir, $path);

    if ($full === '') {
        return null;
    }

    if (isset($lookup['path'][$full])) {
        return $lookup['path'][$full];
    }

    if (isset($lookup['lower'][mb_strtolower($full)])) {
        return $lookup['lower'][mb_strtolower($full)];
    }

    foreach ($lookup['stem'][mb_strtolower(ws_note_import_dir_of($full) . '/' . pg_file_name_stem($full))] ?? array() as $row) {
        if (!in_array(strtolower((string) pathinfo((string) $row[0], PATHINFO_EXTENSION)), array('json', 'html'), true)) {
            return $row;
        }
    }

    return null;
}

/**
 * Lays out the Keep notes of an uploaded zip in its stage: every note a
 * Markdown file, every attachment a file of its own, as the files of an
 * upload. A zip without a Keep note is left alone (keep false) and read as
 * an upload of Markdown files; one with Keep notes is read for them only.
 *
 * Attachments are copied out of the zip only up to $bytes_max in all, the
 * bytes one import may link: a small zip can name one large, well packed
 * picture any number of times, and each name would copy it out again.
 *
 * @param string     $dir       the stage folder (its files/ is made here)
 * @param ZipArchive $archive   the uploaded zip, open
 * @param array      $entries   the zip's entries as ws_note_import_stage()
 *                              lists them: name, size, time, index
 * @param int|null   $bytes_max attachment bytes copied in all; null for
 *                              WS_NOTE_IMPORT_FILES_BYTES
 * @return array keep, ok, error, entries (as ws_note_import_stage() keeps
 *               them; an attachment's row carries a fifth value, 1)
 */
function ws_note_keep_stage($dir, $archive, $entries, $bytes_max = null)
{
    $out = array('keep' => false, 'ok' => false, 'error' => '', 'entries' => array());
    $lookup = array('path' => array(), 'lower' => array(), 'stem' => array());
    $notes = array();

    foreach ($entries as $row) {
        $path = (string) $row[0];

        $lookup['path'][$path] = $row;
        $lookup['lower'][mb_strtolower($path)] = $row;
        $lookup['stem'][mb_strtolower(ws_note_import_dir_of($path) . '/' . pg_file_name_stem($path))][] = $row;
    }

    foreach ($entries as $row) {
        if ((strtolower((string) pathinfo((string) $row[0], PATHINFO_EXTENSION)) !== 'json') || ((int) $row[1] > WS_NOTE_IMPORT_TEXT_MAX)) {
            continue;
        }

        $error = '';
        $bytes = pg_zip_entry_contents($archive, (int) $row[3], WS_NOTE_IMPORT_TEXT_MAX, $error);
        $note = is_string($bytes) ? ws_note_keep_parse($bytes) : null;

        if ($note) {
            $notes[] = array($row, $note);
        }
    }

    if (empty($notes)) {
        return $out;
    }

    $out['keep'] = true;

    if (!is_dir($dir . '/files') && !@mkdir($dir . '/files', 0755)) {
        ws_note_import_log_failure('creating the files folder of a Google Keep import');
        $out['error'] = lang('The file could not be saved.');

        return $out;
    }

    $context = array(
        'dir'       => $dir,
        'next'      => 0,
        'notes'     => 0,
        'taken'     => array(),
        'entries'   => array(),
        'file_max'  => function_exists('ws_upload_max_bytes') ? ws_upload_max_bytes() : 20971520,
        'bytes'     => 0,
        'bytes_max' => ($bytes_max !== null) ? (int) $bytes_max : WS_NOTE_IMPORT_FILES_BYTES,
        'error'     => '',
    );

    foreach ($notes as $pair) {
        if ($pair[1]['trashed']) {
            continue;
        }

        if (!ws_note_keep_add($archive, $lookup, $pair[0], $pair[1], $context)) {
            $out['error'] = $context['error'];

            return $out;
        }
    }

    if ($context['notes'] === 0) {
        $out['error'] = lang('The Google Keep export holds no notes outside the bin.');

        return $out;
    }

    $out['ok'] = true;
    $out['entries'] = $context['entries'];

    return $out;
}

/**
 * One Keep note added to the stage: its attachments copied out of the zip
 * into attachments/<n>/, linked at the end of its text, and the Markdown
 * file. An attachment the zip lacks keeps its link, which the import reports
 * as missing; one past what a note keeps is counted only, as for a zip.
 *
 * @param ZipArchive $archive
 * @param array      $lookup  ws_note_keep_stage()'s maps of the entries
 * @param array      $row     the note's entry
 * @param array      $note    ws_note_keep_parse()
 * @param array      $context ws_note_keep_stage()'s; error is set when false is returned
 * @return bool
 */
function ws_note_keep_add($archive, $lookup, $row, $note, &$context)
{
    if ((count($context['entries']) + 1 + count($note['files'])) > WS_NOTE_IMPORT_ENTRIES_MAX) {
        $context['error'] = lang(array('string' => 'The export holds more than {var:1} notes and attachments.', 'vars' => WS_NOTE_IMPORT_ENTRIES_MAX));

        return false;
    }

    $number = ++$context['notes'];
    $title = ws_note_keep_title($note);
    $name = ws_note_export_name($title, $number, $context['taken']) . '.md';
    $time = ($note['updated'] > 0) ? $note['updated'] : (($note['created'] > 0) ? $note['created'] : (int) $row[2]);
    $folder = 'attachments/' . $number . '/';
    $dir = ws_note_import_dir_of((string) $row[0]);
    $taken = array();
    $links = array();
    $rows = array();

    foreach ($note['files'] as $index => $file) {
        $file_name = ws_note_enex_file_name($file['path'], $file['mime'], $index + 1, $taken);
        $links[] = '[' . $file_name . '](' . ws_note_import_link_path($folder . $file_name) . ')';
        $source = ws_note_keep_attachment($lookup, $dir, $file['path']);

        if (!$source) {
            continue;
        }

        // Past what a note keeps, or past what the whole import may copy:
        // counted only.
        if (((int) $source[1] > $context['file_max']) || (($context['bytes'] + (int) $source[1]) > $context['bytes_max'])) {
            $rows[] = array($folder . $file_name, (int) $source[1], $time, $context['next']++, 1);
            continue;
        }

        $error = '';
        $bytes = pg_zip_entry_contents($archive, (int) $source[3], $context['file_max'], $error);

        if (!is_string($bytes)) {
            continue;
        }

        $file_number = $context['next']++;

        if (@file_put_contents($context['dir'] . '/files/' . $file_number . '.txt', $bytes) === false) {
            ws_note_import_log_failure('writing an attachment of a Google Keep import');
            $context['error'] = lang('The file could not be saved.');

            return false;
        }

        $context['bytes'] += strlen($bytes);
        $rows[] = array($folder . $file_name, strlen($bytes), $time, $file_number, 1);
    }

    $body = ws_note_keep_body($note);

    if (!empty($links)) {
        $body = ltrim($body . "\n\n" . implode("\n", $links));
    }

    $text = ws_note_enex_file(array('title' => $title, 'created' => $note['created'], 'updated' => $note['updated'], 'tags' => $note['tags']), $body);
    $file_number = $context['next']++;

    if (@file_put_contents($context['dir'] . '/files/' . $file_number . '.txt', $text) === false) {
        ws_note_import_log_failure('writing a note of a Google Keep import');
        $context['error'] = lang('The file could not be saved.');

        return false;
    }

    $context['entries'][] = array($name, strlen($text), $time, $file_number);

    foreach ($rows as $attachment) {
        $context['entries'][] = $attachment;
    }

    return true;
}
