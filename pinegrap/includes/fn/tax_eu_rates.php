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
 * Everything but pg_tax_by_country_on() is pure.
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

    $rate = false;
    $start = '';

    foreach (pg_eu_vat_rates() as $row) {
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
