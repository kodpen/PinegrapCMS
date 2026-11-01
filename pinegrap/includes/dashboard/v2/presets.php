<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's five preset layouts (Corporate, Blog, Ecommerce,
 * Agency, Simple): the cards each starts with and their widths, and the
 * layout a preset makes. The greeting card's part of a preset (shortcuts,
 * tiles, sign-in chips) is pg_dashboard_v2_greeting_preset(). The page, the
 * edit bar and the layout save all read this one list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_greeting.php');

/**
 * The presets in the edit bar's order. cards is "id:width" in grid order
 * (S narrow, M medium, L full); recommended is the gallery's Recommended
 * list.
 *
 * @return array key => array(title, cards, recommended)
 */
function pg_dashboard_v2_presets()
{
    $p = function ($title, $cards, $recommended) {
        return array('title' => $title, 'cards' => $cards, 'recommended' => $recommended);
    };

    return array(
        'corporate' => $p(lang('Corporate'),
            'ai:M goals:S pipeline:L queue:M flows:S visitors:M visitmap:S content:M activity:S firewall:S team:S week:S comments:S gsearch:S pins:S',
            array('engagement', 'mytasks', 'notes', 'calendars')),
        'blog' => $p(lang('Blog'),
            'ai:M goals:S pipeline:L content:M comments:S visitors:M visitmap:S queue:M gsearch:S calendars:S notes:S pins:S perf:S flows:S',
            array('notes', 'engagement', 'ga', 'files')),
        'ecommerce' => $p(lang('Ecommerce'),
            'ai:M goals:S pipeline:L ops:M orders:S salesmap:M shop:S visitors:M engagement:S queue:M flows:S offers:S firewall:S comments:S',
            array('fx', 'perf', 'mytasks', 'visitmap')),
        'agency' => $p(lang('Agency'),
            'ai:M mytasks:S pipeline:L queue:M waiting:S flows:M team:S content:M week:S files:S calendars:S perf:S visitors:M',
            array('notes', 'pins', 'gsearch', 'comments')),
        'simple' => $p(lang('Simple'),
            'ai:M goals:S queue:M activity:S visitors:L',
            array()),
    );
}

/**
 * The preset a person without a saved layout starts from: Ecommerce with the
 * shop on, else Corporate (Pinegrap has no site-type setting).
 *
 * @param bool $shop the ECOMMERCE switch is on
 * @return string
 */
function pg_dashboard_v2_preset_first($shop)
{
    return $shop ? 'ecommerce' : 'corporate';
}

/**
 * A preset's cards as layout entries, every setting at the card's default.
 * Pure.
 *
 * @param string $preset
 * @return array list of id, w, per, cmp, badge
 */
function pg_dashboard_v2_preset_cards($preset)
{
    $presets = pg_dashboard_v2_presets();
    $cards = pg_dashboard_v2_cards();
    $out = array();

    if (!isset($presets[$preset])) {
        return $out;
    }

    foreach (explode(' ', $presets[$preset]['cards']) as $item) {
        list($id, $width) = explode(':', $item);

        $out[] = array(
            'id'    => $id,
            'w'     => $width,
            'per'   => (int) $cards[$id]['period'],
            'cmp'   => 0,
            'badge' => 1,
        );
    }

    return $out;
}

/**
 * The whole layout a preset makes: its cards, every top-section part on,
 * its shortcuts and tiles, and no goals, pins or notes. An unknown preset
 * is the first default. Pure.
 *
 * @param string $preset
 * @param bool   $shop
 * @return array
 */
function pg_dashboard_v2_preset_layout($preset, $shop)
{
    if (!isset(pg_dashboard_v2_presets()[$preset])) {
        $preset = pg_dashboard_v2_preset_first($shop);
    }

    $greeting = pg_dashboard_v2_greeting_preset($preset, $shop);

    return array(
        'v'       => 1,
        'preset'  => $preset,
        'cards'   => pg_dashboard_v2_preset_cards($preset),
        'top'     => array('since' => 1, 'actions' => 1, 'tiles' => 1, 'status' => 1),
        'actions' => $greeting['actions'],
        'tiles'   => $greeting['tiles'],
        'goals'   => array(),
        'pins'    => array(),
        'notes'   => '',
    );
}
