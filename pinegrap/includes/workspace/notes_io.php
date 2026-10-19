<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - notes going out to other note apps.
 *
 * A note leaves as Markdown with a front matter block, the way Obsidian,
 * Joplin, Bear and Notion read a file: the body as the note keeps it, with
 * the tags written out for the reader who exported (a record as a link to
 * its screen, a person as @name), and the note's own files beside it in
 * attachments/. "Export all" is a zip of every note the person owns and
 * every note shared with them, which opens as a vault. The screen that sends
 * the files is workspace_notes_export.php.
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

        if ($match[1] === '@') {
            return '@' . $label;
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
