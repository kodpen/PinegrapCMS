<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's card list: the thirty cards a layout may hold, each
 * with its title, icon, category, default width and period, the gate that
 * decides who may see it, and whether its card function is built yet. The
 * page, the gallery and the layout save all read this one list.
 *
 * Card ids are strings and permanent (layouts store them); they never take a
 * widget number, which stays the old dashboard's.
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
 * The categories in gallery order.
 *
 * @return array key => title
 */
function pg_dashboard_v2_categories()
{
    return array(
        'general'  => lang('General'),
        'content'  => lang('Content'),
        'visitors' => lang('Visitors'),
        'security' => lang('Security'),
        'team'     => lang('Team'),
        'shop'     => lang('Ecommerce'),
        'system'   => lang('System'),
    );
}

/**
 * The periods a card with a period cycles through; a layout stores the index.
 *
 * @return array index => title
 */
function pg_dashboard_v2_periods()
{
    return array(
        0 => lang('Today'),
        1 => lang('Last 7 Days'),
        2 => lang('Last 30 Days'),
    );
}

/**
 * The thirty cards, in the gallery's order.
 *
 * width:  S narrow (one track), M medium (two), L full (every track).
 * period: index into pg_dashboard_v2_periods(), null for a card without one.
 * gate:   a key of pg_dashboard_v2_gate().
 * built:  its card function exists; a card not built yet is neither drawn
 *         nor offered.
 *
 * @return array id => array(title, icon, category, width, period, gate, built)
 */
function pg_dashboard_v2_cards()
{
    // The cards whose function exists.
    $built = array('visitors', 'engagement', 'gsearch', 'ga', 'content', 'comments', 'files', 'activity', 'firewall', 'team', 'mytasks', 'waiting', 'week', 'calendars');
    $built = array_merge($built, array('shop', 'salesmap', 'orders', 'offers', 'fx'));

    $card = function ($title, $icon, $category, $width, $period, $gate) {
        return array(
            'title'    => $title,
            'icon'     => $icon,
            'category' => $category,
            'width'    => $width,
            'period'   => $period,
            'gate'     => $gate,
            'built'    => false,
        );
    };

    $cards = array(

        // General
        'ai'         => $card(lang('Assistant'), 'bi-stars', 'general', 'M', null, 'workspace'),
        'goals'      => $card(lang('My goals'), 'bi-bullseye', 'general', 'S', null, 'all'),
        'pipeline'   => $card(lang('Pipeline'), 'bi-funnel', 'general', 'L', 2, 'all'),
        'queue'      => $card(lang('Next up'), 'bi-list-ol', 'general', 'M', null, 'all'),
        'activity'   => $card(lang('Activity'), 'bi-activity', 'general', 'S', null, 'all'),
        'flows'      => $card(lang('Flows'), 'bi-diagram-3', 'general', 'S', null, 'workspace'),
        'pins'       => $card(lang('Pinned'), 'bi-pin-angle', 'general', 'S', null, 'all'),
        'notes'      => $card(lang('My notes'), 'bi-journal-text', 'general', 'S', null, 'all'),
        // Content
        'content'    => $card(lang('Content and design'), 'bi-layout-text-window', 'content', 'M', null, 'files'),
        'comments'   => $card(lang('Comment Moderation'), 'bi-chat-square-quote', 'content', 'S', null, 'files'),
        'files'      => $card(lang('File Management'), 'bi-hdd-stack', 'content', 'S', null, 'files'),
        // Visitors
        'visitors'   => $card(lang('Visitor Summaries'), 'bi-graph-up', 'visitors', 'M', 0, 'visitors'),
        'visitmap'   => $card(lang('Visitor map'), 'bi-geo-alt', 'visitors', 'S', 2, 'visitors'),
        'engagement' => $card(lang('Online Engagement'), 'bi-broadcast-pin', 'visitors', 'S', null, 'staff'),
        'gsearch'    => $card(lang('Google Search'), 'bi-google', 'visitors', 'S', 1, 'gsearch'),
        'ga'         => $card(lang('Google Analytics'), 'bi-bar-chart-line', 'visitors', 'M', null, 'ga'),
        // Security
        'firewall'   => $card(lang('Firewall'), 'bi-shield-check', 'security', 'S', 0, 'staff'),
        // Team
        'team'       => $card(lang('Team Pulse'), 'bi-people', 'team', 'S', null, 'team'),
        'mytasks'    => $card(lang('My Tasks'), 'bi-list-check', 'team', 'S', null, 'workspace'),
        'waiting'    => $card(lang('Waiting on Me'), 'bi-hourglass-split', 'team', 'S', null, 'workspace'),
        'week'       => $card(lang('My Week'), 'bi-calendar-week', 'team', 'S', null, 'workspace'),
        'calendars'  => $card(lang('Calendars'), 'bi-calendar3', 'team', 'S', null, 'calendars'),
        // Ecommerce
        'shop'       => $card(lang('Ecommerce Summary'), 'bi-cash-stack', 'shop', 'M', 2, 'shop_summary'),
        'salesmap'   => $card(lang('Sales Map'), 'bi-map', 'shop', 'M', null, 'shop_reports'),
        'ops'        => $card(lang('Operations Desk'), 'bi-clipboard-check', 'shop', 'M', null, 'shop'),
        'orders'     => $card(lang('Orders'), 'bi-cart4', 'shop', 'S', null, 'shop'),
        'offers'     => $card(lang('Offers'), 'bi-tag', 'shop', 'S', null, 'shop'),
        'fx'         => $card(lang('Exchange rates'), 'bi-currency-exchange', 'shop', 'S', null, 'shop'),
        // System
        'perf'       => $card(lang('Performance'), 'bi-speedometer2', 'system', 'S', null, 'perf'),
        'subs'       => $card(lang('Subscriptions'), 'bi-patch-check', 'system', 'S', null, 'designer'),
    );

    foreach ($built as $id) {
        $cards[$id]['built'] = true;
    }

    return $cards;
}

/**
 * The cards drawn again at the session's refresh time (their content moves
 * minute to minute), and the slow ones asked for on their own beside the
 * batch so they do not hold the others back.
 *
 * @return array refresh => ids, solo => ids
 */
function pg_dashboard_v2_card_timing()
{
    return array(
        'refresh' => array('engagement', 'firewall', 'visitors'),
        'solo'    => array('gsearch', 'ga', 'flows'),
    );
}

/**
 * The gate keys a card may name.
 *
 * @return string[]
 */
function pg_dashboard_v2_gates()
{
    return array('all', 'staff', 'designer', 'files', 'visitors', 'gsearch', 'ga', 'workspace', 'team', 'calendars', 'shop', 'shop_summary', 'shop_reports', 'perf');
}

/**
 * May the person see a card behind this gate? The rules are the old
 * dashboard's `show` rules for the widget each card grows from, so a person
 * sees the same things on both dashboards. An unknown gate is closed.
 *
 * @param string $gate a key of pg_dashboard_v2_gates()
 * @param array  $user the row validate_user() returned
 * @return bool
 */
function pg_dashboard_v2_gate($gate, $user)
{
    $role = (int) $user['role'];
    $visitors = ($role < 3) || !empty($user['manage_visitors']);
    $shop = (defined('ECOMMERCE') && (ECOMMERCE === true));

    switch ((string) $gate) {
        case 'all':
            return true;
        case 'staff':
            return $role < 3;
        case 'designer':
            return $role < 2;
        case 'files':
            return ($role < 3) || no_acl_check($user['id']);
        case 'visitors':
            return $visitors;
        case 'gsearch':
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_console.php');
            return $visitors && pg_gsc_linked();
        case 'ga':
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/google_analytics.php');
            return $visitors && pg_ga_linked();
        case 'workspace':
        case 'team':
            require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');
            $rights = pg_dashboard_workspace_rights($user);
            if ($rights === null) {
                return false;
            }
            return ($gate === 'workspace') || !empty($rights['board']) || !empty(ws_led_people($rights['id']));
        case 'calendars':
            return defined('CALENDARS') && (CALENDARS === true) && (validate_calendars_access($user, true) != false);
        case 'shop':
            return $shop && (($role < 3) || !empty($user['manage_ecommerce']));
        case 'shop_summary':
            return $shop && (($role < 3) || (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE) || (defined('USER_MANAGE_ECOMMERCE_REPORTS') && USER_MANAGE_ECOMMERCE_REPORTS));
        case 'shop_reports':
            return $shop && defined('USER_MANAGE_ECOMMERCE_REPORTS') && USER_MANAGE_ECOMMERCE_REPORTS;
        case 'perf':
            return ($role < 3) && ((!defined('PERF_MONITOR_ENABLED')) || PERF_MONITOR_ENABLED);
    }

    return false;
}

/**
 * The cards the person may see and that are built, in list order.
 *
 * @param array $user
 * @return array id => card
 */
function pg_dashboard_v2_cards_for($user)
{
    $cards = array();

    foreach (pg_dashboard_v2_cards() as $id => $card) {
        if ($card['built'] && pg_dashboard_v2_gate($card['gate'], $user)) {
            $cards[$id] = $card;
        }
    }

    return $cards;
}
