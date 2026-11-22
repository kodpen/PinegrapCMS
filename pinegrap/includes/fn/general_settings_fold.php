<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > General as folded rows (includes/settings/fold.php): the
 * rows' order, names and icons, and each part's one line of state. The
 * pg_general_fold_<part>() lines are pure but for lang();
 * includes/settings/general.php reads the facts they take.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The rows' keys in reading order. A key is its card's id without pgset-.
 *
 * @return string[]
 */
function pg_general_fold_order()
{
    return array('organization', 'server', 'software', 'datetime');
}

/**
 * A row's name, icon and tone.
 *
 * @param string $key one of pg_general_fold_order()
 * @return array name, icon, tone
 */
function pg_general_fold_face($key)
{
    switch ($key) {
        case 'organization':
            return array('name' => lang('Organization'), 'icon' => 'bi-building', 'tone' => 'pg-tone-blue');
        case 'server':
            return array('name' => lang('Site address'), 'icon' => 'bi-globe2', 'tone' => 'pg-tone-teal');
        case 'software':
            return array('name' => lang('Software'), 'icon' => 'bi-box-seam', 'tone' => 'pg-tone-violet');
        default:
            return array('name' => lang('Date and time'), 'icon' => 'bi-clock', 'tone' => 'pg-tone-amber');
    }
}

/**
 * Organization's line: the name and the city, or Not filled.
 *
 * @param string $name
 * @param string $city
 * @return array line, pill, attention
 */
function pg_general_fold_organization($name, $city)
{
    $parts = array_values(array_filter(array(trim((string) $name), trim((string) $city)), 'strlen'));

    return array(
        'line'      => empty($parts) ? lang('Not filled') : implode(', ', $parts),
        'pill'      => null,
        'attention' => false,
    );
}

/**
 * Site address's line: the Hostname.
 *
 * @param string $hostname
 * @return array line, pill, attention
 */
function pg_general_fold_site($hostname)
{
    $hostname = trim((string) $hostname);

    return array(
        'line'      => ($hostname !== '') ? $hostname : lang('No hostname set'),
        'pill'      => null,
        'attention' => false,
    );
}

/**
 * Software's line: whether a licence key is set, and verbose database errors
 * while they are on, as visitors then see them.
 *
 * @param bool $key_set a subscription key is saved
 * @param bool $verbose Verbose database errors is on
 * @return array line, pill, attention
 */
function pg_general_fold_software($key_set, $verbose)
{
    $line = $key_set ? lang('Licence set') : lang('No licence key');

    if ($verbose) {
        $line .= ' · ' . lang('verbose database errors on');
    }

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * Date and time's line: the time zone and today's date in the chosen format.
 *
 * @param string $zone        the site's time zone, as PHP names it
 * @param string $date_format month_day or day_month
 * @param int    $timestamp   now
 * @return array line, pill, attention
 */
function pg_general_fold_datetime($zone, $date_format, $timestamp)
{
    $date = date(($date_format === 'month_day') ? 'n/j/Y' : 'j/n/Y', (int) $timestamp);
    $zone = trim((string) $zone);

    return array(
        'line'      => (($zone !== '') ? $zone . ', ' : '') . $date,
        'pill'      => null,
        'attention' => false,
    );
}
