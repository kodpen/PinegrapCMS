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
 * The script's configuration.
 *
 * @param array $user
 * @return array
 */
function pg_dashboard_v2_config($user)
{
    $cards = array();

    foreach (pg_dashboard_v2_cards_for($user) as $id => $card) {
        $cards[] = array(
            'id'       => $id,
            'title'    => $card['title'],
            'icon'     => $card['icon'],
            'category' => $card['category'],
            'width'    => $card['width'],
            'period'   => $card['period'],
        );
    }

    return array(
        'cards'      => $cards,
        'categories' => pg_dashboard_v2_categories(),
        'periods'    => pg_dashboard_v2_periods(),
        'refresh'    => pg_dashboard_refresh_seconds($_SESSION['software']['welcome']['widget']['refresh'] ?? '', 60),
        'strings'    => array(
            'empty' => lang('No cards on your dashboard yet.'),
        ),
    );
}

/**
 * The grid, empty until the script draws the cards, and its configuration.
 *
 * @param array $user
 * @return string HTML
 */
function pg_dashboard_v2_grid($user)
{
    return '
        <div class="pg-dash-grid" id="pg_dash_grid" aria-live="polite"></div>
        <script type="application/json" id="pg_dash_config">' . pg_dashboard_v2_json(pg_dashboard_v2_config($user)) . '</script>';
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
