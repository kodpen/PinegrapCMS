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
 * Some lines make blocks of their own: a table written the way Markdown
 * writes one (a header row, a row of dashes, the rows), a checklist item
 * ("- [ ] item"), and in the running text a list item ("- item", "1. item")
 * or a quoted line ("> line"), drawn as <ul>, <ol> and <blockquote> with no
 * attribute (ws_text_blocks()). Everything in a cell or an item is drawn as
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
    // A channel's own icon carries the mark of its kind (ws_channel_icon_badge()).
    $icon = ($ref['icon'] !== '') ? ws_icon_badged_html($ref['icon'], (string) ($ref['badge'] ?? '')) : '';
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
 * null when the line opens nothing. Only a hesap block may carry settings
 * after its language (```hesap ondalık=2); any other line with words after
 * the language opens nothing, as before.
 *
 * @param string $line
 * @return string|null
 */
function ws_code_fence_open($line)
{
    if (!preg_match('/^\s*```\s*([A-Za-z0-9_+#.-]{0,20})(?:[ \t]+([^`\r\n]{1,80}?))?\s*$/u', (string) $line, $match)) {
        return null;
    }

    if (isset($match[2]) && ($match[2] !== '') && !(function_exists('ws_calc_block_language') && ws_calc_block_language($match[1]))) {
        return null;
    }

    return $match[1];
}

/**
 * The settings after a hesap block's language on its opening line.
 *
 * @param string $line
 * @return string
 */
function ws_code_fence_info($line)
{
    return (preg_match('/^\s*```\s*[A-Za-z0-9_+#.-]{1,20}[ \t]+([^`\r\n]{1,80}?)\s*$/u', (string) $line, $match)) ? $match[1] : '';
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
 * One line as an item of a list or a line of a quote: "- " or "* " (a
 * bulleted item), a number and ". " (a numbered one), "> " or a lone ">"
 * (a quote). A checklist item ("- [ ] "), a rule ("* * *") and a number
 * written with a leading zero ("01. ") are none of them: "01." read as 1
 * would be drawn and written back as "1.". The writing box's twin is
 * listLine() in assets/js/workspace_editor.js.
 *
 * @param string $line
 * @return array|null kind (bullet | number | quote), number (0 unless
 *                    numbered), text (the words after the mark)
 */
function ws_list_line($line)
{
    $line = (string) $line;

    if (preg_match('/^[ \t]*>(?:[ \t]+(.*))?$/u', $line, $match)) {
        return array('kind' => 'quote', 'number' => 0, 'text' => rtrim((string) ($match[1] ?? '')));
    }

    if (preg_match('/^[ \t]*[-*][ \t]\[[ xX]\](?:[ \t]|$)/', $line) || preg_match('/^[ \t]*([-*_])(?:[ \t]*\1){2,}[ \t]*$/', $line)) {
        return null;
    }

    if (preg_match('/^[ \t]*[-*][ \t]+(\S.*)$/u', $line, $match)) {
        return array('kind' => 'bullet', 'number' => 0, 'text' => rtrim($match[1]));
    }

    if (preg_match('/^[ \t]*(0|[1-9][0-9]{0,2})\.[ \t]+(\S.*)$/u', $line, $match)) {
        return array('kind' => 'number', 'number' => (int) $match[1], 'text' => rtrim($match[2]));
    }

    return null;
}

/**
 * The lines of a piece of running text grouped into what they are drawn
 * as: text, a bulleted list, a numbered list or a quote.
 *
 * One level: an indented item is an item like the others. A numbered list
 * starts at a line "1. " and goes on with every numbered line after it,
 * whatever its number; a number that starts no list stays text, so a reader
 * never sees a number other than the one written (an <ol> carries no
 * start). Empty lines between two items of a list keep it one list, as
 * Markdown's loose lists; between two lines of a quote they end it. Lines
 * of ">" with no words quote nothing and stay text (an arrow, a prompt).
 * The writing box's twin is parseBlocks() in assets/js/workspace_editor.js.
 *
 * @param string[] $lines
 * @return array[] kind (text | bullet | number | quote), lines (each line
 *                 of text as written; each item or quoted line without
 *                 its mark)
 */
function ws_text_blocks($lines)
{
    $lines = array_values((array) $lines);
    $count = count($lines);
    $out = array();

    for ($i = 0; $i < $count; $i++) {
        $item = ws_list_line($lines[$i]);
        $kind = $item ? $item['kind'] : 'text';

        if (($kind === 'number') && ($item['number'] !== 1)) {
            $kind = 'text';
        }

        if ($kind === 'text') {
            $last = count($out) - 1;

            if (($last >= 0) && ($out[$last]['kind'] === 'text')) {
                $out[$last]['lines'][] = (string) $lines[$i];
            } else {
                $out[] = array('kind' => 'text', 'lines' => array((string) $lines[$i]));
            }

            continue;
        }

        $block = array('kind' => $kind, 'lines' => array($item['text']));
        $first = $i;

        while (($i + 1) < $count) {
            $next = ws_list_line($lines[$i + 1]);

            if ($next && ($next['kind'] === $kind)) {
                $block['lines'][] = $next['text'];
                $i++;
                continue;
            }

            // Empty lines between two items of the same list.
            $skip = $i + 1;

            while (($kind !== 'quote') && ($skip < $count) && (trim((string) $lines[$skip]) === '')) {
                $skip++;
            }

            $after = (($skip > $i + 1) && ($skip < $count)) ? ws_list_line($lines[$skip]) : null;

            if ($after && ($after['kind'] === $kind)) {
                $i = $skip - 1;
                continue;
            }

            break;
        }

        // A quote of empty lines only: its lines as written, as text.
        if (($kind === 'quote') && (implode('', $block['lines']) === '')) {
            $last = count($out) - 1;
            $written = array_map('strval', array_slice($lines, $first, $i - $first + 1));

            if (($last >= 0) && ($out[$last]['kind'] === 'text')) {
                $out[$last]['lines'] = array_merge($out[$last]['lines'], $written);
            } else {
                $out[] = array('kind' => 'text', 'lines' => $written);
            }

            continue;
        }

        $out[] = $block;
    }

    return $out;
}

/**
 * Running text as HTML with its lists and quotes drawn as <ul>, <ol>, <li>
 * and <blockquote>, none of them with an attribute. Every word in them is
 * the author's, drawn by the callbacks, which escape it first.
 *
 * A line break stands between two items and between two blocks or runs:
 * nothing on screen (message bodies collapse white space), but a reader of
 * the words alone (strip_tags(), textContent) keeps them apart.
 *
 * @param string   $text
 * @param callable $run    function (string $text): string, a run of text
 * @param callable $inline function (string $text): string, an item, or the
 *                         lines of a quote
 * @return string
 */
function ws_text_blocks_html($text, $run, $inline)
{
    $pieces = array();

    foreach (ws_text_blocks(preg_split('/\r\n|\r|\n/', (string) $text)) as $block) {
        if ($block['kind'] === 'text') {
            $pieces[] = $run(implode("\n", $block['lines']));
            continue;
        }

        if ($block['kind'] === 'quote') {
            $quoted = trim(implode("\n", $block['lines']), "\n");
            $pieces[] = ($quoted !== '') ? '<blockquote>' . $inline($quoted) . '</blockquote>' : '';
            continue;
        }

        $tag = ($block['kind'] === 'number') ? 'ol' : 'ul';
        $items = array();

        foreach ($block['lines'] as $line) {
            $items[] = '<li>' . $inline($line) . '</li>';
        }

        $pieces[] = '<' . $tag . '>' . implode("\n", $items) . '</' . $tag . '>';
    }

    return implode("\n", array_filter($pieces, function ($piece) {
        return $piece !== '';
    }));
}

/**
 * The lists and quotes ws_text_blocks_html() drew, back as the lines a
 * plain text writes them as ("- item", "1. item", "> line"); everything
 * else as it was. Reads only that function's own markup.
 *
 * @param string $html
 * @return string
 */
function ws_html_blocks_text($html)
{
    // \x03 marks where a block starts and ends; marks that meet, with the
    // line break ws_text_blocks_html() puts between blocks, are one line
    // break.
    $html = preg_replace_callback('#<(ul|ol)>(.*?)</\1>#s', function ($list) {
        $items = array();

        if (preg_match_all('#<li>(.*?)</li>#s', $list[2], $found)) {
            foreach ($found[1] as $index => $item) {
                $items[] = (($list[1] === 'ol') ? ($index + 1) . '. ' : '- ') . $item;
            }
        }

        return "\x03" . implode("\n", $items) . "\x03";
    }, (string) $html);

    $html = preg_replace_callback('#<blockquote>(.*?)</blockquote>#s', function ($quote) {
        return "\x03" . implode("\n", array_map(function ($line) {
            return '> ' . $line;
        }, preg_split('#<br\s*/?>\n?#', $quote[1]))) . "\x03";
    }, $html);

    return preg_replace("/(?:\n?\x03\n?)+/", "\n", $html);
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
 * @param array|null $calc      for a piece of a longer text (a note's block):
 *                              seed, the names its hesap blocks inherit, and
 *                              all, the names its tables may use
 * @return string
 */
function ws_render_body($body, $refs, $checks = null, $can_check = false, $calc = null)
{
    $body = (string) $body;

    // A submitted form tagged in the text gets its card under it, for a
    // reader who may open it (includes/workspace/watch.php).
    $cards = function_exists('ws_form_cards_html') ? ws_form_cards_html($body, $refs) : '';

    // Running text only - nearly every message - is drawn in one piece.
    // A list item or a quoted line (ws_list_line()) needs the long way.
    if (!preg_match('/^\s*(\||[-*]\s\[[ xX]\]\s|```|:::\s|[-*][ \t]+\S|1\.[ \t]+\S|>(?:[ \t]|$))/m', $body)) {
        return ws_render_text($body, $refs) . $cards;
    }

    $lines = preg_split('/\r\n|\r|\n/', $body);
    $count = count($lines);
    $html = '';
    $text = array();
    $item = 0;

    // The names the hesap blocks give: each block knows those of the blocks
    // above it, a table those of them all (includes/workspace/calc.php).
    $scope = array('seeds' => array(), 'all' => array());

    if (function_exists('ws_calc_body_scope') && (($calc !== null) || (strpos($body, '```') !== false))) {
        $scope = ws_calc_body_scope($lines, (array) ($calc['seed'] ?? array()));

        if (isset($calc['all']) && is_array($calc['all'])) {
            $scope['all'] = array_merge($scope['all'], $calc['all']);
        }
    }
    $row_pattern = '/^\s*\|.*\|\s*$/';
    $check_pattern = '/^\s*[-*]\s\[( |x|X)\]\s+(.+)$/u';

    // The running text between two blocks, its lists and quotes drawn as
    // such (ws_text_blocks_html()).
    $flush = function () use (&$text, &$html, $refs) {
        $chunk = trim(implode("\n", $text), "\n");
        $text = array();

        if (trim($chunk) === '') {
            return;
        }

        $html .= ws_text_blocks_html($chunk, function ($run) use ($refs) {
            $run = trim($run, "\n");

            return (trim($run) !== '') ? '<div class="ws-text">' . ws_render_text($run, $refs) . '</div>' : '';
        }, function ($words) use ($refs) {
            return ws_render_text($words, $refs);
        });
    };

    // A title right above a block ("::: Title", includes/workspace/blocks.php)
    // is drawn as its caption.
    $title = '';
    $titled = function ($block) use (&$title) {
        if ($title === '') {
            return $block;
        }

        $out = '<div class="ws-block-titled"><div class="ws-block-title"><i class="bi bi-bookmark" aria-hidden="true"></i><span>' . h($title) . '</span></div>' . $block . '</div>';
        $title = '';

        return $out;
    };

    for ($i = 0; $i < $count; $i++) {
        $line = $lines[$i];

        if (function_exists('ws_block_title_line') && (($heading = ws_block_title_line($line)) !== null) && ws_block_starts($lines, $i + 1)) {
            $flush();
            $title = $heading;
            continue;
        }

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
            $html .= $titled((function_exists('ws_calc_block_language') && ws_calc_block_language($language))
                ? ws_calc_block_html($code, $scope['seeds'][$i] ?? array(), ws_code_fence_info($line))
                : ws_code_block_html(implode("\n", $code), $language));
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
            $grid = (function_exists('ws_calc_has_formula') && ws_calc_has_formula($grid_rows)) ? ws_calc_grid($grid_rows, $scope['all']) : null;

            // A table that uses a name keeps its cells, so that it follows
            // the figures a reader tries in the blocks (ws_calc_live()).
            $live = false;

            foreach (($grid !== null) ? $grid : array() as $grid_row) {
                foreach ($grid_row as $grid_cell) {
                    $live = $live || !empty($grid_cell['named']);
                }
            }

            $align = function ($column, $formula = false) use ($alignments) {
                $side = $alignments[$column] ?? '';

                if (($side === '') && $formula) {
                    $side = 'right';
                }

                return ($side !== '') ? ' class="text-' . (($side === 'right') ? 'end' : (($side === 'center') ? 'center' : 'start')) . '"' : '';
            };

            $cell_html = function ($row, $column) use ($grid, $grid_rows, $refs, $live) {
                if (($grid !== null) && !empty($grid[$row][$column]['formula'])) {
                    return ws_calc_cell_html($grid[$row][$column], $live ? $row . ':' . $column : '');
                }

                return ws_render_inline($grid_rows[$row][$column] ?? '', $refs);
            };

            $live_data = $live ? json_encode($grid_rows, JSON_UNESCAPED_UNICODE) : false;
            $table = '<div class="ws-table-wrap"><table class="table table-sm ws-table' . (($grid !== null) ? ' ws-table-calc' : '') . '"'
                . (is_string($live_data) ? ' data-ws-calc-grid="' . h($live_data) . '"' : '') . '><thead><tr>';

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

            $html .= $titled($table . '</tbody></table></div>');
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
            $html .= $titled('<ul class="ws-checklist">' . $list . '</ul>');
            continue;
        }

        $text[] = $line;
    }

    $flush();

    return $html . $cards;
}

/**
 * A stored text as plain words, tags spelled out, for what an assistant or
 * the API reads (text), an excerpt, a device banner or an e-mail line.
 * Running text folds into one line; each item of a list and each line of a
 * quote keeps a line of its own ("- item", "1. item", "> line"), so a list
 * still reads as one. ws_plain_excerpt() folds those too.
 *
 * @param array  $viewer
 * @param string $body
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
    $text = preg_replace('/^\s*:::\s+(.+)$/mu', '$1:', $text);
    $text = preg_replace('#\[([^\]\n]{1,300})\]\(((?:https?://|mailto:)[^\s()<>"]{1,1000})\)#i', '$1', $text);
    $text = preg_replace('/(?<![\p{L}\p{N}_])_([^_\n]{1,300}?)_(?![\p{L}\p{N}_])/u', '$1', $text);
    $text = str_replace(array('**', '`', '~~'), '', $text);

    // A list or a quote (ws_text_blocks()): an item or a quoted line a line,
    // numbered as a reader sees it; the rest folds as before.
    $pieces = array();

    foreach (ws_text_blocks(preg_split('/\r\n|\r|\n/', $text)) as $block) {
        if ($block['kind'] === 'text') {
            $pieces[] = implode(' ', $block['lines']);
            continue;
        }

        foreach ($block['lines'] as $index => $line) {
            if (trim($line) !== '') {
                $pieces[] = (($block['kind'] === 'number') ? ($index + 1) . '. ' : (($block['kind'] === 'quote') ? '> ' : '- ')) . $line;
            }
        }
    }

    $lines = array();

    foreach ($pieces as $piece) {
        $piece = trim(preg_replace('/\s+/u', ' ', $piece));

        if ($piece !== '') {
            $lines[] = $piece;
        }
    }

    return implode("\n", $lines);
}

/**
 * The plain words on one line, cut to a length: a list reads "- a - b".
 *
 * @param array  $viewer
 * @param string $body
 * @param int    $length
 * @return string
 */
function ws_plain_excerpt($viewer, $body, $length = 120)
{
    $text = trim(preg_replace('/\s+/u', ' ', ws_plain_text($viewer, $body)));

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
