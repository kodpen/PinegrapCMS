<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shared pieces of the new dashboard's Team cards (team, mytasks, waiting,
 * week, calendars): a person as card data, the days of a week or of a
 * five-week grid, and the short date a dated row shows. The cards return
 * data, never HTML, so a person is letters, a tone class and a photo
 * address, drawn by assets/js/pg_dash_cards_team.js.
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
 * A person as the Team cards draw them: their photo when they have one,
 * else their letters on their tone (the classes pg_avatar_markup() uses).
 *
 * @param array $person ws_people() brief, or name only
 * @return array name, initials, theme, photo, presence
 */
function pg_dashboard_v2_person($person)
{
    $person = (array) $person;
    $name = trim((string) ($person['name'] ?? ''));
    $initials = (string) ($person['initials'] ?? '');

    if ($initials === '') {
        $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $initials = empty($words) ? '?' : mb_strtoupper(mb_substr($words[0], 0, 1, 'UTF-8') . ((count($words) > 1) ? mb_substr($words[count($words) - 1], 0, 1, 'UTF-8') : ''), 'UTF-8');
    }

    $tone = isset($person['tone']) ? (int) $person['tone'] : (abs(crc32($name !== '' ? $name : $initials)) % 8);
    $picture = (string) ($person['avatar'] ?? '');
    $kind = (string) ($person['avatar_kind'] ?? '');

    // A picture of letters is drawn as letters; only a photo stays a picture.
    $photo = (($picture !== '') && (strncmp($picture, 'data:image/svg+xml', 18) !== 0) && ($kind === 'photo')) ? $picture : '';

    return array(
        'name'     => $name,
        'initials' => $initials,
        'theme'    => function_exists('pg_avatar_theme_class') ? pg_avatar_theme_class($tone) : '',
        'photo'    => $photo,
        'presence' => in_array(($person['presence'] ?? ''), array('online', 'away'), true) ? $person['presence'] : 'offline',
    );
}

/**
 * The Monday that starts the week a moment falls in, at midnight.
 *
 * @param int $timestamp
 * @return int
 */
function pg_dashboard_v2_week_start($timestamp)
{
    $midnight = mktime(0, 0, 0, (int) date('n', $timestamp), (int) date('j', $timestamp), (int) date('Y', $timestamp));

    return mktime(0, 0, 0, (int) date('n', $midnight), (int) date('j', $midnight) - ((int) date('N', $midnight) - 1), (int) date('Y', $midnight));
}

/**
 * Days from a first one, each with its date, its short weekday, its day of
 * the month, whether it is today, how many items fall on it and a label
 * saying so for a screen reader.
 *
 * @param int   $first  midnight of the first day
 * @param int   $count  how many days
 * @param array $counts Y-m-d => items on that day
 * @param int   $now
 * @return array[] date, name, day, month, today, items, label
 */
function pg_dashboard_v2_days($first, $count, $counts, $now)
{
    $today = date('Y-m-d', $now);
    $days = array();

    for ($i = 0; $i < $count; $i++) {
        // mktime() by the day number, not $first + 86400 * $i: a day with a
        // clock change is not 86400 seconds long.
        $day = mktime(0, 0, 0, (int) date('n', $first), (int) date('j', $first) + $i, (int) date('Y', $first));
        $date = date('Y-m-d', $day);
        $items = (int) ($counts[$date] ?? 0);
        $name = lang(date('l', $day)) . ' ' . date('j', $day);

        if ($items === 0) {
            $label = lang(array('string' => '{var:1}: nothing', 'vars' => $name));
        } elseif ($items === 1) {
            $label = lang(array('string' => '{var:1}: one item', 'vars' => $name));
        } else {
            $label = lang(array('string' => '{var:1}: {var:2} items', 'vars' => array($name, pg_format_number($items, 0))));
        }

        $days[] = array(
            'date'  => $date,
            'name'  => lang(date('D', $day)),
            'day'   => (int) date('j', $day),
            'month' => (int) date('n', $day),
            'today' => ($date === $today),
            'items' => $items,
            'label' => $label,
        );
    }

    return $days;
}

/**
 * The short date a dated row shows: the day and the month's first three
 * letters ("02", "Nov").
 *
 * @param int $timestamp
 * @return array day, month
 */
function pg_dashboard_v2_short_date($timestamp)
{
    return array(
        'day'   => date('d', $timestamp),
        'month' => mb_substr(lang(date('F', $timestamp)), 0, 3, 'UTF-8'),
    );
}
