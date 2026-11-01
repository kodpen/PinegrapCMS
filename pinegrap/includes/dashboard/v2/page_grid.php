<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's card grid and the configuration its script starts
 * from: the cards the person may see with their titles, icons, categories,
 * widths and periods, and the texts the script shows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * The cards the grid draws, in order: the stored layout's cards the person
 * may see (unknown ids, repeats and bad values dropped or set to the card's
 * default), or every card they may see when nothing is stored. A stored card
 * the person may not see stays stored and is only skipped here.
 *
 * @param mixed $stored  the layout's cards list
 * @param array $visible id => card, the cards the person may see
 * @return array list of id, w, per, cmp, badge
 */
function pg_dashboard_v2_grid_layout($stored, $visible)
{
    $out = array();
    $seen = array();

    $entries = (is_array($stored) && !empty($stored)) ? $stored : array();

    if (empty($entries)) {
        foreach (array_keys($visible) as $id) {
            $entries[] = array('id' => $id);
        }
    }

    foreach ($entries as $entry) {
        if (!is_array($entry) || !isset($entry['id']) || !is_string($entry['id']) || !isset($visible[$entry['id']]) || isset($seen[$entry['id']])) {
            continue;
        }

        $id = $entry['id'];
        $card = $visible[$id];
        $seen[$id] = true;

        $per = (isset($entry['per']) && is_int($entry['per']) && ($entry['per'] >= 0) && ($entry['per'] <= 2)) ? $entry['per'] : (int) $card['period'];

        $out[] = array(
            'id'    => $id,
            'w'     => (isset($entry['w']) && in_array($entry['w'], array('S', 'M', 'L'), true)) ? $entry['w'] : $card['width'],
            'per'   => ($card['period'] === null) ? null : $per,
            'cmp'   => (isset($entry['cmp']) && is_int($entry['cmp']) && ($entry['cmp'] >= 0) && ($entry['cmp'] <= 2)) ? $entry['cmp'] : 0,
            'badge' => isset($entry['badge']) ? (int) (bool) $entry['badge'] : 1,
        );
    }

    return $out;
}

/**
 * The script's configuration.
 *
 * @param array      $user
 * @param array|null $row the person's dashboard_layout row
 * @return array
 */
function pg_dashboard_v2_config($user, $row = null)
{
    $visible = pg_dashboard_v2_cards_for($user);
    $cards = array();

    foreach ($visible as $id => $card) {
        $cards[$id] = array(
            'title'    => $card['title'],
            'icon'     => $card['icon'],
            'category' => $card['category'],
            'period'   => $card['period'],
        );
    }

    $stored = null;

    if (is_array($row) && isset($row['layout']) && is_string($row['layout']) && ($row['layout'] !== '')) {
        $layout = json_decode($row['layout'], true);
        $stored = (is_array($layout) && isset($layout['cards'])) ? $layout['cards'] : null;
    }

    return array(
        'cards'      => $cards,
        'layout'     => pg_dashboard_v2_grid_layout($stored, $visible),
        'timing'     => pg_dashboard_v2_card_timing(),
        'categories' => pg_dashboard_v2_categories(),
        'periods'    => pg_dashboard_v2_periods(),
        'refresh'    => pg_dashboard_refresh_seconds($_SESSION['software']['welcome']['widget']['refresh'] ?? '', 60),
        'strings'    => array(
            'empty'   => lang('No cards on your dashboard yet.'),
            'error'   => lang('This card could not be loaded.'),
            'retry'   => lang('Try again'),
            'loading' => lang('Loading…'),
            'period'  => lang('Period: {var:1}. Choose to change.'),
            'all'     => lang('See all'),
        ),
    );
}

/**
 * The grid, empty until the script draws the cards, and its configuration.
 *
 * @param array      $user
 * @param array|null $row
 * @return string HTML
 */
function pg_dashboard_v2_grid($user, $row = null)
{
    return '
        <div class="pg-dash-grid" id="pg_dash_grid" aria-live="polite"></div>
        <script type="application/json" id="pg_dash_config">' . pg_dashboard_v2_json(pg_dashboard_v2_config($user, $row)) . '</script>';
}

/**
 * JSON safe inside a <script> element: no "</" can close it early.
 *
 * @param mixed $value
 * @return string
 */
function pg_dashboard_v2_json($value)
{
    return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}
