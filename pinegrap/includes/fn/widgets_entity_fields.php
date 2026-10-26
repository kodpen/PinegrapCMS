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
//   day         a DATE column ('YYYY-MM-DD') as a date in $ctx['date_format'];
//               '0000-00-00' gives ''
//   stamp       a unix time stamp as a date in $ctx['date_format'] (or through
//               $ctx['format_stamp'], a callable taking the stamp); 0 gives ''
//   period      a recurring product's payment period, as translated words
//   recurrence  a calendar event's recurrence type, as translated words
//   refund      an order's refund status, as translated words
//   place       an address type (residential, business), as translated words
//   yesno       '1' or '' (a condition reads it; never shown as text)
// Options: 'when' => columns that must all be non-zero, else the value is '';
// 'is' => the value that means yes (yesno), else any non-zero value does;
// 'from' => the table the reader takes the column from, when not the entity's.
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

    // A pg_sw_entity_order_read() row. Only the order's own view and its
    // receipt e-mail offer these: the owner sees what the classic receipt
    // showed them. Card details, IP, referrer, UTM codes, notes, commission
    // and accounting or marketplace ids stay off.
    if ($entity === 'order') {
        return array(
            '__order_po_number'            => array('po_number', 'text'),
            '__order_custom_field_1'       => array('custom_field_1', 'text'),
            '__order_custom_field_1_label' => array('custom_field_1_label', 'text', array('when' => array('custom_field_1'), 'from' => 'billing_information_pages')),
            '__order_custom_field_2'       => array('custom_field_2', 'text'),
            '__order_custom_field_2_label' => array('custom_field_2_label', 'text', array('when' => array('custom_field_2'), 'from' => 'billing_information_pages')),
            '__billing_fax'                => array('billing_fax_number', 'text'),
            '__order_gift_card_discount'   => array('gift_card_discount', 'money'),
            '__order_currency'             => array('currency_code', 'text'),
            '__order_paid_date'            => array('paid_at', 'stamp'),
            '__order_cancelled_date'       => array('cancelled_at', 'stamp'),
            '__order_cancellation_reason'  => array('cancellation_reason', 'text', array('when' => array('cancelled_at'))),
            '__order_refund_status'        => array('refund_status', 'refund'),
            '__order_refunded_amount'      => array('refunded_amount', 'money'),
            '__order_refunded_date'        => array('refunded_at', 'stamp'),
            '__order_is_tax_exempt'        => array('tax_exempt', 'yesno'),
        );
    }

    // An order_items row with pg_sw_entity_order_line_event()'s columns, in
    // the order view's item loop (the owner's own order). The line's product
    // values come from the 'product' list beside these.
    if ($entity === 'order_line') {
        return array(
            '__item_shipped_qty'         => array('shipped_quantity', 'number', array('when' => array('show_shipped_quantity'))),
            '__item_vat'                 => array('tax_total', 'money'),
            '__item_payment_period'      => array('recurring_payment_period', 'period', array('when' => array('recurring_payment_period'))),
            '__item_payment_count'       => array('recurring_number_of_payments', 'count', array('when' => array('recurring_payment_period'))),
            '__item_first_payment_date'  => array('recurring_start_date', 'day', array('when' => array('recurring_payment_period'))),
            '__item_event_name'          => array('event_name', 'text', array('from' => 'calendar_events')),
            '__item_event_date'          => array('event_date', 'text', array('from' => 'calendar_events')),
            '__item_added_by_offer'      => array('added_by_offer', 'yesno'),
            '__item_discounted_by_offer' => array('discounted_by_offer', 'yesno'),
        );
    }

    // A pg_sw_entity_account_read() row: the signed-in member's own account
    // (my account, profile, login region) or the addressee of an account
    // e-mail. Password and hint, session and secret keys, device PIN, roles
    // and rights, staff notes and accounting ids stay off.
    if ($entity === 'account') {
        return array(
            '__member_reward_points'    => array('user_reward_points', 'number'),
            '__member_expiry_date'      => array('expiration_date', 'day'),
            '__member_salutation'       => array('salutation', 'text'),
            '__member_nickname'         => array('nickname', 'text'),
            '__member_company'          => array('company', 'text'),
            '__member_job_title'        => array('title', 'text'),
            '__member_website'          => array('website', 'text'),
            '__member_mobile_phone'     => array('mobile_phone', 'text'),
            '__member_business_phone'   => array('business_phone', 'text'),
            '__member_home_phone'       => array('home_phone', 'text'),
            '__member_business_city'    => array('business_city', 'text'),
            '__member_business_country' => array('business_country', 'text'),
            '__member_time_zone'        => array('timezone', 'text'),
            '__member_tax_number'       => array('tax_number', 'text'),
            '__member_tax_office'       => array('tax_office', 'text'),
            '__member_affiliate_code'   => array('affiliate_code', 'text', array('when' => array('affiliate_approved'))),
        );
    }

    // A ship_tos row: a recipient of the order, in the order view and its
    // receipt e-mail (the owner's own order). Its name and address lines are
    // the order view's own __shipping_ tokens; these are the rest of the
    // allow-list. The shipping cost, zone, offer and package data stay off.
    if ($entity === 'ship_to') {
        return array(
            '__ship_to_name'            => array('ship_to_name', 'text'),
            '__shipping_salutation'     => array('salutation', 'text'),
            '__shipping_address_type'   => array('address_type', 'place'),
            '__shipping_ship_date'      => array('ship_date', 'day'),
            '__shipping_delivered_date' => array('delivery_date', 'day'),
        );
    }

    // An address_book row with its country's name as 'country': a recipient
    // the signed-in member saved, in the address book's row loop. Its
    // ship-to name, full name, company, phone and one-line address are the
    // widget's own __recipient_ tokens.
    if ($entity === 'address_book') {
        return array(
            '__recipient_salutation'   => array('salutation', 'text'),
            '__recipient_first_name'   => array('first_name', 'text'),
            '__recipient_last_name'    => array('last_name', 'text'),
            '__recipient_address_1'    => array('address_1', 'text'),
            '__recipient_address_2'    => array('address_2', 'text'),
            '__recipient_city'         => array('city', 'text'),
            '__recipient_state'        => array('state', 'text'),
            '__recipient_zip'          => array('zip_code', 'text'),
            '__recipient_country'      => array('country', 'text'),
            '__recipient_address_type' => array('address_type', 'place'),
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
    $tables = array('product' => 'products', 'order_line' => 'order_items', 'ship_to' => 'ship_tos');
    $table = isset($tables[$entity]) ? $tables[$entity] : '';
    if ($table === '') return '';

    // The event of an order line is read by pg_sw_entity_order_line_event().
    $columns = ($entity === 'order_line') ? array('calendar_event_id' => true, 'recurrence_number' => true) : array();
    foreach (pg_sw_entity_fields($entity) as $spec) {
        if (isset($spec[2]['from'])) continue;
        $columns[$spec[0]] = true;
        if (isset($spec[2]['when'])) {
            foreach ($spec[2]['when'] as $gate) $columns[$gate] = true;
        }
    }

    $sql = '';
    foreach (array_keys($columns) as $column) {
        $added = ($column === 'tax_rate' || $column === 'tax_total');
        $sql .= ($added && !pg_schema_has($table, $column))
            ? ', NULL AS ' . $column
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

// The words of an order's refund status (orders.refund_status) as the
// customer reads them, in the interface language; '' for none. A refund
// still to do by hand or one the gateway refused reads "in progress", as
// the cancellation e-mail tells the customer: a failure is the shop's to fix.
function pg_sw_entity_refund_words($status)
{
    if ($status === 'refunded') return (string)lang('Refunded');
    if ($status === 'pending' || $status === 'failed' || $status === 'manual_required') return (string)lang('Refund in progress');

    return '';
}

// The words of an address type (ship_tos / address_book.address_type), in
// the interface language; '' for none.
function pg_sw_entity_place_words($type)
{
    if ($type === 'residential') return (string)lang('Residential address');
    if ($type === 'business') return (string)lang('Business address');

    return '';
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
            if (isset($ctx['format_stamp']) && is_callable($ctx['format_stamp'])) return (string)call_user_func($ctx['format_stamp'], (int)$value);
            return date(isset($ctx['date_format']) && $ctx['date_format'] !== '' ? (string)$ctx['date_format'] : 'Y-m-d', (int)$value);
        case 'day':
            $text = trim((string)$value);
            if ($text === '' || strpos($text, '0000-00-00') === 0) return '';
            $ts = strtotime($text);
            if (!$ts) return '';
            if (isset($ctx['format_stamp']) && is_callable($ctx['format_stamp'])) return (string)call_user_func($ctx['format_stamp'], $ts);
            return date(isset($ctx['date_format']) && $ctx['date_format'] !== '' ? (string)$ctx['date_format'] : 'Y-m-d', $ts);
        case 'period':
            return pg_sw_entity_period_words((string)$value);
        case 'recurrence':
            return pg_sw_entity_recurrence_words((string)$value);
        case 'refund':
            return pg_sw_entity_refund_words((string)$value);
        case 'place':
            return pg_sw_entity_place_words((string)$value);
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

// The order $id's allow-listed columns, as pg_sw_entity_fields('order')
// reads them; null when there is no such order. One query for the order
// (a column a migration added and this database lacks reads as empty), one
// for the custom field labels: those of the billing information page this
// session passed, else of the site's first billing information page. The
// caller has already checked that the visitor may see the order.
function pg_sw_entity_order_read($id)
{
    $id = (int)$id;
    if ($id <= 0) return null;

    $columns = array();
    foreach (pg_sw_entity_fields('order') as $spec) {
        if (isset($spec[2]['from'])) continue;
        $columns[$spec[0]] = true;
    }

    $select = array();
    foreach (array_keys($columns) as $column) {
        $select[] = pg_schema_has('orders', $column) ? $column : 'NULL AS ' . $column;
    }

    $row = db_item("SELECT " . implode(', ', $select) . " FROM orders WHERE id = '" . $id . "' LIMIT 1");
    if (!is_array($row)) return null;

    // Tax exemption shows only where the checkout offers it, as on the
    // classic receipt.
    if (!(defined('ECOMMERCE_TAX') && ECOMMERCE_TAX && defined('ECOMMERCE_TAX_EXEMPT') && ECOMMERCE_TAX_EXEMPT)) {
        $row['tax_exempt'] = 0;
    }

    $row['custom_field_1_label'] = '';
    $row['custom_field_2_label'] = '';
    if ((string)$row['custom_field_1'] !== '' || (string)$row['custom_field_2'] !== '') {
        $page_id = isset($_SESSION['ecommerce']['billing_information_page_id']) ? (int)$_SESSION['ecommerce']['billing_information_page_id'] : 0;
        $labels = ($page_id > 0)
            ? db_item("SELECT custom_field_1_label, custom_field_2_label FROM billing_information_pages WHERE page_id = '" . $page_id . "' LIMIT 1")
            : null;
        if (!is_array($labels)) {
            $labels = db_item(
                "SELECT billing_information_pages.custom_field_1_label, billing_information_pages.custom_field_2_label
                 FROM billing_information_pages
                 INNER JOIN page ON page.page_id = billing_information_pages.page_id
                 WHERE page.page_type = 'billing information'
                 ORDER BY billing_information_pages.page_id
                 LIMIT 1");
        }
        if (is_array($labels)) {
            $row['custom_field_1_label'] = (string)$labels['custom_field_1_label'];
            $row['custom_field_2_label'] = (string)$labels['custom_field_2_label'];
        }
    }

    return $row;
}

// $row (an order_items row) with the name and the date range of the event
// the line reserved, as the classic receipt prints them; '' each when it
// reserved none or calendars are off.
function pg_sw_entity_order_line_event($row)
{
    $row['event_name'] = '';
    $row['event_date'] = '';
    if (empty($row['calendar_event_id']) || !(defined('CALENDARS') && CALENDARS) || !function_exists('get_calendar_event')) return $row;

    $event = get_calendar_event((int)$row['calendar_event_id'], (int)(isset($row['recurrence_number']) ? $row['recurrence_number'] : 0));
    if (is_array($event)) {
        $row['event_name'] = isset($event['name']) ? (string)$event['name'] : '';
        $row['event_date'] = isset($event['date_and_time_range']) ? trim(strip_tags((string)$event['date_and_time_range'])) : '';
    }

    return $row;
}

// The account $user_id's allow-listed columns, as pg_sw_entity_fields(
// 'account') reads them; null when there is no such account. One query
// (user and contact; tax columns came with an upgrade), one more for the
// country's name. Reward points only while the reward program runs, the
// affiliate code only while the affiliate program does; the time zone is
// the member's own, else the site's, by its pick-list label. The caller
// decides that the visitor may see this account.
function pg_sw_entity_account_read($user_id)
{
    $user_id = (int)$user_id;
    if ($user_id <= 0) return null;

    $tax = array();
    foreach (array('tax_number', 'tax_office') as $column) {
        $tax[] = pg_schema_has('contacts', $column) ? 'contacts.' . $column : 'NULL AS ' . $column;
    }

    $row = db_item(
        "SELECT user.user_reward_points, user.timezone,
                contacts.expiration_date, contacts.salutation, contacts.nickname,
                contacts.company, contacts.title, contacts.website,
                contacts.mobile_phone, contacts.business_phone, contacts.home_phone,
                contacts.business_city, contacts.business_country,
                contacts.affiliate_approved, contacts.affiliate_code,
                " . implode(', ', $tax) . "
         FROM user
         LEFT JOIN contacts ON contacts.id = user.user_contact
         WHERE user.user_id = '" . $user_id . "'
         LIMIT 1");
    if (!is_array($row)) return null;

    if (!(defined('ECOMMERCE_REWARD_PROGRAM') && ECOMMERCE_REWARD_PROGRAM)) $row['user_reward_points'] = null;
    if (!(defined('AFFILIATE_PROGRAM') && AFFILIATE_PROGRAM)) $row['affiliate_approved'] = 0;

    $country = trim((string)$row['business_country']);
    if ($country !== '') {
        $name = (string)db_value("SELECT name FROM countries WHERE code = '" . e($country) . "' LIMIT 1");
        if ($name !== '') $row['business_country'] = $name;
    }

    $zone = trim((string)$row['timezone']);
    if ($zone === '') $zone = (defined('TIMEZONE') && TIMEZONE != '') ? (string)TIMEZONE : (defined('SERVER_TIMEZONE') ? (string)SERVER_TIMEZONE : '');
    if ($zone !== '' && function_exists('get_timezones')) {
        $label = array_search(pg_timezone_list_value($zone), get_timezones());
        if ($label) $zone = (string)$label;
    }
    $row['timezone'] = $zone;

    return $row;
}

// The account values a widget tree binds, escaped for the token map; an
// empty array when the tree binds none (no query then) or there is no
// account.
function pg_sw_entity_account_tokens($user_id, $tree_json)
{
    if ((int)$user_id <= 0 || !pg_sw_entity_wanted('account', $tree_json)) return array();

    $row = pg_sw_entity_account_read($user_id);
    if (!is_array($row)) return array();

    return array_map('h', pg_sw_entity_values('account', $row, array('date_format' => pg_sw_default_date_format('date'))));
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
