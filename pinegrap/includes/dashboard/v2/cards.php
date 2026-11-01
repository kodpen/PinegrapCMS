<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * New dashboard - the list of the 30 cards.
 *
 * One entry per card, keyed by its string id; new cards never take a widget
 * number, and a card drawn from a widget's data names it in 'widget'. Each
 * entry:
 *
 *   title     the card's title, through lang()
 *   icon      Bootstrap icon class
 *   category  general, content, visitors, security, team, ecommerce, system
 *             (the gallery's groups and the icon colour)
 *   width     default width: S (one track), M (two), L (every track)
 *   period    default period index (0 today, 1 seven days, 2 thirty days),
 *             or null for a card without a period pill
 *   gate      who may see it: all, staff (role < 3), admin (role < 2),
 *             workspace (Workspace members), ecommerce, calendars, or
 *             widget (the 'widget' number's show rule on the current
 *             dashboard)
 *   widget    the widget whose data the card shares, or 0
 *   built     true once its step has drawn it; a card that is not built is
 *             neither offered nor drawn
 *
 * A card step sets its entries' 'built' and leaves the rest of the list
 * alone.
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
 * The card categories in gallery order, id => title.
 *
 * @return array
 */
function pg_dashboard_v2_card_categories()
{
    return array(
        'general'   => lang('General'),
        'content'   => lang('Content'),
        'visitors'  => lang('Visitors'),
        'security'  => lang('Security'),
        'team'      => lang('Team'),
        'ecommerce' => lang('Ecommerce'),
        'system'    => lang('System'),
    );
}

/**
 * @return array id => entry (see the file header)
 */
function pg_dashboard_v2_cards()
{
    $card = function ($title, $icon, $category, $width, $period, $gate, $widget = 0) {
        return array(
            'title'    => $title,
            'icon'     => $icon,
            'category' => $category,
            'width'    => $width,
            'period'   => $period,
            'gate'     => $gate,
            'widget'   => $widget,
            'built'    => false,
        );
    };

    return array(
        // General
        'ai'         => $card(lang('Assistant'), 'bi-stars', 'general', 'M', null, 'workspace'),
        'goals'      => $card(lang('My Goals'), 'bi-bullseye', 'general', 'S', null, 'all'),
        'pipeline'   => $card(lang('Pipeline'), 'bi-funnel', 'general', 'L', 2, 'all'),
        'queue'      => $card(lang('Next Up'), 'bi-list-ol', 'general', 'M', null, 'all'),
        'activity'   => $card(lang('Activity'), 'bi-activity', 'general', 'S', null, 'widget', 7),
        'flows'      => $card(lang('Flows'), 'bi-diagram-3', 'general', 'S', null, 'workspace'),
        'pins'       => $card(lang('Pinned'), 'bi-pin-angle', 'general', 'S', null, 'all'),
        'notes'      => $card(lang('My Notes'), 'bi-journal-text', 'general', 'S', null, 'all'),

        // Content
        'content'    => $card(lang('Content and Design'), 'bi-palette', 'content', 'M', null, 'all'),
        'comments'   => $card(lang('Comment Moderation'), 'bi-chat-square-quote', 'content', 'S', null, 'widget', 25),
        'files'      => $card(lang('File Management'), 'bi-hdd-stack', 'content', 'S', null, 'widget', 18),

        // Visitors
        'visitors'   => $card(lang('Visitor Summaries'), 'bi-graph-up', 'visitors', 'M', 0, 'widget', 5),
        'visitmap'   => $card(lang('Visitor Map'), 'bi-geo-alt', 'visitors', 'S', 2, 'staff'),
        'engagement' => $card(lang('Online Engagement'), 'bi-broadcast-pin', 'visitors', 'S', null, 'widget', 4),
        'gsearch'    => $card(lang('Google Search'), 'bi-google', 'visitors', 'S', 1, 'widget', 31),
        'ga'         => $card(lang('Google Analytics'), 'bi-bar-chart-line', 'visitors', 'M', 1, 'widget', 32),

        // Security
        'firewall'   => $card(lang('Firewall'), 'bi-shield-check', 'security', 'S', 0, 'staff', 21),

        // Team
        'team'       => $card(lang('Team Pulse'), 'bi-people', 'team', 'S', null, 'widget', 30),
        'mytasks'    => $card(lang('My Tasks'), 'bi-list-check', 'team', 'S', null, 'workspace', 27),
        'waiting'    => $card(lang('Waiting on Me'), 'bi-hourglass-split', 'team', 'S', null, 'workspace', 28),
        'week'       => $card(lang('My Week'), 'bi-calendar-week', 'team', 'S', null, 'workspace', 29),
        'calendars'  => $card(lang('Calendars'), 'bi-calendar3', 'team', 'S', null, 'calendars', 20),

        // Ecommerce
        'shop'       => $card(lang('Ecommerce Summary'), 'bi-cash-stack', 'ecommerce', 'M', 2, 'widget', 3),
        'salesmap'   => $card(lang('Sales Map'), 'bi-map', 'ecommerce', 'M', 2, 'widget', 1),
        'ops'        => $card(lang('Operations Desk'), 'bi-clipboard-check', 'ecommerce', 'M', null, 'widget', 9),
        'orders'     => $card(lang('Orders'), 'bi-cart4', 'ecommerce', 'S', null, 'widget', 8),
        'offers'     => $card(lang('Offers'), 'bi-tag', 'ecommerce', 'S', null, 'ecommerce', 15),
        'fx'         => $card(lang('Exchange Rates'), 'bi-currency-exchange', 'ecommerce', 'S', null, 'ecommerce', 16),

        // System
        'perf'       => $card(lang('Performance'), 'bi-speedometer2', 'system', 'S', null, 'widget', 23),
        'subs'       => $card(lang('Subscriptions'), 'bi-patch-check', 'system', 'S', null, 'admin'),
    );
}
