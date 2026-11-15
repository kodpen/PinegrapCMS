<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The link preview of a Workspace message: the first web address of its
 * text, read once by the general job, kept as one ws_link_previews row per
 * message (title, description, site name) for the card under the message.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest address kept; a longer one gets no preview. */
const WS_LINK_PREVIEW_URL_MAX = 2048;

/**
 * The first http or https address of a message's text, or ''. An address
 * inside code (a `span` or a ``` block) or inside a tag (<#order:5>,
 * <@app:2>) is not one. Closing punctuation of the sentence is left out.
 *
 * @param string $text
 * @return string
 */
function ws_link_preview_first_url($text)
{
    $text = (string) $text;

    // Code first: a fenced block, then an inline span; then the tags.
    $text = preg_replace('/```.*?(```|$)/s', ' ', $text);
    $text = preg_replace('/`[^`\n]*`/', ' ', (string) $text);
    $text = preg_replace('/<[#@][^>\s]*>/', ' ', (string) $text);

    if (!preg_match('#\bhttps?://[^\s<>"\'`]+#i', (string) $text, $match)) {
        return '';
    }

    $url = $match[0];

    // A sentence's end is not part of the address; a closing bracket is,
    // when the address opened one itself (a Wikipedia title).
    while ($url !== '') {
        $last = substr($url, -1);

        if (strpos('.,;:!?\'"*_', $last) !== false) {
            $url = substr($url, 0, -1);
        } elseif ((($last === ')') && (substr_count($url, '(') < substr_count($url, ')')))
            || (($last === ']') && (substr_count($url, '[') < substr_count($url, ']')))) {
            $url = substr($url, 0, -1);
        } else {
            break;
        }
    }

    if ((strlen($url) > WS_LINK_PREVIEW_URL_MAX) || !preg_match('#^https?://[^/?\#]+#i', $url)) {
        return '';
    }

    return $url;
}
