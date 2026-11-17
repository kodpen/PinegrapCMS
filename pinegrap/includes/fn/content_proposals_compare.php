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
 * Every value is escaped; nothing reaches the page raw.
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
 * Do the two values differ only in their markup: the same words, other HTML?
 *
 * @param mixed $was
 * @param mixed $now
 * @return bool
 */
function pg_content_proposals_compare_markup_only($was, $now)
{
    if (!(is_scalar($was) || ($was === null)) || !(is_scalar($now) || ($now === null))) {
        return false;
    }

    return (pg_content_proposals_compare_words($was) === pg_content_proposals_compare_words($now))
        && (trim((string) $was) !== trim((string) $now));
}

/**
 * One side of a line as HTML. $markup prints the value's HTML, escaped, in
 * a code block and whole, so a change past the words' width is not cut off.
 * A picture of the site's own files gets its small image above the address.
 *
 * @param mixed $value
 * @param bool  $picture
 * @param bool  $markup
 * @return string
 */
function pg_content_proposals_compare_value($value, $picture = false, $markup = false)
{
    if ($markup) {
        $raw = trim((string) $value);

        if ($raw !== '') {
            return '<code class="d-block bg-1 border rounded p-2 small font-monospace text-break">' . h($raw) . '</code>';
        }
    }

    $text = pg_content_proposals_compare_words($value);

    if ($text === '') {
        return '<span class="pg-text-meta">' . h(lang('(empty)')) . '</span>';
    }

    $thumb = ($picture && pg_content_proposals_local_picture($text))
        ? '<img src="' . h($text) . '" alt="" height="64" loading="lazy" class="d-block mb-1 rounded">' : '';

    return $thumb . '<span class="text-break">' . h(mb_strimwidth($text, 0, PG_CONTENT_PROPOSALS_COMPARE_WIDTH, '…')) . '</span>';
}

/**
 * One change of the comparison: its kind, what the site shows, what is
 * proposed; side by side from md up, one under the other on a phone. A
 * change only in the markup says so under its kind and shows both sides'
 * HTML.
 *
 * @param string $label
 * @param mixed  $was
 * @param mixed  $now
 * @param bool   $picture
 * @return string
 */
function pg_content_proposals_compare_line($label, $was, $now, $picture = false)
{
    $markup = !$picture && pg_content_proposals_compare_markup_only($was, $now);

    return '<div class="row g-2 py-2 border-top">'
        . '<div class="col-12 md:col-2 pg-text-2"><strong>' . h($label) . '</strong>'
        . ($markup ? '<div class="pg-text-meta">' . h(lang('Only the markup changed: links, formatting or styles.')) . '</div>' : '')
        . '</div>'
        . '<div class="col-12 md:col-5"><div class="pg-text-meta">' . h(lang('On the site')) . '</div>' . pg_content_proposals_compare_value($was, $picture, $markup) . '</div>'
        . '<div class="col-12 md:col-5"><div class="pg-text-meta">' . h(lang('Proposed')) . '</div>' . pg_content_proposals_compare_value($now, $picture, $markup) . '</div>'
        . '</div>';
}
