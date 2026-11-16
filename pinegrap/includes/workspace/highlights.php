<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a reader's highlights on a message (ws_highlights): a part of
 * a message's drawn text marked for everyone (scope 'all') or for its maker
 * only (scope 'me'). The message's text is never changed: a highlight is
 * kept beside it by its text (quote) and which occurrence of that text in
 * the message's plain text it is (occurrence, 0 the first). This file holds
 * the readiness check and the pure part both the server and the browser's
 * twin follow: the plain text, finding a quote, and what an edit of the
 * message does to a highlight.
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
 * Whether the highlights table is there: on a site whose update has not run,
 * nothing can be highlighted and everything else works as before.
 *
 * @return bool
 */
function ws_highlights_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_highlights', 'occurrence');
    }

    return $ready;
}

/**
 * Every run of white space folded to one space, the ends trimmed. White
 * space is what the browser's \s reads: ASCII white space, the no-break
 * space and every other Unicode space. Pure.
 *
 * @param string $text
 * @return string
 */
function ws_highlight_fold($text)
{
    return trim((string) preg_replace('/[\s\p{Z}\x{FEFF}]+/u', ' ', (string) $text), ' ');
}

/**
 * The plain text of a message's drawn body (ws_render_body()'s HTML): the
 * tags taken out, a line break and the edge of a block (a line, a list item,
 * a quote, a table cell) read as white space, entities decoded, white space
 * folded. A highlight's quote and occurrence are read against this text.
 * Pure.
 *
 * @param string $html
 * @return string
 */
function ws_highlight_plain($html)
{
    $html = (string) $html;

    // A block's edge separates words even when no white space is written
    // between two blocks (</li><li>).
    $html = preg_replace('#<(?:br|hr)\b[^>]*>|</?(?:div|p|li|ul|ol|blockquote|pre|table|thead|tbody|tr|td|th|h[1-6])\b[^>]*>#i', ' ', $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return ws_highlight_fold($text);
}

/**
 * Where the given occurrence of a quote begins in a plain text, in
 * characters, or null when the text has no such occurrence. The quote's
 * white space is folded as the text's is. Every place a quote begins is an
 * occurrence, overlapping ones too, so each place can be named. Pure.
 *
 * @param string $plain      ws_highlight_plain() output
 * @param string $quote
 * @param int    $occurrence 0 the first
 * @return int|null
 */
function ws_highlight_find($plain, $quote, $occurrence)
{
    $plain = (string) $plain;
    $quote = ws_highlight_fold($quote);
    $occurrence = (int) $occurrence;

    if (($quote === '') || ($occurrence < 0)) {
        return null;
    }

    $offset = -1;

    for ($index = 0; $index <= $occurrence; $index++) {
        $offset = mb_strpos($plain, $quote, $offset + 1, 'UTF-8');

        if ($offset === false) {
            return null;
        }
    }

    return $offset;
}

/**
 * How many times a quote occurs in a plain text, overlapping places
 * counted. Pure.
 *
 * @param string $plain
 * @param string $quote
 * @return int
 */
function ws_highlight_count($plain, $quote)
{
    $plain = (string) $plain;
    $quote = ws_highlight_fold($quote);

    if ($quote === '') {
        return 0;
    }

    $count = 0;
    $offset = -1;

    while (($offset = mb_strpos($plain, $quote, $offset + 1, 'UTF-8')) !== false) {
        $count++;
    }

    return $count;
}

/**
 * What an edit of its message does to one highlight: the row as it was when
 * its occurrence is still in the new text, the row moved to the quote's
 * last occurrence when that one is gone, or null (the row is deleted) when
 * the quote is no longer in the new text. Pure.
 *
 * @param array  $row       a ws_highlights row (quote, occurrence)
 * @param string $old_plain the plain text before the edit
 * @param string $new_plain the plain text after it
 * @return array|null
 */
function ws_highlight_remap_one($row, $old_plain, $new_plain)
{
    $row = (array) $row;
    $quote = (string) ($row['quote'] ?? '');
    $occurrence = (int) ($row['occurrence'] ?? 0);

    if (ws_highlight_fold($quote) === '') {
        return null;
    }

    // Nothing in the text changed: the row stays where it is.
    if ((string) $old_plain === (string) $new_plain) {
        return $row;
    }

    $count = ws_highlight_count($new_plain, $quote);

    if ($count === 0) {
        return null;
    }

    if ($occurrence < $count) {
        return $row;
    }

    $row['occurrence'] = $count - 1;

    return $row;
}
