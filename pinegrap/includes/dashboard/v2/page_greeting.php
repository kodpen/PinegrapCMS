<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's greeting card: the date and time line in the person's
 * time zone and the greeting by the hour with their name.
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
 * The top section the person's stored layout asks for, or their preset's:
 * the preset, the shortcut keys, the tile keys and which parts are shown.
 * Unknown keys and bad values fall back to the preset's.
 *
 * @param array|null $row the person's dashboard_layout row
 * @return array preset, actions, tiles, show (since, actions, tiles)
 */
function pg_dashboard_v2_top_state($row)
{
    $layout = array();

    if (is_array($row) && isset($row['layout']) && is_string($row['layout']) && ($row['layout'] !== '')) {
        $decoded = json_decode($row['layout'], true);
        $layout = is_array($decoded) ? $decoded : array();
    }

    $presets = pg_dashboard_v2_top_presets();
    $preset = (isset($layout['preset']) && is_string($layout['preset']) && (isset($presets[$layout['preset']]) || ($layout['preset'] === 'simple')))
        ? $layout['preset']
        : pg_dashboard_v2_first_preset();
    $defaults = pg_dashboard_v2_top_for($preset);

    $show = array('since' => true, 'actions' => true, 'tiles' => true);

    if (isset($layout['top']) && is_array($layout['top'])) {
        foreach ($show as $part => $on) {
            if (isset($layout['top'][$part])) {
                $show[$part] = !empty($layout['top'][$part]);
            }
        }
    }

    return array(
        'preset'  => $preset,
        'actions' => (isset($layout['actions']) && is_array($layout['actions'])) ? $layout['actions'] : $defaults['actions'],
        'tiles'   => (isset($layout['tiles']) && is_array($layout['tiles'])) ? $layout['tiles'] : $defaults['tiles'],
        'since'   => $defaults['since'],
        'show'    => $show,
    );
}

/**
 * The count a shortcut's label carries ("Forms · 4"), or null.
 *
 * @param string $count a shortcut's count key
 * @param array  $user
 * @return int|null
 */
function pg_dashboard_v2_shortcut_count($count, $user)
{
    if ($count === 'comments') {
        return (int) db_value("SELECT COUNT(*) FROM comments WHERE published = '0'");
    }

    $figure = pg_dashboard_v2_figure($count, $user);

    return ($figure === null) ? null : (int) $figure['value'];
}

/**
 * @param array      $user the row validate_user() returned
 * @param array|null $row  the person's dashboard_layout row
 * @return string HTML
 */
function pg_dashboard_v2_greeting($user, $row = null)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/home.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/figures.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/top.php');

    $now = time();
    $state = pg_dashboard_v2_top_state($row);

    $when = get_absolute_time(array('timestamp' => $now, 'type' => 'date', 'size' => 'long', 'format' => 'plain_text'))
        . ' · ' . get_absolute_time(array('timestamp' => $now, 'type' => 'time', 'format' => 'plain_text'));

    $output = '
        <section class="card pg-dash-card pg-dash-greeting" aria-labelledby="pg_dash_greeting_title">
            <p class="pg-dash-greeting-when">' . h($when) . '</p>
            <h2 class="pg-dash-greeting-title" id="pg_dash_greeting_title">' . h(ws_home_greeting(pg_dashboard_v2_local_hour($now))) . ', <span class="pg-dash-name">' . h((string) $user['username']) . '</span></h2>';

    // Since the sign-in before this one; nothing on the first one, and no
    // line when every chip is zero.
    $since = is_array($row) ? (int) ($row['signed_in_before'] ?? 0) : 0;

    if ($state['show']['since'] && ($since > 0)) {
        $chips = '';

        foreach ($state['since'] as $key) {
            $chip = pg_dashboard_v2_since_chip($key, $user, $since);

            if ($chip !== null) {
                $chips .= '<a class="pg-dash-chip" href="' . h($chip['url']) . '"' . (($chip['tone'] !== '') ? ' data-tone="' . h($chip['tone']) . '"' : '') . '>' . h($chip['text']) . '</a>';
            }
        }

        if ($chips !== '') {
            $output .= '
            <div class="pg-dash-since">
                <span class="pg-dash-kicker">' . h(lang('Since your last sign-in')) . ' · ' . h(get_absolute_time(array('timestamp' => $since, 'format' => 'plain_text'))) . '</span>
                ' . $chips . '
            </div>';
        }
    }

    if ($state['show']['actions']) {
        $shortcuts = pg_dashboard_v2_shortcuts();
        $buttons = '';

        foreach (pg_dashboard_v2_actions_for($state['actions'], $user) as $i => $key) {
            $label = $shortcuts[$key]['label'];

            if (isset($shortcuts[$key]['count'])) {
                $count = pg_dashboard_v2_shortcut_count($shortcuts[$key]['count'], $user);

                if ($count !== null) {
                    $label .= ' · ' . pg_format_number($count, 0);
                }
            }

            $buttons .= '<a class="btn ' . (($i === 0) ? 'pg-dash-action-main' : 'btn-outline theme-secondary') . ' pg-dash-action" href="' . h($shortcuts[$key]['url']) . '">' . h($label) . '</a>';
        }

        if ($buttons !== '') {
            $output .= '
            <nav class="pg-dash-actions" aria-label="' . h(lang('Shortcuts')) . '">' . $buttons . '</nav>';
        }
    }

    if ($state['show']['tiles']) {
        $allowed = array();

        foreach (array_keys(pg_dashboard_v2_figures()) as $key) {
            if (pg_dashboard_v2_figure_allowed($key, $user)) {
                $allowed[] = $key;
            }
        }

        $labels = pg_dashboard_v2_figures();
        $tiles = '';

        foreach (pg_dashboard_v2_tiles_for($state['tiles'], $allowed) as $key) {
            $figure = pg_dashboard_v2_figure($key, $user, $now);

            if ($figure === null) {
                continue;
            }

            $tiles .= '
                <a class="pg-dash-tile" href="' . h($figure['url']) . '" data-figure="' . h($key) . '">
                    <span class="pg-dash-tile-label">' . h($labels[$key]) . '</span>
                    <span class="pg-dash-tile-value">' . h($figure['text']) . '</span>
                    <span class="pg-dash-tile-note"' . (($figure['tone'] !== '') ? ' data-tone="' . h($figure['tone']) . '"' : '') . '>' . h($figure['note']) . '</span>
                </a>';
        }

        if ($tiles !== '') {
            $output .= '
            <div class="pg-dash-tiles">' . $tiles . '
            </div>';
        }
    }

    return $output . '
        </section>';
}
