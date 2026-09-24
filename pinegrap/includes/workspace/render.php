<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - turning stored text into what a reader sees.
 *
 * A message is stored as the author typed it, with tags as tokens. It is
 * drawn here and only here: the text is split on the tokens, every piece of
 * the author's own text is escaped before anything else happens to it, and
 * the light formatting (bold, inline code, links, line breaks) is applied to
 * the escaped text. No HTML the author wrote can reach the page, and a tag is
 * drawn with what this reader may see of the record behind it.
 *
 * Two kinds of line make blocks of their own: a table written the way
 * Markdown writes one (a header row, a row of dashes, the rows) and a
 * checklist item ("- [ ] item"). Everything in a cell or an item is drawn as
 * the rest of the text is.
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
 * One tag as a chip.
 *
 * @param array $ref ws_refs_resolve() entry
 * @return string
 */
function ws_chip_html($ref)
{
    $class = 'ws-chip ws-chip-' . preg_replace('/[^a-z]/', '', $ref['type']) . ($ref['known'] ? '' : ' ws-chip-muted');
    $icon = ($ref['icon'] !== '') ? '<i class="bi ' . h($ref['icon']) . '" aria-hidden="true"></i>' : '';
    $title = trim($ref['meta'] . (($ref['status'] !== '') ? ' · ' . $ref['status'] : ''), ' ·');
    $title_attr = ($title !== '') ? ' title="' . h($title) . '"' : '';
    $data = ' data-ws-ref="' . h($ref['type'] . ':' . $ref['id']) . '"';

    if ($ref['url'] !== '') {
        return '<a class="' . $class . '" href="' . h($ref['url']) . '"' . $title_attr . $data . '>' . $icon . '<span>' . h($ref['label']) . '</span></a>';
    }

    return '<span class="' . $class . '"' . $title_attr . $data . '>' . $icon . '<span>' . h($ref['label']) . '</span></span>';
}

/**
 * The author's own text, escaped and lightly formatted.
 *
 * @param string $text
 * @return string
 */
function ws_format_text($text)
{
    $html = h($text);

    // Pieces already drawn are set aside, so later rules cannot reach into
    // them: code, then links, then bare addresses.
    $kept = array();
    $keep = function ($markup) use (&$kept) {
        $kept[] = $markup;

        return "\x01" . (count($kept) - 1) . "\x01";
    };

    // **bold**, _italic_, ~~struck~~. An underscore inside a word
    // (file_name_here) is not italic.
    $emphasis = function ($html) {
        $html = preg_replace('/\*\*([^*\n]{1,300})\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/~~([^~\n]{1,300})~~/', '<del>$1</del>', $html);

        return preg_replace('/(?<![\p{L}\p{N}_])_([^_\n]{1,300}?)_(?![\p{L}\p{N}_])/u', '<em>$1</em>', $html);
    };

    // Inline code first, so nothing inside it is read as formatting.
    $html = preg_replace_callback('/`([^`\n]{1,300})`/', function ($match) use ($keep) {
        return $keep('<code>' . $match[1] . '</code>');
    }, $html);

    // [text](address): web and mail addresses only. Both parts are escaped
    // already, so neither can leave the attribute or the tag it lands in.
    $html = preg_replace_callback('#\[([^\]\n\x01]{1,300})\]\(((?:https?://|mailto:)[^\s()<>"\x01]{1,1000})\)#i', function ($match) use ($keep, $emphasis) {
        return $keep('<a href="' . $match[2] . '" target="_blank" rel="noopener noreferrer">' . $emphasis($match[1]) . '</a>');
    }, $html);

    // Bare web addresses.
    $html = preg_replace_callback('#\bhttps?://[^\s<>"\x01]{2,500}#i', function ($match) use ($keep) {
        $url = rtrim($match[0], '.,;:!?)');
        $tail = substr($match[0], strlen($url));

        return $keep('<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>') . $tail;
    }, $html);

    $html = $emphasis($html);

    $html = preg_replace_callback("/\x01([0-9]+)\x01/", function ($match) use ($kept) {
        return $kept[(int) $match[1]] ?? '';
    }, $html);

    return nl2br($html, false);
}

/**
 * The opening line of a block of code (```, or ```php): its language, or
 * null when the line opens nothing.
 *
 * @param string $line
 * @return string|null
 */
function ws_code_fence_open($line)
{
    return preg_match('/^\s*```\s*([A-Za-z0-9_+#.-]{0,20})\s*$/', (string) $line, $match) ? $match[1] : null;
}

/**
 * @param string $line
 * @return bool
 */
function ws_code_fence_close($line)
{
    return (bool) preg_match('/^\s*```\s*$/', (string) $line);
}

/**
 * A block of code as the conversation shows it: kept as written, in a box of
 * its own height that scrolls, with a button that copies it.
 *
 * @param string $code
 * @param string $language
 * @return string
 */
function ws_code_block_html($code, $language)
{
    return '<div class="ws-code">'
        . '<div class="ws-code-head"><span>' . h(($language !== '') ? $language : lang('Code')) . '</span>'
        . '<button type="button" class="ws-code-copy" data-ws-copy-code title="' . h(lang('Copy the code')) . '" aria-label="' . h(lang('Copy the code')) . '"><i class="bi bi-clipboard" aria-hidden="true"></i></button></div>'
        . '<pre><code>' . h($code) . '</code></pre>'
        . '</div>';
}

/**
 * A piece of running text as HTML: tags as chips, the rest escaped and
 * lightly formatted.
 *
 * @param string $body
 * @param array  $refs ws_refs_resolve() output covering the text's tokens
 * @return string
 */
function ws_render_inline($body, $refs)
{
    $parts = preg_split('/(<[@#](?:' . ws_token_types_pattern() . '):[0-9]{1,10}>)/', (string) $body, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';

    foreach ($parts as $index => $part) {
        if (($index % 2) === 1) {
            $key = preg_replace('/^<[@#]([a-z_]+):([0-9]+)>$/', '$1:$2', $part);
            $html .= isset($refs[$key]) ? ws_chip_html($refs[$key]) : h($part);
        } else {
            $html .= ws_format_text($part);
        }
    }

    return $html;
}

/**
 * The cells of one table row: the outer pipes dropped, "\|" kept as a pipe.
 *
 * @param string $line
 * @return string[]
 */
function ws_table_cells($line)
{
    $line = trim((string) $line);
    $line = preg_replace('/^\|/', '', $line);
    $line = preg_replace('/(?<!\\\\)\|$/', '', $line);

    return array_map(function ($cell) {
        return trim(str_replace('\\|', '|', $cell));
    }, preg_split('/(?<!\\\\)\|/', $line));
}

/**
 * Is this the row of dashes under a table's header? Returns each column's
 * alignment, or null.
 *
 * @param string $line
 * @return string[]|null left | center | right | ''
 */
function ws_table_alignments($line)
{
    if (!preg_match('/^\s*\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)*\|?\s*$/', (string) $line) || (strpos((string) $line, '-') === false)) {
        return null;
    }

    $out = array();

    foreach (ws_table_cells($line) as $cell) {
        $left = (substr($cell, 0, 1) === ':');
        $right = (substr($cell, -1) === ':');
        $out[] = ($left && $right) ? 'center' : ($right ? 'right' : ($left ? 'left' : ''));
    }

    return $out;
}

/**
 * Running text as HTML: what ws_render_inline() draws, with the result of
 * every line that ends with "=" after it (includes/workspace/calc.php).
 *
 * @param string $text
 * @param array  $refs
 * @return string
 */
function ws_render_text($text, $refs)
{
    if (!function_exists('ws_calc_inline') || (strpos((string) $text, '=') === false)) {
        return ws_render_inline($text, $refs);
    }

    $results = array();
    $lines = preg_split('/\r\n|\r|\n/', (string) $text);

    foreach ($lines as $index => $line) {
        $inline = ws_calc_inline($line);

        if ($inline !== null) {
            $results[] = $inline;
            $lines[$index] = $line . "\x02" . (count($results) - 1) . "\x02";
        }
    }

    $html = ws_render_inline(implode("\n", $lines), $refs);

    if (empty($results)) {
        return $html;
    }

    return preg_replace_callback('/\x02([0-9]+)\x02/', function ($match) use ($results) {
        return isset($results[(int) $match[1]]) ? ws_calc_inline_html($results[(int) $match[1]]) : '';
    }, $html);
}

/**
 * A stored text as HTML for this reader.
 *
 * @param string     $body
 * @param array      $refs      ws_refs_resolve() output covering the text's tokens
 * @param array|null $checks    ws_checks_map() entry for this message: who ticked which item
 * @param bool       $can_check the reader may tick the checklist items
 * @return string
 */
function ws_render_body($body, $refs, $checks = null, $can_check = false)
{
    $body = (string) $body;

    // Running text only - nearly every message - is drawn in one piece.
    if (!preg_match('/^\s*(\||[-*]\s\[[ xX]\]\s|```)/m', $body)) {
        return ws_render_text($body, $refs);
    }

    $lines = preg_split('/\r\n|\r|\n/', $body);
    $count = count($lines);
    $html = '';
    $text = array();
    $item = 0;
    $row_pattern = '/^\s*\|.*\|\s*$/';
    $check_pattern = '/^\s*[-*]\s\[( |x|X)\]\s+(.+)$/u';

    $flush = function () use (&$text, &$html, $refs) {
        $chunk = trim(implode("\n", $text), "\n");
        $text = array();

        if (trim($chunk) !== '') {
            $html .= '<div class="ws-text">' . ws_render_text($chunk, $refs) . '</div>';
        }
    };

    for ($i = 0; $i < $count; $i++) {
        $line = $lines[$i];

        // A block of code: every line up to the closing fence, as written.
        $language = ws_code_fence_open($line);

        if ($language !== null) {
            $flush();

            $code = array();
            $j = $i + 1;

            while (($j < $count) && !ws_code_fence_close($lines[$j])) {
                $code[] = $lines[$j];
                $j++;
            }

            // A hesap block is worked out, not shown as code.
            $html .= (function_exists('ws_calc_block_language') && ws_calc_block_language($language))
                ? ws_calc_block_html($code)
                : ws_code_block_html(implode("\n", $code), $language);
            $i = $j;
            continue;
        }

        $alignments = (($i + 1) < $count) ? ws_table_alignments($lines[$i + 1]) : null;

        // A table: its header, the row of dashes, then every row that follows.
        if (preg_match($row_pattern, $line) && ($alignments !== null)) {
            $flush();

            $header = ws_table_cells($line);
            $columns = min(12, max(count($header), count($alignments)));
            $grid_rows = array($header);
            $j = $i + 2;

            while (($j < $count) && preg_match($row_pattern, $lines[$j]) && (count($grid_rows) <= 200)) {
                $grid_rows[] = ws_table_cells($lines[$j]);
                $j++;
            }

            // Cells starting with = are formulas, worked out here
            // (includes/workspace/calc.php); their column reads from the right.
            $grid = (function_exists('ws_calc_has_formula') && ws_calc_has_formula($grid_rows)) ? ws_calc_grid($grid_rows) : null;

            $align = function ($column, $formula = false) use ($alignments) {
                $side = $alignments[$column] ?? '';

                if (($side === '') && $formula) {
                    $side = 'right';
                }

                return ($side !== '') ? ' class="text-' . (($side === 'right') ? 'end' : (($side === 'center') ? 'center' : 'start')) . '"' : '';
            };

            $cell_html = function ($row, $column) use ($grid, $grid_rows, $refs) {
                if (($grid !== null) && !empty($grid[$row][$column]['formula'])) {
                    return ws_calc_cell_html($grid[$row][$column]);
                }

                return ws_render_inline($grid_rows[$row][$column] ?? '', $refs);
            };

            $table = '<div class="ws-table-wrap"><table class="table table-sm ws-table' . (($grid !== null) ? ' ws-table-calc' : '') . '"><thead><tr>';

            for ($column = 0; $column < $columns; $column++) {
                $table .= '<th' . $align($column, !empty($grid[0][$column]['formula'])) . '>' . $cell_html(0, $column) . '</th>';
            }

            $table .= '</tr></thead><tbody>';

            for ($row = 1; $row < count($grid_rows); $row++) {
                $table .= '<tr>';

                for ($column = 0; $column < $columns; $column++) {
                    $table .= '<td' . $align($column, !empty($grid[$row][$column]['formula'])) . '>' . $cell_html($row, $column) . '</td>';
                }

                $table .= '</tr>';
            }

            $html .= $table . '</tbody></table></div>';
            $i = $j - 1;
            continue;
        }

        // A checklist: consecutive items make one list. The tick someone gave
        // an item since overrides how it was written.
        if (preg_match($check_pattern, $line)) {
            $flush();
            $list = '';

            while (($i < $count) && preg_match($check_pattern, $lines[$i], $match)) {
                $state = $checks[$item] ?? null;
                $checked = ($state !== null) ? $state['checked'] : ($match[1] !== ' ');
                $who = ($state !== null) ? '<span class="ws-check-who">' . h($state['name'] . ' · ' . $state['at']) . '</span>' : '';

                $list .= '<li class="ws-check' . ($checked ? ' ws-checked' : '') . '"><label>'
                    . '<input type="checkbox" class="form-check-input" data-ws-check="' . $item . '"' . ($checked ? ' checked' : '') . ($can_check ? '' : ' disabled') . '>'
                    . '<span class="ws-check-text">' . ws_render_inline(trim($match[2]), $refs) . '</span></label>' . $who . '</li>';

                $item++;
                $i++;
            }

            $i--;
            $html .= '<ul class="ws-checklist">' . $list . '</ul>';
            continue;
        }

        $text[] = $line;
    }

    $flush();

    return $html;
}

/**
 * A stored text as plain words, tags spelled out, for an excerpt, a device
 * banner or an e-mail line.
 *
 * @param array  $viewer
 * @param string $body
 * @param int    $length
 * @param array|null $refs resolved already, to spare the queries
 * @return string
 */
function ws_plain_text($viewer, $body, $refs = null)
{
    // A formula reads as what it comes to, with the formula beside it.
    if (function_exists('ws_calc_plain')) {
        $body = ws_calc_plain($body);
    }

    if ($refs === null) {
        $refs = ws_refs_resolve($viewer, ws_tokens($body));
    }

    $text = preg_replace_callback('/<[@#](' . ws_token_types_pattern() . '):([0-9]{1,10})>/', function ($match) use ($refs) {
        $key = $match[1] . ':' . $match[2];

        return isset($refs[$key]) ? $refs[$key]['label'] : '';
    }, (string) $body);

    // A checklist reads as boxes, a table without its row of dashes, code
    // without its fences, a link as its words.
    $text = preg_replace('/^\s*[-*]\s\[ \]\s+/mu', '☐ ', $text);
    $text = preg_replace('/^\s*[-*]\s\[[xX]\]\s+/mu', '☑ ', $text);
    $text = preg_replace('/^\s*\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)*\|?\s*$/m', '', $text);
    $text = preg_replace('/^\s*```.*$/m', '', $text);
    $text = preg_replace('#\[([^\]\n]{1,300})\]\(((?:https?://|mailto:)[^\s()<>"]{1,1000})\)#i', '$1', $text);
    $text = preg_replace('/(?<![\p{L}\p{N}_])_([^_\n]{1,300}?)_(?![\p{L}\p{N}_])/u', '$1', $text);

    return trim(preg_replace('/\s+/u', ' ', str_replace(array('**', '`', '~~'), '', $text)));
}

/**
 * The plain words cut to a length.
 *
 * @param array  $viewer
 * @param string $body
 * @param int    $length
 * @return string
 */
function ws_plain_excerpt($viewer, $body, $length = 120)
{
    $text = ws_plain_text($viewer, $body);

    if (mb_strlen($text) > $length) {
        $text = rtrim(mb_substr($text, 0, $length - 1)) . '…';
    }

    return $text;
}

/**
 * A moment as the lists say it: the time today, "yesterday", the date after.
 *
 * @param int $timestamp
 * @return string
 */
function ws_time_label($timestamp)
{
    $timestamp = (int) $timestamp;

    if ($timestamp <= 0) {
        return '';
    }

    $today = date('Y-m-d');
    $day = date('Y-m-d', $timestamp);

    if ($day === $today) {
        return date('H:i', $timestamp);
    }

    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return lang('Yesterday') . ' ' . date('H:i', $timestamp);
    }

    if (date('Y', $timestamp) === date('Y')) {
        return date('d.m H:i', $timestamp);
    }

    return date('d.m.Y', $timestamp);
}

/**
 * A day as the planning screens say it: "Fri 26.09".
 *
 * @param string $date Y-m-d
 * @return string
 */
function ws_day_label($date)
{
    $timestamp = strtotime((string) $date);

    if ($timestamp === false) {
        return '';
    }

    $names = ws_weekday_short_names();

    return $names[(int) date('N', $timestamp)] . ' ' . date('d.m', $timestamp);
}

/**
 * Short weekday names, Monday = 1.
 *
 * @return array
 */
function ws_weekday_short_names()
{
    return array(
        1 => lang('Mon'),
        2 => lang('Tue'),
        3 => lang('Wed'),
        4 => lang('Thu'),
        5 => lang('Fri'),
        6 => lang('Sat'),
        7 => lang('Sun'),
    );
}
