<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's greeting card: the date and time line in the person's
 * time zone with the next meeting beside it, the greeting by the hour with
 * their name, the brief under it, the "since your last sign-in" chips, the
 * shortcuts and the four figure tiles. What each preset starts the card with
 * is kept here; the figures are in figures.php, the brief's sentence in
 * includes/dashboard/brief.php.
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
 * @return array preset, actions, tiles, chips, top (since, actions, tiles,
 *               status, brief)
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
    foreach (array('since', 'actions', 'tiles', 'status', 'brief') as $part) {
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
    $row = pg_dashboard_v2_row_for($user);
    $settings = pg_dashboard_v2_greeting_settings((string) ($row['layout'] ?? ''), defined('ECOMMERCE') && (ECOMMERCE === true));

    $when = get_absolute_time(array('timestamp' => $now, 'type' => 'date', 'size' => 'long'))
        . ' · ' . get_absolute_time(array('timestamp' => $now, 'type' => 'time'));

    // Switched off in the top section, the brief is not worked out at all.
    $brief = $settings['top']['brief'] ? pg_dashboard_v2_brief_data($user, $now) : null;

    $output = '
            <div class="pg-dash-greeting-head">
                <p class="pg-dash-greeting-when">' . $when . '</p>'
                . (($brief !== null) ? pg_dashboard_v2_brief_next_markup($brief) : '') . '
            </div>
            <h2 class="pg-dash-greeting-title" id="pg_dash_greeting_title">' . h(ws_home_greeting(pg_dashboard_v2_local_hour($now))) . ', <span class="pg-dash-name">' . h((string) $user['username']) . '</span></h2>';

    if ($brief !== null) {
        $output .= pg_dashboard_v2_brief_markup($brief, $now);
    }

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
                $output .= '<a class="pg-dash-btn' . (($i === 0) ? ' pg-dash-btn-main' : '') . ' pg-dash-action" data-shortcut="' . h($key) . '" href="' . h($shortcut['url']) . '">'
                    . h($shortcut['label']) . (($count > 0) ? ' · ' . h(pg_format_number($count, 0)) : '') . '</a>';
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
 * The brief for this person on the new dashboard: brief.php's sentence,
 * workspace strip and next meeting, the tips worded for this dashboard and
 * the mark following the hour the greeting uses. The page and api.php's
 * get_dashboard_brief both go through here.
 *
 * @param array $user the row validate_user() returned
 * @param int   $now
 * @return array pg_dashboard_brief()'s answer
 */
function pg_dashboard_v2_brief_data($user, $now)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/brief.php');

    return pg_dashboard_brief($user, pg_changelog_sections(), array(
        'dashboard' => 'v2',
        'hour'      => pg_dashboard_v2_local_hour($now),
        'edit'      => !pg_dashboard_v2_locked_for($user),
    ));
}

/**
 * The brief under the greeting's title: the hour's mark, the sentence with
 * "Say something else" at its end, and the workspace strip under them. On a
 * narrow dashboard the brief is a box of its own (pg_dash.src.css) whose
 * first row holds the next meeting, or the brief's name when nothing is
 * left today; the group carries the name for screen readers. The page
 * script (pg_dash_brief.js) asks for another line and counts the next
 * meeting down from data-pg-now with the texts in data-pg-until. Pure.
 *
 * @param array $brief pg_dashboard_brief()'s answer
 * @param int   $now
 * @return string HTML
 */
function pg_dashboard_v2_brief_markup($brief, $now)
{
    $until = array(
        'now'  => lang('Now'),
        'min'  => lang(array('string' => 'in {var:1} min', 'vars' => array('%1'))),
        'h'    => lang(array('string' => 'in {var:1} h', 'vars' => array('%1'))),
        'hmin' => lang(array('string' => 'in {var:1} h {var:2} min', 'vars' => array('%1', '%2'))),
    );
    $again = h(lang('Say something else'));
    $name = h(lang("Today's brief"));

    return '
            <div class="pg-dash-brief" id="pg_dash_brief" role="group" aria-label="' . $name . '" data-top-part="brief"'
                . ' data-pg-now="' . (int) $now . '" data-pg-until="' . h(json_encode($until, JSON_UNESCAPED_UNICODE)) . '">
                <div class="pg-dash-brief-row">
                    <span class="pg-dash-brief-mark" aria-hidden="true"><i class="bi ' . h((string) $brief['icon']) . '"></i></span>
                    <span class="pg-dash-brief-head"><span class="pg-dash-brief-kicker" aria-hidden="true">' . $name . '</span>' . pg_dashboard_v2_brief_next_markup($brief, true) . '</span>
                    <p class="pg-dash-brief-text" aria-live="polite">' . $brief['line'] . '</p>
                    <button type="button" class="pg-dash-brief-again" id="pg_dash_brief_again" title="' . $again . '" aria-label="' . $again . '"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></button>
                </div>' . $brief['strip'] . '
            </div>';
}

/**
 * The next meeting or visit in a box the page script fills again with
 * another line; empty when nothing is left today. It is drawn twice: beside
 * the date line, shown on a wide dashboard, and in the brief's first row,
 * shown on a narrow one, so it never takes a line of its own; CSS shows one.
 * Pure.
 *
 * @param array $brief  pg_dashboard_brief()'s answer
 * @param bool  $inside the copy in the brief's first row
 * @return string HTML
 */
function pg_dashboard_v2_brief_next_markup($brief, $inside = false)
{
    return '<span class="pg-dash-brief-next ' . ($inside ? 'is-inside' : 'is-head') . '" data-top-part="brief">' . $brief['next'] . '</span>';
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
