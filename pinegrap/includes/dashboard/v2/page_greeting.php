<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's greeting card: the date and time line in the person's
 * time zone, the greeting by the hour with their name, the "since your last
 * sign-in" chips, the shortcuts and the four figure tiles. What each preset
 * starts the card with is kept here; the figures are in figures.php.
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
 * The hour of the day where the person is: their own time zone when they set
 * one, else the site's (the zone get_absolute_time() draws the line in).
 *
 * @param int $timestamp
 * @return int 0-23
 */
function pg_dashboard_v2_local_hour($timestamp)
{
    $date = new DateTime('@' . (int) $timestamp);
    $zone = pg_time_uses_user_zone(array()) ? (string) USER_TIMEZONE : '';
    $date->setTimezone(pg_timezone_object($zone));

    return (int) $date->format('G');
}

/**
 * The presets' greeting defaults: shortcuts (the first is the main one),
 * the four tiles and the sign-in chips. Simple has none of its own and takes
 * the first default's.
 *
 * @param string $preset corporate, blog, ecommerce, agency or simple
 * @param bool   $shop   the ECOMMERCE switch is on (decides Simple's)
 * @return array actions, tiles, chips
 */
function pg_dashboard_v2_greeting_preset($preset, $shop = false)
{
    $presets = array(
        'corporate' => array(
            'actions' => array('edit_page', 'forms', 'team_task', 'announcement'),
            'tiles'   => array('visits', 'forms', 'seo', 'tasks'),
            'chips'   => array('visits', 'forms', 'comments', 'blocks', 'tasks_done'),
        ),
        'blog' => array(
            'actions' => array('write_post', 'comments', 'visual_editor', 'newsletter'),
            'tiles'   => array('visits', 'subscribers', 'seo', 'inbox'),
            'chips'   => array('visits', 'comments', 'subscribers'),
        ),
        'ecommerce' => array(
            'actions' => array('orders', 'products', 'campaign', 'visual_editor'),
            'tiles'   => array('orders', 'visits', 'online', 'seo'),
            'chips'   => array('orders', 'visits', 'blocks'),
        ),
        'agency' => array(
            'actions' => array('designs', 'share_prototype', 'client_tasks', 'new_page'),
            'tiles'   => array('tasks', 'visits', 'a11y', 'flows'),
            'chips'   => array('comments', 'tasks_done', 'files'),
        ),
    );

    if (!isset($presets[$preset])) {
        $preset = $shop ? 'ecommerce' : 'corporate';
    }

    return $presets[$preset];
}

/**
 * The greeting card's settings from the stored layout JSON: the preset, the
 * shortcuts, the tiles and which parts are on. A missing or unknown value
 * takes the preset's default; the first default is Ecommerce with the shop
 * on, else Corporate. Pure.
 *
 * @param string $layout_json dashboard_layout.layout, '' when none
 * @param bool   $shop
 * @return array preset, actions, tiles, chips, top (since, actions, tiles)
 */
function pg_dashboard_v2_greeting_settings($layout_json, $shop)
{
    $layout = json_decode((string) $layout_json, true);
    $layout = is_array($layout) ? $layout : array();

    $presets = array('corporate', 'blog', 'ecommerce', 'agency', 'simple');
    $preset = (isset($layout['preset']) && in_array($layout['preset'], $presets, true))
        ? $layout['preset']
        : ($shop ? 'ecommerce' : 'corporate');

    $defaults = pg_dashboard_v2_greeting_preset($preset, $shop);

    $keys = function ($name) use ($layout, $defaults) {
        if (!isset($layout[$name]) || !is_array($layout[$name])) {
            return $defaults[$name];
        }
        return array_values(array_filter($layout[$name], 'is_string'));
    };

    $top = array();
    foreach (array('since', 'actions', 'tiles') as $part) {
        $top[$part] = (isset($layout['top'][$part]) && ((int) $layout['top'][$part] === 0)) ? 0 : 1;
    }

    return array(
        'preset'  => $preset,
        'actions' => $keys('actions'),
        'tiles'   => $keys('tiles'),
        'chips'   => $defaults['chips'],
        'top'     => $top,
    );
}

/**
 * The previous sign-in for the chips' line: "today 09:12", "yesterday
 * 22:14", else the date and time.
 *
 * @param int $timestamp
 * @param int $now
 * @return string HTML (get_absolute_time() writes <time> elements)
 */
function pg_dashboard_v2_since_text($timestamp, $now)
{
    $time = get_absolute_time(array('timestamp' => $timestamp, 'type' => 'time'));
    $day = date('Y-m-d', $timestamp);

    if ($day === date('Y-m-d', $now)) {
        return h(lang('today')) . ' ' . $time;
    }

    if ($day === date('Y-m-d', strtotime('-1 day', $now))) {
        return h(lang('yesterday')) . ' ' . $time;
    }

    return get_absolute_time(array('timestamp' => $timestamp, 'type' => 'date')) . ' ' . $time;
}

/**
 * @param array $user the row validate_user() returned
 * @return string HTML
 */
function pg_dashboard_v2_greeting($user)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/home.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/figures.php');

    $now = time();
    $row = pg_dashboard_v2_row($user['id']);
    $settings = pg_dashboard_v2_greeting_settings((string) ($row['layout'] ?? ''), defined('ECOMMERCE') && (ECOMMERCE === true));

    $when = get_absolute_time(array('timestamp' => $now, 'type' => 'date', 'size' => 'long'))
        . ' · ' . get_absolute_time(array('timestamp' => $now, 'type' => 'time'));

    $output = '
            <p class="pg-dash-greeting-when">' . $when . '</p>
            <h2 class="pg-dash-greeting-title" id="pg_dash_greeting_title">' . h(ws_home_greeting(pg_dashboard_v2_local_hour($now))) . ', <span class="pg-dash-name">' . h((string) $user['username']) . '</span></h2>';

    // Since the previous sign-in: no previous one, or nothing happened, no line.
    $since = (int) ($row['signed_in_before'] ?? 0);
    $chips = $settings['top']['since'] ? pg_dashboard_v2_chips($settings['chips'], $user, $since) : array();

    if (!empty($chips)) {
        $output .= '
            <div class="pg-dash-since">
                <span class="pg-dash-since-label">' . h(lang('Since your last sign-in')) . ' · ' . pg_dashboard_v2_since_text($since, $now) . '</span>
                <ul class="pg-dash-chips">';

        foreach ($chips as $chip) {
            $output .= '<li><a class="pg-dash-chip" data-tone="' . h($chip['tone']) . '" href="' . h(pg_dashboard_v2_chip_url($chip['url'], $since, $now)) . '">' . h($chip['text']) . '</a></li>';
        }

        $output .= '</ul>
            </div>';
    }

    if ($settings['top']['actions']) {
        $pool = pg_dashboard_v2_shortcuts();
        $actions = pg_dashboard_v2_shortcuts_pick($settings['actions'], pg_dashboard_v2_shortcuts_allowed($user));

        if (!empty($actions)) {
            $output .= '
            <nav class="pg-dash-actions" aria-label="' . h(lang('Shortcuts')) . '">';

            foreach ($actions as $i => $key) {
                $shortcut = $pool[$key];
                $count = ($shortcut['count'] !== '') ? pg_dashboard_v2_shortcut_count($shortcut['count'], $user) : 0;
                $output .= '<a class="btn ' . (($i === 0) ? 'pg-dash-action-main' : 'btn-outline theme-secondary') . ' pg-dash-action" data-shortcut="' . h($key) . '" href="' . h($shortcut['url']) . '">'
                    . '<i class="bi ' . h($shortcut['icon']) . '" aria-hidden="true"></i> '
                    . '<span class="pg-btn-label">' . h($shortcut['label']) . (($count > 0) ? ' · ' . h(pg_format_number($count, 0)) : '') . '</span></a>';
            }

            $output .= '</nav>';
        }
    }

    if ($settings['top']['tiles']) {
        $tiles = pg_dashboard_v2_tiles_resolve($settings['tiles'], pg_dashboard_v2_figures_allowed($user));

        if (!empty($tiles)) {
            $output .= '
            <ul class="pg-dash-tiles">';

            foreach ($tiles as $key) {
                $figure = pg_dashboard_v2_figure($key, $user);
                $output .= '<li><a class="pg-dash-tile" data-figure="' . h($key) . '" href="' . h($figure['url']) . '">'
                    . '<span class="pg-dash-tile-label">' . h($figure['label']) . '</span>'
                    . '<span class="pg-dash-tile-value">' . h(pg_format_number($figure['value'], 0)) . '</span>'
                    . (($figure['note'] !== '') ? '<span class="pg-dash-tile-note" data-tone="' . h($figure['tone']) . '">' . h($figure['note']) . '</span>' : '')
                    . '</a></li>';
            }

            $output .= '</ul>';
        }
    }

    return '
        <section class="card pg-dash-card pg-dash-greeting" aria-labelledby="pg_dash_greeting_title">' . $output . '
        </section>';
}

/**
 * A chip's list opened on the time since the sign-in: the list screens that
 * take a date range get it; the others open as they are.
 *
 * @param string $url
 * @param int    $since
 * @param int    $now
 * @return string
 */
function pg_dashboard_v2_chip_url($url, $since, $now)
{
    if (in_array($url, array('view_orders.php', 'view_submitted_forms.php'), true)) {
        require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');
        return pg_dashboard_list_url($url, array(), $since, $now);
    }

    return $url;
}
