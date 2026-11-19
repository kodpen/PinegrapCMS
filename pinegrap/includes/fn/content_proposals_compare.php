<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The lines of Compare on the Waiting Content Changes screen
 * (content_changes.php): one change's kind, what the site shows and what is
 * proposed.
 *
 * A value is shown as its words, tags stripped. When the words of both sides
 * are the same but their HTML is not (a link's address or target, bold or
 * italic, an inline picture, a styled span), the stripped words would hide
 * the change: such a line shows the HTML of both sides as code instead.
 * When the words and the tags both changed, the HTML is shown under the
 * words, and words that differ only past the cut are printed whole: two
 * values that differ never print the same. Every value is escaped; nothing
 * reaches the page raw.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// pg_content_proposals_local_picture() decides which pictures get a small image.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

// The most characters of a value's words a line prints.
define('PG_CONTENT_PROPOSALS_COMPARE_WIDTH', 600);

/**
 * A value's words as Compare reads them: tags stripped and trimmed; an
 * array or object as JSON.
 *
 * @param mixed $value
 * @return string
 */
function pg_content_proposals_compare_words($value)
{
    if (is_scalar($value) || ($value === null)) {
        return trim(strip_tags((string) $value));
    }

    return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
}

/**
 * A value's tags with their attributes, in order: two values whose lists
 * differ have other links, formatting or styles.
 *
 * @param string $value
 * @return string[]
 */
function pg_content_proposals_compare_tags($value)
{
    return preg_match_all('~<[^>]*>~', (string) $value, $found) ? $found[0] : array();
}

/**
 * How a line shows its change: 'markup' when the words are the same and the
 * HTML is not (the HTML alone), 'both' when the words and the tags changed
 * (the words, and the HTML under them), 'plain' otherwise (the words).
 *
 * @param mixed $was
 * @param mixed $now
 * @return string
 */
function pg_content_proposals_compare_kind($was, $now)
{
    if (!(is_scalar($was) || ($was === null)) || !(is_scalar($now) || ($now === null))
        || (trim((string) $was) === trim((string) $now))) {
        return 'plain';
    }

    if (pg_content_proposals_compare_words($was) === pg_content_proposals_compare_words($now)) {
        return 'markup';
    }

    return (pg_content_proposals_compare_tags($was) !== pg_content_proposals_compare_tags($now)) ? 'both' : 'plain';
}

/**
 * The width the words of a line are cut at: PG_CONTENT_PROPOSALS_COMPARE_WIDTH,
 * or 0 (whole) when the cut would print two different values the same.
 *
 * @param mixed $was
 * @param mixed $now
 * @return int
 */
function pg_content_proposals_compare_width($was, $now)
{
    $a = pg_content_proposals_compare_words($was);
    $b = pg_content_proposals_compare_words($now);
    $cut = function ($text) {
        return mb_strimwidth($text, 0, PG_CONTENT_PROPOSALS_COMPARE_WIDTH, '…');
    };

    return (($a !== $b) && ($cut($a) === $cut($b))) ? 0 : PG_CONTENT_PROPOSALS_COMPARE_WIDTH;
}

/**
 * One side of a line as HTML. $kind 'markup' prints the value's HTML alone,
 * 'both' its words and its HTML under them; the HTML is escaped, in a code
 * block, whole. $width cuts the words (0: whole). A picture of the site's
 * own files gets its small image above the address.
 *
 * @param mixed  $value
 * @param bool   $picture
 * @param string $kind
 * @param int    $width
 * @return string
 */
function pg_content_proposals_compare_value($value, $picture = false, $kind = 'plain', $width = PG_CONTENT_PROPOSALS_COMPARE_WIDTH)
{
    $raw = ($kind !== 'plain') ? trim((string) $value) : '';
    $code = ($raw !== '') ? '<code class="d-block bg-1 border rounded p-2 small font-monospace text-break">' . h($raw) . '</code>' : '';

    if (($kind === 'markup') && ($code !== '')) {
        return $code;
    }

    $text = pg_content_proposals_compare_words($value);

    if ($text === '') {
        return '<span class="pg-text-meta">' . h(lang('(empty)')) . '</span>' . $code;
    }

    $thumb = ($picture && pg_content_proposals_local_picture($text))
        ? '<img src="' . h($text) . '" alt="" height="64" loading="lazy" class="d-block mb-1 rounded">' : '';
    $words = ($width > 0) ? mb_strimwidth($text, 0, $width, '…') : $text;

    return $thumb . '<span class="text-break' . (($code !== '') ? ' d-block mb-1' : '') . '">' . h($words) . '</span>' . $code;
}

/**
 * One change of the comparison: its kind, what the site shows, what is
 * proposed; side by side from md up, one under the other on a phone. A
 * change in the markup says so under its kind and shows both sides' HTML,
 * so two values that differ never print the same.
 *
 * @param string $label
 * @param mixed  $was
 * @param mixed  $now
 * @param bool   $picture
 * @return string
 */
function pg_content_proposals_compare_line($label, $was, $now, $picture = false)
{
    $kind = $picture ? 'plain' : pg_content_proposals_compare_kind($was, $now);
    $width = pg_content_proposals_compare_width($was, $now);
    $notes = array(
        'markup' => lang('Only the markup changed: links, formatting or styles.'),
        'both'   => lang('The markup changed too: links, formatting or styles.'),
    );

    return '<div class="row g-2 py-2 border-top">'
        . '<div class="col-12 md:col-2 pg-text-2"><strong>' . h($label) . '</strong>'
        . (isset($notes[$kind]) ? '<div class="pg-text-meta">' . h($notes[$kind]) . '</div>' : '')
        . '</div>'
        . '<div class="col-12 md:col-5"><div class="pg-text-meta">' . h(lang('On the site')) . '</div>' . pg_content_proposals_compare_value($was, $picture, $kind, $width) . '</div>'
        . '<div class="col-12 md:col-5"><div class="pg-text-meta">' . h(lang('Proposed')) . '</div>' . pg_content_proposals_compare_value($now, $picture, $kind, $width) . '</div>'
        . '</div>';
}
