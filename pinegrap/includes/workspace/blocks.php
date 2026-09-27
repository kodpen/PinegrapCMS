<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - blocks with a title: a table, a checklist, a block of code or a
 * calculation that carries a line "::: Title" right above it. The title is
 * its caption where it is shown, and the way to find it again: the writing
 * box of a channel or a note can pull a titled block written anywhere the
 * person can read ("Add > From elsewhere", or # and its title), as a copy
 * with a line saying where it came from.
 *
 * ws_blocks is the index of the titled blocks: where each is (a message or a
 * note, and which block of it), its kind, its title and who wrote it. The
 * words stay in the message or the note; a pull reads them from there, as
 * they are then, and only for somebody who may read them.
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
 * Has the database the index of titled blocks (2026.4.5, 5.88)?
 *
 * @return bool
 */
function ws_blocks_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('ws_blocks', 'title');
    }

    return $ready;
}

/**
 * A title line: "::: Title".
 *
 * @param string $line
 * @return string|null the title
 */
function ws_block_title_line($line)
{
    return preg_match('/^\s*:::\s+(\S.*)$/u', (string) $line, $match) ? mb_substr(trim($match[1]), 0, 160) : null;
}

/**
 * Does a block start on this line: a fence, a table (its header and the row
 * of dashes), a checklist item?
 *
 * @param string[] $lines
 * @param int      $index
 * @return bool
 */
function ws_block_starts($lines, $index)
{
    if (!isset($lines[$index])) {
        return false;
    }

    $line = $lines[$index];

    if (ws_code_fence_open($line) !== null) {
        return true;
    }

    if (preg_match('/^\s*\|.*\|\s*$/', $line) && isset($lines[$index + 1]) && (ws_table_alignments($lines[$index + 1]) !== null)) {
        return true;
    }

    return (bool) preg_match('/^\s*[-*]\s\[( |x|X)\]\s+(.+)$/u', $line);
}

/**
 * The titled blocks of a text, in their order.
 *
 * @param string $body
 * @return array[] position, kind (table | checklist | code | calc), title, markup (the title line and the block)
 */
function ws_blocks_extract($body)
{
    $body = (string) $body;

    if (strpos($body, ':::') === false) {
        return array();
    }

    $lines = preg_split('/\r\n|\r|\n/', $body);
    $count = count($lines);
    $out = array();

    for ($i = 0; $i < $count; $i++) {
        $title = ws_block_title_line($lines[$i]);

        if (($title === null) || !ws_block_starts($lines, $i + 1)) {
            continue;
        }

        $start = $i + 1;
        $line = $lines[$start];
        $end = $start;
        $language = ws_code_fence_open($line);

        if ($language !== null) {
            $kind = (function_exists('ws_calc_block_language') && ws_calc_block_language($language)) ? 'calc' : 'code';
            $end = $start + 1;

            while (($end < $count) && !ws_code_fence_close($lines[$end])) {
                $end++;
            }
        } elseif (preg_match('/^\s*\|.*\|\s*$/', $line)) {
            $kind = 'table';
            $end = $start + 2;

            while (($end < $count) && preg_match('/^\s*\|.*\|\s*$/', $lines[$end])) {
                $end++;
            }

            $end--;
        } else {
            $kind = 'checklist';

            while ((($end + 1) < $count) && preg_match('/^\s*[-*]\s\[( |x|X)\]\s+(.+)$/u', $lines[$end + 1])) {
                $end++;
            }
        }

        $end = min($end, $count - 1);

        $out[] = array(
            'position' => count($out),
            'kind'     => $kind,
            'title'    => $title,
            'markup'   => implode("\n", array_slice($lines, $i, $end - $i + 1)),
        );

        $i = $end;
    }

    return $out;
}

/**
 * Brings the index in step with a message or a note: its titled blocks as
 * they are now, none when it is gone.
 *
 * @param string $kind      message | note
 * @param int    $source_id
 * @param string $body      '' when it is gone
 * @param int    $author_id
 * @param int    $channel_id for a message
 */
function ws_blocks_index($kind, $source_id, $body, $author_id = 0, $channel_id = 0)
{
    if (!ws_blocks_ready() || !in_array($kind, array('message', 'note'), true)) {
        return;
    }

    $had = (int) db_value("SELECT COUNT(*) FROM ws_blocks WHERE source_kind = '" . e($kind) . "' AND source_id = '" . (int) $source_id . "'");
    $blocks = ws_blocks_extract($body);

    // Nearly every text has none and had none: nothing to write.
    if (($had === 0) && empty($blocks)) {
        return;
    }

    db("DELETE FROM ws_blocks WHERE source_kind = '" . e($kind) . "' AND source_id = '" . (int) $source_id . "'");

    $now = time();

    foreach (array_slice($blocks, 0, 50) as $block) {
        db("INSERT INTO ws_blocks (source_kind, source_id, channel_id, position, kind, title, author_id, updated_at)
            VALUES ('" . e($kind) . "', '" . (int) $source_id . "', '" . (int) $channel_id . "', '" . (int) $block['position'] . "',
                '" . e($block['kind']) . "', '" . e($block['title']) . "', '" . (int) $author_id . "', '" . $now . "')");
    }
}

/**
 * Where a titled block is, for this person: the message or note it is in,
 * readable, and the block as it is now. Null for one they may not read.
 *
 * @param array $viewer
 * @param array $row a ws_blocks row
 * @return array|null markup, where, author, time, url
 */
function ws_block_source($viewer, $row)
{
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

    if ($row['source_kind'] === 'message') {
        $message = ws_message((int) $row['source_id']);

        if (!$message || ((int) $message['deleted_at'] > 0)) {
            return null;
        }

        $channel = ws_channel((int) $message['channel_id']);

        if (!$channel || !ws_can_read_channel($viewer, $channel)) {
            return null;
        }

        if (function_exists('ws_message_hidden_ids') && !empty(ws_message_hidden_ids($viewer['id'], array((int) $message['id'])))) {
            return null;
        }

        $body = (string) $message['body'];
        $where = '#' . $channel['name'];
        $time = (int) $message['created_at'];
        $url = $base . 'workspace.php?channel=' . (int) $channel['id'] . '&message=' . (int) $message['id'];
    } else {
        $note = function_exists('ws_note') ? ws_note((int) $row['source_id']) : null;

        if (!$note || (ws_note_access($viewer, $note) === '')) {
            return null;
        }

        $body = (string) $note['body'];
        $where = ws_note_title($viewer, $note);
        $time = (int) $note['updated_at'];
        $url = $base . 'workspace_notes.php?note=' . (int) $note['id'];
    }

    $blocks = ws_blocks_extract($body);
    $block = $blocks[(int) $row['position']] ?? null;

    // The text changed since it was indexed and the block moved: by title.
    if (!$block || ($block['title'] !== (string) $row['title'])) {
        $block = null;

        foreach ($blocks as $candidate) {
            if ($candidate['title'] === (string) $row['title']) {
                $block = $candidate;
                break;
            }
        }
    }

    if (!$block) {
        return null;
    }

    return array(
        'markup' => $block['markup'],
        'where'  => $where,
        'author' => ws_person_name($row['author_id']),
        'time'   => ws_time_label($time),
        'url'    => $url,
    );
}

/**
 * The titled blocks this person can read whose title matches, newest first.
 *
 * @param array  $viewer
 * @param string $query
 * @param int    $limit
 * @return array[] id, kind, title, where, author, time
 */
function ws_blocks_search($viewer, $query, $limit = 20)
{
    if (!ws_blocks_ready()) {
        return array();
    }

    $query = trim(mb_substr((string) $query, 0, 100));
    $where = ($query !== '') ? " WHERE title LIKE '%" . e(addcslashes($query, '%_\\')) . "%'" : '';
    $out = array();

    // A few times the number wanted, as some are in places the person does
    // not read; each is checked as it would be opened.
    foreach ((array) db_items("SELECT * FROM ws_blocks" . $where . " ORDER BY updated_at DESC, id DESC LIMIT " . (max(1, (int) $limit) * 5)) as $row) {
        $source = ws_block_source($viewer, $row);

        if ($source === null) {
            continue;
        }

        $out[] = array(
            'id'     => (int) $row['id'],
            'kind'   => (string) $row['kind'],
            'title'  => (string) $row['title'],
            'where'  => $source['where'],
            'author' => $source['author'],
            'time'   => $source['time'],
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * One titled block to pull into a text: its markup and the line that says
 * where it came from.
 *
 * @param array $viewer
 * @param int   $block_id
 * @return array ok, error, kind, markup, from
 */
function ws_block_pull($viewer, $block_id)
{
    $row = ws_blocks_ready() ? db_item("SELECT * FROM ws_blocks WHERE id = '" . (int) $block_id . "'") : null;
    $source = is_array($row) ? ws_block_source($viewer, $row) : null;

    if (!$source) {
        return array('ok' => false, 'error' => lang('That block could not be found.'), 'kind' => '', 'markup' => '', 'from' => '');
    }

    return array(
        'ok'     => true,
        'error'  => '',
        // The writing box knows tables, checklists and fenced blocks.
        'kind'   => in_array($row['kind'], array('code', 'calc'), true) ? 'code' : (string) $row['kind'],
        'markup' => $source['markup'],
        'from'   => lang(array('string' => 'From {var:1} · {var:2} · {var:3}', 'vars' => array($source['where'], $source['author'], $source['time']))),
    );
}

/**
 * The words the screen needs for titled blocks.
 *
 * @return array
 */
function ws_blocks_js_strings()
{
    return array(
        'block_title'       => lang('Title'),
        'block_title_help'  => lang('Optional: with a title it can be found and pulled into another conversation or note.'),
        'pull_title'        => lang('From elsewhere'),
        'pull_help'         => lang('Tables, checklists, code and calculations with a title, from the channels and notes you read. The one you choose goes in as a copy, with where it came from.'),
        'pull_search'       => lang('Search by title'),
        'pull_none'         => lang('No titled block found.'),
        'pull_kind_table'   => lang('Table'),
        'pull_kind_checklist' => lang('Checklist'),
        'pull_kind_code'    => lang('Code'),
        'pull_kind_calc'    => lang('Calculation'),
        'pull_hash_group'   => lang('Titled blocks'),
    );
}
