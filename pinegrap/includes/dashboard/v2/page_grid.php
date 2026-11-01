<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * New dashboard - the card grid.
 *
 * The grid the cards are drawn into: three tracks over about 1000 px, two
 * to 640 px, one below (assets/css/dashboard_v2.src.css). Empty until the
 * card frame step draws each card's frame and skeleton here.
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
 * @return string
 */
function pg_dashboard_v2_grid()
{
    return '
    <section class="pg-dash-grid" id="pg_dash_grid" aria-label="' . h(lang('Cards')) . '"></section>';
}
