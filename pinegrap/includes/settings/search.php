<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The search of the Site Settings dialog: one index of every card the reader
 * may open, with the card's name, its category, its keywords and the labels
 * of the fields it draws.
 *
 * The names and keywords come from the registry. The field labels come from
 * the cards themselves, drawn the way the dialog draws them, so a field added
 * to a card is found without anyone keeping a second list of it, and a field
 * a role does not see is not offered to that role.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}


/**
 * The names a set of drawn cards shows: each card's heading and the labels,
 * legends and headings inside it.
 *
 * A card runs from the element carrying its id to the next listed card's id,
 * so a sub-anchor inside a card (pgsub-…) stays part of it and an element
 * that is no card of the list ends the one before it only when listed. A
 * text longer than a name (a sentence used as a label) is skipped: it would
 * match half the queries typed.
 *
 * @param string $html the cards as the dialog receives them
 * @param array  $ids  the card ids to read, from the registry's sections
 * @return array card id => list of texts, in drawing order, repeats dropped
 */
function pg_settings_card_labels($html, $ids)
{
    $html = (string) $html;
    $starts = array();

    foreach ($ids as $id) {
        $at = strpos($html, 'id="' . $id . '"');

        if ($at !== false) {
            $starts[$id] = $at;
        }
    }

    // Any other pgset- card ends the one before it too, so a card the list
    // leaves out (a role's card, say) lends it no labels.
    $bounds = array_values($starts);

    if (preg_match_all('/id="pgset-[a-z0-9-]+"/', $html, $all, PREG_OFFSET_CAPTURE)) {
        foreach ($all[0] as $hit) {
            $bounds[] = $hit[1];
        }
    }

    sort($bounds);

    $labels = array();

    foreach ($starts as $id => $start) {

        $end = strlen($html);

        foreach ($bounds as $bound) {
            if ($bound > $start) {
                $end = $bound;
                break;
            }
        }

        $segment = substr($html, $start, $end - $start);

        preg_match_all(
            '~<(label|legend|h[2-6])\b[^>]*>(.*?)</\1>|<div\b[^>]*class="[^"]*\bcard-header\b[^"]*"[^>]*>(.*?)</div>~is',
            $segment, $match, PREG_SET_ORDER);

        $texts = array();
        $seen = array();

        foreach ($match as $found) {

            $raw = isset($found[3]) && ($found[3] !== '') ? $found[3] : $found[2];
            $text = html_entity_decode(strip_tags($raw), ENT_QUOTES, 'UTF-8');
            $text = trim(preg_replace('/\s+/u', ' ', $text));
            $text = trim(rtrim($text, ':* '));

            if (($text === '') || (mb_strlen($text, 'UTF-8') > 60)) {
                continue;
            }

            $folded = mb_strtolower($text, 'UTF-8');

            if (!isset($seen[$folded])) {
                $seen[$folded] = true;
                $texts[] = $text;
            }
        }

        $labels[$id] = $texts;
    }

    return $labels;
}


/**
 * The search index: a row per category, then a row per card of it.
 *
 * Only the sections the registry lists are indexed, so a card it leaves out
 * for this role has no row even when its labels were read. A card's own name
 * is not repeated among its fields.
 *
 * @param array $categories as pg_settings_categories() returns them
 * @param array $labels     category key => pg_settings_card_labels() result
 * @return array list of rows: pane, card ('' for the category), title, place,
 *               keys (keywords), fields (labels)
 */
function pg_settings_search_index($categories, $labels)
{
    $index = array();

    foreach ($categories as $key => $category) {

        $place = (string) $category['label'];

        $index[] = array(
            'pane' => (string) $key, 'card' => '', 'title' => $place,
            'place' => '', 'keys' => array(), 'fields' => array(),
        );

        $sections = isset($category['sections']) ? $category['sections'] : array();

        foreach ($sections as $id => $title) {

            $title = (string) $title;
            $fields = array();

            if (isset($labels[$key][$id])) {
                foreach ($labels[$key][$id] as $text) {
                    if (mb_strtolower($text, 'UTF-8') !== mb_strtolower($title, 'UTF-8')) {
                        $fields[] = (string) $text;
                    }
                }
            }

            $index[] = array(
                'pane'   => (string) $key,
                'card'   => (string) $id,
                'title'  => $title,
                'place'  => $place,
                'keys'   => isset($category['keywords'][$id]) ? array_values($category['keywords'][$id]) : array(),
                'fields' => $fields,
            );
        }
    }

    return $index;
}


/**
 * The search rows of the System tools screen and of every tool on it.
 *
 * The dialog's list ends on one "System tools" row; its screens are found by
 * the search too, under the gates pg_settings_tool_groups() gave them. A row
 * carries the url it opens instead of a pane: a tool that asks first (a
 * confirm) opens the System tools screen, where the question is asked. A
 * keyword that only starts another of the tool's words ("tem" beside
 * "temizle") is the palette's shorthand and is left out: here it would pull
 * the tool into every query that starts with it.
 *
 * @param array  $groups   pg_settings_tool_groups()
 * @param string $base_url the software directory, ending in a slash
 * @return array rows as pg_settings_search_index() writes them, plus url,
 *               target and icon; empty when the reader has no tool
 */
function pg_settings_search_tool_rows($groups, $base_url)
{
    $keys = pg_settings_tool_keys();
    $screen_url = $base_url . 'system_tools.php';
    $screen = lang('System tools');
    $rows = array();

    foreach ((array) $groups as $group) {

        if (empty($group['items'])) {
            continue;
        }

        foreach ($group['items'] as $item) {

            $script = (string) strtok((string) $item['url'], '?');
            $words = isset($keys[$script]) ? $keys[$script] : array();

            $rows[] = array(
                'pane'   => '',
                'card'   => '',
                'title'  => (string) $item['label'],
                'place'  => $screen . ' › ' . $group['label'],
                'keys'   => pg_settings_search_tool_words($item['label'], $words),
                'fields' => array(),
                'url'    => isset($item['confirm']) ? $screen_url : $base_url . $item['url'],
                'target' => isset($item['target']) ? (string) $item['target'] : '',
                'icon'   => (string) $item['icon'],
            );
        }
    }

    if (count($rows) === 0) {
        return array();
    }

    array_unshift($rows, array(
        'pane' => '', 'card' => '', 'title' => $screen, 'place' => '',
        'keys' => pg_settings_search_tool_words($screen, $keys['system_tools.php']),
        'fields' => array(), 'url' => $screen_url, 'target' => '', 'icon' => 'bi-tools',
    ));

    return $rows;
}


/**
 * A tool's keywords for the settings search: its words without the ones that
 * only start another of its words or its label.
 *
 * @param string $label the tool's label
 * @param array  $words pg_settings_tool_keys() of its script
 * @return array lower-case words
 */
function pg_settings_search_tool_words($label, $words)
{
    $all = array_merge(array(mb_strtolower((string) $label, 'UTF-8')), (array) $words);
    $kept = array();

    foreach ($all as $word) {

        $word = (string) $word;
        $short = false;

        foreach ($all as $other) {
            $other = (string) $other;

            if (($other !== $word) && (mb_strlen($other, 'UTF-8') > mb_strlen($word, 'UTF-8')) && (mb_strpos($other, $word, 0, 'UTF-8') === 0)) {
                $short = true;
                break;
            }
        }

        if (!$short && !in_array($word, $kept, true)) {
            $kept[] = $word;
        }
    }

    return $kept;
}


/**
 * Build the index for the reader: every category drawn once, its labels read,
 * then the System tools screen and the tools the reader may open.
 *
 * A category that fails to draw keeps its registry row and keywords; the
 * search must not be lost to one card's error.
 *
 * @param array  $user     the row validate_user() returned
 * @param object $liveform the form the cards report through
 * @return array pg_settings_search_index()
 */
function pg_settings_search_build($user, $liveform)
{
    $categories = pg_settings_categories();
    $labels = array();

    foreach ($categories as $key => $category) {

        // Anything a card prints on its own would land in the JSON answer.
        ob_start();

        try {
            $fragment = pg_settings_fragment($key, $user, $liveform);
            $labels[$key] = pg_settings_card_labels($fragment['html'], array_keys($category['sections']));

        } catch (Throwable $error) {
            $labels[$key] = array();

        } finally {
            ob_end_clean();
        }
    }

    return array_merge(
        pg_settings_search_index($categories, $labels),
        pg_settings_search_tool_rows(pg_settings_tool_groups($user), OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/'));
}
