<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - notes going out to other note apps, and coming in from them.
 *
 * A note leaves as Markdown with a front matter block, the way Obsidian,
 * Joplin, Bear and Notion read a file: the body as the note keeps it, with
 * the tags written out for the reader who exported (a record as a link to
 * its screen, a person as @name), and the note's own files beside it in
 * attachments/. "Export all" is a zip of every note the person owns and
 * every note shared with them, which opens as a vault. The screen that sends
 * the files is workspace_notes_export.php.
 *
 * A Markdown or text file comes in as the workspace writes a note: headings
 * as bold lines, every list item as "- ", *italic* as _italic_, a block of
 * LaTeX no longer taken for a calculation, and nothing in the text read as a
 * tag. The functions here only read text; staging an upload and writing the
 * notes is includes/workspace/notes_import.php.
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
 * The longest file name a note gets in a zip, in characters.
 */
define('WS_NOTE_EXPORT_NAME_MAX', 80);

/**
 * A value as a YAML double-quoted scalar: backslashes, quotes and control
 * characters escaped, so a title with a colon or a hash reads back whole.
 *
 * @param mixed $value
 * @return string with its quotes
 */
function ws_note_export_yaml($value)
{
    $text = (string) $value;
    $text = str_replace(array('\\', '"'), array('\\\\', '\\"'), $text);
    $text = preg_replace_callback('/[\x00-\x1F]/', function ($match) {
        $map = array("\n" => '\\n', "\r" => '\\r', "\t" => '\\t');

        return $map[$match[0]] ?? sprintf('\\x%02X', ord($match[0]));
    }, $text);

    return '"' . $text . '"';
}

/**
 * The file name a note gets, from its title: what a desktop and a note app
 * both accept, the note's id only when two titles would collide.
 *
 * @param string $title
 * @param int    $id
 * @param array  $taken file names already given, lower-cased, as keys
 * @return string without the extension
 */
function ws_note_export_name($title, $id, &$taken = array())
{
    $name = preg_replace('/\s*[\\\\\/:*?"<>|\x00-\x1F]+\s*/u', ' - ', (string) $title);
    $name = trim(preg_replace('/\s+/u', ' ', $name), ' .-');

    if ($name === '') {
        $name = 'note-' . (int) $id;
    }

    if (mb_strlen($name) > WS_NOTE_EXPORT_NAME_MAX) {
        $name = rtrim(mb_substr($name, 0, WS_NOTE_EXPORT_NAME_MAX));
    }

    $key = mb_strtolower($name);

    if (isset($taken[$key])) {
        $name .= ' (' . (int) $id . ')';
        $key = mb_strtolower($name);
    }

    $taken[$key] = true;

    return $name;
}

/**
 * The tags of a text written out for a reader outside the workspace: a
 * record as a Markdown link to its screen when the reader may open it, its
 * name alone when not; a person, a department or an application as @name.
 *
 * @param string $body
 * @param array  $refs ws_refs_resolve() output for the text's tokens
 * @param string $base scheme and host put before a link that starts at the
 *                     site's root, so it opens from outside the site
 * @return string
 */
function ws_note_export_tokens($body, $refs, $base = '')
{
    return preg_replace_callback('/<([@#])(' . ws_token_types_pattern() . '):([0-9]{1,10})>/', function ($match) use ($refs, $base) {
        $ref = $refs[$match[2] . ':' . $match[3]] ?? null;
        $label = $ref ? trim((string) $ref['label']) : '';

        if ($label === '') {
            return '';
        }

        // A person's, a department's or an application's name carries its @
        // already (ws_refs_resolve()).
        if ($match[1] === '@') {
            return '@' . ltrim($label, '@');
        }

        $label = str_replace(array('[', ']'), array('(', ')'), $label);
        $url = (string) $ref['url'];

        if (($url !== '') && ($url[0] === '/') && ($base !== '')) {
            $url = rtrim($base, '/') . $url;
        }

        return ($url !== '') ? '[' . $label . '](' . $url . ')' : $label;
    }, (string) $body);
}

/**
 * The note's own files, linked as [name](absolute url) in its text, pointed
 * at the copies beside it in the zip.
 *
 * @param string $body
 * @param array  $map absolute url => path inside the zip
 * @return string
 */
function ws_note_export_attachment_links($body, $map)
{
    if (empty($map)) {
        return (string) $body;
    }

    return preg_replace_callback('#\]\(((?:https?://)[^\s()<>"]{1,1000})\)#i', function ($match) use ($map) {
        $url = $match[1];

        if (!isset($map[$url])) {
            return $match[0];
        }

        return '](' . str_replace(array(' ', '(', ')'), array('%20', '%28', '%29'), $map[$url]) . ')';
    }, (string) $body);
}

/**
 * One note as a Markdown file: the front matter, then the body.
 *
 * @param array  $viewer
 * @param array  $note        the ws_notes row
 * @param array  $refs        ws_refs_resolve() output for the note's tokens
 * @param array  $attachments absolute url => path inside the zip, or empty
 * @param array  $extra       more front matter lines: key => value
 * @return string
 */
function ws_note_export_markdown($viewer, $note, $refs, $attachments = array(), $extra = array())
{
    $lines = array('---');
    $lines[] = 'title: ' . ws_note_export_yaml(ws_note_title($viewer, $note));
    $lines[] = 'created: ' . date('c', (int) $note['created_at']);
    $lines[] = 'updated: ' . date('c', (int) $note['updated_at']);

    $author = function_exists('ws_person_name') ? ws_person_name((int) $note['user_id']) : '';

    if ($author !== '') {
        $lines[] = 'author: ' . ws_note_export_yaml($author);
    }

    foreach ($extra as $key => $value) {
        if ((string) $value !== '') {
            $lines[] = $key . ': ' . ws_note_export_yaml($value);
        }
    }

    // The note's own number on this site, for a later import to know it.
    $lines[] = 'pinegrap_note: ' . (int) $note['id'];
    $lines[] = '---';

    $body = rtrim(str_replace("\r\n", "\n", (string) $note['body']));
    $body = ws_note_export_tokens($body, $refs, (defined('URL_SCHEME') && defined('HOSTNAME_SETTING')) ? URL_SCHEME . HOSTNAME_SETTING : '');
    $body = ws_note_export_attachment_links($body, $attachments);

    return implode("\n", $lines) . "\n\n" . $body . "\n";
}

/**
 * Where a note came from, for the front matter: the channel it was kept
 * from, when the reader may still read that channel.
 *
 * @param array $viewer
 * @param array $note
 * @return string
 */
function ws_note_export_source($viewer, $note)
{
    if ((int) ($note['source_channel_id'] ?? 0) <= 0) {
        return '';
    }

    $channel = ws_channel((int) $note['source_channel_id']);

    return ($channel && ws_can_read_channel($viewer, $channel)) ? '#' . $channel['name'] : '';
}

/**
 * The notes a person takes with them: every note they own and every note
 * shared with them, however many (ws_notes_list() shows the latest 500 of
 * each on the screen; an export is not cut).
 *
 * @param array $viewer
 * @return array mine, shared
 */
function ws_note_export_rows($viewer)
{
    $me = (int) $viewer['id'];

    $mine = (array) db_items("SELECT * FROM ws_notes WHERE user_id = '" . $me . "' ORDER BY pinned DESC, updated_at DESC, id DESC");

    $shared = (array) db_items("SELECT n.* FROM ws_notes n
        INNER JOIN ws_note_shares s ON s.note_id = n.id AND s.user_id = '" . $me . "'
        WHERE n.user_id <> '" . $me . "'
        GROUP BY n.id
        ORDER BY n.updated_at DESC, n.id DESC");

    return array('mine' => $mine, 'shared' => $shared);
}

/**
 * The files a note keeps in its folder, as the zip will carry them.
 *
 * @param array $note
 * @return array[] path (on disk), zip (path inside the zip), url (as the note links it)
 */
function ws_note_export_attachments($note)
{
    $out = array();

    if (!function_exists('ws_note_folder_id') || !ws_note_folders_ready()) {
        return $out;
    }

    $folder_id = (int) ws_note_folder_id($note, 0, false);

    if ($folder_id <= 0) {
        return $out;
    }

    foreach ((array) db_items("SELECT name FROM files WHERE folder = '" . $folder_id . "' ORDER BY id") as $file) {
        $name = (string) $file['name'];
        $path = FILE_DIRECTORY_PATH . '/' . $name;

        if (($name === '') || !is_file($path)) {
            continue;
        }

        $out[] = array(
            'path' => $path,
            'zip'  => 'attachments/' . (int) $note['id'] . '/' . $name,
            'url'  => URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($name),
        );
    }

    return $out;
}

/**
 * Writes the person's notes into a zip that opens as a vault: the owned
 * notes at the top, the shared ones under Shared/, every note's files under
 * attachments/<note id>/, the links in the text pointing there.
 *
 * @param array  $viewer
 * @param string $zip_path where the zip is written
 * @return array ok, error, count
 */
function ws_note_export_vault($viewer, $zip_path)
{
    if (!class_exists('ZipArchive')) {
        return array('ok' => false, 'error' => lang('This server cannot make zip files (the PHP zip extension is missing). Export the notes one by one instead.'), 'count' => 0);
    }

    $zip = new ZipArchive();

    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return array('ok' => false, 'error' => lang('The file could not be saved.'), 'count' => 0);
    }

    $rows = ws_note_export_rows($viewer);
    $count = 0;

    foreach (array('mine' => '', 'shared' => 'Shared/') as $set => $folder) {
        $taken = array();

        foreach ($rows[$set] as $note) {
            $refs = ws_refs_resolve($viewer, ws_tokens((string) $note['body']));
            $attachments = ws_note_export_attachments($note);
            $map = array();

            foreach ($attachments as $attachment) {
                $map[$attachment['url']] = '../' . $attachment['zip'];

                if ($folder === '') {
                    $map[$attachment['url']] = $attachment['zip'];
                }

                $zip->addFile($attachment['path'], $attachment['zip']);
            }

            $extra = array('source' => ws_note_export_source($viewer, $note));

            if ($set === 'shared') {
                $extra['shared_by'] = function_exists('ws_person_name') ? ws_person_name((int) $note['user_id']) : '';
            }

            $name = ws_note_export_name(ws_note_title($viewer, $note), (int) $note['id'], $taken);
            $zip->addFromString($folder . $name . '.md', ws_note_export_markdown($viewer, $note, $refs, $map, $extra));
            $count++;
        }
    }

    $zip->close();

    return array('ok' => true, 'error' => '', 'count' => $count);
}

/* ---------------------------------------------------------------------------
   Coming in: a file read as a note. Nothing below touches the database.
   --------------------------------------------------------------------------- */

/**
 * The most a Markdown or text file may weigh to be read as a note, in bytes:
 * well over the longest note (WS_NOTE_MAX characters of up to four bytes).
 */
define('WS_NOTE_IMPORT_TEXT_MAX', 1048576);

/**
 * The words of a file, whatever program saved it: UTF-8 without its byte
 * order mark, UTF-16 converted, anything else read as the Turkish Windows
 * code page; line breaks as LF, control characters other than the tab gone.
 *
 * @param string $bytes
 * @return string
 */
function ws_note_import_text($bytes)
{
    $bytes = (string) $bytes;

    if (strncmp($bytes, "\xEF\xBB\xBF", 3) === 0) {
        $bytes = substr($bytes, 3);
    } elseif ((strncmp($bytes, "\xFF\xFE", 2) === 0) || (strncmp($bytes, "\xFE\xFF", 2) === 0)) {
        $bytes = (string) @mb_convert_encoding(substr($bytes, 2), 'UTF-8', ($bytes[0] === "\xFF") ? 'UTF-16LE' : 'UTF-16BE');
    } elseif (!mb_check_encoding($bytes, 'UTF-8')) {
        // Windows-1254 is Latin-1 with ğ, ı, ş and their capitals where
        // Latin-1 has Icelandic letters; ISO-8859-9 is its older twin.
        $legacy = in_array('Windows-1254', mb_list_encodings(), true) ? 'Windows-1254' : 'ISO-8859-9';
        $bytes = (string) @mb_convert_encoding($bytes, 'UTF-8', $legacy);
    }

    // Whatever is still not UTF-8 becomes a question mark rather than a
    // query that fails.
    if (!mb_check_encoding($bytes, 'UTF-8')) {
        $bytes = (string) mb_convert_encoding($bytes, 'UTF-8', 'UTF-8');
    }

    $text = str_replace(array("\r\n", "\r"), "\n", $bytes);

    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
}

/**
 * One YAML value as a front matter line holds it: a quoted string with its
 * escapes undone, a [flow, list] as an array, a plain word with any comment
 * after it dropped.
 *
 * @param string $value what follows "key:"
 * @return string|string[]
 */
function ws_note_import_yaml_value($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (($value[0] === '"') && preg_match('/^"((?:[^"\\\\]|\\\\.)*)"/su', $value, $match)) {
        return preg_replace_callback('/\\\\(x[0-9A-Fa-f]{2}|u[0-9A-Fa-f]{4}|.)/su', function ($escape) {
            $map = array('n' => "\n", 'r' => "\r", 't' => "\t", '0' => '', '"' => '"', '\\' => '\\', '/' => '/', ' ' => ' ');
            $code = $escape[1];

            if ((strlen($code) > 1) && (($code[0] === 'x') || ($code[0] === 'u'))) {
                return html_entity_decode('&#' . hexdec(substr($code, 1)) . ';', ENT_QUOTES, 'UTF-8');
            }

            return $map[$code] ?? $code;
        }, $match[1]);
    }

    if (($value[0] === "'") && preg_match("/^'((?:[^']|'')*)'/su", $value, $match)) {
        return str_replace("''", "'", $match[1]);
    }

    if (($value[0] === '[') && (substr($value, -1) === ']')) {
        $items = array();

        foreach (str_getcsv(substr($value, 1, -1), ',', '"', '\\') as $item) {
            $item = ws_note_import_yaml_value((string) $item);

            if (is_string($item) && ($item !== '')) {
                $items[] = $item;
            }
        }

        return $items;
    }

    // A plain value ends where a comment starts.
    return trim(preg_replace('/\s+#.*$/u', '', $value));
}

/**
 * The front matter block at the top of a file, and the text under it. A
 * block with a line YAML would not hold is not a front matter: the file then
 * starts with a rule, and is kept whole.
 *
 * @param string $text
 * @return array meta (lower-cased key => string or list), body
 */
function ws_note_import_front_matter($text)
{
    $text = (string) $text;
    $none = array('meta' => array(), 'body' => $text);

    if (!preg_match('/\A---[ \t]*\n(?:(.*?)\n)?(?:---|\.\.\.)[ \t]*(?:\n|\z)/s', $text, $match) || (strlen($match[0]) > 16384)) {
        return $none;
    }

    $meta = array();
    $key = null;

    foreach (explode("\n", (string) ($match[1] ?? '')) as $line) {
        if ((trim($line) === '') || preg_match('/^\s*#/', $line)) {
            continue;
        }

        if (preg_match('/^([A-Za-z0-9_][A-Za-z0-9_ .-]{0,60}?)\s*:(?:[ \t]+(.*))?$/u', $line, $pair)) {
            $key = strtolower(trim($pair[1]));
            $meta[$key] = ws_note_import_yaml_value($pair[2] ?? '');
            continue;
        }

        // "- item" under a key with nothing after its colon.
        if (($key !== null) && preg_match('/^\s*-\s+(.*)$/u', $line, $item)) {
            if (!is_array($meta[$key])) {
                $meta[$key] = ($meta[$key] === '') ? array() : array($meta[$key]);
            }

            $value = ws_note_import_yaml_value($item[1]);

            if (is_string($value) && ($value !== '')) {
                $meta[$key][] = $value;
            }

            continue;
        }

        // A value folded onto the next lines.
        if (($key !== null) && preg_match('/^[ \t]+\S/', $line)) {
            continue;
        }

        return $none;
    }

    return array('meta' => $meta, 'body' => (string) substr($text, strlen($match[0])));
}

/**
 * A moment a front matter names, as a timestamp: an ISO date, a date and
 * time, or seconds (milliseconds too) since 1970. One before 1990 or more
 * than a day ahead was not when a note was written, and reads as none.
 *
 * @param mixed $value
 * @return int 0 when there is none
 */
function ws_note_import_time($value)
{
    if (!is_string($value) && !is_int($value)) {
        return 0;
    }

    $value = trim((string) $value);

    if ($value === '') {
        return 0;
    }

    if (preg_match('/^[0-9]{9,13}$/', $value)) {
        $time = (int) $value;

        if ($time > 99999999999) {
            $time = (int) floor($time / 1000);
        }
    } else {
        $time = strtotime($value);

        if ($time === false) {
            return 0;
        }
    }

    return (($time >= 631152000) && ($time <= time() + 86400)) ? (int) $time : 0;
}

/**
 * The title a file's name gives a note: the name without its extension, and
 * without the 32-character page id Notion puts after it.
 *
 * @param string $path
 * @return string
 */
function ws_note_import_name_title($path)
{
    $name = basename(str_replace('\\', '/', (string) $path));
    $name = preg_replace('/\.(md|markdown|txt)$/i', '', $name);
    $name = preg_replace('/\s+[0-9a-f]{32}$/i', '', $name);

    return trim($name);
}

/**
 * One line of Markdown as the workspace writes it: an image as a link to the
 * file, a link's title dropped, __bold__ as **bold**, *italic* as _italic_,
 * ==highlight== as **bold**, escapes of characters that mean nothing here
 * undone. What is inside `code` is left as it is.
 *
 * @param string $line
 * @return string
 */
function ws_note_import_inline($line)
{
    $kept = array();

    $line = preg_replace_callback('/(`+)(.+?)\1/u', function ($match) use (&$kept) {
        $kept[] = $match[0];

        return "\x01" . (count($kept) - 1) . "\x01";
    }, (string) $line);

    // A line break inside a table cell (Notion writes <br>) keeps the row whole.
    $line = preg_replace('#<br\s*/?>#i', preg_match('/^\s*\|/', $line) ? ' ' : "\n", $line);

    // ![alt](file) is a link to the file; a picture without words is called
    // by its file name.
    $line = preg_replace_callback('/!\[([^\]\n]*)\]\(\s*(<[^<>\n]{1,1000}>|(?:[^\s()<>]|\([^\s()<>]*\))+)(?:\s+"[^"\n]*")?\s*\)/u', function ($match) {
        $label = trim($match[1]);

        if ($label === '') {
            $label = basename(rawurldecode(preg_replace('/[?#].*$/', '', trim($match[2], '<>'))));
        }

        return '[' . (($label !== '') ? $label : 'image') . '](' . $match[2] . ')';
    }, $line);

    // [words](address "title") and [words](<address with spaces>).
    $line = preg_replace_callback('/\[([^\[\]\n]{1,300})\]\(\s*(<[^<>\n]{1,1000}>|(?:[^\s()<>]|\([^\s()<>]*\))+)\s+"[^"\n]*"\s*\)/u', function ($match) {
        return '[' . $match[1] . '](' . $match[2] . ')';
    }, $line);

    $line = preg_replace_callback('/\]\(<([^<>\n]{1,1000})>\)/u', function ($match) {
        return '](' . str_replace(array(' ', '(', ')'), array('%20', '%28', '%29'), $match[1]) . ')';
    }, $line);

    $line = preg_replace('/(?<![\p{L}\p{N}_])__(?=\S)([^_\n]{1,300}?)(?<=\S)__(?![\p{L}\p{N}_])/u', '**$1**', $line);
    $line = preg_replace('/(?<![*\\\\\p{L}\p{N}])\*(?=[^\s*])([^*\n]{1,300}?)(?<=[^\s*\\\\])\*(?![*\p{L}\p{N}])/u', '_$1_', $line);
    $line = preg_replace('/(?<!=)==(?=\S)([^=\n]{1,300}?)(?<=\S)==(?!=)/u', '**$1**', $line);
    $line = preg_replace('/\\\\([#\[\]()>+.!{}-])/', '$1', $line);

    return preg_replace_callback("/\x01([0-9]+)\x01/", function ($match) use ($kept) {
        return $kept[(int) $match[1]] ?? '';
    }, $line);
}

/**
 * A Markdown text written the workspace's way (see ws_render_body(), which
 * draws neither headings nor nested lists): a heading becomes a bold line,
 * a list item marked * or + becomes "- ", a block of code fenced with ~~~
 * gets ``` and loses what follows its language, and a ```math block (LaTeX
 * in the apps that write one) is called latex, since the workspace works a
 * math block out as a calculation.
 *
 * @param string $body
 * @return string
 */
function ws_note_import_convert($body)
{
    $out = array();
    $fence = '';

    foreach (explode("\n", (string) $body) as $line) {
        if ($fence !== '') {
            if (preg_match('/^\s*' . preg_quote($fence[0], '/') . '{' . strlen($fence) . ',}\s*$/', $line)) {
                $out[] = '```';
                $fence = '';
            } else {
                $out[] = $line;
            }

            continue;
        }

        // A fence of backticks has none after it; ```x``` on one line is code
        // inside the text.
        if (preg_match('/^\s{0,3}(`{3,}|~{3,})(.*)$/', $line, $match) && (($match[1][0] === '~') || (strpos($match[2], '`') === false))) {
            $fence = $match[1];
            $language = preg_match('/^\s*([A-Za-z0-9_+#.-]{1,20})/', $match[2], $word) ? $word[1] : '';
            $out[] = '```' . ((strtolower($language) === 'math') ? 'latex' : $language);
            continue;
        }

        // A rule stays a rule (and is not read as a list item below).
        if (preg_match('/^\s{0,3}([-*_])(?:\s*\1){2,}\s*$/', $line, $rule)) {
            $previous = empty($out) ? '' : end($out);

            // Under a line of words, "---" underlines a heading.
            if (($rule[1] === '-') && (trim($previous) !== '') && !preg_match('/^\s*([-*+|>]|\d+[.)]|\*\*)/', $previous)) {
                $out[count($out) - 1] = '**' . trim(str_replace('**', '', $previous)) . '**';
                continue;
            }

            $out[] = '---';
            continue;
        }

        if (preg_match('/^\s{0,3}=+\s*$/', $line) && !empty($out) && (trim(end($out)) !== '') && !preg_match('/^\s*([-*+|>]|\d+[.)]|\*\*)/', end($out))) {
            $out[count($out) - 1] = '**' . trim(str_replace('**', '', end($out))) . '**';
            continue;
        }

        if (preg_match('/^\s{0,3}#{1,6}(?:[ \t]+(.*?))?(?:[ \t]+#+)?[ \t]*$/u', $line, $heading)) {
            $words = trim(str_replace('**', '', (string) ($heading[1] ?? '')));
            $out[] = ($words !== '') ? '**' . ws_note_import_inline($words) . '**' : '';
            continue;
        }

        $line = preg_replace('/^(\s*)[*+](\s+)/', '$1-$2', $line);
        $out[] = ws_note_import_inline($line);
    }

    return implode("\n", $out);
}

/**
 * Text shaped like the workspace's own tags (<@user:12>, <#order:5>) is the
 * file's words, not a tag: the angle brackets go, so nobody is mentioned and
 * no record is pointed at by a file.
 *
 * @param string $body
 * @return string
 */
function ws_note_import_untag($body)
{
    return preg_replace('/<([@#](?:' . ws_token_types_pattern() . '):[0-9]{1,10})>/', '$1', (string) $body);
}

/**
 * A file read as a note: its title, its text written the workspace's way,
 * its dates, and the note it was exported from when it was one of ours.
 *
 * A file exported from here (it carries pinegrap_note) keeps its text as it
 * is; so does a .txt. The title is the front matter's, or a first-level
 * heading on the first line (taken out of the text), or the file's name; a
 * heading that repeats the front matter's title is taken out too.
 *
 * @param string $text  ws_note_import_text() output
 * @param string $path  where the file sat in the upload
 * @param int    $mtime when the file says it was last changed, 0 if unknown
 * @return array title, body, created, updated, origin (a note id, 0 if
 *               none), origin_created (the created time it was exported
 *               with), named (the title is the file's name)
 */
function ws_note_import_parse($text, $path, $mtime = 0)
{
    $split = ws_note_import_front_matter($text);
    $meta = $split['meta'];
    $body = $split['body'];
    $pick = function ($keys) use ($meta) {
        foreach ($keys as $key) {
            if (isset($meta[$key]) && is_string($meta[$key]) && (trim($meta[$key]) !== '')) {
                return trim($meta[$key]);
            }
        }

        return '';
    };

    $origin = $pick(array('pinegrap_note'));
    $origin = ctype_digit($origin) ? (int) $origin : 0;
    $title = $pick(array('title'));

    if (($origin === 0) && (strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION)) !== 'txt')) {
        if (preg_match('/\A(?:[ \t]*\n)*[ \t]{0,3}#[ \t]+(.+?)(?:[ \t]+#+)?[ \t]*(?:\n|\z)/u', $body, $heading)) {
            $words = trim(str_replace('**', '', $heading[1]));

            if (($title === '') || (mb_strtolower($words) === mb_strtolower($title))) {
                $title = ($title === '') ? $words : $title;
                $body = (string) substr($body, strlen($heading[0]));
            }
        }

        $body = ws_note_import_convert($body);

        // Tags kept in the front matter (Obsidian, Joplin, Bear) read on as
        // the #words those apps also write inside a note.
        $tags = $meta['tags'] ?? ($meta['tag'] ?? array());
        $tags = is_array($tags) ? $tags : preg_split('/[\s,]+/u', (string) $tags);
        $words = array();

        foreach ($tags as $tag) {
            $tag = preg_replace('/[^\p{L}\p{N}_\/-]+/u', '', ltrim((string) $tag, '#'));

            if ($tag !== '') {
                $words[] = '#' . $tag;
            }
        }

        if (!empty($words)) {
            $body = rtrim($body) . "\n\n" . implode(' ', array_unique($words));
        }
    }

    $named = ($title === '');

    if ($named) {
        $title = ws_note_import_name_title($path);
    }

    $created = ws_note_import_time($meta['created'] ?? ($meta['date'] ?? ($meta['created_at'] ?? ($meta['creation date'] ?? ''))));
    $updated = ws_note_import_time($meta['updated'] ?? ($meta['modified'] ?? ($meta['updated_at'] ?? ($meta['last modified'] ?? ''))));
    $mtime = ((int) $mtime > 0) ? ws_note_import_time((string) (int) $mtime) : 0;

    $created = ($created > 0) ? $created : $mtime;
    $updated = ($updated > 0) ? $updated : (($mtime > 0) ? $mtime : $created);

    return array(
        'title'          => mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $title)), 0, 200),
        'body'           => rtrim(preg_replace('/\A(?:[ \t]*\n)+/', '', ws_note_import_untag($body))),
        'created'        => $created,
        'updated'        => max($created, $updated),
        'origin'         => $origin,
        'origin_created' => ($origin > 0) ? ws_note_import_time($meta['created'] ?? '') : 0,
        'named'          => $named,
    );
}

/**
 * A path inside the upload, from a link written in a file that sits in $dir:
 * ./ and ../ worked out, a leading / read from the top of the upload.
 *
 * @param string $dir    the folder of the file the link is in ('' at the top)
 * @param string $target the link's path, decoded
 * @return string '' when it climbs out of the upload
 */
function ws_note_import_path($dir, $target)
{
    $target = str_replace('\\', '/', (string) $target);
    $parts = (($target !== '') && ($target[0] === '/')) ? array() : array_values(array_filter(explode('/', (string) $dir), 'strlen'));

    foreach (explode('/', $target) as $part) {
        if (($part === '') || ($part === '.')) {
            continue;
        }

        if ($part === '..') {
            if (empty($parts)) {
                return '';
            }

            array_pop($parts);
            continue;
        }

        $parts[] = $part;
    }

    return implode('/', $parts);
}

/**
 * A path as a link holds it: what would end the link (a space, a bracket)
 * encoded, the way ws_note_export_attachment_links() writes one.
 *
 * @param string $path
 * @return string
 */
function ws_note_import_link_path($path)
{
    return str_replace(array(' ', '(', ')'), array('%20', '%28', '%29'), (string) $path);
}

/**
 * Every link of a text outside its code, handed to $resolve, which says what
 * it becomes (null: left as written). A link is one of:
 *   wiki - [[Name]], [[Name#Heading|words]], ![[file.png]]: name, label, embed
 *   link - [words](path), a path in the upload (no web or mail address):
 *          label, target as written, path decoded without #part or ?query
 *
 * @param string   $body
 * @param callable $resolve function (array $link): string|null
 * @return string
 */
function ws_note_import_relink($body, $resolve)
{
    $lines = explode("\n", (string) $body);
    $code = false;

    foreach ($lines as $index => $line) {
        if (preg_match('/^\s*```/', $line)) {
            $code = !$code;
            continue;
        }

        if ($code || ((strpos($line, '[') === false))) {
            continue;
        }

        $kept = array();
        $line = preg_replace_callback('/(`+)(.+?)\1/u', function ($match) use (&$kept) {
            $kept[] = $match[0];

            return "\x01" . (count($kept) - 1) . "\x01";
        }, $line);

        $line = preg_replace_callback('/(!?)\[\[([^\[\]\n]{1,300}?)\]\]|\[([^\[\]\n]{0,300})\]\(\s*((?:[^\s()<>]|\([^\s()<>]*\))+)\s*\)/u', function ($match) use ($resolve) {
            if (($match[2] ?? '') !== '') {
                $parts = explode('|', $match[2], 2);
                $name = trim(preg_split('/[#^]/', $parts[0], 2)[0]);
                $label = isset($parts[1]) ? trim($parts[1]) : '';

                if ($name === '') {
                    return $match[0];
                }

                $out = $resolve(array('kind' => 'wiki', 'name' => $name, 'label' => ($label !== '') ? $label : $name, 'embed' => ($match[1] === '!')));

                return ($out === null) ? $match[0] : $out;
            }

            $target = $match[4];

            // A web or mail address, another app's own link, or a place on
            // the same page.
            if (preg_match('/^(?:[A-Za-z][A-Za-z0-9+.-]*:|\/\/|#)/', $target)) {
                return $match[0];
            }

            $path = preg_replace('/[?#].*$/', '', $target);
            $path = (strpos($path, '%') !== false) ? rawurldecode($path) : $path;

            if ($path === '') {
                return $match[0];
            }

            $out = $resolve(array('kind' => 'link', 'label' => $match[3], 'target' => $target, 'path' => $path));

            return ($out === null) ? $match[0] : $out;
        }, $line);

        $lines[$index] = preg_replace_callback("/\x01([0-9]+)\x01/", function ($match) use ($kept) {
            return $kept[(int) $match[1]] ?? '';
        }, $line);
    }

    return implode("\n", $lines);
}

/**
 * The links of a file that sits in $dir pointed from the top of the upload,
 * so every file's links read alike: [plan](../files/a b.pdf) in notes/x.md
 * is [plan](files/a%20b.pdf).
 *
 * @param string $body
 * @param string $dir
 * @return string
 */
function ws_note_import_root_links($body, $dir)
{
    return ws_note_import_relink($body, function ($link) use ($dir) {
        if ($link['kind'] !== 'link') {
            return null;
        }

        $path = ws_note_import_path($dir, $link['path']);

        return ($path === '') ? null : '[' . $link['label'] . '](' . ws_note_import_link_path($path) . ')';
    });
}

/**
 * A file exported from a note, laid back over that note. Every line the file
 * still has as the export wrote it is the note's own line again, with its
 * tags and its file links; a line changed outside keeps its words, and gets
 * back the tags whose written form it still holds (a record's link, @name).
 * A tag that went out as a bare word cannot be told from the word and stays
 * one.
 *
 * @param string  $body      the file's text, links pointed from the top (ws_note_import_root_links())
 * @param string  $original  the note's text as stored
 * @param array   $refs      ws_refs_resolve() output for the note's tokens, for the person importing
 * @param string  $base      scheme and host, as for ws_note_export_tokens()
 * @param array[] $file_maps where the export may have put the note's files:
 *                           each a map of absolute url => path in the upload
 * @return string
 */
function ws_note_import_merge($body, $original, $refs, $base, $file_maps = array())
{
    $original = rtrim(str_replace("\r\n", "\n", (string) $original));
    $lines = array();
    $forms = array();

    foreach (explode("\n", $original) as $line) {
        $written = ws_note_export_tokens($line, $refs, $base);
        $variants = array($written);

        foreach ((array) $file_maps as $files) {
            $variants[] = ws_note_export_attachment_links($written, (array) $files);
        }

        foreach ($variants as $form) {
            if (!isset($lines[$form])) {
                $lines[$form] = $line;
            }
        }
    }

    foreach (ws_tokens($original) as $token) {
        $raw = '<' . $token['sigil'] . $token['type'] . ':' . $token['id'] . '>';
        $written = ws_note_export_tokens($raw, $refs, $base);

        if (($written !== '') && (($written[0] === '[') || ($written[0] === '@'))) {
            $forms[$written] = $raw;
        }
    }

    // The longest form first, so "@Ali Veli" is not read as "@Ali".
    uksort($forms, function ($a, $b) {
        return mb_strlen($b) - mb_strlen($a);
    });

    $pattern = array();

    foreach (array_keys($forms) as $form) {
        $pattern[] = preg_quote($form, '/') . (($form[0] === '@') ? '(?![\p{L}\p{N}_])' : '');
    }

    $out = array();

    foreach (explode("\n", (string) $body) as $line) {
        if (isset($lines[$line])) {
            $out[] = $lines[$line];
        } elseif (!empty($pattern)) {
            $out[] = preg_replace_callback('/' . implode('|', $pattern) . '/u', function ($match) use ($forms) {
                return $forms[$match[0]] ?? $match[0];
            }, $line);
        } else {
            $out[] = $line;
        }
    }

    return implode("\n", $out);
}

/**
 * A path inside an upload as the importer keeps it: forward slashes, no
 * leading slash or ./, UTF-8, no control characters.
 *
 * @param string $name as the zip or the browser gave it
 * @return string
 */
function ws_note_import_entry_name($name)
{
    $name = str_replace('\\', '/', (string) $name);

    if (!mb_check_encoding($name, 'UTF-8')) {
        $name = (string) mb_convert_encoding($name, 'UTF-8', 'UTF-8');
    }

    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);

    return ltrim(preg_replace('#^(?:\./|/)+#', '', $name), '/');
}

/**
 * Is a path of the upload one to look at? Hidden folders and files (.obsidian,
 * .trash, .DS_Store) and the __MACOSX folder a Mac adds to a zip are not.
 *
 * @param string $path
 * @return bool
 */
function ws_note_import_visible($path)
{
    foreach (explode('/', (string) $path) as $part) {
        if (($part !== '') && (($part[0] === '.') || ($part === '__MACOSX'))) {
            return false;
        }
    }

    return true;
}

/**
 * Is a path of the upload read as a note: a Markdown or text file to look at?
 *
 * @param string $path
 * @return bool
 */
function ws_note_import_is_note($path)
{
    return ws_note_import_visible($path) && (bool) preg_match('/\.(md|markdown|txt)$/i', (string) $path);
}
