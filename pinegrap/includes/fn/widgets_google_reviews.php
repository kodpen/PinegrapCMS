<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Google Reviews system widget (kind
 * 'google_reviews'): the business's Google rating and up to five reviews,
 * read by includes/fn/google_reviews.php. With no key or Place ID, or
 * nothing read, the widget draws nothing.
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
 * One row of tokens per review.
 *
 * @param array $place pg_grev_parse()
 * @return array
 */
function pg_grev_rows($place)
{
    $rows = array();

    foreach ((is_array($place) && isset($place['reviews'])) ? $place['reviews'] : array() as $review) {
        $rows[] = array(
            '__review_author_name'   => (string) $review['author'],
            '__review_author_url'    => (string) $review['author_url'],
            '__review_author_photo'  => (string) $review['photo'],
            '__review_rating'        => (string) (int) $review['rating'],
            '__review_text'          => (string) $review['text'],
            '__review_relative_time' => (string) $review['relative'],
            '__review_url'           => (string) $review['url'],
        );
    }

    return $rows;
}

/**
 * The tokens outside the rows.
 *
 * @param array $place pg_grev_parse()
 * @return array
 */
function pg_grev_static_tokens($place)
{
    return array(
        '__place_name'         => (string) $place['name'],
        '__place_rating'       => number_format((float) $place['rating'], 1, '.', ''),
        '__place_review_count' => (string) (int) $place['count'],
        '__place_maps_url'     => (string) $place['maps_url'],
        // Google's attribution for Places content shown without a map: its
        // own words, never translated.
        '__google_attribution' => 'Google Maps',
    );
}

// Render a 'google_reviews' system widget.
//
// loop_area: one row per review (pg_grev_rows()). Row tokens:
// ^^__review_author_name^^, ^^__review_author_url^^ (the author on Google
// Maps), ^^__review_author_photo^^, ^^__review_rating^^ (1-5),
// ^^__review_text^^, ^^__review_relative_time^^ ("2 weeks ago", Google's
// words in the language asked), ^^__review_url^^ (the review on Google Maps).
// Tokens: ^^__place_name^^, ^^__place_rating^^ (one decimal),
// ^^__place_review_count^^, ^^__place_maps_url^^, ^^__google_attribution^^.
// Flags: has_reviews, no_reviews; row: review_has_photo, review_has_text.
// No Messages block.
// $place: for the tests, the place to draw in place of the one read
// (null: nothing read); false reads it.
function _render_system_widget_google_reviews($tree_json, $widget_id, $cfg = array(), $mode = 'preview', $place = false)
{
    static $placement = 0;
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    if ($place === false) $place = pg_grev_place();
    if (!is_array($place)) return '';
    $rows = pg_grev_rows($place);

    // A row's own flags are decided per row, below.
    _eo_apply_visibility_bindings($tree, array(
        'has_reviews'           => (bool)$rows,
        'no_reviews'            => !$rows,
        'review_has_photo'      => true,
        'review_has_text'       => true,
    ));
    _pg_inject_messages_node($tree, 'google_reviews');

    $split = _split_widget_tree($tree);
    $loop_rendered = '';
    if ($split['loop_children'] !== null) {
        $row_tree = array('type' => 'root', 'props' => array(), 'children' => $split['loop_children']);
        foreach ($rows as $i => $row) {
            $t = $row_tree;
            _eo_apply_visibility_bindings($t, array(
                'review_has_photo'      => $row['__review_author_photo'] !== '',
                'review_has_text'       => trim($row['__review_text']) !== '',
            ));
            $html = '';
            foreach ($t['children'] as $child) $html .= _render_tree_node($child, 0, 0);
            $html = pg_sw_sweep_tokens(_pg_sw_fill_tokens(trim($html), pg_sw_escape_token_values($row)));
            $loop_rendered .= pg_sw_uniquify_row_ids($html, $widget_id, $i + 1);
        }
    }

    $static = trim(_render_tree_node($split['static_tree'], 0, 0));
    $tokens = pg_sw_escape_token_values(pg_grev_static_tokens($place) + array(
        '__review_total'       => (string)count($rows),
    ));
    // A page may place the widget twice.
    $static = pg_sw_uniquify_row_ids(pg_sw_sweep_tokens(_pg_sw_fill_tokens($static, $tokens)), $widget_id, 1000 + (++$placement));
    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static);
}
