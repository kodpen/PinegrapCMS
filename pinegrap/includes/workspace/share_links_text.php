<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - share links of a table or a checklist written in a message of
 * an agent channel (share_links.php keeps the links themselves).
 *
 * The item is the n-th table or the n-th checklist of the message's text, as
 * the channel draws them (ws_render_body()): ws_share_links.item_id holds n,
 * counted from 1 for each kind, and the message's id tells two messages'
 * first tables apart. The link reads the text as it is now: an edit shows
 * through, and a block written away closes the link.
 *
 * The address answers a read-only page of the block, tags and record links
 * as plain names; with &raw=1 a table gives CSV (a formula as its value) and
 * a checklist its Markdown with the ticks given since.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most characters of a table's or a checklist's name. */
define('WS_SHARE_LINK_TEXT_NAME_MAX', 80);

/* ---------------------------------------------------------------------------
   Pure rules
   --------------------------------------------------------------------------- */

/**
 * The kinds that are a block of a message's text. Pure.
 *
 * @return string[]
 */
function ws_share_link_text_kinds()
{
    return array('table', 'list');
}

/**
 * The tables and checklists of a message's text in their order, read as
 * ws_render_body() draws a message: a "::: Title" line right above a block
 * is its title, a block of code is skipped whole, a table is its header,
 * the row of dashes and up to 200 rows, a checklist its consecutive items.
 * Pure.
 *
 * @param string $body
 * @return array[] kind (table | list), number (from 1 for each kind), title,
 *                 lines (as written, without the title); a table: rows
 *                 (cells as written, the header first), columns; a list:
 *                 items (index in the message's checklist, checked as
 *                 written, text)
 */
function ws_share_link_text_blocks($body)
{
    $body = (string) $body;

    if (!preg_match('/^\s*(\||[-*]\s\[[ xX]\]\s)/m', $body)) {
        return array();
    }

    $lines = preg_split('/\r\n|\r|\n/', $body);
    $count = count($lines);
    $row_pattern = '/^\s*\|.*\|\s*$/';
    $check_pattern = '/^\s*[-*]\s\[( |x|X)\]\s+(.+)$/u';
    $numbers = array('table' => 0, 'list' => 0);
    $title = '';
    $item = 0;
    $out = array();

    for ($i = 0; $i < $count; $i++) {
        $line = $lines[$i];

        if (function_exists('ws_block_title_line') && (($heading = ws_block_title_line($line)) !== null) && ws_block_starts($lines, $i + 1)) {
            $title = $heading;
            continue;
        }

        if (ws_code_fence_open($line) !== null) {
            $j = $i + 1;

            while (($j < $count) && !ws_code_fence_close($lines[$j])) {
                $j++;
            }

            $title = '';
            $i = $j;
            continue;
        }

        $alignments = (($i + 1) < $count) ? ws_table_alignments($lines[$i + 1]) : null;

        if (preg_match($row_pattern, $line) && ($alignments !== null)) {
            $rows = array(ws_table_cells($line));
            $source = array($line, $lines[$i + 1]);
            $j = $i + 2;

            while (($j < $count) && preg_match($row_pattern, $lines[$j]) && (count($rows) <= 200)) {
                $rows[] = ws_table_cells($lines[$j]);
                $source[] = $lines[$j];
                $j++;
            }

            $numbers['table']++;
            $out[] = array(
                'kind'    => 'table',
                'number'  => $numbers['table'],
                'title'   => $title,
                'lines'   => $source,
                'rows'    => $rows,
                'columns' => min(12, max(count($rows[0]), count($alignments))),
            );
            $title = '';
            $i = $j - 1;
            continue;
        }

        if (preg_match($check_pattern, $line)) {
            $items = array();
            $source = array();

            while (($i < $count) && preg_match($check_pattern, $lines[$i], $match)) {
                $items[] = array('index' => $item, 'checked' => ($match[1] !== ' '), 'text' => trim($match[2]));
                $source[] = $lines[$i];
                $item++;
                $i++;
            }

            $i--;
            $numbers['list']++;
            $out[] = array('kind' => 'list', 'number' => $numbers['list'], 'title' => $title, 'lines' => $source, 'items' => $items);
            $title = '';
        }
    }

    return $out;
}

/**
 * The n-th table or checklist of a text, or null. Pure.
 *
 * @param string $body
 * @param string $kind   table | list
 * @param int    $number from 1
 * @return array|null ws_share_link_text_blocks() block
 */
function ws_share_link_text_block($body, $kind, $number)
{
    foreach (ws_share_link_text_blocks($body) as $block) {
        if (($block['kind'] === (string) $kind) && ($block['number'] === (int) $number)) {
            return $block;
        }
    }

    return null;
}

/**
 * Is the message one whose text is drawn with its tables and checklists: a
 * person's or an application's? A guest's text and a mail are drawn
 * without them. Pure.
 *
 * @param array|null $message ws_messages row
 * @return bool
 */
function ws_share_link_text_message($message)
{
    return is_array($message) && in_array((string) ($message['sender_kind'] ?? ''), array('user', 'app'), true);
}

/**
 * A block's name as it is, before its tags are read: its title, else a
 * table's header cells or a checklist's first item, on one line and cut to
 * WS_SHARE_LINK_TEXT_NAME_MAX. Pure.
 *
 * @param array $block ws_share_link_text_blocks() block
 * @return string '' when nothing names it
 */
function ws_share_link_text_name_source($block)
{
    $name = trim((string) $block['title']);

    if ($name === '') {
        $name = ($block['kind'] === 'table')
            ? implode(' · ', array_filter(array_map('trim', (array) $block['rows'][0]), 'strlen'))
            : (string) ($block['items'][0]['text'] ?? '');
    }

    return trim(preg_replace('/\s+/u', ' ', $name));
}

/**
 * Cuts a name to WS_SHARE_LINK_TEXT_NAME_MAX characters. Pure.
 *
 * @param string $name
 * @return string
 */
function ws_share_link_text_name_cut($name)
{
    $name = trim((string) $name);

    return (mb_strlen($name) > WS_SHARE_LINK_TEXT_NAME_MAX) ? rtrim(mb_substr($name, 0, WS_SHARE_LINK_TEXT_NAME_MAX - 1)) . '…' : $name;
}

/**
 * A table as CSV: its columns as the channel draws them, a formula as its
 * worked-out value, tags as plain names; every cell quoted and guarded
 * against a spreadsheet reading it as a formula. Pure but for the tag
 * labels passed in.
 *
 * @param array $block ws_share_link_text_blocks() table
 * @param array $grid  ws_calc_grid() of its rows, or null
 * @param callable $plain the cell's text with tags as plain names
 * @return string
 */
function ws_share_link_table_csv($block, $grid, $plain)
{
    $out = array();

    foreach ((array) $block['rows'] as $r => $row) {
        $cells = array();

        for ($c = 0; $c < (int) $block['columns']; $c++) {
            $value = (($grid !== null) && !empty($grid[$r][$c]['formula']))
                ? (string) $grid[$r][$c]['display']
                : (string) call_user_func($plain, (string) ($row[$c] ?? ''));
            $cells[] = '"' . csv_cell($value) . '"';
        }

        $out[] = implode(',', $cells);
    }

    return implode("\r\n", $out) . "\r\n";
}

/**
 * A checklist as Markdown: its title as the heading, then each item with
 * the tick given since it was written (or as written). Pure but for the tag
 * labels passed in.
 *
 * @param array    $block  ws_share_link_text_blocks() list
 * @param array    $checks item index => [checked, ...] (ws_checks_map())
 * @param callable $plain  the item's text with tags as plain names
 * @return string
 */
function ws_share_link_list_markdown($block, $checks, $plain)
{
    $out = (trim((string) $block['title']) !== '') ? '# ' . trim((string) $block['title']) . "\n\n" : '';

    foreach ((array) $block['items'] as $item) {
        $state = $checks[(int) $item['index']] ?? null;
        $checked = ($state !== null) ? !empty($state['checked']) : !empty($item['checked']);
        $out .= '- [' . ($checked ? 'x' : ' ') . '] ' . call_user_func($plain, (string) $item['text']) . "\n";
    }

    return $out;
}

/**
 * The ticks of a message's checklist given since it was written, numbered
 * from the block's first item, as ws_render_body() reads them for the block
 * drawn alone. Pure.
 *
 * @param array $block  ws_share_link_text_blocks() list
 * @param array $checks item index in the message => state
 * @return array item index in the block => state
 */
function ws_share_link_list_checks($block, $checks)
{
    $out = array();

    foreach ((array) $block['items'] as $n => $item) {
        if (isset($checks[(int) $item['index']])) {
            $out[$n] = $checks[(int) $item['index']];
        }
    }

    return $out;
}

/* ---------------------------------------------------------------------------
   Reads
   --------------------------------------------------------------------------- */

/**
 * The table or checklist of a message a link is to, or null.
 *
 * @param array|null $message
 * @param string     $kind
 * @param int        $number
 * @return array|null
 */
function ws_share_link_text_row($message, $kind, $number)
{
    if (!ws_share_link_text_message($message) || ((int) $number <= 0)) {
        return null;
    }

    return ws_share_link_text_block((string) $message['body'], (string) $kind, (int) $number);
}

/**
 * A text with its tags as plain names, read for one block at once.
 *
 * @param array $block
 * @return callable string => string
 */
function ws_share_link_text_plain($block)
{
    $refs = ws_guest_refs(array(implode("\n", array_merge(array((string) $block['title']), (array) $block['lines']))));

    return function ($text) use ($refs) {
        return ws_note_export_tokens((string) $text, $refs);
    };
}

/**
 * What a table or a checklist is called on its page, in the conversation's
 * line and in the channel's Sharing: its title, else its header or first
 * item, tags as plain names; "Table" / "Checklist" when nothing names it.
 *
 * @param array $block
 * @return string
 */
function ws_share_link_text_name($block)
{
    $name = ws_share_link_text_name_source($block);
    $name = ($name !== '') ? trim(preg_replace('/\s+/u', ' ', call_user_func(ws_share_link_text_plain($block), $name))) : '';

    if ($name === '') {
        return ($block['kind'] === 'table') ? lang('Table') : lang('Checklist');
    }

    return ws_share_link_text_name_cut($name);
}

/**
 * The names the hesap blocks of a message give, for a formula of its table.
 *
 * @param array $message
 * @return array
 */
function ws_share_link_text_scope($message)
{
    if (!function_exists('ws_calc_body_scope') || (strpos((string) $message['body'], '```') === false)) {
        return array();
    }

    $scope = ws_calc_body_scope(preg_split('/\r\n|\r|\n/', (string) $message['body']));

    return (array) $scope['all'];
}

/**
 * A shared table as CSV (&raw=1).
 *
 * @param array $answer ws_share_link_answer() of a table
 * @return string
 */
function ws_share_link_table_raw($answer)
{
    $block = $answer['block'];
    $grid = (function_exists('ws_calc_has_formula') && ws_calc_has_formula($block['rows']))
        ? ws_calc_grid($block['rows'], ws_share_link_text_scope($answer['message'])) : null;

    return ws_share_link_table_csv($block, $grid, ws_share_link_text_plain($block));
}

/**
 * A shared checklist as Markdown (&raw=1).
 *
 * @param array $answer ws_share_link_answer() of a list
 * @return string
 */
function ws_share_link_list_raw($answer)
{
    $checks = ws_checks_map(array((int) $answer['message']['id']));

    return ws_share_link_list_markdown($answer['block'], (array) ($checks[(int) $answer['message']['id']] ?? array()), ws_share_link_text_plain($answer['block']));
}

/**
 * The page of a shared table or checklist: the block drawn alone as the
 * channel draws it, tags as plain names, nothing to tick or open.
 *
 * @param array $answer ws_share_link_answer() of a table or a list
 * @return string HTML
 */
function ws_share_link_text_page($answer)
{
    $block = $answer['block'];
    $text = implode("\n", (array) $block['lines']);
    $refs = ws_guest_refs(array($text));

    if ($block['kind'] === 'table') {
        $html = ws_render_body($text, $refs, null, false, array('all' => ws_share_link_text_scope($answer['message'])));
    } else {
        $checks = ws_checks_map(array((int) $answer['message']['id']));
        $html = ws_render_body($text, $refs, ws_share_link_list_checks($block, (array) ($checks[(int) $answer['message']['id']] ?? array())), false);
    }

    return ws_share_link_page($answer, $html);
}

/**
 * The tables and checklists of a page of messages a link can be made to, in
 * an agent channel, read with one query: per message a list of kind,
 * item_id, name, open (ws_share_link_item_mark()) or null, can_make.
 *
 * @param array   $viewer
 * @param array[] $rows     ws_messages rows
 * @param array   $can_post channel id => may post
 * @return array message id => array[]
 */
function ws_share_links_text_map($viewer, $rows, $can_post)
{
    if (!ws_share_links_ready()) {
        return array();
    }

    $agent = array();
    $blocks = array();
    $live = array();

    foreach ((array) $rows as $row) {
        if (((int) $row['deleted_at'] > 0) || !ws_share_link_text_message($row)) {
            continue;
        }

        $found = ws_share_link_text_blocks((string) $row['body']);

        if (!$found) {
            continue;
        }

        $channel_id = (int) $row['channel_id'];

        if (!array_key_exists($channel_id, $agent)) {
            $agent[$channel_id] = ws_share_link_agent_channel(ws_channel($channel_id));
        }

        if ($agent[$channel_id]) {
            $blocks[(int) $row['id']] = $found;
            $live[(int) $row['id']] = $row;
        }
    }

    if (!$blocks) {
        return array();
    }

    $now = time();
    $open = array();

    foreach ((array) db_items("SELECT * FROM ws_share_links WHERE message_id IN (" . implode(',', array_keys($blocks)) . ")
        AND kind IN ('table', 'list') AND revoked_at = 0 AND expires_at > '" . $now . "'") as $link) {
        $open[(int) $link['message_id'] . ':' . $link['kind'] . ':' . (int) $link['item_id']] = $link;
    }

    $maker = ws_share_link_maker((int) $viewer['id']);
    $out = array();

    foreach ($blocks as $id => $found) {
        $row = $live[$id];
        $channel = $agent[(int) $row['channel_id']];
        $past = function_exists('ws_message_in_past') && ws_message_in_past($row);

        foreach ($found as $block) {
            $link = $open[$id . ':' . $block['kind'] . ':' . $block['number']] ?? null;

            $out[$id][] = array(
                'kind'     => $block['kind'],
                'item_id'  => $block['number'],
                'name'     => ws_share_link_text_name($block),
                'open'     => ($link && !$past) ? ws_share_link_item_mark($link, ws_share_link_can_revoke($viewer, $link, $channel, $maker)) : null,
                'can_make' => !$link && !$past && !empty($can_post[(int) $row['channel_id']]),
            );
        }
    }

    return $out;
}
