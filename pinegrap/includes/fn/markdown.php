<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Markdown reader of the designer's Markdown component: the text the
 * operator writes as Markdown in, the HTML a page shows out. The browser
 * draws the same markup with assets/js/pg_markdown.js (window.PGMarkdown,
 * the part 'markdown'); tests/markdown_test.php holds the two equal.
 *
 * Every piece of the author's text is escaped before it is marked up, so no
 * HTML the author wrote reaches the page. A link keeps an http, https,
 * mailto or tel address, or one on this site; a picture an http or https
 * one, or one on this site; anything else is drawn as its words.
 *
 * Blocks: # headings (six levels), paragraphs with hard breaks (two spaces
 * or a backslash at the end of a line), - * + and 1. lists (nested by
 * indenting), > quotes, ``` and ~~~ fenced code, rules (--- *** ___) and
 * tables (a header row, a row of dashes with : for alignment, the rows).
 * Inline: **bold** __bold__, *italic* _italic_, ~~struck~~, `code`,
 * [links](address "title"), ![pictures](address "title"), <addresses> and
 * backslash escapes.
 *
 * Memory: docs/memory/designer/markdown-component.md
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
 * The text escaped for HTML, quotes included.
 *
 * @param string $text
 * @return string
 */
function pg_markdown_esc($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * The address a link or a picture may keep, or false.
 *
 * Browsers drop spaces and control characters inside an address
 * ("java\tscript:"), so they go before the scheme is read. A scheme must be
 * one of the safe ones; an address with a colon before its first slash,
 * question mark or hash that names no scheme is refused too.
 *
 * @param string $url
 * @param bool $picture true for a picture: http and https only
 * @return string|false
 */
function pg_markdown_safe_url($url, $picture)
{
    $url = preg_replace('/[\x00-\x20\x7F]+/', '', (string) $url);

    if ($url === '' || $url === null) {
        return false;
    }

    $allowed = $picture ? array('http', 'https') : array('http', 'https', 'mailto', 'tel');

    if (preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):/', $url, $m)) {
        return in_array(strtolower($m[1]), $allowed, true) ? $url : false;
    }

    $head = preg_split('/[\/?#]/', $url, 2);

    return (strpos($head[0], ':') === false) ? $url : false;
}

/**
 * The width of a line's leading spaces, a tab counted as four.
 *
 * @param string $line
 * @return int
 */
function pg_markdown_indent($line)
{
    $width = 0;
    $length = strlen($line);

    for ($i = 0; $i < $length; $i++) {
        if ($line[$i] === ' ') {
            $width++;
        } elseif ($line[$i] === "\t") {
            $width += 4;
        } else {
            break;
        }
    }

    return $width;
}

/**
 * The line with up to $width columns of leading spaces taken off.
 *
 * @param string $line
 * @param int $width
 * @return string
 */
function pg_markdown_dedent($line, $width)
{
    $taken = 0;
    $i = 0;
    $length = strlen($line);

    while (($i < $length) && ($taken < $width) && (($line[$i] === ' ') || ($line[$i] === "\t"))) {
        $taken += ($line[$i] === "\t") ? 4 : 1;
        $i++;
    }

    return (string) substr($line, $i);
}

/**
 * A list item's parts, or null: its indent, whether it is numbered, its
 * number, the column its text starts at and the text.
 *
 * @param string $line
 * @return array|null
 */
function pg_markdown_item($line)
{
    if (!preg_match('/^([ \t]*)([-*+]|[0-9]{1,9}[.)])[ \t]+([^\n]*)$/', $line, $m)) {
        return null;
    }

    $ordered = ctype_digit(substr($m[2], 0, 1));
    $indent = pg_markdown_indent($m[1]);

    return array(
        'indent'  => $indent,
        'ordered' => $ordered,
        'start'   => $ordered ? (int) $m[2] : 1,
        'column'  => $indent + strlen($m[2]) + 1,
        'text'    => $m[3],
    );
}

/**
 * Whether the line is blank.
 *
 * @param string $line
 * @return bool
 */
function pg_markdown_blank($line)
{
    return trim($line, " \t") === '';
}

/**
 * The opening fence of a code block as [fence, info], or null.
 *
 * @param string $line
 * @return array|null
 */
function pg_markdown_fence($line)
{
    return preg_match('/^ {0,3}(`{3,}|~{3,})[ \t]*([^`\n]*)$/', $line, $m) ? array($m[1], trim($m[2])) : null;
}

/**
 * Whether the line is a rule.
 *
 * @param string $line
 * @return bool
 */
function pg_markdown_rule($line)
{
    return (bool) preg_match('/^ {0,3}([-*_])(?:[ \t]*\1){2,}[ \t]*$/', $line);
}

/**
 * A heading's level and text, or null.
 *
 * @param string $line
 * @return array|null
 */
function pg_markdown_heading($line)
{
    if (!preg_match('/^ {0,3}(#{1,6})[ \t]+([^\n]*)$/', $line, $m)) {
        return null;
    }

    return array(strlen($m[1]), trim(preg_replace('/[ \t]+#+[ \t]*$/', '', $m[2]), " \t"));
}

/**
 * A table row's cells; an escaped pipe stays in its cell.
 *
 * @param string $line
 * @return array
 */
function pg_markdown_cells($line)
{
    $line = trim(str_replace('\\|', "\x04", $line), " \t");

    if (substr($line, 0, 1) === '|') {
        $line = (string) substr($line, 1);
    }

    if (substr($line, -1) === '|') {
        $line = (string) substr($line, 0, -1);
    }

    $cells = array();

    foreach (explode('|', $line) as $cell) {
        $cells[] = trim(str_replace("\x04", '\\|', $cell), " \t");
    }

    return $cells;
}

/**
 * Whether a table starts at line $i: a row with a pipe, then a row of
 * dashes with as many cells.
 *
 * @param array $lines
 * @param int $i
 * @return bool
 */
function pg_markdown_table_at($lines, $i)
{
    if (!isset($lines[$i + 1]) || (strpos($lines[$i], '|') === false)) {
        return false;
    }

    $delimiter = $lines[$i + 1];

    if ((strpos($delimiter, '|') === false) && (strpos($delimiter, '-') === false)) {
        return false;
    }

    if (!preg_match('/^[ \t]*\|?[ \t]*:?-+:?[ \t]*(\|[ \t]*:?-+:?[ \t]*)*\|?[ \t]*$/', $delimiter)) {
        return false;
    }

    return count(pg_markdown_cells($lines[$i])) === count(pg_markdown_cells($delimiter));
}

/**
 * Whether the line opens a block other than a paragraph.
 *
 * @param array $lines
 * @param int $i
 * @return bool
 */
function pg_markdown_block_start($lines, $i)
{
    $line = $lines[$i];

    return (pg_markdown_fence($line) !== null) || (pg_markdown_heading($line) !== null) || pg_markdown_rule($line)
        || (bool) preg_match('/^ {0,3}>/', $line) || (pg_markdown_item($line) !== null) || pg_markdown_table_at($lines, $i);
}

/**
 * Inline Markdown to HTML: the pieces that must not be read again (code,
 * escapes, pictures, links, addresses) are put aside as \x01n\x02, the rest
 * is escaped and marked up, then the pieces come back. \x03 is a hard
 * break.
 *
 * @param string $text
 * @return string
 */
function pg_markdown_inline($text)
{
    $html = array();
    $plain = array();
    $put = function ($markup, $words) use (&$html, &$plain) {
        $html[] = $markup;
        $plain[] = $words;

        return "\x01" . (count($html) - 1) . "\x02";
    };
    $back_plain = function ($s) use (&$plain) {
        return preg_replace_callback('/\x01([0-9]+)\x02/', function ($m) use (&$plain) {
            return $plain[(int) $m[1]];
        }, $s);
    };
    $back_html = function ($s) use (&$html) {
        return preg_replace_callback('/\x01([0-9]+)\x02/', function ($m) use (&$html) {
            return $html[(int) $m[1]];
        }, $s);
    };
    $marks = function ($s) {
        $s = preg_replace('/\*\*([^ \t\n*](?:[^\n]*?[^ \t\n*])?)\*\*/u', '<strong>$1</strong>', $s);
        $s = preg_replace('/(^|[^A-Za-z0-9_])__([^ \t\n_](?:[^\n]*?[^ \t\n_])?)__(?![A-Za-z0-9_])/u', '$1<strong>$2</strong>', $s);
        $s = preg_replace('/~~([^ \t\n~](?:[^\n]*?[^ \t\n~])?)~~/u', '<del>$1</del>', $s);
        $s = preg_replace('/\*([^ \t\n*](?:[^\n]*?[^ \t\n*])?)\*/u', '<em>$1</em>', $s);

        return preg_replace('/(^|[^A-Za-z0-9_])_([^ \t\n_](?:[^\n]*?[^ \t\n_])?)_(?![A-Za-z0-9_])/u', '$1<em>$2</em>', $s);
    };
    $url = '((?:[^()\x00-\x20]|\([^()\x00-\x20]*\))*)(?:[ \t]+"([^"\n]*)")?';

    $text = preg_replace_callback('/`([^`\n]+)`/', function ($m) use ($put) {
        return $put('<code>' . pg_markdown_esc($m[1]) . '</code>', $m[1]);
    }, $text);
    $text = preg_replace_callback('/\\\\([!-\/:-@\[-`{-~])/', function ($m) use ($put) {
        return $put(pg_markdown_esc($m[1]), $m[1]);
    }, $text);
    $text = preg_replace_callback('/!\[([^\]\n]*)\]\(' . $url . '\)/', function ($m) use ($put, $back_plain) {
        $alt = $back_plain($m[1]);
        $src = pg_markdown_safe_url($back_plain($m[2]), true);

        if ($src === false) {
            return $put(pg_markdown_esc($alt), $alt);
        }

        $title = isset($m[3]) ? $back_plain($m[3]) : '';

        return $put('<img src="' . pg_markdown_esc($src) . '" alt="' . pg_markdown_esc($alt) . '"'
            . (($title !== '') ? ' title="' . pg_markdown_esc($title) . '"' : '')
            . ' class="img-fluid" loading="lazy">', $alt);
    }, $text);
    $text = preg_replace_callback('/\[([^\]\n]+)\]\(' . $url . '\)/', function ($m) use ($put, $back_plain, $back_html, $marks) {
        $label = $back_html($marks(pg_markdown_esc($m[1])));
        $words = $back_plain($m[1]);
        $href = pg_markdown_safe_url($back_plain($m[2]), false);

        if ($href === false) {
            return $put($label, $words);
        }

        $title = isset($m[3]) ? $back_plain($m[3]) : '';

        return $put('<a href="' . pg_markdown_esc($href) . '"' . (($title !== '') ? ' title="' . pg_markdown_esc($title) . '"' : '') . '>' . $label . '</a>', $words);
    }, $text);
    $text = preg_replace_callback('/<((?:https?:\/\/|mailto:)[^\x00-\x20<>]+)>/', function ($m) use ($put, $back_plain) {
        $address = $back_plain($m[1]);

        return $put('<a href="' . pg_markdown_esc($address) . '">' . pg_markdown_esc($address) . '</a>', $address);
    }, $text);

    return str_replace("\x03", '<br>', $back_html($marks(pg_markdown_esc($text))));
}

/**
 * A paragraph's lines to its HTML: leading spaces go, a line ending in two
 * spaces or a backslash breaks.
 *
 * @param array $lines
 * @return string
 */
function pg_markdown_paragraph($lines)
{
    $count = count($lines);
    $out = array();

    foreach ($lines as $k => $line) {
        $line = ltrim($line, " \t");

        if ($k === $count - 1) {
            $out[] = rtrim($line, " \t");
        } elseif (preg_match('/(?: {2,}|\\\\)$/', $line)) {
            $out[] = rtrim(preg_replace('/(?: {2,}|\\\\)$/', '', $line), " \t") . "\x03";
        } else {
            $out[] = rtrim($line, " \t");
        }
    }

    return pg_markdown_inline(implode("\n", $out));
}

/**
 * Lines to their blocks' HTML, one string per block.
 *
 * @param array $lines
 * @param int $depth how deep in quotes and lists; past 8 the rest is text
 * @return array
 */
function pg_markdown_blocks($lines, $depth)
{
    $out = array();
    $n = count($lines);
    $i = 0;

    if ($depth > 8) {
        $text = trim(implode("\n", $lines));

        return ($text === '') ? array() : array('<p>' . pg_markdown_inline($text) . '</p>');
    }

    while ($i < $n) {
        $line = $lines[$i];

        if (pg_markdown_blank($line)) {
            $i++;
            continue;
        }

        $fence = pg_markdown_fence($line);

        if ($fence !== null) {
            $code = array();
            $i++;
            $close = '/^ {0,3}' . preg_quote(substr($fence[0], 0, 1), '/') . '{' . strlen($fence[0]) . ',}[ \t]*$/';

            while (($i < $n) && !preg_match($close, $lines[$i])) {
                $code[] = $lines[$i];
                $i++;
            }

            $i++;
            $words = preg_split('/[ \t]+/', $fence[1]);
            $language = preg_match('/^[A-Za-z0-9_+#-]{1,30}$/', $words[0]) ? $words[0] : '';
            $out[] = '<pre><code' . (($language !== '') ? ' class="language-' . pg_markdown_esc($language) . '"' : '') . '>'
                . pg_markdown_esc(implode("\n", $code)) . '</code></pre>';
            continue;
        }

        $heading = pg_markdown_heading($line);

        if ($heading !== null) {
            $out[] = '<h' . $heading[0] . '>' . pg_markdown_inline($heading[1]) . '</h' . $heading[0] . '>';
            $i++;
            continue;
        }

        if (pg_markdown_rule($line)) {
            $out[] = '<hr>';
            $i++;
            continue;
        }

        if (pg_markdown_table_at($lines, $i)) {
            $head = pg_markdown_cells($line);
            $aligns = array();

            foreach (pg_markdown_cells($lines[$i + 1]) as $cell) {
                $left = (substr($cell, 0, 1) === ':');
                $right = (substr($cell, -1) === ':');
                $aligns[] = ($left && $right) ? ' class="text-center"' : ($right ? ' class="text-end"' : ($left ? ' class="text-start"' : ''));
            }

            $html = '<div class="table-responsive"><table class="table"><thead><tr>';

            foreach ($head as $k => $cell) {
                $html .= '<th scope="col"' . $aligns[$k] . '>' . pg_markdown_inline($cell) . '</th>';
            }

            $html .= '</tr></thead>';
            $i += 2;
            $rows = '';

            while (($i < $n) && !pg_markdown_blank($lines[$i]) && (strpos($lines[$i], '|') !== false)) {
                $cells = pg_markdown_cells($lines[$i]);
                $rows .= '<tr>';

                foreach ($head as $k => $unused) {
                    $rows .= '<td' . $aligns[$k] . '>' . (isset($cells[$k]) ? pg_markdown_inline($cells[$k]) : '') . '</td>';
                }

                $rows .= '</tr>';
                $i++;
            }

            $out[] = $html . (($rows !== '') ? '<tbody>' . $rows . '</tbody>' : '') . '</table></div>';
            continue;
        }

        if (preg_match('/^ {0,3}>/', $line)) {
            $quoted = array();

            while (($i < $n) && preg_match('/^ {0,3}>/', $lines[$i])) {
                $quoted[] = preg_replace('/^ {0,3}> ?/', '', $lines[$i]);
                $i++;
            }

            $out[] = "<blockquote class=\"blockquote\">\n" . implode("\n", pg_markdown_blocks($quoted, $depth + 1)) . "\n</blockquote>";
            continue;
        }

        $first = pg_markdown_item($line);

        if ($first !== null) {
            $base = $first['indent'];
            $items = array();

            while ($i < $n) {
                $current = $lines[$i];

                if (pg_markdown_blank($current)) {
                    $j = $i + 1;

                    while (($j < $n) && pg_markdown_blank($lines[$j])) {
                        $j++;
                    }

                    $next = ($j < $n) ? pg_markdown_item($lines[$j]) : null;

                    if (($j < $n) && ((pg_markdown_indent($lines[$j]) > $base + 1)
                        || (($next !== null) && ($next['ordered'] === $first['ordered'])))) {
                        $i = $j;
                        continue;
                    }

                    break;
                }

                $item = pg_markdown_item($current);

                if (($item !== null) && ($item['indent'] <= $base + 1)) {
                    if ($item['ordered'] !== $first['ordered']) {
                        break;
                    }

                    $items[] = array('text' => $item['text'], 'column' => $item['column'], 'rest' => array());
                    $i++;
                    continue;
                }

                $last = count($items) - 1;

                if (pg_markdown_indent($current) > $base + 1) {
                    $items[$last]['rest'][] = pg_markdown_dedent($current, min(pg_markdown_indent($current), $items[$last]['column']));
                    $i++;
                    continue;
                }

                if (pg_markdown_block_start($lines, $i)) {
                    break;
                }

                $items[$last]['rest'][] = $current;
                $i++;
            }

            $li = array();

            foreach ($items as $item) {
                $para = array($item['text']);
                $rest = $item['rest'];

                while ($rest && !pg_markdown_blank($rest[0]) && !pg_markdown_block_start($rest, 0)) {
                    $para[] = array_shift($rest);
                }

                $inner = pg_markdown_blocks($rest, $depth + 1);
                $li[] = '<li>' . pg_markdown_paragraph($para) . ($inner ? "\n" . implode("\n", $inner) : '') . '</li>';
            }

            $tag = $first['ordered'] ? 'ol' : 'ul';
            $out[] = '<' . $tag . (($first['ordered'] && ($first['start'] !== 1)) ? ' start="' . $first['start'] . '"' : '') . ">\n"
                . implode("\n", $li) . "\n</" . $tag . '>';
            continue;
        }

        $para = array($line);
        $i++;

        while (($i < $n) && !pg_markdown_blank($lines[$i]) && !pg_markdown_block_start($lines, $i)) {
            $para[] = $lines[$i];
            $i++;
        }

        $out[] = '<p>' . pg_markdown_paragraph($para) . '</p>';
    }

    return $out;
}

/**
 * Markdown to HTML; the site's half of window.PGMarkdown.html().
 *
 * @param string $text
 * @return string
 */
function pg_markdown_html($text)
{
    $text = (string) $text;

    if (!preg_match('//u', $text)) {
        $text = function_exists('mb_convert_encoding') ? mb_convert_encoding($text, 'UTF-8', 'UTF-8') : '';
    }

    $text = str_replace(array("\r\n", "\r"), "\n", $text);
    // The reader's own marks (\x01 to \x04) and other control characters
    // never come from the author.
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

    return implode("\n", pg_markdown_blocks(explode("\n", $text), 0));
}

/**
 * The Markdown component's element on the site: a div with the operator's
 * class, inline style and attributes (as the badge component writes them),
 * the text drawn inside. COMPONENTS.markdown in style_designer.js draws the
 * same.
 *
 * @param array $props the node's props
 * @param string $pad the line's indent
 * @return string
 */
function pg_markdown_component_html($props, $pad)
{
    $props = is_array($props) ? $props : array();
    $body = pg_markdown_html(isset($props['markdown']) ? $props['markdown'] : '');
    $class = !empty($props['cssClass']) ? ' class="' . pg_markdown_esc($props['cssClass']) . '"' : '';
    $attrs = function_exists('_compose_inline_style') ? _compose_inline_style($props) . _render_extra_attrs($props, array(), false, true) : '';

    return $pad . '<div' . $class . $attrs . '>' . (($body !== '') ? "\n" . $body . "\n" : '') . '</div>';
}
