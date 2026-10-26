<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the record values a system widget may show
// beyond its own tokens, read from one allow-list per entity (product, ...).
//
// Loaded by functions.php through require_once, never on its own.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The allow-list of an entity: token => array(column, kind[, options]). A
// token on it is never taken off again: a page that bound it would go empty.
// Secrets and staff-only columns (notes, BCC address, commission, code, SEO
// analysis, marketplace and accounting ids, who created or edited a record)
// are never listed.
//
// Kinds, all read by pg_sw_entity_value():
//   text        the column as written (shop text, translated with the page)
//   measure     a decimal; zero gives '', trailing zeros dropped
//   count       a whole number; zero gives ''
//   number      a whole number, zero included ('0' places left)
//   money       integer minor units; zero gives '', else the visitor's money text
//   rate        a percentage column that may be NULL (no rate of its own): ''
//   stamp       a unix time stamp as a date in $ctx['date_format']; 0 gives ''
//   period      a recurring product's payment period, as translated words
//   recurrence  a calendar event's recurrence type, as translated words
//   yesno       '1' or '' (a condition reads it; never shown as text)
// Options: 'when' => columns that must all be non-zero, else the value is '';
// 'is' => the value that means yes (yesno), else any non-zero value does.
function pg_sw_entity_fields($entity)
{
    if ($entity === 'product') {
        return array(
            '__keywords'            => array('keywords', 'text'),
            '__length'              => array('length', 'measure'),
            '__width'               => array('width', 'measure'),
            '__height'              => array('height', 'measure'),
            '__minimum_quantity'    => array('minimum_quantity', 'count'),
            '__maximum_quantity'    => array('maximum_quantity', 'count'),
            '__preparation_days'    => array('preparation_time', 'count'),
            '__extra_shipping_cost' => array('extra_shipping_cost', 'money'),
            '__vat_rate'            => array('tax_rate', 'rate'),
            '__payment_period'      => array('payment_period', 'period', array('when' => array('recurring'))),
            '__number_of_payments'  => array('number_of_payments', 'count', array('when' => array('recurring'))),
            '__is_free_shipping'    => array('free_shipping', 'yesno'),
            '__is_gift_card'        => array('gift_card', 'yesno'),
            '__is_donation'         => array('selection_type', 'yesno', array('is' => 'donation')),
        );
    }

    // A get_calendar_event() row with pg_sw_entity_event_read()'s columns.
    if ($entity === 'calendar_event') {
        return array(
            '__event_spots_left'        => array('number_of_remaining_spots', 'number', array('when' => array('reservations', 'limit_reservations'))),
            '__event_updated_date'      => array('last_modified_timestamp', 'stamp'),
            '__event_reservation_price' => array('product_price', 'money', array('when' => array('reservations'))),
            '__event_recurrence'        => array('recurrence_type', 'recurrence', array('when' => array('recurrence'))),
            '__event_reservations_open' => array('reservations', 'yesno'),
            '__event_spots_limited'     => array('limit_reservations', 'yesno', array('when' => array('reservations'))),
            '__event_is_recurring'      => array('recurrence', 'yesno'),
        );
    }

    // A forms row with its form's custom_form_pages.quiz as 'quiz'. Only the
    // record's own view offers these: a list would show other people's scores.
    if ($entity === 'form_record') {
        return array(
            '__quiz_score' => array('quiz_score', 'number', array('when' => array('quiz'))),
        );
    }

    return array();
}

// The columns the allow-list reads, as a SELECT list of `$alias.column`
// with a leading comma, for a query that already selects the row. A column
// a migration added and this database lacks is selected as NULL under its
// own name, so the value reads as empty.
function pg_sw_entity_select($entity, $alias)
{
    $table = ($entity === 'product') ? 'products' : '';
    if ($table === '') return '';

    $columns = array();
    foreach (pg_sw_entity_fields($entity) as $spec) {
        $columns[$spec[0]] = true;
        if (isset($spec[2]['when'])) {
            foreach ($spec[2]['when'] as $gate) $columns[$gate] = true;
        }
    }

    $sql = '';
    foreach (array_keys($columns) as $column) {
        $sql .= ($column === 'tax_rate' && !pg_schema_has($table, 'tax_rate'))
            ? ', NULL AS tax_rate'
            : ', ' . $alias . '.' . $column;
    }

    return $sql;
}

// The words of a payment period (products.payment_period), in the
// interface language; '' for none or a value the column does not hold.
function pg_sw_entity_period_words($period)
{
    $words = array(
        'Monthly'           => lang('Monthly'),
        'Weekly'            => lang('Weekly'),
        'Every Two Weeks'   => lang('Every Two Weeks'),
        'Twice every Month' => lang('Twice every Month'),
        'Every Four Weeks'  => lang('Every Four Weeks'),
        'Quarterly'         => lang('Quarterly'),
        'Twice every Year'  => lang('Twice every Year'),
        'Yearly'            => lang('Yearly'),
    );

    return isset($words[$period]) ? (string)$words[$period] : '';
}

// The words of a calendar event's recurrence type (calendar_events.
// recurrence_type), in the interface language; '' for none.
function pg_sw_entity_recurrence_words($type)
{
    $words = array(
        'day'   => lang('Daily'),
        'week'  => lang('Weekly'),
        'month' => lang('Monthly'),
        'year'  => lang('Yearly'),
    );

    return isset($words[$type]) ? (string)$words[$type] : '';
}

// A number column as plain text: '12.5000' gives '12.5', '0.0000' gives ''.
function pg_sw_entity_number($value)
{
    $text = trim((string)$value);
    if ($text === '' || !is_numeric($text) || (float)$text == 0) return '';
    if (strpos($text, '.') !== false) $text = rtrim(rtrim($text, '0'), '.');

    return $text;
}

// One allow-listed value of $row as plain text, not escaped yet.
// $ctx: currency_symbol (null: the visitor's), date_format (stamp).
function pg_sw_entity_value($spec, $row, $ctx = array())
{
    $column = $spec[0];
    $kind   = $spec[1];
    $opts   = isset($spec[2]) ? $spec[2] : array();
    $value  = isset($row[$column]) ? $row[$column] : null;

    if (isset($opts['when'])) {
        foreach ($opts['when'] as $gate) {
            if (empty($row[$gate])) return '';
        }
    }

    switch ($kind) {
        case 'text':
            return trim((string)$value);
        case 'measure':
        case 'rate':
            return pg_sw_entity_number($value);
        case 'count':
            return ((int)$value > 0) ? (string)(int)$value : '';
        case 'number':
            return ($value === null || $value === '') ? '' : (string)max(0, (int)$value);
        case 'money':
            if ((int)$value <= 0) return '';
            $symbol = array_key_exists('currency_symbol', $ctx) ? $ctx['currency_symbol'] : null;
            $suffix = defined('VISITOR_CURRENCY_CODE_FOR_OUTPUT') ? (string)VISITOR_CURRENCY_CODE_FOR_OUTPUT : '';
            return pg_money_text(pg_visitor_amount((int)$value / 100), $symbol, $suffix);
        case 'stamp':
            if ((int)$value <= 0) return '';
            return date(isset($ctx['date_format']) && $ctx['date_format'] !== '' ? (string)$ctx['date_format'] : 'Y-m-d', (int)$value);
        case 'period':
            return pg_sw_entity_period_words((string)$value);
        case 'recurrence':
            return pg_sw_entity_recurrence_words((string)$value);
        case 'yesno':
            if (isset($opts['is'])) return ((string)$value === (string)$opts['is']) ? '1' : '';
            return !empty($value) ? '1' : '';
    }

    return '';
}

// Every allow-listed value of an entity's $row: token => plain text, not
// escaped yet (the renderer escapes its whole map once).
function pg_sw_entity_values($entity, $row, $ctx = array())
{
    $values = array();
    foreach (pg_sw_entity_fields($entity) as $token => $spec) {
        $values[$token] = is_array($row) ? pg_sw_entity_value($spec, $row, $ctx) : '';
    }

    return $values;
}

// The calendar event columns get_calendar_event() leaves out, for the
// events $ids: id => array(last_modified_timestamp, product_price). One query.
function pg_sw_entity_event_read($ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids))));
    if (!$ids) return array();

    $out = array();
    $rows = db_items(
        "SELECT calendar_events.id, calendar_events.last_modified_timestamp, products.price AS product_price
         FROM calendar_events
         LEFT JOIN products ON products.id = calendar_events.product_id
         WHERE calendar_events.id IN (" . implode(',', $ids) . ")");
    foreach ((array)$rows as $row) {
        $out[(int)$row['id']] = array(
            'last_modified_timestamp' => (int)$row['last_modified_timestamp'],
            'product_price'           => ($row['product_price'] === null) ? 0 : (int)$row['product_price'],
        );
    }

    return $out;
}

// Whether a widget tree binds any allow-listed value of $entity, read from
// its JSON (a condition or an attribute counts too).
function pg_sw_entity_wanted($entity, $tree_json)
{
    foreach (array_keys(pg_sw_entity_fields($entity)) as $token) {
        if (strpos((string)$tree_json, $token) !== false) return true;
    }

    return false;
}
