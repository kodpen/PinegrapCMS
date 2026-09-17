<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sales map data for the dashboard: completed orders summarised by the country
 * and the region of their billing address.
 *
 * Two views over the same numbers. The world map answers "which countries", a
 * country's own map answers "which parts of it", and the list beside either one
 * always names regions -- a map gives the scale, the list gives the detail.
 *
 * The status filter and the columns are the ones the Sales Report uses for its
 * "Billing State" summary (view_order_report.php), so the card and the report
 * cannot disagree. Rendering lives in api.php with the other dashboard widgets;
 * this file only answers questions about the data.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Countries we can draw at region level, and the outlines that do it. Every
// installation gets the world map; a country only gets its own when there is a
// file here for it.
//
// Adding one is three things and no more: generate the SVG (see
// docs/_tools/build_map_svg.js), add the row here, and add a code -> name table
// for it below. Nothing else in this file or in api.php is country-specific.
function pg_sales_map_region_maps()
{
    static $maps = array(
        'TR' => array('file' => 'tr-regions.svg', 'names' => 'pg_sales_map_tr_regions'));

    return $maps;
}

// Turkey's 81 provinces, keyed by ISO 3166-2. That code is also the id of the
// matching path in the SVG, and its last two digits are the licence plate
// number, so nothing here has to be kept in step with a second numbering.
//
// The names are ours rather than the map's: the Natural Earth dataset the SVG
// was generated from spells several of them wrong ("Zinguldak", "Kinkkale",
// "K. Maras"), and the states table is not authoritative either -- an operator
// can rename or delete a row in it.
function pg_sales_map_tr_regions()
{
    static $regions = array(
        'TR-01' => 'Adana',        'TR-02' => 'Adıyaman',     'TR-03' => 'Afyonkarahisar',
        'TR-04' => 'Ağrı',         'TR-05' => 'Amasya',       'TR-06' => 'Ankara',
        'TR-07' => 'Antalya',      'TR-08' => 'Artvin',       'TR-09' => 'Aydın',
        'TR-10' => 'Balıkesir',    'TR-11' => 'Bilecik',      'TR-12' => 'Bingöl',
        'TR-13' => 'Bitlis',       'TR-14' => 'Bolu',         'TR-15' => 'Burdur',
        'TR-16' => 'Bursa',        'TR-17' => 'Çanakkale',    'TR-18' => 'Çankırı',
        'TR-19' => 'Çorum',        'TR-20' => 'Denizli',      'TR-21' => 'Diyarbakır',
        'TR-22' => 'Edirne',       'TR-23' => 'Elazığ',       'TR-24' => 'Erzincan',
        'TR-25' => 'Erzurum',      'TR-26' => 'Eskişehir',    'TR-27' => 'Gaziantep',
        'TR-28' => 'Giresun',      'TR-29' => 'Gümüşhane',    'TR-30' => 'Hakkâri',
        'TR-31' => 'Hatay',        'TR-32' => 'Isparta',      'TR-33' => 'Mersin',
        'TR-34' => 'İstanbul',     'TR-35' => 'İzmir',        'TR-36' => 'Kars',
        'TR-37' => 'Kastamonu',    'TR-38' => 'Kayseri',      'TR-39' => 'Kırklareli',
        'TR-40' => 'Kırşehir',     'TR-41' => 'Kocaeli',      'TR-42' => 'Konya',
        'TR-43' => 'Kütahya',      'TR-44' => 'Malatya',      'TR-45' => 'Manisa',
        'TR-46' => 'Kahramanmaraş','TR-47' => 'Mardin',       'TR-48' => 'Muğla',
        'TR-49' => 'Muş',          'TR-50' => 'Nevşehir',     'TR-51' => 'Niğde',
        'TR-52' => 'Ordu',         'TR-53' => 'Rize',         'TR-54' => 'Sakarya',
        'TR-55' => 'Samsun',       'TR-56' => 'Siirt',        'TR-57' => 'Sinop',
        'TR-58' => 'Sivas',        'TR-59' => 'Tekirdağ',     'TR-60' => 'Tokat',
        'TR-61' => 'Trabzon',      'TR-62' => 'Tunceli',      'TR-63' => 'Şanlıurfa',
        'TR-64' => 'Uşak',         'TR-65' => 'Van',          'TR-66' => 'Yozgat',
        'TR-67' => 'Zonguldak',    'TR-68' => 'Aksaray',      'TR-69' => 'Bayburt',
        'TR-70' => 'Karaman',      'TR-71' => 'Kırıkkale',    'TR-72' => 'Batman',
        'TR-73' => 'Şırnak',       'TR-74' => 'Bartın',       'TR-75' => 'Ardahan',
        'TR-76' => 'Iğdır',        'TR-77' => 'Yalova',       'TR-78' => 'Karabük',
        'TR-79' => 'Kilis',        'TR-80' => 'Osmaniye',     'TR-81' => 'Düzce');

    return $regions;
}

// The spellings that reach us alongside an official name, per country. The
// states table ships "Afyon"; Mersin was called İçel until 2002 and old address
// books still say so; and billing addresses are typed by hand often enough that
// "Urfa" and "Maraş" have to resolve as well.
//
// Written the way a person writes them -- pg_sales_map_normalize() is what makes
// the comparison work.
function pg_sales_map_aliases()
{
    static $aliases = array(
        'TR-03' => array('Afyon'),
        'TR-27' => array('Antep'),
        'TR-31' => array('Antakya'),
        'TR-33' => array('İçel'),
        'TR-41' => array('İzmit'),
        'TR-46' => array('Maraş', 'K. Maraş'),
        'TR-54' => array('Adapazarı'),
        'TR-62' => array('Dersim'),
        'TR-63' => array('Urfa'));

    return $aliases;
}

// Everything that separates two spellings of the same place: accents, case,
// spaces and punctuation. "K. Maraş", "K.Maraş" and "kmaras" all come out the
// same, which is the point -- these values are typed into a shop form.
//
// Transliterate first and lowercase second. The other way round turns "İ" into
// an "i" with a combining dot, and the dot then survives as its own character
// (see CLAUDE.md, address generation).
function pg_sales_map_normalize($value)
{
    $value = pg_transliterate_to_ascii((string) $value);
    $value = mb_strtolower($value, 'UTF-8');

    return (string) preg_replace('/[^a-z0-9]+/', '', $value);
}

// Billing state as typed -> ISO 3166-2 code, or '' when this country has no
// region map or the value does not match one of its regions.
function pg_sales_map_match_region($country, $value)
{
    static $index = array();

    $country = strtoupper((string) $country);
    $maps = pg_sales_map_region_maps();

    if (!isset($maps[$country])) {
        return '';
    }

    if (!isset($index[$country])) {

        $index[$country] = array();
        $names = call_user_func($maps[$country]['names']);

        foreach ($names as $code => $name) {
            $index[$country][pg_sales_map_normalize($name)] = $code;
        }

        foreach (pg_sales_map_aliases() as $code => $spellings) {

            if (strpos($code, $country . '-') !== 0) {
                continue;
            }

            foreach ($spellings as $spelling) {
                $index[$country][pg_sales_map_normalize($spelling)] = $code;
            }
        }
    }

    $key = pg_sales_map_normalize($value);

    return (($key !== '') && (isset($index[$country][$key]))) ? $index[$country][$key] : '';
}

// One region's own name, out of the table that belongs to its country's map.
function pg_sales_map_region_name($country, $code)
{
    $maps = pg_sales_map_region_maps();
    $country = strtoupper((string) $country);

    if (!isset($maps[$country])) {
        return '';
    }

    $names = call_user_func($maps[$country]['names']);

    return $names[$code] ?? '';
}

// Country as stored -> two letter code.
//
// select_country() writes the code, so almost every row is already one. The
// rest are the awkward ones: rows imported from elsewhere carry a name, and
// rows written before the field was required carry nothing at all. An empty
// country whose state matches a region we have a map for is credited to that
// country, because the alternative is a shop losing part of its own map to
// "unknown".
function pg_sales_map_country_code($country, $state = '')
{
    static $names = null;

    $country = trim((string) $country);

    if ($country === '') {

        foreach (pg_sales_map_region_maps() as $code => $map) {

            if (pg_sales_map_match_region($code, $state) !== '') {
                return $code;
            }
        }

        return '';
    }

    if (strlen($country) === 2) {
        return strtoupper($country);
    }

    if ($names === null) {

        $names = array();

        foreach (db_items("SELECT code, name FROM countries") as $row) {
            $names[pg_sales_map_normalize($row['name'])] = strtoupper($row['code']);
        }
    }

    $key = pg_sales_map_normalize($country);

    return (isset($names[$key])) ? $names[$key] : '';
}

// Region code as stored -> the name an operator would recognise, using the
// states table the shop's own address forms are built from. "TX" is a code on
// an order and "Texas" on the screen, and only that table knows which is which.
function pg_sales_map_state_names()
{
    static $names = null;

    if ($names === null) {

        $names = array();

        foreach (db_items(
            "SELECT states.name, states.code, countries.code AS country
            FROM states
            LEFT JOIN countries ON countries.id = states.country_id") as $row) {

            $country = strtoupper((string) $row['country']);
            $key = pg_sales_map_normalize($row['code']);

            if (($country === '') || ($key === '')) {
                continue;
            }

            $names[$country][$key] = $row['name'];
        }
    }

    return $names;
}

// The name to put on screen for one billing region, from the best source that
// has it: the country's own map table, then the shop's states table ("TX" is a
// code on an order and "Texas" on a screen, and only that table knows which is
// which), then whatever was typed.
//
// Shared with the dashboard briefing, which names the same places in a sentence.
function pg_sales_map_label($country, $state)
{
    $state = trim((string) $state);
    $region = pg_sales_map_match_region($country, $state);

    if ($region !== '') {
        return pg_sales_map_region_name($country, $region);
    }

    $names = pg_sales_map_state_names();
    $key = pg_sales_map_normalize($state);
    $country = strtoupper((string) $country);

    if (isset($names[$country][$key])) {
        return $names[$country][$key];
    }

    return ($state !== '') ? $state : lang('Unknown');
}

// The three periods the card can show, newest window first. 'from' is a
// timestamp, or 0 for everything ever sold.
//
// Anchored to midnight rather than to time(), so "last 30 days" does not mean
// "the last 720 hours" and shift what it counts as the afternoon wears on. Same
// reasoning as the Ecommerce Summary card.
function pg_sales_map_periods()
{
    $midnight = strtotime(date('Y-m-d'));

    return array(
        'd30' => array('label' => lang('Last 30 days'),   'from' => $midnight - (29 * 86400)),
        'm12' => array('label' => lang('Last 12 months'), 'from' => strtotime('-12 months', $midnight)),
        'all' => array('label' => lang('All time'),       'from' => 0));
}

// Columns that both queries below have to bucket identically. Written once
// because two lists of CASE expressions would drift the first time a period
// changed.
function pg_sales_map_period_columns($periods)
{
    $columns = '';

    foreach ($periods as $key => $period) {

        if ($period['from'] <= 0) {
            continue;
        }

        $from = (int) $period['from'];

        $columns .=
            ", COALESCE(SUM(CASE WHEN orders.order_date >= $from THEN 1 ELSE 0 END), 0) AS " . $key . "_count"
            . ", COALESCE(SUM(CASE WHEN orders.order_date >= $from THEN orders.total ELSE 0 END), 0) AS " . $key . "_total";
    }

    return $columns;
}

// How much of a summed row belongs to one period. 'all' is the row itself.
function pg_sales_map_period_values($row, $key)
{
    if ($key === 'all') {
        return array('count' => (int) $row['all_count'], 'total' => (float) $row['all_total']);
    }

    return array(
        'count' => (int) ($row[$key . '_count'] ?? 0),
        'total' => (float) ($row[$key . '_total'] ?? 0));
}

// Everything the card needs, for both views and all three periods at once.
//
// One pass over the orders table answers all of it: the period switch and the
// view switch are preferences about which set of numbers to show, and making
// either one a second request would put a full table scan behind a button
// people press three times in a row.
//
// Returns:
//   'status'    'ok' | 'empty'
//   'home'      the country with the most orders, '' when none can be resolved
//   'home_name' its name, as the countries table spells it
//   'home_map'  its outlines, or '' when we do not ship any for it
//   'default'   'home' | 'world' -- which view the card opens on
//   'first'     timestamp of the oldest completed order, for "all time" links
//   'periods'   key => label, short, from, total, count, countries, regions
function pg_sales_map_collect()
{
    $periods = pg_sales_map_periods();
    $columns = pg_sales_map_period_columns($periods);

    $rows = db_items(
        "SELECT
            orders.billing_country,
            orders.billing_state,
            COUNT(*) AS all_count,
            COALESCE(SUM(orders.total), 0) AS all_total,
            COALESCE(MIN(orders.order_date), 0) AS first_date
            $columns
        FROM orders
        WHERE orders.status IN ('complete', 'exported')
        GROUP BY orders.billing_country, orders.billing_state");

    if (empty($rows)) {
        return array('status' => 'empty');
    }

    // ── Home country ────────────────────────────────────────────────────────
    //
    // Decided on order count rather than on revenue: a single large order
    // should not be able to send the whole card to another country. Decided
    // over all time rather than per period, so switching the period never swaps
    // the map out from under the reader.
    $by_country = array();
    $first = 0;

    foreach ($rows as $index => $row) {

        $country = pg_sales_map_country_code($row['billing_country'], $row['billing_state']);

        // Kept on the row so the loops below do not resolve it again.
        $rows[$index]['pg_country'] = $country;
        $rows[$index]['pg_region'] = pg_sales_map_match_region($country, $row['billing_state']);

        if ($country !== '') {
            $by_country[$country] = ($by_country[$country] ?? 0) + (int) $row['all_count'];
        }

        $date = (int) $row['first_date'];

        if (($date > 0) && (($first === 0) || ($date < $first))) {
            $first = $date;
        }
    }

    arsort($by_country);

    $home = (string) key($by_country);

    $maps = pg_sales_map_region_maps();
    $home_map = isset($maps[$home]) ? $maps[$home]['file'] : '';

    // Open on the busiest country whenever we can draw it, however much of the
    // shop it is: that is the view that answers the next question. Its own
    // share is not a condition -- a shop split evenly between two countries
    // still opens on the one it sells most in, and the world is a click away in
    // the menu for the comparison.
    //
    // The world is the fallback, not the default: it is what a shop sees when
    // its busiest country is one we ship no outlines for.
    $default = ($home_map !== '') ? 'home' : 'world';

    // ── Cities, for the tooltip ─────────────────────────────────────────────
    //
    // Capped, and ordered by what they sold, so the cap can only ever cost the
    // tooltip of a region that sold little. A shop with thousands of distinct
    // city spellings would otherwise pay for all of them to name three.
    $cities = db_items(
        "SELECT
            orders.billing_country,
            orders.billing_state,
            orders.billing_city,
            COUNT(*) AS all_count,
            COALESCE(SUM(orders.total), 0) AS all_total
            $columns
        FROM orders
        WHERE orders.status IN ('complete', 'exported')
        AND orders.billing_city <> ''
        GROUP BY orders.billing_country, orders.billing_state, orders.billing_city
        ORDER BY all_total DESC
        LIMIT 400");

    // ── Summaries ───────────────────────────────────────────────────────────
    $country_rows = db_items("SELECT code, name FROM countries", 'code');
    $summary = array();

    foreach ($periods as $key => $period) {

        $countries = array();
        $regions = array();
        $total = 0;
        $count = 0;

        foreach ($rows as $row) {

            $values = pg_sales_map_period_values($row, $key);

            if ($values['count'] < 1) {
                continue;
            }

            $total += $values['total'];
            $count += $values['count'];

            $country = $row['pg_country'];
            $country_name = ($country === '')
                ? lang('Unknown')
                : (isset($country_rows[$country]) ? $country_rows[$country]['name'] : $country);

            $country_key = ($country === '') ? '?' : $country;

            if (!isset($countries[$country_key])) {
                $countries[$country_key] = array(
                    'code'  => $country,
                    'name'  => $country_name,
                    'total' => 0,
                    'count' => 0);
            }

            $countries[$country_key]['total'] += $values['total'];
            $countries[$country_key]['count'] += $values['count'];

            // A region is named by its map where there is one, by the shop's
            // own states table otherwise, and by whatever was typed when even
            // that has nothing to say.
            $state = trim((string) $row['billing_state']);
            $region = $row['pg_region'];
            $name = pg_sales_map_label($country, $state);

            $region_key = $country_key . '|' . (($region !== '') ? $region : pg_sales_map_normalize($state));

            if (!isset($regions[$region_key])) {
                $regions[$region_key] = array(
                    'code'         => $region,
                    'name'         => $name,
                    'state'        => '',
                    'lead'         => -1,
                    'country'      => $country,
                    'country_name' => $country_name,
                    'total'        => 0,
                    'count'        => 0,
                    'cities'       => array());
            }

            $regions[$region_key]['total'] += $values['total'];
            $regions[$region_key]['count'] += $values['count'];

            // The spelling that sold most is the one the card links with: the
            // same region can arrive under two spellings, and view_orders.php
            // filters on the text rather than on a code.
            if (($state !== '') && ($values['total'] > $regions[$region_key]['lead'])) {
                $regions[$region_key]['state'] = $state;
                $regions[$region_key]['lead'] = $values['total'];
            }
        }

        // Cities are attached after the regions exist, so a city whose region
        // sold nothing in this period is dropped with it.
        foreach ($cities as $row) {

            $values = pg_sales_map_period_values($row, $key);

            if ($values['count'] < 1) {
                continue;
            }

            $country = pg_sales_map_country_code($row['billing_country'], $row['billing_state']);
            $region = pg_sales_map_match_region($country, $row['billing_state']);
            $state = trim((string) $row['billing_state']);

            $region_key = (($country === '') ? '?' : $country)
                . '|' . (($region !== '') ? $region : pg_sales_map_normalize($state));

            if (!isset($regions[$region_key])) {
                continue;
            }

            $regions[$region_key]['cities'][] = array(
                'name'  => trim((string) $row['billing_city']),
                'total' => $values['total']);
        }

        foreach ($regions as $region_key => $region) {

            usort($regions[$region_key]['cities'], function ($a, $b) {
                return ($b['total'] < $a['total']) ? -1 : (($b['total'] > $a['total']) ? 1 : 0);
            });

            $regions[$region_key]['cities'] = array_slice($regions[$region_key]['cities'], 0, 3);

            unset($regions[$region_key]['lead']);
        }

        uasort($regions, 'pg_sales_map_sort_by_total');
        uasort($countries, 'pg_sales_map_sort_by_total');

        $summary[$key] = array(
            'label'     => $period['label'],
            'from'      => $period['from'],
            'total'     => $total,
            'count'     => $count,
            'countries' => $countries,
            'regions'   => $regions);
    }

    // The period the card opens on: the narrowest one that actually sold
    // something. A shop that has been quiet for a fortnight would otherwise open
    // on an empty map, which reads as a broken card rather than as a quiet
    // fortnight.
    $period_default = 'all';

    foreach ($summary as $key => $period) {

        if ($period['count'] > 0) {
            $period_default = $key;
            break;
        }
    }

    return array(
        'status'    => 'ok',
        'home'      => $home,
        'home_name' => ($home !== '' && isset($country_rows[$home])) ? $country_rows[$home]['name'] : $home,
        'home_map'  => $home_map,
        'default'   => $default,
        'period'    => $period_default,
        'first'     => $first,
        'periods'   => $summary);
}

// Descending revenue, with the order count as the tie-breaker so two entries
// that sold the same amount do not swap places between periods.
function pg_sales_map_sort_by_total($a, $b)
{
    if ($a['total'] != $b['total']) {
        return ($a['total'] < $b['total']) ? 1 : -1;
    }

    if ($a['count'] != $b['count']) {
        return ($a['count'] < $b['count']) ? 1 : -1;
    }

    return strcmp((string) $a['name'], (string) $b['name']);
}

// Colour steps for a map, as key => 1..4.
//
// Quantiles rather than an even split of the range. Sales are heavily skewed --
// one place routinely outsells the next ten together -- and on a linear scale
// everything except that one lands in the palest step, which is a map that has
// stopped saying anything.
function pg_sales_map_steps($entries, $steps = 4)
{
    $totals = array();

    foreach ($entries as $key => $entry) {

        if ($entry['total'] > 0) {
            $totals[$key] = $entry['total'];
        }
    }

    if (empty($totals)) {
        return array();
    }

    $sorted = array_values($totals);
    sort($sorted);

    $count = count($sorted);
    $bounds = array();

    // The upper bound of each step but the last. With fewer entries than steps
    // the bounds repeat, which simply leaves the lower steps unused.
    for ($i = 1; $i < $steps; $i++) {
        $bounds[] = $sorted[(int) floor(($count * $i) / $steps)];
    }

    $result = array();

    foreach ($totals as $key => $total) {

        $step = $steps;

        foreach ($bounds as $index => $bound) {

            if ($total < $bound) {
                $step = $index + 1;
                break;
            }
        }

        $result[$key] = $step;
    }

    return $result;
}

// A link to the orders screen, filtered to one country or one region and to the
// period the card is showing.
//
// view_orders.php copies every request value into its own session, so this is
// the whole of the integration. type=any because the card counts every kind of
// order and that screen defaults to online only -- without it the list would be
// short of the number that was clicked.
//
// The country goes in by name, not by code: that filter matches the code OR the
// country's name with LIKE '%...%', and a two letter code is a substring of
// half the names in the table ("US" matches Belarus).
function pg_sales_map_orders_url($country_name, $state, $from, $first)
{
    $start = ($from > 0) ? $from : (($first > 0) ? $first : time());
    $stop = time();

    $query = array(
        'advanced_filters' => 'true',
        'status'           => 'complete_or_exported',
        'type'             => 'any');

    if ((string) $country_name !== '') {
        $query['billing_country'] = (string) $country_name;
    }

    if ((string) $state !== '') {
        $query['billing_state'] = (string) $state;
    }

    $query['start_month'] = date('m', $start);
    $query['start_day']   = date('d', $start);
    $query['start_year']  = date('Y', $start);
    $query['stop_month']  = date('m', $stop);
    $query['stop_day']    = date('d', $stop);
    $query['stop_year']   = date('Y', $stop);

    return 'view_orders.php?' . http_build_query($query);
}
