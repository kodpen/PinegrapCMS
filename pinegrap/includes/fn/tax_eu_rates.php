<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The EU member states' VAT rates, shipped with the software and changed with
 * its updates, and the switch "Rates by the buyer's country" (Site Settings ›
 * Commerce) that lets the checkout charge a buyer in no tax zone their member
 * state's standard rate (pg_tax_buyer_zone_rate() in ecommerce.php). Every row
 * holds the day it applies from, so a changed rate applies on its date.
 *
 * The rates are read from the European Commission's Taxes in Europe Database
 * (TEDB, service retrieveVatRates, https://ec.europa.eu/taxation_customs/tedb/),
 * (c) European Union, reused under CC BY 4.0 (Commission Decision of
 * 12 December 2011); they were kept here as rows, with Greece under its ISO
 * code GR instead of TEDB's EL. The Commission does not warrant that the data
 * is complete, accurate or up to date; only the Official Journal is authentic.
 * It also holds the rate a stored line's tax stands for (pg_tax_line_rate()),
 * which the renewals and the marketplace imports write as the line's rate, and
 * the notice that tells the administrators once when an update changes a row
 * of the list (pg_eu_vat_rate_notice_run(), from the general job).
 * Everything but pg_tax_by_country_on() and the notice's run and sending is
 * pure.
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
 * The EU's VAT rates: member state (ISO 3166 code), kind, rate in percent and
 * the day it applies from (Y-m-d). A row applies from its day until the next
 * row of the same state and kind. A state's first row carries the earliest
 * day the source holds (2016-01-01 for a rate unchanged since), not the day
 * its law set the rate. Only the standard rate is listed. Pure.
 *
 * @return array[] list of array(country, kind, rate, from)
 */
function pg_eu_vat_rates()
{
    return array(
        array('AT', 'standard', 20.0, '2016-01-01'),
        array('BE', 'standard', 21.0, '2016-01-01'),
        array('BG', 'standard', 20.0, '2016-01-01'),
        array('CY', 'standard', 19.0, '2016-01-01'),
        array('CZ', 'standard', 21.0, '2016-01-01'),
        array('DE', 'standard', 19.0, '2016-01-01'),
        array('DE', 'standard', 16.0, '2020-07-01'),
        array('DE', 'standard', 19.0, '2021-01-01'),
        array('DK', 'standard', 25.0, '2016-01-01'),
        array('EE', 'standard', 20.0, '2016-01-01'),
        array('EE', 'standard', 22.0, '2024-01-01'),
        array('EE', 'standard', 24.0, '2025-07-01'),
        array('ES', 'standard', 21.0, '2016-01-01'),
        array('FI', 'standard', 24.0, '2016-01-01'),
        array('FI', 'standard', 25.5, '2024-09-01'),
        array('FR', 'standard', 20.0, '2016-01-01'),
        array('GR', 'standard', 24.0, '2016-01-01'),
        array('HR', 'standard', 25.0, '2016-01-01'),
        array('HU', 'standard', 27.0, '2016-01-01'),
        array('IE', 'standard', 23.0, '2016-01-01'),
        array('IE', 'standard', 21.0, '2020-09-01'),
        array('IE', 'standard', 23.0, '2021-03-01'),
        array('IT', 'standard', 22.0, '2016-01-01'),
        array('LT', 'standard', 21.0, '2016-01-01'),
        array('LU', 'standard', 17.0, '2016-01-01'),
        array('LU', 'standard', 16.0, '2023-01-01'),
        array('LU', 'standard', 17.0, '2024-01-01'),
        array('LV', 'standard', 21.0, '2016-01-01'),
        array('MT', 'standard', 18.0, '2016-01-01'),
        array('NL', 'standard', 21.0, '2016-07-01'),
        array('PL', 'standard', 23.0, '2016-01-01'),
        array('PT', 'standard', 23.0, '2016-01-01'),
        array('RO', 'standard', 20.0, '2016-01-01'),
        array('RO', 'standard', 19.0, '2017-01-01'),
        array('RO', 'standard', 21.0, '2025-08-01'),
        array('SE', 'standard', 25.0, '2016-01-01'),
        array('SI', 'standard', 22.0, '2016-05-20'),
        array('SK', 'standard', 20.0, '2016-01-01'),
        array('SK', 'standard', 23.0, '2025-01-01'),
    );
}

/**
 * A member state's VAT rate of one kind on a day: the row of
 * pg_eu_vat_rates() with the latest start on or before it. Pure.
 *
 * @param string $country ISO code, any case; EL (the EU's code for Greece) is read as GR
 * @param string $kind    'standard'
 * @param string $date    Y-m-d; a longer date-time is cut to its day
 * @return float|false    false outside the EU, for a kind the list does not
 *                        hold, a malformed day or a day before the state's first row
 */
function pg_eu_vat_rate($country, $kind, $date)
{
    $country = strtoupper(trim((string) $country));
    $kind = strtolower(trim((string) $kind));
    $date = substr(trim((string) $date), 0, 10);

    if ($country === 'EL') {
        $country = 'GR';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
        return false;
    }

    return pg_eu_vat_rate_in(pg_eu_vat_rates(), $country, $kind, $date);
}

/**
 * pg_eu_vat_rate() on a given list of rows: the rate of the row with the
 * latest start on or before the day. Pure.
 *
 * @param array[] $rows    rows as pg_eu_vat_rates() gives them
 * @param string  $country ISO code, upper case
 * @param string  $kind    lower case
 * @param string  $date    Y-m-d
 * @return float|false
 */
function pg_eu_vat_rate_in($rows, $country, $kind, $date)
{
    $rate = false;
    $start = '';

    foreach ((array) $rows as $row) {
        if (($row[0] === $country) && ($row[1] === $kind) && ($row[3] <= $date) && ($row[3] >= $start)) {
            $rate = (float) $row[2];
            $start = $row[3];
        }
    }

    return $rate;
}

/**
 * Whether "Rates by the buyer's country" is on: config_text
 * ecommerce_tax_by_country '1'. Off while the row or its table is missing.
 *
 * @return bool
 */
function pg_tax_by_country_on()
{
    return function_exists('pg_config_text') && (pg_config_text('ecommerce_tax_by_country') === '1');
}

/**
 * The rate a line's tax stands for, in percent: the fewest decimal places
 * (none to three) whose tax on the amount, rounded as the checkout rounds
 * (pg_tax_apply_rate()), comes within $slack minor units of the tax; else the
 * ratio at three places. What a renewal without a stored rate and a
 * marketplace import write as order_items.tax_rate. Pure.
 *
 * @param int $base  the line's amount the tax is on, minor units
 * @param int $tax   the line's tax, minor units
 * @param int $slack how far off the tax may be: the quantity when the tax was
 *                   rounded a unit at a time
 * @return float 0 without an amount or a tax; at most 999.999 (DECIMAL(6,3))
 */
function pg_tax_line_rate($base, $tax, $slack = 0)
{
    $base = (int) round((float) $base);
    $tax = (int) round((float) $tax);
    $slack = max(0, (int) $slack);

    if (($base <= 0) || ($tax <= 0)) {
        return 0.0;
    }

    $ratio = $tax * 100 / $base;

    if ($ratio >= 999.999) {
        return 999.999;
    }

    for ($places = 0; $places <= 3; $places++) {
        $candidate = round($ratio, $places);

        if (abs(pg_tax_apply_rate($base, $candidate) - $tax) <= $slack) {
            return (float) $candidate;
        }
    }

    return (float) round($ratio, 3);
}

/**
 * What changed between the list an earlier run saw and this one: for every
 * day a row was added, removed or corrected, the rate before and after on
 * that day, where they differ. Oldest first, then by country. Pure.
 *
 * @param array[] $seen the rows as stored by the last run
 * @param array[] $rows the rows now (pg_eu_vat_rates())
 * @return array[] list of array(country, kind, old (float|false), new (float|false), from)
 */
function pg_eu_vat_rate_changes($seen, $rows)
{
    $key = function ($row) {
        return implode('|', array((string) $row[0], (string) $row[1], (string) (float) $row[2], (string) $row[3]));
    };

    $seen_keys = array_map($key, (array) $seen);
    $row_keys = array_map($key, (array) $rows);
    $days = array();

    foreach (array(array((array) $seen, $row_keys), array((array) $rows, $seen_keys)) as $pass) {
        foreach ($pass[0] as $row) {
            if (!in_array($key($row), $pass[1], true)) {
                $days[(string) $row[3] . '|' . (string) $row[0] . '|' . (string) $row[1]] = array((string) $row[0], (string) $row[1], (string) $row[3]);
            }
        }
    }

    ksort($days);
    $changes = array();

    foreach ($days as $day) {
        $old = pg_eu_vat_rate_in($seen, $day[0], $day[1], $day[2]);
        $new = pg_eu_vat_rate_in($rows, $day[0], $day[1], $day[2]);

        if ($old !== $new) {
            $changes[] = array('country' => $day[0], 'kind' => $day[1], 'old' => $old, 'new' => $new, 'from' => $day[2]);
        }
    }

    return $changes;
}

/**
 * The changes as the notice's row keeps them (notifications.title, 256
 * characters): "EE 22>24 2025-07-01, ...", "-" for no rate; cut at a whole
 * entry. Pure.
 *
 * @param array[] $changes pg_eu_vat_rate_changes()
 * @return string
 */
function pg_eu_vat_rate_notice_title($changes)
{
    $rate = function ($value) {
        return ($value === false) ? '-' : format_tax_rate($value);
    };

    $title = '';

    foreach ((array) $changes as $change) {
        $entry = $change['country'] . ' ' . $rate($change['old']) . '>' . $rate($change['new']) . ' ' . $change['from'];
        $next = ($title === '') ? $entry : $title . ', ' . $entry;

        if (strlen($next) > 256) {
            break;
        }

        $title = $next;
    }

    return $title;
}

/**
 * The notice's row read back into changes. Pure.
 *
 * @param string $title pg_eu_vat_rate_notice_title()
 * @return array[] list of array(country, old, new, from)
 */
function pg_eu_vat_rate_notice_parse($title)
{
    $changes = array();

    foreach (explode(', ', (string) $title) as $entry) {
        if (preg_match('/^([A-Z]{2}) (-|[0-9.]+)>(-|[0-9.]+) (\d{4}-\d{2}-\d{2})$/', $entry, $match) !== 1) {
            continue;
        }

        $changes[] = array(
            'country' => $match[1],
            'old'     => ($match[2] === '-') ? false : (float) $match[2],
            'new'     => ($match[3] === '-') ? false : (float) $match[3],
            'from'    => $match[4],
        );
    }

    return $changes;
}

/**
 * One change as a line of the notice and the mail, in the reader's language.
 *
 * @param array $change pg_eu_vat_rate_notice_parse() or pg_eu_vat_rate_changes()
 * @return string plain text
 */
function pg_eu_vat_rate_change_text($change)
{
    $rate = function ($value) {
        return ($value === false) ? lang('none') : lang(array('string' => '{var:1}%', 'vars' => array(format_tax_rate($value))));
    };

    return lang(array('string' => '{var:1}: {var:2} to {var:3} from {var:4}', 'vars' => array(
        $change['country'], $rate($change['old']), $rate($change['new']), $change['from'],
    )));
}

/**
 * Tells the administrators once when an update changed a row of the list,
 * while "Rates by the buyer's country" is on: a bell line (tax_rate_change)
 * and a mail to each enabled administrator. The list it last saw is kept in
 * config_text tax_eu_rates_seen; the first run, and every run with the switch
 * off, only takes the list in. Of two overlapping runs only the one that
 * moves the row tells. Called by the general job.
 *
 * @return int the changes told, 0 when none
 */
function pg_eu_vat_rate_notice_run()
{
    if (!function_exists('pg_config_text') || !pg_schema_has('config_text')) {
        return 0;
    }

    $rows = pg_eu_vat_rates();
    $current = json_encode($rows);
    $stored = pg_config_text('tax_eu_rates_seen');

    if (($current === false) || ($stored === $current)) {
        return 0;
    }

    $seen = ($stored === '') ? null : json_decode($stored, true);

    if (!pg_config_text_claim('tax_eu_rates_seen', $stored, $current)) {
        return 0;
    }

    $on = (defined('ECOMMERCE') && (ECOMMERCE === true)) && pg_tax_by_country_on();

    if (!is_array($seen) || !$on) {
        return 0;
    }

    $changes = pg_eu_vat_rate_changes($seen, $rows);

    if (!$changes) {
        return 0;
    }

    if (function_exists('create_notification')) {
        create_notification(array(
            'action'      => 'tax_rate_change',
            'type'        => 'warning',
            'title'       => pg_eu_vat_rate_notice_title($changes),
            'order_total' => (string) count($changes),
            'user'        => 'system',
        ));
    }

    pg_eu_vat_rate_notice_mail($changes);

    log_activity(lang(array('string' => 'An update changed {var:1} EU VAT rate(s) used by Rates by the buyer\'s country.', 'vars' => array(count($changes)))), 'UNKNOWN');

    return count($changes);
}

/**
 * The mail of pg_eu_vat_rate_notice_run(): one plain-text message to every
 * enabled administrator with an address, through the mail queue.
 *
 * @param array[] $changes pg_eu_vat_rate_changes()
 * @return void
 */
function pg_eu_vat_rate_notice_mail($changes)
{
    $addresses = array_values(array_unique(array_filter(array_map('strval', (array) db_values(
        "SELECT user_email FROM user WHERE user_role = '0'" . pg_user_enabled_sql()
    )), 'validate_email_address')));

    if (!$addresses || !function_exists('email')) {
        return;
    }

    $lines = array_map('pg_eu_vat_rate_change_text', $changes);

    $body = lang('An update of the software changed rates in the EU VAT list that Rates by the buyer\'s country charges (Site Settings › Commerce):') . "\n\n"
        . implode("\n", $lines) . "\n\n"
        . lang('Each rate applies from its date. The rates are yours to check: a wrong rate is your liability.');

    foreach ($addresses as $address) {
        try {
            email(array(
                'to'                 => $address,
                'from_email_address' => EMAIL_ADDRESS,
                'subject'            => lang('EU VAT rates changed'),
                'format'             => 'plain_text',
                'body'               => $body,
                'type'               => 'system',
                'queue'              => true,
            ));
        } catch (\Throwable $e) {
            // A refused message leaves the others to go.
        }
    }
}
