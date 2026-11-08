<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's memory: entries the members and the agents keep
 * (a title, a text, tags, pinned, an expiry) and the digest every agent
 * brief carries, the pinned entries first, then the newest, then the
 * channel's decision lines, within a size the brief can afford. The checks
 * and the digest here are pure; the rows are read by their callers.
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
 * The longest title, text and tag of an entry, and its most tags.
 */
define('WS_MEMORY_TITLE_MAX', 150);
define('WS_MEMORY_TEXT_MAX', 4000);
define('WS_MEMORY_TAG_MAX', 32);
define('WS_MEMORY_TAGS_MAX', 10);

/**
 * The digest's default size in characters: room for the brief's other parts.
 */
define('WS_MEMORY_DIGEST_MAX', 6000);

/**
 * An entry's tags as stored: trimmed, lower case, without a leading #,
 * each once, in the order given. Pure.
 *
 * @param string|string[] $tags a list, or one text split at commas and spaces
 * @return string[]
 */
function ws_memory_tags($tags)
{
    if (!is_array($tags)) {
        $tags = preg_split('/[\s,]+/u', (string) $tags);
    }

    $out = array();

    foreach ($tags as $tag) {
        $tag = mb_strtolower(ltrim(trim((string) $tag), '#'));

        if (($tag !== '') && !in_array($tag, $out, true)) {
            $out[] = $tag;
        }
    }

    return $out;
}

/**
 * What is wrong with a memory entry: '' when nothing is. Pure: the time is
 * given so an expiry in the past is refused against the caller's clock.
 *
 * @param array $entry title, text, tags (ws_memory_tags() form), expires_at
 *                     (0 for never)
 * @param int   $now
 * @return string
 */
function ws_memory_entry_problem($entry, $now)
{
    $title = trim((string) ($entry['title'] ?? ''));
    $text = trim((string) ($entry['text'] ?? ''));
    $tags = (array) ($entry['tags'] ?? array());
    $expires_at = (int) ($entry['expires_at'] ?? 0);

    if ($title === '') {
        return lang('A memory entry needs a title.');
    }

    if (mb_strlen($title) > WS_MEMORY_TITLE_MAX) {
        return lang(array('string' => 'A memory entry\'s title is at most {var:1} characters.', 'vars' => WS_MEMORY_TITLE_MAX));
    }

    if (mb_strlen($text) > WS_MEMORY_TEXT_MAX) {
        return lang(array('string' => 'A memory entry\'s text is at most {var:1} characters.', 'vars' => WS_MEMORY_TEXT_MAX));
    }

    if (count($tags) > WS_MEMORY_TAGS_MAX) {
        return lang(array('string' => 'A memory entry has at most {var:1} tags.', 'vars' => WS_MEMORY_TAGS_MAX));
    }

    foreach ($tags as $tag) {
        if (!preg_match('/^[\p{Ll}\p{Lo}\p{N}][\p{Ll}\p{Lo}\p{N}_.\-]*$/u', (string) $tag) || (mb_strlen((string) $tag) > WS_MEMORY_TAG_MAX)) {
            return lang(array('string' => 'A tag is one word of letters, digits, dots, dashes or underscores, at most {var:1} characters.', 'vars' => WS_MEMORY_TAG_MAX));
        }
    }

    if (($expires_at < 0) || (($expires_at > 0) && ($expires_at <= (int) $now))) {
        return lang('A memory entry\'s expiry must be in the future, or left empty.');
    }

    return '';
}

/**
 * The digest of a channel's memory for an agent's brief: the live entries,
 * pinned first and then the newest, each as "- title [tags]: text" on one
 * line, then the decision lines, newest first. A line that no longer fits
 * is left out whole, never cut, with every line after it, and the count left out closes the digest
 * so the agent knows to read the rest. Pure.
 *
 * @param array[] $entries   title, text, tags, pinned, expires_at, updated_at
 * @param array[] $decisions body, marked_at (newest first)
 * @param int     $now
 * @param int     $max       characters
 * @return string '' when there is nothing to tell
 */
function ws_memory_digest($entries, $decisions, $now, $max = WS_MEMORY_DIGEST_MAX)
{
    $live = array();

    foreach ((array) $entries as $entry) {
        $expires_at = (int) ($entry['expires_at'] ?? 0);

        if (($expires_at === 0) || ($expires_at > (int) $now)) {
            $live[] = $entry;
        }
    }

    usort($live, function ($a, $b) {
        $pinned = (int) !empty($b['pinned']) - (int) !empty($a['pinned']);

        return ($pinned !== 0) ? $pinned : ((int) ($b['updated_at'] ?? 0) - (int) ($a['updated_at'] ?? 0));
    });

    $one_line = function ($text) {
        return trim(preg_replace('/\s+/u', ' ', (string) $text));
    };

    $sections = array();

    foreach ($live as $entry) {
        $tags = ws_memory_tags($entry['tags'] ?? array());
        $line = '- ' . $one_line($entry['title'] ?? '');

        if ($tags) {
            $line .= ' [' . implode(', ', $tags) . ']';
        }

        $text = $one_line($entry['text'] ?? '');
        $sections['memory'][] = ($text === '') ? $line : $line . ': ' . $text;
    }

    foreach ((array) $decisions as $decision) {
        $body = $one_line($decision['body'] ?? '');

        if ($body !== '') {
            $sections['decisions'][] = '- ' . $body;
        }
    }

    if (!$sections) {
        return '';
    }

    $headings = array('memory' => 'Channel memory:', 'decisions' => 'Decisions in this channel:');
    $out = array();
    $used = 0;
    $left = 0;
    $full = false;

    foreach ($headings as $key => $heading) {
        if (empty($sections[$key])) {
            continue;
        }

        $kept = array();

        foreach ($sections[$key] as $line) {
            $cost = mb_strlen($line) + 1 + ($kept ? 0 : mb_strlen($heading) + 2);

            // Once a line does not fit, the lines after it wait too, so a
            // short old line never takes the place of a newer one.
            if ($full || ($used + $cost > $max)) {
                $full = true;
                $left++;
                continue;
            }

            $kept[] = $line;
            $used += $cost;
        }

        if ($kept) {
            $out[] = $heading . "\n" . implode("\n", $kept);
        }
    }

    if ($left > 0) {
        $out[] = '(' . $left . ' more not shown: read the channel\'s memory and decisions.)';
    }

    return implode("\n\n", $out);
}
