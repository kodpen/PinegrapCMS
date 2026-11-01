<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's card gallery: for every card of the card list the
 * line the gallery shows under its title, whether it is new with this
 * dashboard (no old widget grew into it), and the small sketch drawn above
 * it; and the edit mode's side panel parts the page sends the script (the
 * Recommended lists, the look choices).
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
 * The sketch kinds pg_dash_edit.js knows how to draw.
 *
 * @return string[]
 */
function pg_dashboard_v2_sketches()
{
    return array('line', 'bars', 'funnel', 'rows', 'timeline', 'rings', 'avatars', 'chat', 'note', 'boxes', 'cells', 'map', 'live', 'gauge', 'flow');
}

/**
 * The gallery's part of every card: desc (one line, through lang()), new
 * (1 for a card with no old widget behind it) and sketch (a kind of
 * pg_dashboard_v2_sketches()). Keyed like pg_dashboard_v2_cards().
 *
 * @return array id => array(desc, new, sketch)
 */
function pg_dashboard_v2_gallery_cards()
{
    $g = function ($desc, $new, $sketch) {
        return array('desc' => $desc, 'new' => $new, 'sketch' => $sketch);
    };

    return array(
        'ai'         => $g(lang('A daily summary, proposals and jobs from the connected Claude'), 1, 'chat'),
        'goals'      => $g(lang('Goals you set yourself, progress ring by ring'), 1, 'rings'),
        'pipeline'   => $g(lang('From visit to result step by step; named after the kind of site'), 1, 'funnel'),
        'queue'      => $g(lang('Work waiting for you in the modules that are on'), 1, 'rows'),
        'activity'   => $g(lang('Content, form, team and security events on a timeline'), 0, 'timeline'),
        'flows'      => $g(lang('Running flows, steps waiting for approval, failed runs'), 1, 'flow'),
        'pins'       => $g(lang('The pages and screens you open often, box by box'), 1, 'boxes'),
        'notes'      => $g(lang('Short notes to yourself'), 1, 'note'),
        'content'    => $g(lang('Pages edited lately, drafts, scheduled ones'), 1, 'boxes'),
        'comments'   => $g(lang('Comments waiting for approval, like a chat'), 0, 'chat'),
        'files'      => $g(lang('Storage ring and the files uploaded lately'), 0, 'rings'),
        'visitors'   => $g(lang('Visit chart, most read pages, sources'), 0, 'line'),
        'visitmap'   => $g(lang('Visitors city by city on a map'), 1, 'map'),
        'engagement' => $g(lang('Who is on the site now, the last 30 minutes'), 0, 'live'),
        'gsearch'    => $g(lang('Search Console clicks, impressions and queries'), 0, 'boxes'),
        'ga'         => $g(lang('GA4 figures and traffic sources'), 0, 'bars'),
        'firewall'   => $g(lang('Blocked requests, bots, failed sign-ins'), 0, 'bars'),
        'team'       => $g(lang('Who is online and who is on what'), 0, 'avatars'),
        'mytasks'    => $g(lang('The tasks on your list; tick them off'), 0, 'rows'),
        'waiting'    => $g(lang('Work your teammates are waiting on you for'), 0, 'rows'),
        'week'       => $g(lang('This week day by day'), 0, 'cells'),
        'calendars'  => $g(lang('The next five weeks at a glance'), 0, 'cells'),
        'shop'       => $g(lang('Revenue, daily sales, best sellers'), 0, 'bars'),
        'salesmap'   => $g(lang('Sales region by region on a map'), 0, 'map'),
        'ops'        => $g(lang('Shipping, returns and products that do not sell'), 0, 'boxes'),
        'orders'     => $g(lang('The latest orders and their status'), 0, 'rows'),
        'offers'     => $g(lang('Active campaigns and coupons'), 0, 'rows'),
        'fx'         => $g(lang('Rates and their weekly course'), 0, 'line'),
        'perf'       => $g(lang('Response time gauge and slow pages'), 0, 'gauge'),
        'subs'       => $g(lang('The state of the Pinegrap subscription'), 0, 'boxes'),
    );
}

/**
 * A preset's Recommended list cut to the cards the person may see. Pure.
 *
 * @param string[] $recommended the preset's list
 * @param array    $visible     id => card
 * @return string[]
 */
function pg_dashboard_v2_recommended($recommended, $visible)
{
    return array_values(array_filter((array) $recommended, function ($id) use ($visible) {
        return is_string($id) && isset($visible[$id]);
    }));
}

/**
 * The look section's choices and the backdrop "Auto" draws for each card
 * style (pg_dashboard_appearance()'s map), so the script shows a pick at
 * once.
 *
 * @return array style, backdrop (value => title), auto (style => backdrop)
 */
function pg_dashboard_v2_look_choices()
{
    $auto = array();

    foreach (pg_dashboard_appearance_choices()['widget_theme'] as $style) {
        $auto[$style] = pg_dashboard_appearance(array('widget_theme' => $style, 'panel_backdrop' => 'auto'))['backdrop'];
    }

    return array(
        'style' => array(
            'flat'   => lang('Flat'),
            'neon'   => lang('Neon'),
            'glass'  => lang('Glass'),
            'aurora' => lang('Aurora'),
        ),
        'backdrop' => array(
            'auto'  => lang('Auto'),
            'none'  => lang('None'),
            'mesh'  => lang('Mesh'),
            'dusk'  => lang('Dusk'),
            'ember' => lang('Ember'),
        ),
        'auto' => $auto,
    );
}

/**
 * The side panel's texts.
 *
 * @return array
 */
function pg_dashboard_v2_gallery_strings()
{
    return array(
        'add_card'     => lang('Add card'),
        'add_from'     => lang('Choose from {var:1} cards'),
        'search_label' => lang('Search cards'),
        'search'       => lang('Search cards: map, flow, order…'),
        'all'          => lang('All'),
        'recommended'  => lang('Recommended'),
        'tag_new'      => lang('New'),
        'add'          => lang('Add'),
        'add_named'    => lang('Add the {var:1} card'),
        'on_board'     => lang('On the dashboard · Remove'),
        'remove_named' => lang('Remove the {var:1} card'),
        'added'        => lang('{var:1} added'),
        'no_match'     => lang('No card matches this search.'),
        'panel'        => lang('Card gallery and look'),
        'top_title'    => lang('Top section'),
        'top_since'    => lang('Since your last sign-in'),
        'top_actions'  => lang('Shortcuts'),
        'top_tiles'    => lang('Tiles'),
        'top_status'   => lang('System Status'),
        'look_title'   => lang('Look'),
        'look_style'   => lang('Card style'),
        'look_backdrop' => lang('Panel backdrop'),
        'look_dense'   => lang('Density'),
        'dense'        => array(lang('Comfortable'), lang('Compact')),
        'look_calm'    => lang('Lights and shadows'),
        'calm'         => array(lang('On'), lang('Off')),
        'calm_hint'    => lang('Off runs smoother on weak devices.'),
    );
}
